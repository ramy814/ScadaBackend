<?php

declare(strict_types=1);

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Http\Requests\Valves\StoreValveTeamRequest;
use App\Http\Requests\Valves\UpdateValveTeamRequest;
use App\Services\Valves\ValveService;
use App\Services\Valves\ValveTeamService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ValveTeamController extends Controller
{
    public function __construct(
        private readonly ValveTeamService $teamService,
        private readonly ValveService $valveService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Valves/Teams/Index', [
            'teams' => $this->teamService->getAllTeamsWithRelations(),
            'valves' => $this->valveService->getAllValves(),
        ]);
    }

    public function store(StoreValveTeamRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $valveIds = $validated['valve_ids'] ?? [];
        unset($validated['valve_ids']);

        $this->teamService->createTeam($validated, $valveIds);

        return redirect()->route('valves.teams.index')
            ->with('success', 'تم إنشاء فريق المحابس بنجاح');
    }

    public function update(UpdateValveTeamRequest $request, int $team): RedirectResponse
    {
        $validated = $request->validated();
        $valveIds = $validated['valve_ids'] ?? null;
        unset($validated['valve_ids']);

        $this->teamService->updateTeam($team, $validated, $valveIds);

        return redirect()->route('valves.teams.index')
            ->with('success', 'تم تحديث فريق المحابس بنجاح');
    }

    public function destroy(int $team): RedirectResponse
    {
        $this->teamService->deleteTeam($team);

        return redirect()->route('valves.teams.index')
            ->with('success', 'تم حذف فريق المحابس بنجاح');
    }
}
