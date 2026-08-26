<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\TripSyncController;
use App\Models\Beneficiary;
use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use App\Services\CurrentBeneficiaryService;
use App\Services\DriverTripSummaryService;
use App\Services\TripSyncService;
use App\Services\VirtualFuelService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TripLiveTelemetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->uuid('local_id')->unique();
            $table->string('oracle_driver_id');
            $table->string('driver_name');
            $table->string('driver_phone');
            $table->string('driver_employee_number')->nullable();
            $table->string('oracle_vehicle_id')->nullable();
            $table->string('vehicle_plate');
            $table->string('vehicle_name')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('origin_type');
            $table->string('origin_id')->nullable();
            $table->string('origin_text');
            $table->string('destination_type');
            $table->string('destination_id')->nullable();
            $table->string('destination_text');
            $table->string('mission_type')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('wait_minutes')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->unsignedInteger('points_expected')->default(0);
            $table->unsignedInteger('points_received')->default(0);
            $table->unsignedTinyInteger('quality_version')->nullable();
            $table->decimal('virtual_fuel_liters', 10, 3)->nullable();
            $table->decimal('distance_km', 10, 3)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('review_status')->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id');
            $table->uuid('local_id')->nullable()->unique();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source')->default('manual');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id');
            $table->uuid('local_id');
            $table->string('event_type');
            $table->timestamp('detected_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('details_json')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'local_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('trip_tracking_events');
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trips');
        Mockery::close();

        parent::tearDown();
    }

    public function test_live_stop_and_tracking_retries_are_idempotent_and_preserve_legacy_waits(): void
    {
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('findAssignedToDriver')->times(3)->andReturn([
            'oracle_id' => '77',
            'plate_number' => '10-1234',
            'name' => 'مركبة اختبار',
            'vehicle_type' => 'شاحنة',
        ]);
        $service = new TripSyncService($vehicles, new VirtualFuelService);
        $driver = new Beneficiary;
        $driver->setRawAttributes([
            'OID' => '42',
            'MOBILE_NUM' => '0599000000',
            'FULL_NAME' => 'سائق اختبار',
            'NATIONAL_ID' => '100',
        ]);

        $payload = $this->payload('11111111-1111-4111-8111-111111111111');
        $payload['manual_wait_minutes'] = 3;
        $payload['wait_minutes'] = 3;
        $payload['stops'] = [[
            'local_id' => '22222222-2222-4222-8222-222222222222',
            'started_at' => '2026-07-29T08:00:00Z',
            'ended_at' => '2026-07-29T08:02:59Z',
            'latitude' => 31.5017,
            'longitude' => 34.4668,
            'source' => 'automatic',
        ]];
        $payload['tracking_events'] = [[
            'local_id' => '33333333-3333-4333-8333-333333333333',
            'event_type' => 'gps_disabled',
            'detected_at' => '2026-07-29T08:03:00Z',
        ]];

        $first = $service->storeTrip($driver, $payload);
        $second = $service->storeTrip($driver, $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(5, (int) $second->fresh()->wait_minutes);
        $this->assertSame(1, $second->stops()->count());
        $this->assertSame(1, $second->trackingEvents()->count());

        $legacy = $this->payload('44444444-4444-4444-8444-444444444444');
        $legacy['wait_minutes'] = 9;
        $legacy['stops'] = [[
            'started_at' => '2026-07-29T09:00:00Z',
            'ended_at' => '2026-07-29T09:02:59Z',
            'source' => 'automatic',
        ]];

        $legacyTrip = $service->storeTrip($driver, $legacy);

        $this->assertSame(9, (int) $legacyTrip->fresh()->wait_minutes);
    }

    public function test_driver_history_is_paginated_and_filtered_on_the_server(): void
    {
        $driver = new Beneficiary;
        $driver->setRawAttributes(['OID' => '42']);
        foreach ([1, 2, 3] as $id) {
            Trip::query()->create([
                'local_id' => "11111111-1111-4111-8111-11111111111{$id}",
                'oracle_driver_id' => '42',
                'driver_name' => 'سائق اختبار',
                'driver_phone' => '0599000000',
                'vehicle_plate' => $id === 3 ? '30-3000' : '20-2000',
                'status' => 'confirmed',
                'review_status' => $id === 3 ? 'approved' : 'pending',
                'started_at' => "2026-08-0{$id} 08:00:00",
                'ended_at' => "2026-08-0{$id} 09:00:00",
                'origin_type' => 'free_text',
                'origin_text' => 'المقر',
                'destination_type' => 'free_text',
                'destination_text' => 'الميدان',
                'mission_type' => 'مهمة اختبار',
                'points_expected' => 10,
                'points_received' => 10,
                'distance_km' => 2,
                'duration_minutes' => 60,
                'virtual_fuel_liters' => 0.24,
            ]);
        }

        $request = Request::create('/api/driver/trips', 'GET');
        $request->query->add([
            'status' => 'confirmed',
            'review_status' => 'pending',
            'q' => '20-2000',
            'page' => 2,
            'per_page' => 1,
        ]);
        $request->setUserResolver(fn () => $driver);

        $response = app(TripSyncController::class)->index(
            $request,
            new CurrentBeneficiaryService,
            new DriverTripSummaryService(new VirtualFuelService),
        );
        $payload = $response->getData(true);

        $this->assertSame(2, $payload['pagination']['current_page']);
        $this->assertSame(2, $payload['pagination']['last_page']);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertSame(1, $payload['trips'][0]['id']);
    }

    public function test_driver_history_does_not_scan_points_for_missing_legacy_summaries(): void
    {
        $driver = new Beneficiary;
        $driver->setRawAttributes(['OID' => '42']);
        Trip::query()->create([
            'local_id' => '55555555-5555-4555-8555-555555555555',
            'oracle_driver_id' => '42',
            'driver_name' => 'سائق قديم',
            'driver_phone' => '0599000000',
            'vehicle_plate' => '20-2000',
            'status' => 'confirmed',
            'review_status' => 'pending',
            'started_at' => '2026-07-01 08:00:00',
            'ended_at' => '2026-07-01 09:00:00',
            'origin_type' => 'free_text',
            'origin_text' => 'المقر',
            'destination_type' => 'free_text',
            'destination_text' => 'الميدان',
            'points_expected' => 10,
            'points_received' => 10,
            'distance_km' => null,
            'duration_minutes' => null,
            'virtual_fuel_liters' => null,
        ]);

        $request = Request::create('/api/driver/trips', 'GET');
        $request->setUserResolver(fn () => $driver);

        $response = app(TripSyncController::class)->index(
            $request,
            new CurrentBeneficiaryService,
            new DriverTripSummaryService(new VirtualFuelService),
        );
        $trip = $response->getData(true)['trips'][0];

        $this->assertNull($trip['distance_km']);
        $this->assertNull($trip['duration_minutes']);
        $this->assertNull($trip['fuel_liters']);
    }

    /** @return array<string, mixed> */
    private function payload(string $localId): array
    {
        return [
            'local_id' => $localId,
            'vehicle_plate' => '10-1234',
            'started_at' => '2026-07-29T08:00:00Z',
            'ended_at' => null,
            'origin' => ['type' => 'free_text', 'text' => 'المقر'],
            'destination' => ['type' => 'free_text', 'text' => 'الميدان'],
            'mission_type' => 'مهمة اختبار',
            'points_expected' => 0,
            'quality_version' => 2,
        ];
    }
}
