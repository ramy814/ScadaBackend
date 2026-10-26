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

        $userId = (int) Auth::guard('admin')->id();
        abort_unless(
            $request->isMethodSafe()
                ? $this->access->canViewTrip($userId, $trip)
                : $this->access->canAccessTrip($userId, $trip),
            403,
            'ليست لديك صلاحية اعتماد الرحلات لهذه المركبة ضمن محطاتك.',
        );

        return $next($request);
    }
}
