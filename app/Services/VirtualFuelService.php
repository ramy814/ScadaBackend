<?php

namespace App\Services;

use App\Models\Trip;

class VirtualFuelService
{
    public function estimateLiters(Trip $trip): float
    {
        $distanceKm = $this->distanceKm($trip);
        $movingFuel = $distanceKm * 0.12;

        $idleMinutes = $trip->stops->sum(function ($stop): int {
            if (! $stop->ended_at) {
                return 0;
            }

            return $stop->started_at->diffInMinutes($stop->ended_at);
        });

        $idleFuel = ($idleMinutes / 60) * 1.0;

        return round($movingFuel + $idleFuel, 3);
    }

    private function distanceKm(Trip $trip): float
    {
        $points = $trip->points()
            ->orderBy('sequence_number')
            ->get(['latitude', 'longitude']);

        $distance = 0.0;

        for ($i = 1; $i < $points->count(); $i++) {
            $distance += $this->haversine(
                (float) $points[$i - 1]->latitude,
                (float) $points[$i - 1]->longitude,
                (float) $points[$i]->latitude,
                (float) $points[$i]->longitude,
            );
        }

        return $distance;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
