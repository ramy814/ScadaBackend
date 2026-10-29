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
use Symfony\Component\HttpKernel\Exception\HttpException;
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
                self::assertSame('draft', $attributes['review_status']);
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

    public function test_it_recalculates_distance_from_odometer_readings_for_create_and_update(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trip = new Trip(['id' => 77]);
        $trips->shouldReceive('findEditableManualForAssistant')->with(77, 140)->andReturn($trip);
        $vehicles->shouldReceive('findForAdministrativeAssistant')->with(140, 88)->andReturn([
            'oracle_id' => 88, 'plate_number' => 'TEST', 'oracle_driver_id' => 501,
        ]);
        $data = [
            'oracle_vehicle_id' => 88,
            'started_at' => '2026-10-12 10:00:00', 'ended_at' => '2026-10-12 11:00:00',
            'origin_text' => 'أ', 'destination_text' => 'ب', 'mission_type' => 'مهمة',
            'start_odometer' => 0, 'end_odometer' => 25, 'estimated_distance_km' => 999,
        ];
        $trips->shouldReceive('createManual')->once()->withArgs(function (array $attributes): bool {
            self::assertSame(25.0, $attributes['distance_km']);

            return true;
        })->andReturn($trip);
        $trips->shouldReceive('updateManual')->once()->withArgs(function (Trip $trip, array $attributes): bool {
            self::assertSame(25.0, $attributes['distance_km']);

            return true;
        })->andReturn($trip);
        $service = new ManualTripService($vehicles, $trips);
        $service->create(140, 'Assistant', $data);
        $service->update(140, 77, 'Assistant', $data);
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

    public function test_it_updates_only_an_owned_draft_manual_trip(): void
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

    public function test_submission_rechecks_live_entry_scope(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trip = new Trip(['oracle_vehicle_id' => '88', 'review_status' => 'draft']);
        $trips->shouldReceive('findEditableManualForAssistant')->with(77, 140)->once()->andReturn($trip);
        $vehicles->shouldReceive('findForAdministrativeAssistant')->with(140, 88)->once()->andReturnNull();
        $trips->shouldNotReceive('submitManual');

        $this->expectException(HttpException::class);
        (new ManualTripService($vehicles, $trips))->submit(140, 77, 'Assistant');
    }

    public function test_it_submits_an_owned_draft_in_the_current_vehicle_scope(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trip = new Trip(['oracle_vehicle_id' => '88', 'review_status' => 'draft']);
        $trips->shouldReceive('findEditableManualForAssistant')->with(77, 140)->once()->andReturn($trip);
        $vehicles->shouldReceive('findForAdministrativeAssistant')->with(140, 88)->once()->andReturn(['oracle_id' => 88]);
        $trips->shouldReceive('submitManual')->with(77, 140, 'Assistant')->once()->andReturn($trip);

        self::assertSame($trip, (new ManualTripService($vehicles, $trips))->submit(140, 77, 'Assistant'));
    }

    public function test_approval_rechecks_the_specific_vehicle_scope(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trip = new Trip;
        $trip->oracle_vehicle_id = '88';
        $trips->shouldReceive('findManualForApproval')->with(77)->once()->andReturn($trip);
        $vehicles->shouldReceive('reviewerCanAccessVehicle')->with(140, '88')->once()->andReturnFalse();
        $trips->shouldNotReceive('approveManual');

        $this->expectException(HttpException::class);
        (new ManualTripService($vehicles, $trips))->approve(140, 77, 'Reviewer', null);
    }

    public function test_it_approves_a_manual_request_for_an_authorized_vehicle(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trip = new Trip;
        $trip->oracle_vehicle_id = '88';
        $trips->shouldReceive('findManualForApproval')->with(77)->once()->andReturn($trip);
        $vehicles->shouldReceive('reviewerCanAccessVehicle')->with(140, '88')->once()->andReturnTrue();
        $trips->shouldReceive('approveManual')->with(77, 140, 'Reviewer', 'Checked')->once()->andReturn($trip);

        self::assertSame($trip, (new ManualTripService($vehicles, $trips))->approve(140, 77, 'Reviewer', 'Checked'));
    }

    public function test_a_mobile_trip_cannot_be_approved_through_the_manual_workflow(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $trips = Mockery::mock(TripRepository::class);
        $trips->shouldReceive('findManualForApproval')->with(77)->once()->andReturnNull();
        $vehicles->shouldNotReceive('reviewerCanAccessVehicle');
        $trips->shouldNotReceive('approveManual');

        $this->expectException(ModelNotFoundException::class);
        (new ManualTripService($vehicles, $trips))->approve(140, 77, 'Reviewer', null);
    }
}
