<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\SystemPermissionRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActionPermission
{
    public function __construct(private readonly SystemPermissionRepository $permissions) {}

    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $userId = (int) Auth::guard('admin')->id();

        abort_unless(
            $userId > 0 && $this->permissions->hasActionPermission($userId, $permissionKey),
            403,
            'ليست لديك الصلاحية المطلوبة لتنفيذ هذه العملية.',
        );

        return $next($request);
    }
}
