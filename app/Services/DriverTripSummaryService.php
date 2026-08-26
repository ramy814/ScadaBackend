<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Trip;

class DriverTripSummaryService
{
    public function __construct(private readonly VirtualFuelService $fuel) {}

    public function summarize(Trip $trip): array
    {
        // ponytail: GET responses only shape persisted metrics; missing legacy
        // summaries stay unavailable instead of rescanning every GPS point.
        $distanceKm = is_numeric($trip->distance_km)
            ? round((float) $trip->distance_km, 3)
            : null;
        $durationMinutes = is_numeric($trip->duration_minutes)
            ? (int) $trip->duration_minutes
            : null;
        $distanceIsFiltered = (int) ($trip->quality_version ?? 0) >= 2;

        $fuelLiters = $trip->virtual_fuel_liters;
        $fuelIsProvisional = false;
        if ($fuelLiters === null && $distanceKm !== null && $distanceKm > 0) {
            $fuelLiters = round(
                $distanceKm * $this->fuel->litersPerKilometer(),
                3,
            );
            $fuelIsProvisional = $fuelLiters !== null;
        }

        return [
            'distance_km' => $distanceKm,
            'distance_is_filtered' => $distanceIsFiltered,
            'duration_minutes' => $durationMinutes,
            'fuel_liters' => is_numeric($fuelLiters)
                ? round((float) $fuelLiters, 3)
                : null,
            'fuel_is_provisional' => $fuelIsProvisional,
            'quality_flags' => [],
        ];
    }
}
