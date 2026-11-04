<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SystemOperationalStateService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectWhenSystemsSuspended
{
    public function __construct(
        private readonly SystemOperationalStateService $operationalState,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->operationalState->current();

        if (! $state['shutdown_enabled']) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->apiResponse($state['reason']);
        }

        return response()
            ->view('system-suspended', ['reason' => $state['reason']], 503)
            ->header('Retry-After', '300')
            ->header('Cache-Control', 'no-store');
    }

    private function apiResponse(?string $reason): JsonResponse
    {
        return response()
            ->json([
                'message' => $reason ?: 'خدمات بلدية غزة متوقفة مؤقتاً. يرجى المحاولة لاحقاً.',
                'code' => 'systems_temporarily_suspended',
                'retryable' => true,
            ], 503)
            ->header('Retry-After', '300')
            ->header('Cache-Control', 'no-store');
    }
}
