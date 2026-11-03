<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Platform\Identity\MobilePrincipals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `mobile.principal:scada` — the token's owner must be that app's principal (legacy rule). */
class EnsureMobilePrincipal
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $principal = MobilePrincipals::BY_ABILITY[$ability] ?? null;

        abort_unless(
            $principal !== null && $request->user() instanceof $principal,
            403,
            'هذا الرمز غير مصرح له بالوصول إلى هذا النظام.',
        );

        return $next($request);
    }
}
