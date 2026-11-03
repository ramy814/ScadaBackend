<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `app.access:wells` — mirrors the legacy `system.access:{key}` guard. */
class EnsureApplicationAccess
{
    public function __construct(private readonly AccessService $access) {}

    public function handle(Request $request, Closure $next, string $application): Response
    {
        if ($this->access->canAccess((int) $request->user('admin')?->getAuthIdentifier(), $application)) {
            $request->attributes->set('application', $application);

            return $next($request);
        }

        return Inertia::render('Errors/NoAccess', ['reason' => 'application', 'application' => $application])
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
