<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Valves\Http\Requests\StoreValveInspectorRequest;
use App\Modules\Valves\Http\Requests\StoreValveTeamRequest;
use App\Modules\Valves\Http\Requests\UpdateValveInspectorRequest;
use App\Modules\Valves\Http\Requests\UpdateValveTeamRequest;
use App\Modules\Valves\Services\GeoTileProxyService;
use App\Modules\Valves\Services\ValveInspectorService;
use App\Modules\Valves\Services\ValveService;
use App\Modules\Valves\Services\ValvesMapService;
use App\Modules\Valves\Services\ValveTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Valve teams, inspectors and the map: delegation to the ported services. */
class MasterDataController extends Controller
{
    public function __construct(
        private readonly ValveTeamService $teams,
        private readonly ValveInspectorService $inspectors,
        private readonly ValveService $valves,
    ) {}

    public function field(): Response
    {
        return Inertia::render('Valves/Field', [
            'teams' => $this->teams->getAllTeamsWithRelations(),
            'inspectors' => $this->inspectors->getAllInspectors(),
            'valves' => $this->valves->getAllValves(),
        ]);
    }

    public function storeTeam(StoreValveTeamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->teams->createTeam(collect($data)->except('valve_ids')->all(), $data['valve_ids'] ?? []);

        return back()->with('success', __('valves.saved.team'));
    }

    public function updateTeam(UpdateValveTeamRequest $request, int $team): RedirectResponse
    {
        $data = $request->validated();
        $this->teams->updateTeam($team, collect($data)->except('valve_ids')->all(), $data['valve_ids'] ?? null);

        return back()->with('success', __('valves.saved.team'));
    }

    public function destroyTeam(int $team): RedirectResponse
    {
        $this->teams->deleteTeam($team);

        return back()->with('success', __('valves.deleted.team'));
    }

    public function storeInspector(StoreValveInspectorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->inspectors->createInspector(collect($data)->except('team_ids')->all(), $data['team_ids'] ?? []);

        return back()->with('success', __('valves.saved.inspector'));
    }

    public function updateInspector(UpdateValveInspectorRequest $request, int $inspector): RedirectResponse
    {
        $data = $request->validated();
        $this->inspectors->updateInspector($inspector, collect($data)->except('team_ids')->all(), $data['team_ids'] ?? null);

        return back()->with('success', __('valves.saved.inspector'));
    }

    public function destroyInspector(int $inspector): RedirectResponse
    {
        $this->inspectors->deleteInspector($inspector);

        return back()->with('success', __('valves.deleted.inspector'));
    }

    public function map(): Response
    {
        return Inertia::render('Valves/Map', ['valves' => $this->valves->getMapValves()]);
    }

    public function features(Request $request, ValvesMapService $map): JsonResponse
    {
        $input = $request->validate([
            'layers' => ['required', 'string', 'max:500'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'zoom' => ['nullable', 'integer', 'between:1,22'],
        ]);

        return response()->json(['features' => $map->features($input['layers'], (float) $input['lat'], (float) $input['lng'], (int) ($input['zoom'] ?? 12))]);
    }

    public function tiles(Request $request, string $service, GeoTileProxyService $proxy): HttpResponse
    {
        return $proxy->tile($service, $request->query());
    }
}
