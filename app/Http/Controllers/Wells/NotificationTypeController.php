<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\StoreNotificationTypeRequest;
use App\Http\Requests\Wells\UpdateNotificationTypeRequest;
use App\Services\Wells\NotificationTypeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTypeController extends Controller
{
    public function __construct(
        private readonly NotificationTypeService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render('WellsSystem/NotificationTypes/Index', [
            'types' => $this->service->getPaginatedTypes(20),
        ]);
    }

    public function store(StoreNotificationTypeRequest $request): RedirectResponse
    {
        $this->service->createType($request->validated());

        return redirect()->route('wells.notification-types.index')
            ->with('success', 'تم إضافة نوع الإشعار بنجاح');
    }

    public function update(
        UpdateNotificationTypeRequest $request,
        int $notificationType
    ): RedirectResponse {
        $this->service->updateType($notificationType, $request->validated());

        return redirect()->route('wells.notification-types.index')
            ->with('success', 'تم تحديث نوع الإشعار بنجاح');
    }

    public function destroy(int $notificationType): RedirectResponse
    {
        try {
            $this->service->deleteType($notificationType);

            return redirect()->route('wells.notification-types.index')
                ->with('success', 'تم حذف نوع الإشعار بنجاح');
        } catch (\RuntimeException $e) {
            return redirect()->route('wells.notification-types.index')
                ->with('error', $e->getMessage());
        }
    }
}
