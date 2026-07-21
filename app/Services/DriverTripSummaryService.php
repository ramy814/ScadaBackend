<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Trip;

class DriverTripSummaryService
{
    public function __construct(private readonly VirtualFuelService $fuel) {}

    public function summarize(Trip $trip): array
    {
        $distanceKm = $trip->distance_km;
        $durationMinutes = $trip->duration_minutes;
        $distanceIsFiltered = (int) ($trip->quality_version ?? 0) >= 2;
        $qualityFlags = [];
        $metrics = null;

        if ($distanceKm === null ||
            $durationMinutes === null ||
            ($trip->virtual_fuel_liters === null && (float) $distanceKm === 0.0)) {
            $metrics = $this->fuel->metrics($trip);
            $distanceIsFiltered = $distanceIsFiltered
                || $this->fuel->hasSufficientQualityData($metrics);
            $distanceKm = $distanceIsFiltered
                ? $metrics['distance_km']
                : $metrics['raw_distance_km'];
            $durationMinutes = $metrics['duration_minutes'];
            $qualityFlags = $metrics['quality_flags'];
        }

        $fuelLiters = $trip->virtual_fuel_liters;
        $fuelIsProvisional = false;
        if ($fuelLiters === null && is_numeric($distanceKm)) {
            $fuelLiters = $distanceIsFiltered && $metrics !== null
                ? $this->fuel->estimateProvisionalFromMetrics($metrics)
                : round(
                    (float) $distanceKm * $this->fuel->litersPerKilometer(),
                    3,
                );
            $fuelIsProvisional = $fuelLiters !== null;
        }

        return [
            'distance_km' => is_numeric($distanceKm)
                ? round((float) $distanceKm, 3)
                : null,
            'distance_is_filtered' => $distanceIsFiltered,
            'duration_minutes' => is_numeric($durationMinutes)
                ? (int) $durationMinutes
                : null,
            'fuel_liters' => is_numeric($fuelLiters)
                ? round((float) $fuelLiters, 3)
                : null,
            'fuel_is_provisional' => $fuelIsProvisional,
            'quality_flags' => $qualityFlags,
        ];
    }
}
