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
            $table->unsignedInteger('points_received')->default(0);
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
}
