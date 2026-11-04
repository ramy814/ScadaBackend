<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Services\Wells\WellNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly WellNotificationService $service
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['is_read']);

        return Inertia::render('WellsSystem/Notifications/Index', [
            'notifications' => $this->service->getPaginatedNotifications(20, $filters),
            'filters'       => $filters,
            'unreadCount'   => $this->service->getUnreadCount(),
        ]);
    }

    public function markAsRead(int $notification): RedirectResponse
    {
        $this->service->markAsRead($notification);

        return back()->with('success', 'تم تحديد الإشعار كمقروء');
    }

    public function markAllAsRead(): RedirectResponse
    {
        $this->service->markAllAsRead();

        return back()->with('success', 'تم تحديد جميع الإشعارات كمقروءة');
    }
}
