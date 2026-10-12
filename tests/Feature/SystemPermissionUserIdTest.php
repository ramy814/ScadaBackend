<?php

namespace Tests\Feature;

use App\Http\Controllers\PortalController;
use App\Http\Middleware\EnsureAdminIsAllowlisted;
use App\Http\Middleware\EnsureSystemAccess;
use App\Models\MogSsoUser;
use App\Repositories\SystemPermissionRepository;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SystemPermissionUserIdTest extends TestCase
{
    public function test_local_middleware_replaces_a_stale_admin_session_with_the_configured_user(): void
    {
        $this->app['env'] = 'local';
        config(['mog-helpers.local_admin_user_id' => 140]);

        $guard = \Mockery::mock(StatefulGuard::class);
        $guard->shouldReceive('id')->once()->andReturn(1061);
        $guard->shouldReceive('loginUsingId')->once()->with(140);

        Auth::shouldReceive('guard')
            ->with('admin')
            ->andReturn($guard);

        $response = (new EnsureAdminIsAllowlisted(
            \Mockery::mock(SystemPermissionRepository::class)
        ))->handle(
            Request::create('/'),
            fn (): Response => new Response('ok')
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_portal_uses_sso_id_not_employee_id_for_system_permissions(): void
    {
        $permissions = new class extends SystemPermissionRepository
        {
            public ?int $resolvedUserId = null;

            public function __construct() {}

            public function accessibleSystems(int $userId): Collection
            {
                $this->resolvedUserId = $userId;

                return collect(['fleet', 'wells', 'valves']);
            }

            public function hasActionPermission(int $userId, string $permissionKey): bool
            {
                return false;
            }
        };

        Auth::shouldReceive('guard')
            ->with('admin')
            ->andReturn(new class($this->ssoUser())
            {
                public function __construct(private readonly MogSsoUser $user) {}

                public function user(): MogSsoUser
                {
                    return $this->user;
                }
            });

        (new PortalController($permissions))->index(Request::create('/'));

        $this->assertSame(1061, $permissions->resolvedUserId);
    }

    public function test_system_access_middleware_uses_sso_id_not_employee_id(): void
    {
        $permissions = new class extends SystemPermissionRepository
        {
            public ?int $resolvedUserId = null;

            public function __construct() {}

            public function hasAccess(int $userId, string $systemKey): bool
            {
                $this->resolvedUserId = $userId;

                return true;
            }
        };

        Auth::shouldReceive('guard')
            ->with('admin')
            ->andReturn(new class($this->ssoUser())
            {
                public function __construct(private readonly MogSsoUser $user) {}

                public function user(): MogSsoUser
                {
                    return $this->user;
                }
            });

        (new EnsureSystemAccess($permissions))->handle(
            Request::create('/admin/dashboard'),
            fn (): Response => new Response('ok'),
            'fleet'
        );

        $this->assertSame(1061, $permissions->resolvedUserId);
    }

    private function ssoUser(): MogSsoUser
    {
        $user = new MogSsoUser;
        $user->forceFill([
            'id' => 1061,
            'emp_id' => 2541,
            'full_name' => 'رامي عبدالسليم غبانة',
        ]);

        return $user;
    }
}
