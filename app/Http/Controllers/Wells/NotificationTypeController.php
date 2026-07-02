<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Services\Wells\NotificationTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTypeController extends Controller
{
    public function __construct(
        private readonly NotificationTypeService $service
    ) {
    }

    public function index(): Response
    {
        $types = $this->service->getPaginatedTypes(20);

        // Add canModify flag to each type
        $typesData = $types->through(function ($type) {
            $arr = $type->toArray();
            $arr['CAN_MODIFY'] = !$type->hasBeenUsed();
            return $arr;
        });

        return Inertia::render('WellsSystem/NotificationTypes/Index', [
            'types' => $typesData,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'NAME'     => 'required|string|max:255',
            'ICON'     => 'nullable|string|max:50',
            'EMOJI'    => 'nullable|string|max:50',
            'COLOR'    => 'nullable|string|max:20',
            'PRIORITY' => 'required|integer|min:1|max:100',
            'IS_ACTIVE'=> 'boolean',
        ]);

        $this->service->createType($validated);

        return redirect()->route('wells.notification-types.index')
            ->with('success', 'تم إضافة نوع الإشعار بنجاح');
    }

    public function update(Request $request, int $notificationType): RedirectResponse
    {
        if (!$this->service->canModify($notificationType)) {
            return redirect()->route('wells.notification-types.index')
                ->with('error', 'لا يمكن تعديل هذا النوع لأنه مرتبط بإشعارات موجودة');
        }

        $validated = $request->validate([
            'NAME'     => 'required|string|max:255',
            'ICON'     => 'nullable|string|max:50',
            'EMOJI'    => 'nullable|string|max:50',
            'COLOR'    => 'nullable|string|max:20',
            'PRIORITY' => 'required|integer|min:1|max:100',
            'IS_ACTIVE'=> 'boolean',
        ]);

        $this->service->updateType($notificationType, $validated);

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
