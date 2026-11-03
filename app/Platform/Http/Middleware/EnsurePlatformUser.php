<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Platform gate: an authenticated SSO user needs at least one application or admin role. */
class EnsurePlatformUser
{
    public function __construct(private readonly AccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->user('admin')?->getAuthIdentifier();

        if ($userId > 0 && $this->access->hasAnyAccess($userId)) {
            return $next($request);
        }

        return Inertia::render('Errors/NoAccess', ['reason' => 'no_application'])
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
