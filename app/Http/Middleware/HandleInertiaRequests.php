<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
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
                'ok'    => fn () => $request->session()->get('ok'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $request->user('admin')
                    ? ['name' => $request->user('admin')->name ?? 'مدير النظام']
                    : null,
                'can_view_logs' => fn () => $logsUrl !== null
                    && $request->user('admin')
                    && in_array(
                        (int) $request->user('admin')->id,
                        array_map('intval', config('mog-helpers.logs_allowed_user_ids', [])),
                        true
                    ),
            ],
            'urls' => [
                'dashboard'    => route('admin.dashboard'),
                'locations'    => route('admin.locations'),
                'missionTypes' => route('admin.mission-types'),
                'reports'      => route('admin.reports'),
                'reportsCsv'   => route('admin.reports.csv'),
                'reportsPdf'   => route('admin.reports.pdf'),
                'logout'       => route('logout'),
                'trips_base'   => url('/admin/trips'),
                'allowlist'    => route('admin.allowlist'),
                'logs'         => $logsUrl,
            ],
            'system' => [
                'last_update' => fn () => file_exists(base_path('.git/FETCH_HEAD')) 
                    ? \Carbon\Carbon::createFromTimestamp(filemtime(base_path('.git/FETCH_HEAD')))->locale('ar')->diffForHumans()
                    : (file_exists(base_path('.git/HEAD')) ? \Carbon\Carbon::createFromTimestamp(filemtime(base_path('.git/HEAD')))->locale('ar')->diffForHumans() : 'غير متوفر'),
            ],
        ]);
    }
}
