<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Repositories\MunicipalityVehicleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VehicleLookupController extends Controller
{
    public function index(Request $request, MunicipalityVehicleRepository $vehicles): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof Beneficiary, 401, 'غير مصرح.');

        $items = $this->vehiclesForBeneficiary($user, $vehicles);
        $search = $request->query('search');

        if (is_string($search) && $search !== '') {
            $items = $items
                ->filter(fn (array $item): bool => str_contains(
                    (string) ($item['plate_number'] ?? ''),
                    $search,
                ))
                ->values();
        }

        return response()->json([
            'vehicles' => $items,
        ]);
    }

    public function myVehicles(
        Request $request,
        MunicipalityVehicleRepository $vehicles,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof Beneficiary, 401, 'غير مصرح.');

        $items = $this->vehiclesForBeneficiary($user, $vehicles);

        return response()->json([
            'vehicles' => $items,
        ]);
    }

    private function vehiclesForBeneficiary(Beneficiary $user, MunicipalityVehicleRepository $vehicles): Collection
    {
        $phone = $user->phoneNumber();

        $items = collect()
            ->merge($vehicles->findByDriverOracleId((string) $user->getAuthIdentifier()));

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

        return $items;
    }
}
