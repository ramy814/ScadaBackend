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
            $table->string('status')->nullable();
            $table->string('review_status')->nullable();
            $table->text('clarification_response')->nullable();
            $table->decimal('virtual_fuel_liters', 10, 3)->nullable();
            $table->unsignedInteger('points_received')->default(0);
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
            true,
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
            true,
        );
        $driverMatches = $repository->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            'محمد',
            true,
        );

        $this->assertSame([1], $vehicleMatches->getCollection()->pluck('id')->all());
        $this->assertSame([2], $driverMatches->getCollection()->pluck('id')->all());
    }

    public function test_dashboard_counts_gps_airplane_and_gap_events_on_finished_trips(): void
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
            ['trip_id' => 7, 'event_type' => 'tracking_gap', 'detected_at' => $now->copy()->subMinutes(8)],
            ['trip_id' => 7, 'event_type' => 'gps_enabled', 'detected_at' => $now->copy()->subMinutes(5)],
        ]);

        $trip = app(TripRepository::class)->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '',
            true,
        )->getCollection()->first();

        $this->assertSame(2, $trip->gps_disabled_events_count);
        $this->assertSame(1, $trip->airplane_mode_events_count);
        $this->assertSame(1, $trip->tracking_gap_events_count);
    }

    public function test_dashboard_hides_trips_with_fewer_than_fifty_points_unless_requested(): void
    {
        $now = now();

        DB::table('trips')->insert([
            [
                'id' => 10,
                'vehicle_plate' => '10-0001',
                'driver_name' => 'سائق فعلي',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'points_received' => 50,
                'started_at' => $now,
                'ended_at' => $now->copy()->addMinutes(10),
            ],
            [
                'id' => 11,
                'vehicle_plate' => '10-0002',
                'driver_name' => 'سائق تجريبي',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'points_received' => 49,
                'started_at' => $now->copy()->subMinute(),
                'ended_at' => $now->copy()->addMinutes(5),
            ],
            [
                'id' => 12,
                'vehicle_plate' => '10-0003',
                'driver_name' => 'سائق مباشر',
                'status' => 'uploaded',
                'review_status' => 'pending',
                'points_received' => 1,
                'started_at' => $now->copy()->addSecond(),
                'ended_at' => null,
            ],
        ]);

        DB::table('trip_points')->insert(collect(range(1, 50))->map(fn (int $id): array => [
            'trip_id' => 10,
            'recorded_at' => $now->copy()->addSeconds($id),
        ])->all());
        DB::table('trip_points')->insert(collect(range(1, 49))->map(fn (int $id): array => [
            'trip_id' => 11,
            'recorded_at' => $now->copy()->addSeconds($id),
        ])->all());
        DB::table('trip_points')->insert([
            'trip_id' => 12,
            'recorded_at' => $now,
        ]);

        $repository = app(TripRepository::class);
        $defaultTrips = $repository->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '',
        );
        $allTrips = $repository->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '',
            true,
        );

        $this->assertSame([12, 10], $defaultTrips->getCollection()->pluck('id')->all());
        $this->assertSame([12, 10, 11], $allTrips->getCollection()->pluck('id')->all());
        $this->assertSame(2, $repository->dashboardCounts()['todayTrips']);
        $this->assertSame(3, $repository->dashboardCounts(true)['todayTrips']);
    }

    public function test_dashboard_fuel_uses_confirmed_visible_trips_in_the_selected_range(): void
    {
        $now = now();

        DB::table('trips')->insert([
            [
                'id' => 20,
                'vehicle_plate' => '20-0001',
                'driver_name' => 'سائق محسوب',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'virtual_fuel_liters' => 2.5,
                'points_received' => 50,
                'started_at' => $now,
                'ended_at' => $now->copy()->addHour(),
            ],
            [
                'id' => 21,
                'vehicle_plate' => '20-0002',
                'driver_name' => 'سائق تجريبي',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'virtual_fuel_liters' => 1.25,
                'points_received' => 49,
                'started_at' => $now,
                'ended_at' => $now->copy()->addHour(),
            ],
            [
                'id' => 22,
                'vehicle_plate' => '20-0003',
                'driver_name' => 'سائق غير مؤكد',
                'status' => 'uploaded',
                'review_status' => 'pending',
                'virtual_fuel_liters' => 4,
                'points_received' => 50,
                'started_at' => $now,
                'ended_at' => $now->copy()->addHour(),
            ],
            [
                'id' => 23,
                'vehicle_plate' => '20-0004',
                'driver_name' => 'سائق خارج الفترة',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'virtual_fuel_liters' => 8,
                'points_received' => 50,
                'started_at' => $now->copy()->subDays(3),
                'ended_at' => $now->copy()->subDays(3)->addHour(),
            ],
        ]);

        foreach ([20 => 50, 21 => 49, 22 => 50, 23 => 50] as $tripId => $pointCount) {
            DB::table('trip_points')->insert(collect(range(1, $pointCount))->map(fn (int $id): array => [
                'trip_id' => $tripId,
                'recorded_at' => $now->copy()->addSeconds($id),
            ])->all());
        }

        $repository = app(TripRepository::class);
        $from = $now->copy()->subDay();
        $to = $now->copy()->addDay();

        $this->assertSame([
            'fuelConsumptionLiters' => 2.5,
            'fuelConsumptionTrips' => 1,
        ], $repository->dashboardFuelConsumption($from, $to));
        $this->assertSame([
            'fuelConsumptionLiters' => 3.75,
            'fuelConsumptionTrips' => 2,
        ], $repository->dashboardFuelConsumption($from, $to, true));
    }
}
