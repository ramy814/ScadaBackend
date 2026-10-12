<?php

namespace App\Http\Controllers;

use App\Repositories\SystemPermissionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::guard('admin')->user();
        $userId = (int) $user->getAuthIdentifier();

        $accessible = $this->permissions->accessibleSystems($userId)->all();
        $canCreateManualTrips = $this->permissions->hasActionPermission(
            $userId,
            SystemPermissionRepository::CREATE_MANUAL_TRIP,
        );

        $systems = [
            [
                'key' => 'fleet',
                'title' => 'تتبع المركبات',
                'description' => 'متابعة رحلات المركبات البلدية ومراجعة التقارير والمهام الميدانية',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-full h-full text-blue-400 drop-shadow-[0_0_8px_rgba(96,165,250,0.5)]"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>',
                'route' => '/admin/dashboard',
                'color' => 'blue',
                'available' => true,
                'hasAccess' => in_array('fleet', $accessible, true),
            ],
            [
                'key' => 'wells',
                'title' => 'إدارة الآبار البلدية',
                'description' => 'متابعة الآبار وقراءات المياه وإدارة فرق الفحص الميداني',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-full h-full text-cyan-400 drop-shadow-[0_0_8px_rgba(34,211,238,0.5)]"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a6.002 6.002 0 003.6-10.8c-.8-.6-1.5-1.4-2.1-2.2C12.8 7 12.3 6 12 5c-.3 1-.8 2-1.5 3-.6.8-1.3 1.6-2.1 2.2A6.002 6.002 0 0012 21z" /></svg>',
                'route' => '/wells',
                'color' => 'cyan',
                'available' => true,
                'hasAccess' => in_array('wells', $accessible, true),
            ],
            [
                'key' => 'valves',
                'title' => 'متابعة المحابس البلدية',
                'description' => 'إدارة ومتابعة محابس الشبكة البلدية',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-full h-full text-orange-400 drop-shadow-[0_0_8px_rgba(251,146,60,0.5)]"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a1.5 1.5 0 01-2.12-2.12l3.278-3.28a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" /></svg>',
                'route' => '/valves',
                'color' => 'orange',
                'available' => true,
                'hasAccess' => in_array('valves', $accessible, true),
            ],
            [
                'key' => 'manual-trips',
                'title' => 'إدخال رحلة يدوية',
                'description' => 'تسجيل ومتابعة رحلات مركبات محطاتك عندما لا يتوفر تطبيق السائق',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-full h-full text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>',
                'route' => route('admin.manual-trips.index'),
                'color' => 'blue',
                'available' => true,
                'hasAccess' => $canCreateManualTrips,
            ],
        ];

        return Inertia::render('Portal', [
            'systems' => $systems,
            'userName' => $user->name ?? $user->full_name ?? 'مستخدم',
            'canManagePermissions' => $this->canManagePermissions($userId),
        ]);
    }

    private function canManagePermissions(int $userId): bool
    {
        $allowedIds = array_map('intval', config('mog-helpers.permissions_admin_user_ids', []));

        return app()->environment('local')
            || in_array($userId, $allowedIds, true);
    }
}
