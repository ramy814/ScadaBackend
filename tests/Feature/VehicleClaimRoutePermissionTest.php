<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\SystemPermissionRepository;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VehicleClaimRoutePermissionTest extends TestCase
{
    public function test_every_vehicle_claim_route_requires_the_dashboard_granted_permission(): void
    {
        foreach (['admin.vehicle-claims', 'admin.vehicle-claims.csv', 'admin.vehicle-claims.pdf'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            self::assertNotNull($route, $name);
            self::assertContains(
                'action.permission:'.SystemPermissionRepository::VIEW_VEHICLE_CLAIMS,
                $route->gatherMiddleware(),
                $name,
            );
        }
    }
}
