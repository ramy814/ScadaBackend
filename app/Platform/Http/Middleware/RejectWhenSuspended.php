<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Access\AccessService;
use App\Platform\Operations\OperationalStateService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honors the legacy global emergency switch for every application workspace.
 * Platform administrators still reach /platform/operations to reopen services.
 */
class RejectWhenSuspended
{
    public function __construct(
        private readonly OperationalStateService $operations,
        private readonly AccessService $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->operations->current();
        if (! $state['shutdown_enabled']) {
            return $next($request);
        }

        return Inertia::render('Errors/Suspended', [
            'reason' => $state['reason'],
            'changedAt' => $state['changed_at'],
            'canReopen' => $this->access->isPlatformAdmin((int) $request->user('admin')?->getAuthIdentifier()),
        ])->toResponse($request)->setStatusCode(503)->header('Retry-After', '300');
    }
}
