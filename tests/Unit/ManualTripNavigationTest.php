<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\ManualTripController;
use App\Repositories\SystemPermissionRepository;
use App\Services\ManualTripService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ManualTripNavigationTest extends TestCase
{
    public function test_assistant_with_fleet_access_returns_to_dashboard_without_review_scope(): void
    {
        self::assertSame([
            'backUrl' => route('admin.dashboard'),
            'backLabel' => 'العودة لداشبورد التتبع',
        ], $this->navigationFor(['fleet', 'wells']));
    }

    public function test_assistant_without_fleet_access_returns_to_the_portal_for_other_systems(): void
    {
        self::assertSame([
            'backUrl' => route('portal'),
            'backLabel' => 'العودة للبوابة',
        ], $this->navigationFor(['wells']));
    }

    public function test_standalone_assistant_does_not_receive_an_unauthorized_dashboard_link(): void
    {
        self::assertSame(['backUrl' => null, 'backLabel' => null], $this->navigationFor([]));
    }

    private function navigationFor(array $systems): array
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('accessibleSystems')->once()->with(166)->andReturn(collect($systems));
        $permissions->shouldNotReceive('hasActionPermission');
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->shouldReceive('id')->once()->andReturn(166);
        Auth::shouldReceive('guard')->with('admin')->andReturn($guard);
        $controller = new ManualTripController(Mockery::mock(ManualTripService::class), $permissions);

        return (new ReflectionMethod($controller, 'navigationProps'))->invoke($controller);
    }
}
