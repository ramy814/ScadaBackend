<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Modules\Fleet\Services\MapClientAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `map.client`: map tiles are served only to authorised app clients (legacy AuthenticateMapClient). */
class AuthenticateMapClient
{
    public function __construct(private readonly MapClientAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->auth->authorize($request->bearerToken());

        return $next($request);
    }
}
