<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverAppPresenceRequest;
use App\Http\Resources\DriverAppPresenceResource;
use App\Services\CurrentBeneficiaryService;
use App\Services\DriverAppUsageService;

class DriverAppPresenceController extends Controller
{
    public function store(
        StoreDriverAppPresenceRequest $request,
        CurrentBeneficiaryService $beneficiaries,
        DriverAppUsageService $usage,
    ): DriverAppPresenceResource {
        $driver = $beneficiaries->resolve($request->user());

        return new DriverAppPresenceResource(
            $usage->record($driver, $request->validated()),
        );
    }
}
