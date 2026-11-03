<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\SystemPermissionRepository;
use App\Platform\Support\OracleReadiness;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `fleet.action:fleet.trips.enter` — Fleet action permission: Oracle-derived assistant scope or a dashboard grant (fails closed). */
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

        return Inertia::render('Errors/NoAccess', ['reason' => match (true) {
            ! $this->readiness->available() => 'oracle_unavailable',
            $action === SystemPermissionRepository::VIEW_VEHICLE_CLAIMS => 'action_grant',
            default => 'manual_scope',
        }])->toResponse($request)->setStatusCode(403);
    }
}
