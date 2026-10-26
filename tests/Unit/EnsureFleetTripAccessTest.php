<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\EnsureFleetTripAccess;
use App\Models\Trip;
use App\Services\FleetVehicleAccessService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureFleetTripAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_allows_a_trip_from_an_authorized_station(): void
    {
        $trip = new Trip;
        $access = Mockery::mock(FleetVehicleAccessService::class);
        $access->shouldReceive('canAccessTrip')->once()->with(140, $trip)->andReturnTrue();

        $response = (new EnsureFleetTripAccess($access))->handle(
            $this->requestForTrip($trip),
            fn (): Response => new Response('ok'),
        );

        self::assertSame('ok', $response->getContent());
    }

    public function test_it_blocks_a_direct_trip_url_outside_the_users_stations(): void
    {
        $trip = new Trip;
        $access = Mockery::mock(FleetVehicleAccessService::class);
        $access->shouldReceive('canAccessTrip')->once()->with(140, $trip)->andReturnFalse();

        try {
            (new EnsureFleetTripAccess($access))->handle(
                $this->requestForTrip($trip),
                fn (): Response => new Response('unreachable'),
            );
            self::fail('The unauthorized trip request was not blocked.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    private function requestForTrip(Trip $trip): Request
    {
        $guard = Mockery::mock(StatefulGuard::class);
        $guard->shouldReceive('id')->once()->andReturn(140);
        Auth::shouldReceive('guard')->once()->with('admin')->andReturn($guard);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->once()->with('trip', null)->andReturn($trip);

        $request = Request::create('/admin/trips/1');
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }
}
