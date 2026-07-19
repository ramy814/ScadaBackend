<?php

namespace Tests\Feature;

use App\Repositories\TripRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TripRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('vehicle_plate');
            $table->string('driver_name');
            $table->string('review_status')->nullable();
            $table->text('clarification_response')->nullable();
            $table->timestamp('started_at');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('trips');

        parent::tearDown();
    }

    public function test_dashboard_search_finds_an_exact_trip_number(): void
    {
        $now = now();

        DB::table('trips')->insert([
            [
                'id' => 42,
                'vehicle_plate' => '10-1234',
                'driver_name' => 'أحمد',
                'review_status' => 'pending',
                'clarification_response' => null,
                'started_at' => $now,
            ],
            [
                'id' => 43,
                'vehicle_plate' => '20-5678',
                'driver_name' => 'محمد',
                'review_status' => 'pending',
                'clarification_response' => null,
                'started_at' => $now,
            ],
        ]);

        $trips = app(TripRepository::class)->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '42',
        );

        $this->assertSame([42], $trips->getCollection()->pluck('id')->all());
    }

    public function test_dashboard_search_keeps_vehicle_and_driver_matching(): void
    {
        $now = now();

        DB::table('trips')->insert([
            [
                'id' => 1,
                'vehicle_plate' => '10-1234',
                'driver_name' => 'أحمد',
                'review_status' => 'pending',
                'clarification_response' => null,
                'started_at' => $now,
            ],
            [
                'id' => 2,
                'vehicle_plate' => '20-5678',
                'driver_name' => 'محمد',
                'review_status' => 'pending',
                'clarification_response' => null,
                'started_at' => $now,
            ],
        ]);

        $repository = app(TripRepository::class);

        $vehicleMatches = $repository->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '1234',
        );
        $driverMatches = $repository->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            'محمد',
        );

        $this->assertSame([1], $vehicleMatches->getCollection()->pluck('id')->all());
        $this->assertSame([2], $driverMatches->getCollection()->pluck('id')->all());
    }
}
