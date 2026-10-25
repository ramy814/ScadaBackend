<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\MapClientAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMapClient
{
    public function __construct(private readonly MapClientAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->auth->authorize($request->bearerToken());

        return $next($request);
    }
}
