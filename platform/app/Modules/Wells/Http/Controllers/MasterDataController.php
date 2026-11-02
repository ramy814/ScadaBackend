<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wells\Http\Requests\StoreInspectorRequest;
use App\Modules\Wells\Http\Requests\StoreNotificationTypeRequest;
use App\Modules\Wells\Http\Requests\StoreTeamRequest;
use App\Modules\Wells\Http\Requests\StoreWellRequest;
use App\Modules\Wells\Http\Requests\UpdateInspectorRequest;
use App\Modules\Wells\Http\Requests\UpdateNotificationTypeRequest;
use App\Modules\Wells\Http\Requests\UpdateTeamRequest;
use App\Modules\Wells\Http\Requests\UpdateWellRequest;
use App\Modules\Wells\Http\Requests\WellsListRequest;
use App\Modules\Wells\Services\GeneratorService;
use App\Modules\Wells\Services\InspectorService;
use App\Modules\Wells\Services\NotificationTypeService;
use App\Modules\Wells\Services\TeamService;
use App\Modules\Wells\Services\WellMediaService;
use App\Modules\Wells\Services\WellService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Wells, teams, inspectors, signal types, photos and map: delegation to the ported services. */
class MasterDataController extends Controller
{
    public function __construct(
        private readonly WellService $wells,
        private readonly TeamService $teams,
        private readonly InspectorService $inspectors,
        private readonly NotificationTypeService $types,
        private readonly GeneratorService $generators,
        private readonly WellMediaService $media,
    ) {}

    public function assets(WellsListRequest $request): Response
    {
        $filters = $request->safe()->only(['search', 'damage_status', 'is_active']);
        $page = $this->wells->getPaginatedWells(20, $filters);
        $ids = collect($page->items())->flatMap(fn ($w) => $w->generators->pluck('generator_oid'))->filter()->unique()->values()->all();

        return Inertia::render('Wells/Assets', [
            'wells' => $page,
            'generators' => $ids === [] ? [] : collect($this->generators->getGeneratorsByIds($ids))->keyBy('OID'),
            'filters' => $filters,
        ]);
    }

    public function createWell(): Response
    {
        return Inertia::render('Wells/WellForm', ['well' => null, ...$this->wellOptions()]);
    }

    public function storeWell(StoreWellRequest $request): RedirectResponse
    {
        $this->wells->createWell($this->wellData($request));

        return redirect()->route('wells.assets')->with('success', __('wells.saved.well'));
    }

    public function editWell(int $well): Response
    {
        return Inertia::render('Wells/WellForm', ['well' => $this->wells->getWell($well), ...$this->wellOptions()]);
    }

    public function updateWell(UpdateWellRequest $request, int $well): RedirectResponse
    {
        $this->wells->updateWell($well, $this->wellData($request));

        return redirect()->route('wells.assets')->with('success', __('wells.saved.well'));
    }

    public function destroyWell(int $well): RedirectResponse
    {
        return $this->guarded(fn () => $this->wells->deleteWell($well), __('wells.deleted.well'));
    }

    public function destroyMedia(int $well, int $media): RedirectResponse
    {
        $this->media->delete($well, $media);

        return back()->with('success', __('wells.deleted.photo'));
    }

    public function showMedia(int $media): StreamedResponse
    {
        return $this->media->response($media);
    }

    public function map(): Response
    {
        return Inertia::render('Wells/Map', ['wells' => $this->wells->getWellsForMap()]);
    }

    public function field(): Response
    {
        return Inertia::render('Wells/Field', [
            'teams' => $this->teams->getAllTeamsWithCounts(),
            'inspectors' => $this->inspectors->getAllInspectors(),
            'wells' => $this->wells->getActiveWells(),
        ]);
    }

    public function storeTeam(StoreTeamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->teams->createTeam(collect($data)->except('well_ids')->all(), $data['well_ids'] ?? []);

        return back()->with('success', __('wells.saved.team'));
    }

    public function updateTeam(UpdateTeamRequest $request, int $team): RedirectResponse
    {
        $data = $request->validated();
        $this->teams->updateTeam($team, collect($data)->except('well_ids')->all(), $data['well_ids'] ?? null);

        return back()->with('success', __('wells.saved.team'));
    }

    public function destroyTeam(int $team): RedirectResponse
    {
        return $this->guarded(fn () => $this->teams->deleteTeam($team), __('wells.deleted.team'));
    }

    public function storeInspector(StoreInspectorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->inspectors->createInspector(collect($data)->except('team_ids')->all(), $data['team_ids'] ?? []);

        return back()->with('success', __('wells.saved.inspector'));
    }

    public function updateInspector(UpdateInspectorRequest $request, int $inspector): RedirectResponse
    {
        $data = $request->validated();
        $this->inspectors->updateInspector($inspector, collect($data)->except('team_ids')->all(), $data['team_ids'] ?? null);

        return back()->with('success', __('wells.saved.inspector'));
    }

    public function destroyInspector(int $inspector): RedirectResponse
    {
        return $this->guarded(fn () => $this->inspectors->deleteInspector($inspector), __('wells.deleted.inspector'));
    }

    public function signalTypes(): Response
    {
        return Inertia::render('Wells/SignalTypes', ['types' => $this->types->getPaginatedTypes(50)]);
    }

    public function storeSignalType(StoreNotificationTypeRequest $request): RedirectResponse
    {
        $this->types->createType($request->validated());

        return back()->with('success', __('common.saved'));
    }

    public function updateSignalType(UpdateNotificationTypeRequest $request, int $type): RedirectResponse
    {
        $this->types->updateType($type, $request->validated());

        return back()->with('success', __('common.saved'));
    }

    public function destroySignalType(int $type): RedirectResponse
    {
        return $this->guarded(fn () => $this->types->deleteType($type), __('common.deleted'));
    }

    private function wellOptions(): array
    {
        return ['quarters' => $this->wells->getQuarters(), 'generators' => $this->generators->getActiveGenerators()];
    }

    /** Same shape the legacy controller passed: generators and uploaded media included. */
    private function wellData(StoreWellRequest|UpdateWellRequest $request): array
    {
        $data = $request->validated();
        if ($request->has('generators')) {
            $data['generators'] = $request->input('generators', []);
        }

        return $data;
    }

    /** Services throw RuntimeException with a friendly Arabic reason when history blocks deletion. */
    private function guarded(Closure $delete, string $success): RedirectResponse
    {
        try {
            $delete();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $success);
    }
}
