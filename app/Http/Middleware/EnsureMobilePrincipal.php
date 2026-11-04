<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Beneficiary;
use App\Models\Valves\ValveInspector;
use App\Models\Wells\Inspector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobilePrincipal
{
    /**
     * @var array<string, class-string>
     */
    private const PRINCIPALS = [
        'driver' => Beneficiary::class,
        'wells' => Inspector::class,
        'scada' => ValveInspector::class,
    ];

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $principal = self::PRINCIPALS[$ability] ?? null;

        abort_unless(
            $principal !== null && $request->user() instanceof $principal,
            403,
            'هذا الرمز غير مصرح له بالوصول إلى هذا النظام.',
        );

        return $next($request);
    }
}
