<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Trip;
use App\Services\DriverTripSummaryService;
use App\Services\VirtualFuelService;
use PHPUnit\Framework\TestCase;

class DriverTripSummaryServiceTest extends TestCase
{
    public function test_it_returns_stored_driver_metrics_and_provisional_fuel(): void
    {
        $trip = new Trip;
        $trip->forceFill([
            'quality_version' => 2,
            'distance_km' => '12.345',
            'duration_minutes' => 75,
            'virtual_fuel_liters' => null,
        ]);

        $summary = (new DriverTripSummaryService(new VirtualFuelService))
            ->summarize($trip);

        self::assertSame(12.345, $summary['distance_km']);
        self::assertSame(75, $summary['duration_minutes']);
        self::assertSame(1.481, $summary['fuel_liters']);
        self::assertTrue($summary['fuel_is_provisional']);
        self::assertTrue($summary['distance_is_filtered']);
    }

    public function test_it_prefers_the_stored_official_fuel_value(): void
    {
        $trip = new Trip;
        $trip->forceFill([
            'quality_version' => 2,
            'distance_km' => 10,
            'duration_minutes' => 30,
            'virtual_fuel_liters' => '1.200',
        ]);

        $summary = (new DriverTripSummaryService(new VirtualFuelService))
            ->summarize($trip);

        self::assertSame(1.2, $summary['fuel_liters']);
        self::assertFalse($summary['fuel_is_provisional']);
    }

    public function test_it_does_not_show_zero_fuel_when_no_gps_point_is_usable(): void
    {
        $fuel = new class extends VirtualFuelService
        {
            public function metrics(Trip $trip): array
            {
                throw new \RuntimeException('GET summaries must not load trip points.');
            }
        };
        $trip = new Trip;
        $trip->forceFill([
            'quality_version' => 2,
            'distance_km' => '0.000',
            'duration_minutes' => 20,
            'virtual_fuel_liters' => null,
        ]);

        $summary = (new DriverTripSummaryService($fuel))->summarize($trip);

        self::assertNull($summary['fuel_liters']);
        self::assertFalse($summary['fuel_is_provisional']);
    }

    public function test_it_returns_nulls_for_legacy_rows_without_loading_points(): void
    {
        $fuel = new class extends VirtualFuelService
        {
            public function metrics(Trip $trip): array
            {
                throw new \RuntimeException('GET summaries must not load trip points.');
            }
        };
        $trip = new Trip;
        $trip->forceFill([
            'quality_version' => null,
            'distance_km' => null,
            'duration_minutes' => null,
            'virtual_fuel_liters' => null,
        ]);

        $summary = (new DriverTripSummaryService($fuel))->summarize($trip);

        self::assertNull($summary['distance_km']);
        self::assertNull($summary['duration_minutes']);
        self::assertNull($summary['fuel_liters']);
        self::assertFalse($summary['fuel_is_provisional']);
        self::assertSame([], $summary['quality_flags']);
    }
}
