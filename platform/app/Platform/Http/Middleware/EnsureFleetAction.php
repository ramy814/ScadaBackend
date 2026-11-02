<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\SystemPermissionRepository;
use App\Platform\Support\OracleReadiness;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `fleet.action:fleet.trips.enter` — runtime Oracle-derived assistant capability (fails closed). */
class EnsureFleetAction
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly OracleReadiness $readiness,
    ) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        if ($this->permissions->hasActionPermission((int) $request->user('admin')?->getAuthIdentifier(), $action)) {
            return $next($request);
        }

        return Inertia::render('Errors/NoAccess', ['reason' => $this->readiness->available() ? 'manual_scope' : 'oracle_unavailable'])->toResponse($request)->setStatusCode(403);
    }
}
