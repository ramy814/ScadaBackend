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
        return array_merge(parent::share($request), [
            'flash' => [
                'ok'    => fn () => $request->session()->get('ok'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $request->user('admin')
                    ? ['name' => $request->user('admin')->name ?? 'مدير النظام']
                    : null,
            ],
        ]);
    }
}
