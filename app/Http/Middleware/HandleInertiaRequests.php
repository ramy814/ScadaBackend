<?php

namespace App\Http\Middleware;

use App\Repositories\SystemPermissionRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly SystemPermissionRepository $permissions) {}

    /**
     * The root template rendered on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version (triggers client-side refresh on deploy).
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data shared with every Inertia page component.
     */
    public function share(Request $request): array
    {
        $logsUrl = null;
        try {
            $logsUrl = route('mog.logs.index');
        } catch (\Exception) {
            // mog-helpers package not loaded or route not registered
        }

        return array_merge(parent::share($request), [
            'flash' => [
                'ok' => fn () => $request->session()->get('ok'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $request->user('admin')
                    ? ['name' => $request->user('admin')->name ?? 'مدير النظام']
                    : null,
                'can_create_manual_trips' => fn () => $request->user('admin')
                    ? $this->permissions->hasActionPermission(
                        (int) $request->user('admin')->getAuthIdentifier(),
                        SystemPermissionRepository::CREATE_MANUAL_TRIP,
                    )
                    : false,
                'can_view_logs' => fn () => $logsUrl !== null
                    && $request->user('admin')
                    && in_array(
                        (int) $request->user('admin')->id,
                        array_map('intval', config('mog-helpers.logs_allowed_user_ids', [])),
                        true
                    ),
            ],
            'urls' => [
                'portal' => route('portal'),
                'dashboard' => route('admin.dashboard'),
                'manualTrips' => route('admin.manual-trips.index'),
                'driverApps' => route('admin.driver-apps'),
                'locations' => route('admin.locations'),
                'missionTypes' => route('admin.mission-types'),
                'reports' => route('admin.reports'),
                'reportsCsv' => route('admin.reports.csv'),
                'reportsPdf' => route('admin.reports.pdf'),
                'logout' => route('logout'),
                'trips_base' => url('/admin/trips'),
                'permissions' => Route::has('portal.permissions') ? route('portal.permissions') : null,
                'allowlist' => Route::has('admin.allowlist') ? route('admin.allowlist') : null,
                'logs' => $logsUrl,
            ],
            'system' => [
                'last_update' => fn () => file_exists(base_path('.git/FETCH_HEAD'))
                    ? Carbon::createFromTimestamp(filemtime(base_path('.git/FETCH_HEAD')))->locale('ar')->diffForHumans()
                    : (file_exists(base_path('.git/HEAD')) ? Carbon::createFromTimestamp(filemtime(base_path('.git/HEAD')))->locale('ar')->diffForHumans() : 'غير متوفر'),
            ],
        ]);
    }
}
