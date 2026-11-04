<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use App\Services\TripSyncService;
use App\Services\VirtualFuelService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TripPointBatchUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('uploaded');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedTinyInteger('quality_version')->nullable();
            $table->unsignedInteger('points_expected')->default(0);
            $table->unsignedInteger('points_received')->default(0);
            $table->string('client_checksum')->nullable();
            $table->string('server_checksum')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('distance_km', 10, 3)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('virtual_fuel_liters', 10, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('trip_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id');
            $table->unsignedInteger('sequence_number');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('provider')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('recorded_at')->nullable();
            $table->boolean('client_is_valid')->default(true);
            $table->string('quality_reason', 80)->nullable();
            $table->unique(['trip_id', 'sequence_number']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('trip_points');
        Schema::dropIfExists('trips');
        Mockery::close();

        parent::tearDown();
    }

    public function test_point_batches_use_chunked_upserts_and_remain_idempotent(): void
    {
        $trip = Trip::query()->create(['status' => 'uploaded']);
        $service = new TripSyncService(
            Mockery::mock(MunicipalityVehicleRepository::class),
            new VirtualFuelService,
        );
        $points = collect(range(1, 250))->map(fn (int $sequence): array => [
            'sequence_number' => $sequence,
            'latitude' => 31.5 + ($sequence / 100000),
            'longitude' => 34.45,
            'accuracy' => 5,
            'captured_at' => '2026-08-24T08:00:00Z',
            'recorded_at' => '2026-08-24T08:00:01Z',
            'client_is_valid' => true,
        ])->all();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame(250, $service->uploadPoints($trip, $points));

        $pointWrites = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'trip_points'))
            ->filter(fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'insert'));

        $this->assertCount(3, $pointWrites);
        $this->assertSame(250, $trip->points()->count());
        $this->assertSame(250, (int) $trip->fresh()->points_received);

        $service->uploadPoints($trip->fresh(), [[
            'sequence_number' => 1,
            'latitude' => 31.6,
            'longitude' => 34.46,
            'captured_at' => '2026-08-24T08:05:00Z',
        ]]);

        $this->assertSame(250, $trip->points()->count());
        $this->assertSame('31.6000000', $trip->points()->where('sequence_number', 1)->value('latitude'));
        $this->assertSame('5.00', $trip->points()->where('sequence_number', 1)->value('accuracy'));
    }

    public function test_confirmation_calculates_and_persists_the_summary_once(): void
    {
        $fuel = Mockery::mock(VirtualFuelService::class)->makePartial();
        $fuel->shouldReceive('metrics')->once()->andReturn([
            'distance_km' => 1.234,
            'raw_distance_km' => 1.5,
            'duration_minutes' => 45,
            'quality_flags' => [],
            'sample_count' => 2,
            'quality_sample_count' => 2,
        ]);
        $service = new TripSyncService(
            Mockery::mock(MunicipalityVehicleRepository::class),
            $fuel,
        );
        $trip = Trip::query()->create([
            'status' => 'uploaded',
            'started_at' => '2026-08-24 08:00:00',
            'ended_at' => '2026-08-24 08:45:00',
            'quality_version' => 2,
        ]);
        $service->uploadPoints($trip, [
            [
                'sequence_number' => 1,
                'latitude' => 31.5,
                'longitude' => 34.45,
                'accuracy' => 5,
                'captured_at' => '2026-08-24T08:00:00Z',
                'client_is_valid' => true,
            ],
            [
                'sequence_number' => 2,
                'latitude' => 31.501,
                'longitude' => 34.45,
                'accuracy' => 5,
                'captured_at' => '2026-08-24T08:00:10Z',
                'client_is_valid' => true,
            ],
        ]);
        $checksum = $service->checksumForPoints(
            $trip->points()->orderBy('sequence_number')->cursor(),
        );

        $confirmed = $service->confirm($trip->fresh(), 2, $checksum);
        $retried = $service->confirm($confirmed, 2, $checksum);

        $this->assertSame('confirmed', $retried->status);
        $this->assertSame(2, (int) $retried->points_received);
        $this->assertSame('1.234', $retried->distance_km);
        $this->assertSame(45, $retried->duration_minutes);
        $this->assertSame('0.148', $retried->virtual_fuel_liters);
    }
}
