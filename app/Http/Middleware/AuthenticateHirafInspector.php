<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateHirafInspector
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $cacheKey = 'hiraf-inspector-token:'.hash('sha256', $token);

        try {
            $authenticated = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($token): bool {
                return Http::withToken($token)
                    ->acceptJson()
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->get((string) config('services.hiraf.profile_url'))
                    ->successful();
            });
        } catch (ConnectionException) {
            return response()->json(['message' => 'Inspector authentication service is unavailable.'], 503);
        }

        if (! $authenticated) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
