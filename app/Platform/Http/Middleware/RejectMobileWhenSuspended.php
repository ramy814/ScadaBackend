<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Operations\OperationalStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global emergency switch for the mobile APIs: the same retryable JSON 503 the legacy
 * RejectWhenSystemsSuspended returns, so offline-first apps keep their queue and retry.
 */
class RejectMobileWhenSuspended
{
    public function __construct(private readonly OperationalStateService $operations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->operations->current();

        if (! $state['shutdown_enabled']) {
            return $next($request);
        }

        return response()
            ->json([
                'message' => $state['reason'] ?: 'خدمات بلدية غزة متوقفة مؤقتاً. يرجى المحاولة لاحقاً.',
                'code' => 'systems_temporarily_suspended',
                'retryable' => true,
            ], 503)
            ->header('Retry-After', '300')
            ->header('Cache-Control', 'no-store');
    }
}
