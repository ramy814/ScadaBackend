<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ApproveManualTripRequest;
use App\Http\Requests\ManualTripIndexRequest;
use App\Http\Requests\StoreManualTripRequest;
use App\Repositories\SystemPermissionRepository;
use App\Services\ManualTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ManualTripController extends Controller
{
    public function __construct(
        private readonly ManualTripService $service,
        private readonly SystemPermissionRepository $permissions,
    ) {}

    public function index(ManualTripIndexRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('Admin/ManualTrips', [
            ...$this->service->indexOptions($this->userId(), $filters),
            'filters' => $filters,
            'indexUrl' => route('admin.manual-trips.index'),
            'createUrl' => route('admin.manual-trips.create'),
            'canCreate' => $this->permissions->hasActionPermission($this->userId(), SystemPermissionRepository::ENTER_MANUAL_TRIP),
            'approvalUrl' => $this->permissions->hasActionPermission($this->userId(), SystemPermissionRepository::APPROVE_MANUAL_TRIP)
                ? route('admin.manual-trips.requests') : null,
            ...$this->navigationProps(),
            'logoutUrl' => route('logout'),
        ]);
    }

    public function requests(ManualTripIndexRequest $request): Response
    {
        $filters = ['review_status' => 'pending', ...$request->validated()];

        return Inertia::render('Admin/ManualTrips', [
            ...$this->service->approvalOptions($this->userId(), $filters),
            'filters' => $filters,
            'approvalMode' => true,
            'indexUrl' => route('admin.manual-trips.requests'),
            'createUrl' => route('admin.manual-trips.create'),
            'backUrl' => route('admin.manual-trips.index'),
            'backLabel' => 'رحلاتي اليدوية',
            'logoutUrl' => route('logout'),
        ]);
    }

    public function approve(ApproveManualTripRequest $request, int $trip): RedirectResponse
    {
        $this->service->approve($this->userId(), $trip, $this->actorName(), $request->validated('note'));

        return redirect()->back()->with('ok', 'تم اعتماد الطلب #'.$trip.' بنجاح.');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ManualTripCreate', [
            ...$this->service->formOptions($this->userId()),
            'storeUrl' => route('admin.manual-trips.store'),
            'indexUrl' => route('admin.manual-trips.index'),
            ...$this->navigationProps(),
            'logoutUrl' => route('logout'),
        ]);
    }

    public function store(StoreManualTripRequest $request): RedirectResponse
    {
        $trip = $this->service->create(
            $this->userId(),
            $this->actorName(),
            $request->validated(),
        );

        return redirect()
            ->route('admin.manual-trips.index')
            ->with('ok', 'تم حفظ الرحلة اليدوية بنجاح. رقم الرحلة: #'.$trip->getKey());
    }

    public function edit(int $trip): Response
    {
        return Inertia::render('Admin/ManualTripCreate', [
            ...$this->service->editOptions($this->userId(), $trip),
            'updateUrl' => route('admin.manual-trips.update', $trip),
            'indexUrl' => route('admin.manual-trips.index'),
            ...$this->navigationProps(),
            'logoutUrl' => route('logout'),
        ]);
    }

    public function update(StoreManualTripRequest $request, int $trip): RedirectResponse
    {
        $updated = $this->service->update(
            $this->userId(),
            $trip,
            $this->actorName(),
            $request->validated(),
        );

        return redirect()
            ->route('admin.manual-trips.index')
            ->with('ok', 'تم تحديث الرحلة #'.$updated->getKey().' بنجاح.');
    }

    private function userId(): int
    {
        return (int) Auth::guard('admin')->id();
    }

    private function actorName(): string
    {
        $user = Auth::guard('admin')->user();

        return $user->name ?? $user->full_name ?? ('admin#'.$user->getAuthIdentifier());
    }

    /** @return array{backUrl: string|null, backLabel: string|null} */
    private function navigationProps(): array
    {
        $systems = $this->permissions->accessibleSystems($this->userId());

        if ($systems->contains('fleet') && $this->permissions->hasActionPermission(
            $this->userId(),
            SystemPermissionRepository::REVIEW_TRIPS,
        )) {
            return [
                'backUrl' => route('admin.dashboard'),
                'backLabel' => 'العودة لتتبع المركبات',
            ];
        }

        if ($systems->reject(fn (string $key): bool => $key === 'fleet')->isNotEmpty()) {
            return [
                'backUrl' => route('portal'),
                'backLabel' => 'العودة للبوابة',
            ];
        }

        return ['backUrl' => null, 'backLabel' => null];
    }
}
