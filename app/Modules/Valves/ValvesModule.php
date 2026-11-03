<?php

declare(strict_types=1);

namespace App\Modules\Valves;

use App\Modules\Valves\Repositories\ValvesRepository;
use App\Platform\Applications\Module;
use Carbon\CarbonImmutable;

class ValvesModule implements Module
{
    public function __construct(private readonly ValvesRepository $valves) {}

    public function navigation(int $userId): array
    {
        $pending = $this->valves->maintenanceCounts()['pending'] ?? 0;

        return [
            ['label' => 'nav.group.daily', 'items' => [
                ['key' => 'today', 'label' => 'nav.valves.today', 'route' => route('valves.today'), 'icon' => 'sun'],
                ['key' => 'maintenance', 'label' => 'nav.valves.maintenance', 'route' => route('valves.maintenance'), 'icon' => 'wrench', 'badge' => $pending ?: null],
                ['key' => 'actions', 'label' => 'nav.valves.actions', 'route' => route('valves.actions'), 'icon' => 'history'],
                ['key' => 'map', 'label' => 'nav.valves.map', 'route' => route('valves.map'), 'icon' => 'map-pin'],
            ]],
            ['label' => 'nav.group.field', 'items' => [
                ['key' => 'field', 'label' => 'nav.valves.field', 'route' => route('valves.field'), 'icon' => 'users'],
            ]],
        ];
    }

    public function attention(int $userId): array
    {
        $counts = $this->valves->maintenanceCounts();

        return array_values(array_filter([
            ($counts['pending'] ?? 0) > 0 ? ['key' => 'valves.pending_maintenance', 'count' => $counts['pending'], 'severity' => 'warning', 'url' => route('valves.maintenance', ['status' => 'pending'])] : null,
            ($counts['in_progress'] ?? 0) > 0 ? ['key' => 'valves.in_progress_maintenance', 'count' => $counts['in_progress'], 'severity' => 'info', 'url' => route('valves.maintenance', ['status' => 'in_progress'])] : null,
        ]));
    }

    public function pulse(int $userId): array
    {
        return [
            ['key' => 'valves.actions_today', 'value' => $this->valves->actionsOn(CarbonImmutable::today()), 'hint' => ['valves' => $this->valves->totalValves()], 'url' => route('valves.actions')],
        ];
    }
}
