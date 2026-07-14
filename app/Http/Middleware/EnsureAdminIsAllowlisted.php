<?php

namespace App\Http\Middleware;

use App\Repositories\SystemPermissionRepository;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureAdminIsAllowlisted
 *
 * Runs after auth:admin. Verifies that the authenticated SSO user has at least
 * one permission in SYSTEM_PERMISSIONS (fleet, wells, or valves).
 * This replaces the old FLEET_ADMIN_ALLOWLIST gate which only covered fleet users.
 *
 * Local rule: normalize the configured development identity, then skip the gate.
 */
class EnsureAdminIsAllowlisted
{
    public function __construct(private readonly SystemPermissionRepository $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        // ── Normalize and bypass in local development ────────────────────────
        if (app()->environment('local')) {
            $guard = Auth::guard('admin');
            $localUserId = (int) config('mog-helpers.local_admin_user_id');

            if ($localUserId > 0
                && $guard instanceof StatefulGuard
                && (int) $guard->id() !== $localUserId) {
                $guard->loginUsingId($localUserId);
            }

            return $next($request);
        }

        // ── Resolve the authenticated admin user ─────────────────────────────
        $user = Auth::guard('admin')->user();

        if (! $user) {
            return $this->denyAccess('لم يتم التعرف على المستخدم.');
        }

        $userId = (int) $user->getAuthIdentifier();

        // ── Check that the user has at least one system permission ────────────
        // (per-system access is enforced by EnsureSystemAccess on each route group)
        $accessibleSystems = $this->permissions->accessibleSystems($userId);

        if ($accessibleSystems->isEmpty()) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->denyAccess('ليس لديك صلاحية الوصول إلى أي من الأنظمة. تواصل مع مسؤول النظام.');
        }

        return $next($request);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function denyAccess(string $reason): Response
    {
        return response(
            '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>غير مصرح بالوصول — بلدية غزة</title>
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
        .badge {
            display: inline-block;
            background: #1d3048;
            border: 1px solid #2563eb44;
            color: #60a5fa;
            border-radius: 8px;
            padding: 6px 16px;
            font-size: 0.85rem;
            margin-bottom: 28px;
            direction: ltr;
        }
        a {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            padding: 10px 28px;
            font-size: 0.95rem;
            transition: background .2s;
        }
        a:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔒</div>
        <h1>ليس لديك صلاحية وصول</h1>
        <p>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>
        <p>إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع مسؤول النظام لإضافة رقم موظفك إلى قائمة المصرح لهم.</p>
        <a href="/admin/login">العودة لتسجيل الدخول</a>
    </div>
</body>
</html>',
            403
        );
    }
}
