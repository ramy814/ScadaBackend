<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\ApproveManualTripRequest;
use App\Modules\Fleet\Http\Requests\ManualTripIndexRequest;
use App\Modules\Fleet\Http\Requests\StoreManualTripRequest;
use App\Modules\Fleet\Services\ManualTripService;
use App\Platform\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Assistant manual entry (register, draft → submit) and approval queue. */
class ManualTripController extends Controller
{
    public function __construct(
        private readonly ManualTripService $service,
        private readonly AccessService $access,
    ) {}

    public function index(ManualTripIndexRequest $request): Response
    {
        return Inertia::render('Fleet/ManualTrips', [
            ...$this->service->indexOptions($this->userId($request), $request->validated()),
            'filters' => $request->validated(),
            'mode' => 'register',
            'can' => $this->access->assistant($this->userId($request)),
        ]);
    }

    public function requests(ManualTripIndexRequest $request): Response
    {
        $filters = ['review_status' => 'pending', ...$request->validated()];

        return Inertia::render('Fleet/ManualTrips', [
            ...$this->service->approvalOptions($this->userId($request), $filters),
            'filters' => $filters,
            'mode' => 'approval',
            'can' => $this->access->assistant($this->userId($request)),
        ]);
    }

    public function approve(ApproveManualTripRequest $request, int $trip): RedirectResponse
    {
        $this->service->approve($this->userId($request), $trip, $request->user('admin')->displayName(), $request->validated('note'));

        return back()->with('success', __('fleet.manual.approved', ['id' => $trip]));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Fleet/ManualTripForm', [...$this->service->formOptions($this->userId($request)), 'trip' => null]);
    }

    public function store(StoreManualTripRequest $request): RedirectResponse
    {
        $trip = $this->service->create($this->userId($request), $request->user('admin')->displayName(), $request->validated());

        return redirect()->route('fleet.manual')->with('success', __('fleet.manual.saved_draft', ['id' => $trip->getKey()]));
    }

    public function edit(Request $request, int $trip): Response
    {
        return Inertia::render('Fleet/ManualTripForm', $this->service->editOptions($this->userId($request), $trip));
    }

    public function update(StoreManualTripRequest $request, int $trip): RedirectResponse
    {
        $this->service->update($this->userId($request), $trip, $request->user('admin')->displayName(), $request->validated());

        return redirect()->route('fleet.manual')->with('success', __('fleet.manual.updated', ['id' => $trip]));
    }

    public function submit(Request $request, int $trip): RedirectResponse
    {
        $this->service->submit($this->userId($request), $trip, $request->user('admin')->displayName());

        return back()->with('success', __('fleet.manual.submitted', ['id' => $trip]));
    }

    private function userId(Request $request): int
    {
        return (int) $request->user('admin')->getAuthIdentifier();
    }
}
