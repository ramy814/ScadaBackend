<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Repositories\DriverAppInstallationRepository;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\TripRepository;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DriverAppUsageService
{
    public function __construct(
        private readonly DriverAppInstallationRepository $installations,
        private readonly TripRepository $trips,
        private readonly MunicipalityVehicleRepository $vehicles,
    ) {}

    public function record(Beneficiary $driver, array $telemetry): array
    {
        $installation = $this->installations->record([
            'oracle_driver_id' => (string) $driver->getAuthIdentifier(),
            'driver_name' => $driver->displayName(),
            'driver_phone' => $driver->phoneNumber(),
            'driver_employee_number' => $driver->employeeNumber(),
        ], $telemetry);

        return [
            'last_seen_at' => $installation->last_seen_at?->toIso8601String(),
            'latest_version' => config('driver_app.latest_version'),
            'latest_build' => (int) config('driver_app.latest_build'),
            'update_required' => $installation->build_number
                < (int) config('driver_app.latest_build'),
        ];
    }

    public function overview(array $filters, string $path): array
    {
        $installationGroups = $this->installations
            ->allOrderedByLastSeen()
            ->groupBy(fn ($item): string => (string) $item->oracle_driver_id);
        $tripRows = $this->trips
            ->latestUsageByDriver()
            ->keyBy(fn ($trip): string => (string) $trip->oracle_driver_id);
        $driverIds = $installationGroups->keys()
            ->merge($tripRows->keys())
            ->unique()
            ->values();
        $vehicles = $this->vehicles->findManyByDriverOracleIds($driverIds->all());

        $rows = $driverIds
            ->map(function (string $driverId) use ($installationGroups, $tripRows, $vehicles): array {
                $devices = $installationGroups->get($driverId, collect());
                $latest = $devices->first();
                $trip = $tripRows->get($driverId);
                $assignedVehicles = collect($vehicles->get($driverId, collect()))
                    ->map(fn ($vehicle): array => (array) $vehicle)
                    ->values()
                    ->all();

                return $this->row($driverId, $latest, $devices->count(), $trip, $assignedVehicles);
            })
            ->sortByDesc(fn (array $row): int => $row['sort_timestamp'])
            ->values();

        $summary = [
            'drivers' => $rows->count(),
            'current' => $rows->where('version_status', 'current')->count(),
            'outdated' => $rows->where('version_status', 'outdated')->count(),
            'not_reported' => $rows->where('version_status', 'not_reported')->count(),
            'store' => $rows->where('install_source', 'store')->count(),
            'recent' => $rows->where('activity_status', 'recent')->count(),
        ];

        $filtered = $this->filter($rows, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 30;
        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $path, 'query' => $filters],
        );

        return [
            'usage' => $paginator,
            'summary' => $summary,
            'filters' => [
                'search' => trim((string) ($filters['search'] ?? '')),
                'version_status' => $filters['version_status'] ?? '',
                'install_source' => $filters['install_source'] ?? '',
                'activity' => $filters['activity'] ?? '',
            ],
            'latestRelease' => [
                'version' => (string) config('driver_app.latest_version'),
                'build' => (int) config('driver_app.latest_build'),
                'recent_days' => (int) config('driver_app.recent_days'),
            ],
        ];
    }

    private function row(
        string $driverId,
        mixed $installation,
        int $deviceCount,
        mixed $trip,
        array $assignedVehicles,
    ): array {
        $build = $installation?->build_number;
        $versionStatus = $build === null
            ? 'not_reported'
            : ($build >= (int) config('driver_app.latest_build') ? 'current' : 'outdated');
        $installSource = $this->installSource($installation?->installer_store);
        $lastSeen = $installation?->last_seen_at;
        $activityStatus = $lastSeen instanceof CarbonInterface
            && $lastSeen->greaterThanOrEqualTo(now()->subDays((int) config('driver_app.recent_days')))
                ? 'recent'
                : 'inactive';

        return [
            'oracle_driver_id' => $driverId,
            'driver_name' => $installation?->driver_name ?? $trip?->driver_name,
            'driver_phone' => $installation?->driver_phone ?? $trip?->driver_phone,
            'driver_employee_number' => $installation?->driver_employee_number
                ?? $trip?->driver_employee_number,
            'assigned_vehicles' => $assignedVehicles,
            'last_trip_vehicle' => $trip ? [
                'oracle_id' => $trip->oracle_vehicle_id,
                'plate_number' => $trip->vehicle_plate,
                'name' => $trip->vehicle_name,
                'vehicle_type' => $trip->vehicle_type,
            ] : null,
            'app_version' => $installation?->app_version,
            'build_number' => $build,
            'version_status' => $versionStatus,
            'installer_store' => $installation?->installer_store,
            'install_source' => $installSource,
            'store_update_status' => $this->storeUpdateStatus($versionStatus, $installSource),
            'device_count' => $deviceCount,
            'installed_at' => $installation?->installed_at?->toIso8601String(),
            'app_updated_at' => $installation?->app_updated_at?->toIso8601String(),
            'first_seen_at' => $installation?->first_seen_at?->toIso8601String(),
            'last_seen_at' => $lastSeen?->toIso8601String(),
            'last_trip_at' => $trip?->started_at?->toIso8601String(),
            'activity_status' => $activityStatus,
            'sort_timestamp' => max(
                $lastSeen?->getTimestamp() ?? 0,
                $trip?->started_at?->getTimestamp() ?? 0,
            ),
        ];
    }

    private function filter(Collection $rows, array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        return $rows
            ->when($search !== '', function (Collection $rows) use ($search): Collection {
                return $rows->filter(function (array $row) use ($search): bool {
                    $vehicles = collect($row['assigned_vehicles'])
                        ->pluck('plate_number')
                        ->push($row['last_trip_vehicle']['plate_number'] ?? null)
                        ->filter()
                        ->implode(' ');
                    $haystack = mb_strtolower(implode(' ', [
                        $row['driver_name'],
                        $row['driver_phone'],
                        $row['driver_employee_number'],
                        $row['app_version'],
                        $row['build_number'],
                        $vehicles,
                    ]));

                    return str_contains($haystack, $search);
                });
            })
            ->when(
                filled($filters['version_status'] ?? null),
                fn (Collection $rows): Collection => $rows->where(
                    'version_status',
                    $filters['version_status'],
                ),
            )
            ->when(
                filled($filters['install_source'] ?? null),
                fn (Collection $rows): Collection => $rows->where(
                    'install_source',
                    $filters['install_source'],
                ),
            )
            ->when(
                filled($filters['activity'] ?? null),
                fn (Collection $rows): Collection => $rows->where(
                    'activity_status',
                    $filters['activity'],
                ),
            )
            ->values();
    }

    private function installSource(?string $installerStore): string
    {
        if ($installerStore === null || trim($installerStore) === '') {
            return 'unknown';
        }

        return in_array($installerStore, [
            'com.android.vending',
            'com.huawei.appmarket',
            'com.sec.android.app.samsungapps',
            'com.amazon.venezia',
            'com.xiaomi.mipicks',
            'com.oppo.market',
            'com.bbk.appstore',
        ], true) ? 'store' : 'external';
    }

    private function storeUpdateStatus(string $versionStatus, string $installSource): string
    {
        if ($versionStatus === 'not_reported') {
            return 'not_reported';
        }
        if ($versionStatus === 'outdated') {
            return 'update_required';
        }
        if ($installSource === 'store') {
            return 'updated_from_store';
        }
        if ($installSource === 'external') {
            return 'current_external';
        }

        return 'current_unknown_source';
    }
}
