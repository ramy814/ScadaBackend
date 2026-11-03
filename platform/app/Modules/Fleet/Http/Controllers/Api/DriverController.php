<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\StoreDriverAppPresenceRequest;
use App\Modules\Fleet\Http\Resources\DriverAppPresenceResource;
use App\Modules\Fleet\Models\Beneficiary;
use App\Modules\Fleet\Services\CurrentBeneficiaryService;
use App\Modules\Fleet\Services\DriverApiService;
use App\Modules\Fleet\Services\DriverAppUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Driver profile, presence and lookups — legacy BeneficiaryProfile/DriverAppPresence/*Lookup controllers. */
class DriverController extends Controller
{
    public function __construct(
        private readonly DriverApiService $driverApi,
        private readonly CurrentBeneficiaryService $drivers,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->driverApi->profile($this->drivers->resolve($request->user())));
    }

    public function clarifications(Request $request): JsonResponse
    {
        return response()->json($this->driverApi->clarifications($this->drivers->resolve($request->user())));
    }

    public function presence(StoreDriverAppPresenceRequest $request, DriverAppUsageService $usage): DriverAppPresenceResource
    {
        return new DriverAppPresenceResource($usage->record($this->drivers->resolve($request->user()), $request->validated()));
    }

    public function vehicles(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:50']]);

        return response()->json($this->driverApi->vehicles($this->beneficiary($request), $request->query('search')));
    }

    public function myVehicles(Request $request): JsonResponse
    {
        return response()->json($this->driverApi->vehicles($this->beneficiary($request)));
    }

    public function locations(): JsonResponse
    {
        return response()->json($this->driverApi->locations());
    }

    public function missionTypes(): JsonResponse
    {
        return response()->json($this->driverApi->missionTypes());
    }

    private function beneficiary(Request $request): Beneficiary
    {
        $user = $request->user();
        abort_unless($user instanceof Beneficiary, 401, 'غير مصرح.');

        return $user;
    }
}
