<?php

declare(strict_types=1);

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Models\Valves\Valve;
use App\Models\Valves\ValveInspector;
use App\Models\Valves\ValveLog;
use App\Models\Valves\ValveMaintenanceReport;
use App\Services\Valves\ValveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValveController extends Controller
{
    public function __construct(
        private readonly ValveService $valveService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $valves = $this->valveService->getAssignedValves(
            $user instanceof ValveInspector ? $user : null,
            $request->only(['area', 'status']),
        );

        return response()->json($valves);
    }

    public function show(Valve $valve): JsonResponse
    {
        $this->authorizeInspectorValveAccess(request(), $valve);

        return response()->json($valve->load('logs.user'));
    }

    /**
     * Inspector action: open, close, maintenance, or replacement on a valve.
     */
    public function action(Request $request, Valve $valve): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:open,close,maintenance,replacement',
            'note' => 'nullable|string|max:500',
        ]);

        $action = $validated['action'];
        $note = $validated['note'] ?? null;
        $user = $request->user();
        $this->authorizeInspectorValveAccess($request, $valve);
        $userOid = (int) ($user->oid ?? $user->OID ?? $user->id);

        ValveLog::create([
            'VALVE_OID' => $valve->oid,
            'USER_OID' => $userOid,
            'ACTION' => $action,
            'NOTE' => $note,
            'ACTION_AT' => now(),
        ]);

        // If maintenance or replacement, create a maintenance report
        if (in_array($action, ['maintenance', 'replacement'])) {
            $description = $action === 'maintenance'
                ? ($note ?? 'يحتاج صيانة')
                : ($note ?? 'يحتاج تعزيل/استبدال');

            ValveMaintenanceReport::create([
                'VALVE_OID' => $valve->oid,
                'REPORTED_BY' => $userOid,
                'DESCRIPTION' => $description,
                'PRIORITY' => $action === 'replacement' ? 'high' : 'medium',
                'STATUS' => 'pending',
            ]);
        }

        $actionTexts = [
            'open' => 'فتح',
            'close' => 'إغلاق',
            'maintenance' => 'طلب صيانة',
            'replacement' => 'طلب تعزيل',
        ];

        return response()->json([
            'success' => true,
            'message' => "✅ تم {$actionTexts[$action]} محبس {$valve->code} بنجاح",
            'valve' => $valve->load('latestLog'),
        ]);
    }

    public function logs(Valve $valve): JsonResponse
    {
        $this->authorizeInspectorValveAccess(request(), $valve);

        $logs = $valve->logs()->with('user')->paginate(20);

        return response()->json($logs);
    }

    private function authorizeInspectorValveAccess(Request $request, Valve $valve): void
    {
        $user = $request->user();

        if (!$user instanceof ValveInspector) {
            return;
        }

        $inspectorTeams = $user->teams()->get(['valve_teams.oid', 'valve_teams.ASSIGN_ALL_VALVES']);
        $hasAllValvesAccess = $inspectorTeams->contains(fn ($team): bool => (bool) ($team->assign_all_valves ?? $team->ASSIGN_ALL_VALVES));

        if ($hasAllValvesAccess) {
            return;
        }

        $teamIds = $inspectorTeams->pluck('oid');
        $hasAccess = $valve->teams()
            ->whereIn('valve_teams.oid', $teamIds)
            ->exists();

        abort_unless($hasAccess, 403, 'هذا المحبس غير مسند لفرق المفتش');
    }
}
