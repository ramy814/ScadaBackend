<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\StoreTeamRequest;
use App\Http\Requests\Wells\UpdateTeamRequest;
use App\Services\Wells\TeamService;
use App\Services\Wells\WellService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $teamService,
        private readonly WellService $wellService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('WellsSystem/Teams/Index', [
            'teams' => $this->teamService->getAllTeamsWithCounts(),
            'wells' => $this->wellService->getActiveWells(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('WellsSystem/Teams/Create', [
            'wells' => $this->wellService->getActiveWells(),
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $wellIds = $validated['well_ids'] ?? [];
        unset($validated['well_ids']);

        $this->teamService->createTeam($validated, $wellIds);

        return redirect()->route('wells.teams.index')
            ->with('success', 'تم إنشاء الفريق بنجاح');
    }

    public function edit(int $team): Response
    {
        return Inertia::render('WellsSystem/Teams/Edit', [
            'team' => $this->teamService->getTeamWithAll($team),
            'wells' => $this->wellService->getActiveWells(),
        ]);
    }

    public function update(UpdateTeamRequest $request, int $team): RedirectResponse
    {
        $validated = $request->validated();
        $wellIds = $validated['well_ids'] ?? null;
        unset($validated['well_ids']);

        $this->teamService->updateTeam($team, $validated, $wellIds);

        return redirect()->route('wells.teams.index')
            ->with('success', 'تم تحديث الفريق بنجاح');
    }

    public function destroy(int $team): RedirectResponse
    {
        try {
            $this->teamService->deleteTeam($team);

            return redirect()->route('wells.teams.index')
                ->with('success', 'تم حذف الفريق بنجاح');
        } catch (RuntimeException $exception) {
            return redirect()->route('wells.teams.index')
                ->with('error', $exception->getMessage());
        }
    }
}
