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
            $table->timestamp('ended_at')->nullable();
        });

        Schema::create('trip_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id');
            $table->timestamp('recorded_at');
        });

        Schema::create('trip_tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id');
            $table->string('event_type');
            $table->timestamp('detected_at');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('trip_tracking_events');
        Schema::dropIfExists('trip_points');
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

    public function test_dashboard_counts_gps_and_airplane_events_on_finished_trips(): void
    {
        $now = now();

        DB::table('trips')->insert([
            'id' => 7,
            'vehicle_plate' => '30-9999',
            'driver_name' => 'سائق الاختبار',
            'review_status' => 'pending',
            'clarification_response' => null,
            'started_at' => $now->copy()->subHour(),
            'ended_at' => $now,
        ]);
        DB::table('trip_tracking_events')->insert([
            ['trip_id' => 7, 'event_type' => 'gps_disabled', 'detected_at' => $now->copy()->subMinutes(30)],
            ['trip_id' => 7, 'event_type' => 'gps_disabled', 'detected_at' => $now->copy()->subMinutes(20)],
            ['trip_id' => 7, 'event_type' => 'airplane_mode_on', 'detected_at' => $now->copy()->subMinutes(10)],
            ['trip_id' => 7, 'event_type' => 'gps_enabled', 'detected_at' => $now->copy()->subMinutes(5)],
        ]);

        $trip = app(TripRepository::class)->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '',
        )->getCollection()->first();

        $this->assertSame(2, $trip->gps_disabled_events_count);
        $this->assertSame(1, $trip->airplane_mode_events_count);
    }
}
