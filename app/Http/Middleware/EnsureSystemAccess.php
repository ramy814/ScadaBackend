<?php

namespace App\Http\Middleware;

use App\Repositories\SystemPermissionRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureSystemAccess
 *
 * Runs after auth:admin. Verifies that the authenticated SSO user has been
 * granted access to the requested system (fleet, wells, valves).
 *
 * Usage: Route::middleware(['auth:admin', 'system.access:fleet'])
 */
class EnsureSystemAccess
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions
    ) {}

    public function handle(Request $request, Closure $next, string $systemKey): Response
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            return $this->denyAccess('لم يتم التعرف على المستخدم.', $systemKey);
        }

        if (! $this->permissions->hasAccess((int) $user->getAuthIdentifier(), $systemKey)) {
            if ($request->isMethodSafe() && $this->permissions->hasActionPermission(
                (int) $user->getAuthIdentifier(), SystemPermissionRepository::CREATE_MANUAL_TRIP,
            )) {
                return redirect()->route('portal');
            }

            return $this->denyAccess(
                sprintf('ليس لديك صلاحية الوصول إلى نظام "%s".', $this->systemLabel($systemKey)),
                $systemKey
            );
        }

        return $next($request);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function systemLabel(string $key): string
    {
        return match ($key) {
            'fleet' => 'تتبع المركبات',
            'wells' => 'إدارة الآبار',
            'valves' => 'متابعة المحابس',
            default => $key,
        };
    }

    private function denyAccess(string $reason, string $systemKey): Response
    {
        return response(
            '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>غير مصرح — بلدية غزة</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,.5);
        }
        .icon { font-size: 56px; margin-bottom: 20px; }
        h1 { font-size: 1.6rem; margin-bottom: 12px; color: #f8fafc; }
        p  { color: #94a3b8; line-height: 1.7; margin-bottom: 24px; font-size: 0.95rem; }
        a {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            padding: 10px 28px;
            font-size: 0.95rem;
        }
        a:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔒</div>
        <h1>ليس لديك صلاحية وصول</h1>
        <p>'.htmlspecialchars($reason, ENT_QUOTES, 'UTF-8').'</p>
        <p>تواصل مع مسؤول النظام لمنحك الصلاحية المطلوبة.</p>
        <a href="/">العودة للبوابة الرئيسية</a>
    </div>
</body>
</html>',
            403
        );
    }
}
