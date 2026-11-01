<?php

declare(strict_types=1);

namespace App\Modules\Wells;

use App\Modules\Wells\Repositories\WellsRepository;
use App\Platform\Applications\Module;
use Carbon\CarbonImmutable;

class WellsModule implements Module
{
    public function __construct(private readonly WellsRepository $wells) {}

    public function navigation(int $userId): array
    {
        return [
            ['label' => 'nav.group.daily', 'items' => [
                ['key' => 'today', 'label' => 'nav.wells.today', 'route' => route('wells.today'), 'icon' => 'sun'],
                ['key' => 'signals', 'label' => 'nav.wells.signals', 'route' => route('wells.signals'), 'icon' => 'bell', 'badge' => $this->wells->unreadSignalCount() ?: null],
                ['key' => 'readings', 'label' => 'nav.wells.readings', 'route' => route('wells.readings'), 'icon' => 'activity'],
            ]],
            ['label' => 'nav.group.assets', 'items' => [
                ['key' => 'assets', 'label' => 'nav.wells.assets', 'route' => route('wells.assets'), 'icon' => 'droplets'],
            ]],
            ['label' => 'nav.group.field', 'items' => [
                ['key' => 'field', 'label' => 'nav.wells.field', 'route' => route('wells.field'), 'icon' => 'users'],
            ]],
        ];
    }

    public function attention(int $userId): array
    {
        $missing = $this->wells->activeWellsWithoutReading(CarbonImmutable::today())->count();
        $unread = $this->wells->unreadSignalCount();

        return array_values(array_filter([
            $unread > 0 ? ['key' => 'wells.unread_signals', 'count' => $unread, 'severity' => 'warning', 'url' => route('wells.signals', ['state' => 'unread'])] : null,
            $missing > 0 ? ['key' => 'wells.missing_readings', 'count' => $missing, 'severity' => 'neutral', 'url' => route('wells.today')] : null,
        ]));
    }

    public function pulse(int $userId): array
    {
        $today = CarbonImmutable::today();
        $active = $this->wells->activeWellCount();

        return [
            ['key' => 'wells.readings_today', 'value' => $this->wells->readingsOn($today), 'hint' => ['active' => $active], 'url' => route('wells.today')],
        ];
    }
}
