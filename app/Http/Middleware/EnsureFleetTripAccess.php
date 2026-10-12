<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Trip;
use App\Services\FleetVehicleAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFleetTripAccess
{
    public function __construct(private readonly FleetVehicleAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $trip = $request->route('trip');
        abort_unless($trip instanceof Trip, 404);

        abort_unless(
            $this->access->canAccessTrip((int) Auth::guard('admin')->id(), $trip),
            403,
            'ليست لديك صلاحية مراجعة مركبة هذه الرحلة.',
        );

        return $next($request);
    }
}
