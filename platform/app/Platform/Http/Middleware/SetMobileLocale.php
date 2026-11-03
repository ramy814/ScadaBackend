<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API responses keep the legacy backend locale (it ships no translations and runs
 * APP_LOCALE=en), so framework validation messages reach the apps with the same text.
 * Messages written in Arabic inside the ported requests and services are unchanged.
 */
class SetMobileLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale((string) config('platform.mobile_api_locale', 'en'));

        return $next($request);
    }
}
