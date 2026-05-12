<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Repositories\MunicipalityVehicleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleLookupController extends Controller
{
    public function index(Request $request, MunicipalityVehicleRepository $vehicles): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'vehicles' => $vehicles->search($request->query('search')),
        ]);
    }

    public function myVehicles(
        Request $request,
        MunicipalityVehicleRepository $vehicles,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof Beneficiary, 401, 'غير مصرح.');
        $phone = $user->phoneNumber();

        $items = collect();
        $items = $items->merge($vehicles->findByDriverOracleId((string) $user->getAuthIdentifier()));
        if ($phone) {
            $items = $items->merge($vehicles->findByDriverMobile($phone));
        }

        $items = $items
            ->filter(fn (array $item) => ! empty($item['plate_number']))
            ->unique(fn (array $item) => (string) $item['plate_number'])
            ->values();

        Log::info('driver.my-vehicles', [
            'beneficiary_id' => $user?->getAuthIdentifier(),
            'phone_from_beneficiary' => $phone,
            'phone_used' => $phone,
            'oracle_driver_id' => $user->getAuthIdentifier(),
            'vehicles_count' => $items->count(),
        ]);

        return response()->json([
            'vehicles' => $items,
        ]);
    }
}
