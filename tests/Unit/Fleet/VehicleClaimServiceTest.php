<?php

declare(strict_types=1);

namespace Tests\Unit\Fleet;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Modules\Fleet\Repositories\TripRepository;
use App\Modules\Fleet\Services\FleetAccessService;
use App\Modules\Fleet\Services\VehicleClaimService;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

/** Ported from the legacy tests/Unit/VehicleClaimServiceTest.php (commit 72f4cfe). */
class VehicleClaimServiceTest extends TestCase
{
    protected bool $fixtureSchema = false;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_amount_uses_trip_durations_times_the_price_captured_at_approval(): void
    {
        $service = $this->service(collect([
            $this->claimTrip(1, '10', '2026-11-03 08:00:00', '2026-11-03 10:30:00', '30.00'),
            $this->claimTrip(2, '10', '2026-11-03 12:00:00', '2026-11-03 12:45:00', '30.00'),
            // Price changed before this approval; the earlier trips keep their own snapshot.
            $this->claimTrip(3, '10', '2026-11-04 08:00:00', '2026-11-04 09:00:00', '40.00'),
        ]), expectedLiveLookup: null);

        $claim = $service->build(140, ['month' => '2026-11']);
        $row = $claim['rows'][0];

        self::assertSame('10', $row['oracle_vehicle_id']);
        self::assertSame(3, $row['trips_count']);
        self::assertSame('4:15', $row['work_time']);
        self::assertSame(4.25, $row['work_hours']);
        // 3.25h × 30 + 1h × 40
        self::assertSame(137.5, $row['amount']);
        self::assertSame('30 – 40', $row['price_label']);
        self::assertSame(0, $row['current_price_trips']);
        self::assertCount(2, $row['days']);
        self::assertSame(['2026-11-03', 2, '3:15', 97.5], [
            $row['days'][0]['date'], $row['days'][0]['trips_count'], $row['days'][0]['work_time'], $row['days'][0]['amount'],
        ]);
        self::assertSame(137.5, $claim['totals']['amount']);
        self::assertSame('2026-11-01', $claim['filters']['date_from']);
        self::assertSame('2026-11-30', $claim['filters']['date_to']);
    }

    public function test_trips_approved_before_capture_use_the_current_price_and_unpriced_vehicles_are_hidden(): void
    {
        $service = $this->service(collect([
            $this->claimTrip(1, '10', '2026-11-03 08:00:00', '2026-11-03 10:00:00', null),
            $this->claimTrip(2, '20', '2026-11-03 08:00:00', '2026-11-03 09:00:00', null),
        ]), expectedLiveLookup: ['10', '20'], livePrices: ['10' => 25.0, '20' => null]);

        $claim = $service->build(140, ['month' => '2026-11']);

        self::assertCount(1, $claim['rows']);
        self::assertSame(50.0, $claim['rows'][0]['amount']);
        self::assertSame(1, $claim['rows'][0]['current_price_trips']);
        self::assertSame(1, $claim['totals']['hidden_unpriced_vehicles']);
        self::assertSame(0, $claim['totals']['unpriced_trips']);
    }

    public function test_unpriced_vehicles_can_be_included_without_adding_to_the_amount(): void
    {
        $service = $this->service(collect([
            $this->claimTrip(2, '20', '2026-11-03 08:00:00', '2026-11-03 09:00:00', null),
        ]), expectedLiveLookup: ['20'], livePrices: []);

        $claim = $service->build(140, ['date_from' => '2026-11-01', 'date_to' => '2026-11-15', 'include_unpriced' => '1']);

        self::assertCount(1, $claim['rows']);
        self::assertSame(0.0, $claim['rows'][0]['amount']);
        self::assertSame(1, $claim['rows'][0]['unpriced_trips']);
        self::assertSame('—', $claim['rows'][0]['price_label']);
        self::assertNull($claim['filters']['month']);
        self::assertSame('2026-11-15', $claim['filters']['date_to']);
    }

    private function service($trips, ?array $expectedLiveLookup, array $livePrices = []): VehicleClaimService
    {
        $tripRepository = Mockery::mock(TripRepository::class);
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $access = Mockery::mock(FleetAccessService::class);

        $access->shouldReceive('allowedVehicleIds')->with(140)->once()->andReturn(['10']);
        $tripRepository->shouldReceive('approvedTripsForClaims')
            ->once()
            ->withArgs(fn ($from, $to, string $search, array $allowed): bool => $allowed === ['10'] && $search === '')
            ->andReturn($trips);

        if ($expectedLiveLookup === null) {
            $vehicles->shouldNotReceive('hourlyPricesFor');
        } else {
            $vehicles->shouldReceive('hourlyPricesFor')->with($expectedLiveLookup)->once()->andReturn($livePrices);
        }

        return new VehicleClaimService($tripRepository, $vehicles, $access);
    }

    private function claimTrip(int $id, string $vehicleId, string $start, string $end, ?string $price): Trip
    {
        $trip = new Trip;
        $trip->forceFill([
            'id' => $id,
            'oracle_vehicle_id' => $vehicleId,
            'vehicle_plate' => 'P-'.$vehicleId,
            'vehicle_name' => 'Bus '.$vehicleId,
            'driver_name' => 'Driver '.$vehicleId,
            'started_at' => CarbonImmutable::parse($start),
            'ended_at' => CarbonImmutable::parse($end),
            'hourly_price' => $price,
        ]);

        return $trip;
    }
}
