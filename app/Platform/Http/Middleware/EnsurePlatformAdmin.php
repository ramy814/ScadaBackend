<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function __construct(private readonly AccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->access->isPlatformAdmin((int) $request->user('admin')?->getAuthIdentifier())) {
            return $next($request);
        }

        return Inertia::render('Errors/NoAccess', ['reason' => 'platform_admin'])
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
