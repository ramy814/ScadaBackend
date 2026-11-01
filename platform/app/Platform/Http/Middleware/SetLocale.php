<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->cookie('locale', config('app.locale', 'ar'));
        app()->setLocale(in_array($locale, config('platform.locales'), true) ? $locale : 'ar');

        return $next($request);
    }
}
