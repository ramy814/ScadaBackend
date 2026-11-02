<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use App\Modules\Valves\Repositories\ValvesRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class ValvesService
{
    public const ACTIONS = ['open', 'close', 'maintenance', 'replacement'];

    public const MAINTENANCE_STATUSES = ['pending', 'in_progress', 'resolved'];

    public function __construct(private readonly ValvesRepository $valves) {}

    public function today(): array
    {
        $maintenance = $this->valves->maintenanceCounts();
        $latest = $this->valves->latestActionDistribution();

        return [
            'stats' => [
                'valves' => $this->valves->totalValves(),
                'actions_today' => $this->valves->actionsOn(CarbonImmutable::today()),
                'pending' => $maintenance['pending'] ?? 0,
                'in_progress' => $maintenance['in_progress'] ?? 0,
            ],
            'distribution' => array_map(fn (string $a): array => ['action' => $a, 'valves' => $latest[$a] ?? 0], self::ACTIONS),
            'openReports' => $this->maintenance('pending')['reports']->getCollection()->take(6)->values(),
        ];
    }

    public function maintenance(?string $status): array
    {
        $page = $this->valves->paginateMaintenance($status);
        $page->getCollection()->transform(fn (Model $r): array => [
            'oid' => (int) $r->getKey(),
            'valve' => $this->valve($r->valve, (int) $this->attr($r, 'valve_oid')),
            'description' => $this->attr($r, 'description'),
            'priority' => $this->attr($r, 'priority'),
            'status' => $this->attr($r, 'status'),
            'reporter' => $this->attr($r->reporter, 'name'),
            'created_at' => $this->date($this->attr($r, 'created_at')),
            'resolved_at' => $this->date($this->attr($r, 'resolved_at')),
        ]);

        return ['reports' => $page, 'status' => $status ?? 'all', 'counts' => $this->valves->maintenanceCounts()];
    }

    public function actions(array $filters): array
    {
        $page = $this->valves->paginateActions($filters);
        $page->getCollection()->transform(fn (Model $l): array => [
            'oid' => (int) $l->getKey(),
            'valve' => $this->valve($l->valve, (int) $this->attr($l, 'valve_oid')),
            'action' => $this->attr($l, 'action'),
            'note' => $this->attr($l, 'note'),
            'inspector' => $this->attr($l->inspector, 'name'),
            'action_at' => $this->date($this->attr($l, 'action_at')),
        ]);

        return [
            'actions' => $page,
            'filters' => $filters,
            'teams' => $this->valves->teams()->map(fn (Model $t): array => ['oid' => (int) $t->getKey(), 'name' => $this->attr($t, 'name')])->values(),
            'inspectors' => $this->valves->inspectors()->map(fn (Model $i): array => ['oid' => (int) $i->getKey(), 'name' => $this->attr($i, 'name')])->values(),
        ];
    }

    /** Valve identity uses the legacy code convention WV-{W4_GENERAL_ID}. */
    private function valve(?Model $valve, int $id): array
    {
        return ['code' => 'WV-'.$id, 'name' => $valve?->name, 'location' => $valve?->location_description];
    }

    private function attr(?Model $model, string $key): mixed
    {
        return $model?->getAttribute($key) ?? $model?->getAttribute(strtoupper($key));
    }

    private function date(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse($value)->toIso8601String();
    }
}
