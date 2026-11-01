<?php

declare(strict_types=1);

namespace App\Platform\Applications;

use App\Http\Controllers\Controller;
use App\Platform\Access\AccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Directory of every managed Flutter application, its contract and release policy. */
class ApplicationsController extends Controller
{
    public function __invoke(Request $request, ApplicationRegistry $registry, AccessService $access): Response
    {
        $locale = app()->getLocale();
        $userId = (int) $request->user('admin')->getAuthIdentifier();

        return Inertia::render('Platform/Applications', [
            'applications' => array_map(fn (array $app): array => [
                ...$registry->present($app['key'], $locale),
                'audience' => $app['audience'][$locale] ?? $app['audience']['ar'],
                'auth' => $app['auth'][$locale] ?? $app['auth']['ar'],
                'platforms' => $app['platforms'],
                'api_prefix' => $app['api_prefix'],
                'offline_first' => $app['offline_first'],
                'release' => $app['release_tracking']
                    ? ['version' => config('driver_app.latest_version'), 'build' => (int) config('driver_app.latest_build')]
                    : null,
                'accessible' => $access->canAccess($userId, $app['key']),
            ], array_values($registry->all())),
        ]);
    }
}
