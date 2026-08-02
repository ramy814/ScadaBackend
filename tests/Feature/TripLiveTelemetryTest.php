<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Repositories\MunicipalityVehicleRepository;
use App\Services\TripSyncService;
use App\Services\VirtualFuelService;
use Illuminate\Database\Schema\Blueprint;
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
            $table->unsignedTinyInteger('quality_version')->nullable();
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
