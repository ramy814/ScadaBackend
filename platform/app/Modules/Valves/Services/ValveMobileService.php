<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Valves\Models\ValveMaintenanceReport;
use App\Modules\Valves\Repositories\ValveRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * SCADA valves mobile API, moved out of the legacy controllers with the same payloads.
 * Documented fixes (docs/04-roadmap.md): `logs.user` exists, the maintenance reporter is
 * selected by its real key (oid), and RESOLVED_BY stores the inspector OID.
 */
class ValveMobileService
{
    private const ACTION_TEXTS = ['open' => 'فتح', 'close' => 'إغلاق', 'maintenance' => 'طلب صيانة', 'replacement' => 'طلب تعزيل'];

    public function __construct(
        private readonly ValveRepository $valves,
        private readonly ValveService $valveService,
        private readonly ValveActionService $actions,
    ) {}

    public function stats(): array
    {
        $latest = $this->valves->latestActionsPerValve();

        return [
            'valves' => [
                'total' => $this->valves->count(),
                'open' => $latest->where('action', 'open')->count(),
                'closed' => $latest->where('action', 'close')->count(),
                'maintenance' => $latest->where('action', 'maintenance')->count(),
                'replacement' => $latest->where('action', 'replacement')->count(),
            ],
            'wells' => ['total' => 0, 'on' => 0, 'off' => 0, 'maintenance' => 0],
        ];
    }

    public function recentActivity(): Collection
    {
        return $this->valves->recentActions(15)->map(fn ($log): array => [
            'type' => 'valve',
            'oid' => $log->oid,
            'asset_code' => $log->valve?->code ?? '-',
            'valve_name' => $log->valve?->name ?? '-',
            'action' => $log->action,
            'note' => $log->note,
            'action_at' => $log->action_at,
        ]);
    }

    public function assigned(mixed $user, array $filters): Collection
    {
        return $this->valveService->getAssignedValves($user instanceof ValveInspector ? $user : null, $filters);
    }

    public function show(mixed $user, Valve $valve): Valve
    {
        $this->authorize($user, $valve);

        return $valve->load('logs.user');
    }

    public function logs(mixed $user, Valve $valve): LengthAwarePaginator
    {
        $this->authorize($user, $valve);

        return $this->valves->paginateLogs($valve, 20);
    }

    public function act(mixed $user, Valve $valve, array $data): array
    {
        $this->authorize($user, $valve);
        abort_unless($user instanceof ValveInspector, 403);
        $this->actions->record($user, $valve, $data);

        return [
            'success' => true,
            'message' => '✅ تم '.self::ACTION_TEXTS[$data['action']]." محبس {$valve->code} بنجاح",
            'valve' => $valve->load('latestLog'),
        ];
    }

    public function maintenance(array $filters): LengthAwarePaginator
    {
        return $this->valves->paginateMaintenanceReports($filters, 20);
    }

    public function updateMaintenance(mixed $user, ValveMaintenanceReport $report, array $validated): ValveMaintenanceReport
    {
        $data = [];
        if (isset($validated['status'])) {
            $data['STATUS'] = $validated['status'];
            if ($validated['status'] === 'resolved') {
                $data['RESOLVED_BY'] = $user?->oid;
                $data['RESOLVED_AT'] = now();
            }
        }
        if (isset($validated['priority'])) {
            $data['PRIORITY'] = $validated['priority'];
        }

        return $this->valves->updateMaintenanceReport($report, $data);
    }

    private function authorize(mixed $user, Valve $valve): void
    {
        if (! $user instanceof ValveInspector) {
            return;
        }

        abort_unless($this->valves->inspectorCanAccess($user, $valve), 403, 'هذا المحبس غير مسند لفرق المفتش');
    }
}
