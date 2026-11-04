<?php

namespace App\Repositories;

use App\Models\DriverAppInstallation;
use Illuminate\Support\Collection;

class DriverAppInstallationRepository
{
    public function record(array $identity, array $telemetry): DriverAppInstallation
    {
        $now = now();
        $installation = DriverAppInstallation::query()->firstOrNew([
            'oracle_driver_id' => $identity['oracle_driver_id'],
            'device_id' => $telemetry['device_id'],
        ]);

        $installation->fill([
            ...$identity,
            'app_version' => $telemetry['app_version'],
            'build_number' => $telemetry['build_number'],
            'package_name' => $telemetry['package_name'],
            'installer_store' => $telemetry['installer_store'] ?? null,
            'installed_at' => $telemetry['installed_at'] ?? null,
            'app_updated_at' => $telemetry['app_updated_at'] ?? null,
            'first_seen_at' => $installation->exists
                ? $installation->first_seen_at
                : $now,
            'last_seen_at' => $now,
        ]);
        $installation->save();

        return $installation->refresh();
    }

    public function allOrderedByLastSeen(): Collection
    {
        return DriverAppInstallation::query()
            ->latest('last_seen_at')
            ->get();
    }

    public function reportingDriverIds(): array
    {
        return DriverAppInstallation::query()
            ->distinct()
            ->pluck('oracle_driver_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }
}
