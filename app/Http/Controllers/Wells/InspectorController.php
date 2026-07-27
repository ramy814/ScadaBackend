<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\StoreInspectorRequest;
use App\Http\Requests\Wells\UpdateInspectorRequest;
use App\Services\Wells\InspectorService;
use App\Services\Wells\TeamService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InspectorController extends Controller
{
    public function __construct(
        private readonly InspectorService $inspectorService,
        private readonly TeamService $teamService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('WellsSystem/Inspectors/Index', [
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'teams' => $this->teamService->getActiveTeams(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('WellsSystem/Inspectors/Create', [
            'teams' => $this->teamService->getActiveTeams(),
        ]);
    }

    public function store(StoreInspectorRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $teamIds = $validated['team_ids'] ?? [];
        unset($validated['team_ids']);

        $this->inspectorService->createInspector($validated, $teamIds);

        return redirect()->route('wells.inspectors.index')
            ->with('success', 'تم إنشاء المفتش بنجاح');
    }

    public function edit(int $inspector): Response
    {
        return Inertia::render('WellsSystem/Inspectors/Edit', [
            'inspector' => $this->inspectorService->getInspector($inspector),
            'teams' => $this->teamService->getActiveTeams(),
        ]);
    }

    public function update(UpdateInspectorRequest $request, int $inspector): RedirectResponse
    {
        $validated = $request->validated();
        $teamIds = $validated['team_ids'] ?? null;
        unset($validated['team_ids']);

        $this->inspectorService->updateInspector($inspector, $validated, $teamIds);

        return redirect()->route('wells.inspectors.index')
            ->with('success', 'تم تحديث المفتش بنجاح');
    }

    public function destroy(int $inspector): RedirectResponse
    {
        try {
            $this->inspectorService->deleteInspector($inspector);

            return redirect()->route('wells.inspectors.index')
                ->with('success', 'تم حذف المفتش بنجاح');
        } catch (RuntimeException $exception) {
            return redirect()->route('wells.inspectors.index')
                ->with('error', $exception->getMessage());
        }
    }
}
