<?php

declare(strict_types=1);

namespace App\Modules\Fleet;

use App\Modules\Fleet\Repositories\DriverAppInstallationRepository;
use App\Modules\Fleet\Repositories\TripRepository;
use App\Modules\Fleet\Services\FleetAccessService;
use App\Modules\Fleet\Services\FleetLiveService;
use App\Platform\Access\AccessService;
use App\Platform\Access\SystemPermissionRepository;
use App\Platform\Applications\Module;
use Carbon\CarbonImmutable;

class FleetModule implements Module
{
    /** @var array<string, array<string, int>> */
    private array $counts = [];

    /** @var array<int, array<string, int>> */
    private array $live = [];

    private ?array $adoption = null;

    public function __construct(
        private readonly TripRepository $trips,
        private readonly FleetAccessService $access,
        private readonly DriverAppInstallationRepository $installations,
        private readonly FleetLiveService $liveTrips,
        private readonly AccessService $platformAccess,
    ) {}

    public function navigation(int $userId): array
    {
        $assistant = $this->platformAccess->assistant($userId);
        $hasFleet = $this->platformAccess->canAccess($userId, 'fleet');

        // Fleet users see both manual screens; without the Oracle station scope they are
        // shown locked and open the explanation page instead of disappearing.
        $manual = array_values(array_filter([
            $assistant['create'] || $hasFleet ? ['key' => 'manual', 'label' => 'nav.fleet.manual', 'route' => route('fleet.manual'), 'icon' => 'pencil', 'locked' => ! $assistant['create']] : null,
            $assistant['approve'] || $hasFleet ? ['key' => 'manual-requests', 'label' => 'nav.fleet.manual_requests', 'route' => route('fleet.manual.requests'), 'icon' => 'check', 'locked' => ! $assistant['approve']] : null,
        ]));
        $manualGroup = $manual === [] ? [] : [['label' => 'nav.group.manual', 'items' => $manual]];

        // Oracle assistant scope alone never opens the Fleet dashboard (legacy rule).
        if (! $hasFleet) {
            return $manualGroup;
        }

        $counts = $this->counts($userId);

        return [
            ['label' => 'nav.group.daily', 'items' => [
                ['key' => 'reviews', 'label' => 'nav.fleet.reviews', 'route' => route('fleet.reviews'), 'icon' => 'inbox',
                    'badge' => $counts['mobile']['awaiting'] + $counts['mobile']['responded'] + $counts['manual']['awaiting'] ?: null],
                ['key' => 'live', 'label' => 'nav.fleet.live', 'route' => route('fleet.live'), 'icon' => 'radio'],
            ]],
            ['label' => 'nav.group.app', 'items' => [
                ['key' => 'app', 'label' => 'nav.fleet.app', 'route' => route('fleet.app'), 'icon' => 'smartphone'],
            ]],
            ['label' => 'nav.group.records', 'items' => array_values(array_filter([
                ['key' => 'reports', 'label' => 'nav.fleet.reports', 'route' => route('fleet.reports'), 'icon' => 'file-text'],
                $this->platformAccess->can($userId, SystemPermissionRepository::VIEW_VEHICLE_CLAIMS)
                    ? ['key' => 'claims', 'label' => 'nav.fleet.claims', 'route' => route('fleet.claims'), 'icon' => 'banknote'] : null,
            ]))],
            ...$manualGroup,
            ['label' => 'nav.group.settings', 'items' => [
                ['key' => 'catalogs', 'label' => 'nav.fleet.catalogs', 'route' => route('fleet.catalogs'), 'icon' => 'list'],
            ]],
        ];
    }

    public function attention(int $userId): array
    {
        $counts = $this->counts($userId);
        $adoption = $this->adoption();
        $live = $this->liveSummary($userId);

        return array_values(array_filter([
            $this->item('fleet.awaiting', $counts['mobile']['awaiting'], 'warning', route('fleet.reviews')),
            $this->item('fleet.responded', $counts['mobile']['responded'], 'info', route('fleet.reviews', ['queue' => 'responded'])),
            $this->item('fleet.manual', $counts['manual']['awaiting'], 'warning', route('fleet.reviews', ['source' => 'manual'])),
            $this->item('fleet.live_incidents', $live['incident'], 'danger', route('fleet.live')),
            $this->item('fleet.outdated', $adoption['outdated'], 'neutral', route('fleet.app', ['version_status' => 'outdated'])),
        ]));
    }

    public function pulse(int $userId): array
    {
        $now = CarbonImmutable::now();
        $fuel = $this->trips->officialFuel($now->startOfMonth(), $now, $this->access->allowedVehicleIds($userId));
        $adoption = $this->adoption();
        $live = $this->liveSummary($userId);

        return [
            ['key' => 'fleet.active_trips', 'value' => $live['total'], 'hint' => ['live' => $live['live']], 'url' => route('fleet.live')],
            ['key' => 'fleet.month_fuel', 'value' => $fuel['liters'], 'hint' => ['trips' => $fuel['trips']], 'url' => route('fleet.reports')],
            ['key' => 'fleet.current_release', 'value' => $adoption['current'], 'hint' => ['reporting' => $adoption['reporting'], 'version' => config('driver_app.latest_version')], 'url' => route('fleet.app')],
        ];
    }

    // attention() and pulse() run in the same request; read each source once.
    private function adoption(): array
    {
        return $this->adoption ??= $this->installations->releaseAdoption((int) config('driver_app.latest_build'));
    }

    private function liveSummary(int $userId): array
    {
        return $this->live[$userId] ??= $this->liveTrips->overview($userId)['summary'];
    }

    private function item(string $key, int $count, string $severity, string $url): ?array
    {
        return $count > 0 ? ['key' => $key, 'count' => $count, 'severity' => $severity, 'url' => $url] : null;
    }

    /** @return array<string, array<string, int>> */
    private function counts(int $userId): array
    {
        $scope = $this->access->allowedVehicleIds($userId);

        return $this->counts[$userId] ??= [
            'mobile' => $this->trips->queueCounts('mobile', false, $scope),
            'manual' => $this->trips->queueCounts('manual', false, $scope),
        ];
    }
}
