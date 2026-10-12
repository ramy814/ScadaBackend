<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\TripRepository;
use App\Services\ManualTripService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ManualTripServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_creates_a_confirmed_manual_trip_from_a_vehicle_in_the_assistants_scope(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $vehicles->shouldReceive('findForAdministrativeAssistant')
            ->once()
            ->with(140, 88)
            ->andReturn([
                'oracle_id' => 88,
                'plate_number' => '3-1234',
                'name' => 'مركبة خدمات',
                'vehicle_type' => 10,
                'oracle_driver_id' => 501,
                'driver_name' => 'سائق المحطة',
                'driver_phone' => '0599000000',
                'driver_employee_number' => '9001',
            ]);

        $trip = new Trip;
        $trip->setAttribute('id', 77);
        $trips->shouldReceive('createManual')
            ->once()
            ->withArgs(function (array $attributes, string $actor): bool {
                self::assertSame('manual', $attributes['entry_source']);
                self::assertSame('confirmed', $attributes['status']);
                self::assertSame('pending', $attributes['review_status']);
                self::assertSame('501', $attributes['oracle_driver_id']);
                self::assertSame('88', $attributes['oracle_vehicle_id']);
                self::assertSame(0, $attributes['points_expected']);
                self::assertSame(90, $attributes['duration_minutes']);
                self::assertSame(12.75, $attributes['distance_km']);
                self::assertSame(140, $attributes['entered_by_user_id']);
                self::assertSame('مساعد إداري', $actor);

                return true;
            })
            ->andReturn($trip);

        $result = (new ManualTripService($vehicles, $trips))->create(
            140,
            'مساعد إداري',
            [
                'oracle_vehicle_id' => 88,
                'started_at' => '2026-10-12 08:00:00',
                'ended_at' => '2026-10-12 09:30:00',
                'origin_text' => 'المحطة',
                'destination_text' => 'البلدية',
                'mission_type' => 'مهمة إدارية',
                'estimated_distance_km' => '12.750',
            ],
        );

        self::assertSame(77, $result->getKey());
    }

    public function test_it_rejects_a_vehicle_outside_the_assistants_scope(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $vehicles->shouldReceive('findForAdministrativeAssistant')->once()->andReturnNull();
        $trips->shouldNotReceive('createManual');

        $this->expectException(ValidationException::class);

        (new ManualTripService($vehicles, $trips))->create(140, 'مساعد', [
            'oracle_vehicle_id' => 999,
            'started_at' => '2026-10-12 08:00:00',
            'ended_at' => '2026-10-12 09:00:00',
            'origin_text' => 'أ',
            'destination_text' => 'ب',
            'mission_type' => 'مهمة',
            'estimated_distance_km' => 2,
        ]);
    }

    public function test_it_updates_only_an_owned_pending_manual_trip(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trip = new Trip;
        $trip->setAttribute('id', 77);

        $trips->shouldReceive('findEditableManualForAssistant')->once()->with(77, 140)->andReturn($trip);
        $vehicles->shouldReceive('findForAdministrativeAssistant')->once()->with(140, 88)->andReturn([
            'oracle_id' => 88,
            'plate_number' => '3-1234',
            'oracle_driver_id' => 501,
            'driver_name' => 'سائق المحطة',
        ]);
        $trips->shouldReceive('updateManual')->once()->withArgs(
            fn (Trip $ownedTrip, array $attributes, string $actor): bool => $ownedTrip === $trip
                && $attributes['distance_km'] === 14.125
                && $attributes['duration_minutes'] === 75
                && $actor === 'مساعد إداري'
        )->andReturn($trip);

        $result = (new ManualTripService($vehicles, $trips))->update(140, 77, 'مساعد إداري', [
            'oracle_vehicle_id' => 88,
            'started_at' => '2026-10-12 08:00:00',
            'ended_at' => '2026-10-12 09:15:00',
            'origin_text' => 'المحطة',
            'destination_text' => 'البلدية',
            'mission_type' => 'مهمة إدارية',
            'estimated_distance_km' => '14.125',
        ]);

        self::assertSame(77, $result->getKey());
    }

    public function test_it_rejects_editing_a_reviewed_or_foreign_manual_trip(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trips->shouldReceive('findEditableManualForAssistant')->once()->with(77, 140)->andReturnNull();
        $vehicles->shouldNotReceive('findForAdministrativeAssistant');
        $trips->shouldNotReceive('updateManual');

        $this->expectException(ModelNotFoundException::class);

        (new ManualTripService($vehicles, $trips))->update(140, 77, 'مساعد إداري', []);
    }
}
