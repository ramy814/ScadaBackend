<?php

declare(strict_types=1);

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Http\Requests\Valves\StoreValveInspectorRequest;
use App\Http\Requests\Valves\UpdateValveInspectorRequest;
use App\Services\Valves\ValveInspectorService;
use App\Services\Valves\ValveTeamService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ValveInspectorController extends Controller
{
    public function __construct(
        private readonly ValveInspectorService $inspectorService,
        private readonly ValveTeamService $teamService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Valves/Inspectors/Index', [
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'teams' => $this->teamService->getActiveTeams(),
        ]);
    }

    public function store(StoreValveInspectorRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $teamIds = $validated['team_ids'] ?? [];
        unset($validated['team_ids']);

        $this->inspectorService->createInspector($validated, $teamIds);

        return redirect()->route('valves.inspectors.index')
            ->with('success', 'تم إنشاء مفتش المحابس بنجاح');
    }

    public function update(UpdateValveInspectorRequest $request, int $inspector): RedirectResponse
    {
        $validated = $request->validated();
        $teamIds = $validated['team_ids'] ?? null;
        unset($validated['team_ids']);

        $this->inspectorService->updateInspector($inspector, $validated, $teamIds);

        return redirect()->route('valves.inspectors.index')
            ->with('success', 'تم تحديث مفتش المحابس بنجاح');
    }

    public function destroy(int $inspector): RedirectResponse
    {
        $this->inspectorService->deleteInspector($inspector);

        return redirect()->route('valves.inspectors.index')
            ->with('success', 'تم حذف مفتش المحابس بنجاح');
    }
}
