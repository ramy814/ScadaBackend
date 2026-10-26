<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Repositories\TripRepository;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
            $table->string('review_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('clarification_response')->nullable();
            $table->decimal('virtual_fuel_liters', 10, 3)->nullable();
            $table->unsignedInteger('points_received')->default(0);
            $table->string('entry_source')->nullable()->default('mobile');
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            $table->string('entered_by_name')->nullable();
            $table->string('oracle_vehicle_id')->nullable();
            $table->string('origin_text')->nullable();
            $table->string('destination_text')->nullable();
            $table->string('mission_type')->nullable();
            $table->decimal('distance_km', 10, 3)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('actor');
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id');
            $table->unsignedInteger('sequence_number')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('client_is_valid')->default(true);
            $table->string('quality_reason')->nullable();
            $table->timestamp('recorded_at')->nullable();
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
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('trips');

        parent::tearDown();
    }

    public function test_approval_register_lists_other_creators_requests_but_only_for_authorized_vehicles(): void
    {
        foreach ([
            [80, '501', 'manual', 'pending', 'confirmed', 140],
            [81, '501', 'manual', 'pending', 'confirmed', 141],
            [82, '502', 'manual', 'pending', 'confirmed', 141],
            [83, '501', 'mobile', 'pending', 'confirmed', 141],
            [84, '501', 'manual', 'approved', 'confirmed', 141],
            [85, '501', 'manual', 'pending', 'uploaded', 141],
        ] as [$id, $vehicle, $source, $review, $status, $creator]) {
            DB::table('trips')->insert([
                'id' => $id, 'oracle_vehicle_id' => $vehicle, 'entry_source' => $source,
                'review_status' => $review, 'status' => $status, 'entered_by_user_id' => $creator,
                'vehicle_plate' => 'TEST', 'driver_name' => 'Driver', 'started_at' => now(), 'ended_at' => now(),
            ]);
        }
        $repository = app(TripRepository::class);
        self::assertEqualsCanonicalizing([80, 81], $repository->paginateManualForApproval(['501'], ['review_status' => 'pending'])->pluck('id')->all());
        self::assertCount(3, $repository->paginateManualForApproval(['501'], ['review_status' => 'all']));
        self::assertCount(0, $repository->paginateManualForApproval([], ['review_status' => 'all']));
        self::assertNull($repository->findManualForApproval(83));
    }

    public function test_manual_approval_is_audited_and_cannot_be_repeated(): void
    {
        DB::table('trips')->insert([
            'id' => 80, 'entry_source' => 'manual', 'status' => 'confirmed', 'review_status' => 'pending',
            'vehicle_plate' => 'TEST', 'driver_name' => 'Driver', 'started_at' => now(), 'ended_at' => now(),
        ]);
        $repository = app(TripRepository::class);
        $staleEditableTrip = Trip::query()->findOrFail(80);
        $trip = $repository->approveManual(80, 140, 'Reviewer', 'Checked');
        self::assertSame('approved', $trip->review_status);
        self::assertSame('140', (string) $trip->reviewed_by);
        self::assertSame('Checked', $trip->review_note);
        self::assertNotNull($trip->reviewed_at);
        self::assertSame(1, DB::table('audit_logs')->count());
        $audit = json_decode(DB::table('audit_logs')->value('payload'), true);
        self::assertSame('Reviewer', $audit['actor_name']);

        try {
            $repository->updateManual($staleEditableTrip, ['distance_km' => 999], 'Editor');
            self::fail('An edit opened before approval must not modify an approved request.');
        } catch (ValidationException) {
            self::assertSame(1, DB::table('audit_logs')->count());
            self::assertNull($trip->refresh()->distance_km);
        }

        try {
            $repository->approveManual(80, 141, 'Other reviewer', null);
            self::fail('A completed approval must be immutable.');
        } catch (ValidationException) {
            self::assertSame(1, DB::table('audit_logs')->count());
            self::assertSame('140', (string) $trip->refresh()->reviewed_by);
        }
    }

    public function test_manual_approval_rolls_back_when_audit_write_fails(): void
    {
        DB::table('trips')->insert([
            'id' => 80, 'entry_source' => 'manual', 'status' => 'confirmed', 'review_status' => 'pending',
            'vehicle_plate' => 'TEST', 'driver_name' => 'Driver', 'started_at' => now(), 'ended_at' => now(),
        ]);
        Schema::drop('audit_logs');
        try {
            app(TripRepository::class)->approveManual(80, 140, 'Reviewer', null);
            self::fail('Approval must not succeed without its audit record.');
        } catch (QueryException) {
            self::assertSame('pending', DB::table('trips')->where('id', 80)->value('review_status'));
        }
    }

    public function test_rejected_or_unfinished_requests_cannot_be_approved(): void
    {
        foreach ([['rejected', 'confirmed', now()], ['pending', 'uploaded', now()], ['pending', 'confirmed', null]] as $index => [$review, $status, $endedAt]) {
            $id = 80 + $index;
            DB::table('trips')->insert([
                'id' => $id, 'entry_source' => 'manual', 'status' => $status, 'review_status' => $review,
                'vehicle_plate' => 'TEST', 'driver_name' => 'Driver', 'started_at' => now(), 'ended_at' => $endedAt,
            ]);
            try {
                app(TripRepository::class)->approveManual($id, 140, 'Reviewer', null);
                self::fail('Only pending, finished, confirmed requests may be approved.');
            } catch (ValidationException) {
                self::assertSame($review, DB::table('trips')->where('id', $id)->value('review_status'));
                self::assertSame(0, DB::table('audit_logs')->count());
            }
        }
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

    public function test_manual_dashboard_is_limited_to_the_authorized_vehicle_ids(): void
    {
        $now = now();

        DB::table('trips')->insert([
            [
                'id' => 44,
                'entry_source' => 'manual',
                'vehicle_plate' => '10-0044',
                'driver_name' => 'سائق مصرح',
                'oracle_vehicle_id' => '501',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'points_received' => 50,
                'virtual_fuel_liters' => 1.5,
                'started_at' => $now,
            ],
            [
                'id' => 45,
                'entry_source' => 'manual',
                'vehicle_plate' => '10-0045',
                'driver_name' => 'سائق غير مصرح',
                'oracle_vehicle_id' => '502',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'points_received' => 50,
                'virtual_fuel_liters' => 2.5,
                'started_at' => $now,
            ],
        ]);

        $trips = app(TripRepository::class)->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '',
            tripSource: 'manual',
            allowedVehicleIds: ['501'],
        );

        self::assertSame([44], $trips->getCollection()->pluck('id')->all());
        self::assertSame(1, app(TripRepository::class)->dashboardCounts(false, ['501'])['todayTrips']);
        self::assertSame([
            'fuelConsumptionLiters' => 1.5,
            'fuelConsumptionTrips' => 1,
        ], app(TripRepository::class)->dashboardFuelConsumption(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            false,
            ['501'],
        ));
    }

    public function test_reports_exclude_unassigned_manual_vehicles_and_deny_empty_manual_scope(): void
    {
        $now = now();
        DB::table('trips')->insert([
            ['id' => 80, 'entry_source' => 'manual', 'vehicle_plate' => '10-0080', 'driver_name' => 'Assigned', 'oracle_vehicle_id' => '501', 'started_at' => $now],
            ['id' => 81, 'entry_source' => 'manual', 'vehicle_plate' => '10-0081', 'driver_name' => 'Unassigned', 'oracle_vehicle_id' => '502', 'started_at' => $now],
        ]);
        $repository = app(TripRepository::class);
        $from = $now->copy()->subDay();
        $to = $now->copy()->addDay();

        self::assertSame([80], $repository->reportQuery($from, $to, '', '', ['501'])->pluck('id')->all());
        self::assertSame([], $repository->reportQuery($from, $to, '', '', [])->pluck('id')->all());
        self::assertSame([], $repository->reportQuery($from, $to, '', '10-0081', ['501'])->pluck('id')->all());
    }

    public function test_mobile_dashboard_reports_and_fuel_remain_visible_without_manual_vehicle_assignments(): void
    {
        $now = now();
        foreach ([
            [44, 'mobile', '501'], [45, 'mobile', '502'], [46, 'manual', '501'],
            [47, 'manual', '502'], [48, null, '503'],
        ] as [$id, $source, $vehicleId]) {
            DB::table('trips')->insert([
                'id' => $id, 'entry_source' => $source, 'oracle_vehicle_id' => $vehicleId,
                'vehicle_plate' => '10-'.$id, 'driver_name' => 'Driver',
                'started_at' => $now, 'ended_at' => $now, 'status' => 'confirmed',
                'review_status' => 'pending', 'points_received' => 50, 'virtual_fuel_liters' => 1,
            ]);
        }
        $repository = app(TripRepository::class);
        $from = $now->copy()->subDay();
        $to = $now->copy()->addDay();
        self::assertEqualsCanonicalizing([44, 45, 48], $repository->paginateForDashboard(
            $from, $to, 'all', '', allowedVehicleIds: [],
        )->pluck('id')->all());
        self::assertSame(3, $repository->dashboardCounts(false, [])['todayTrips']);
        self::assertSame(0, $repository->dashboardCounts(false, [])['manualPendingTrips']);
        self::assertSame(['fuelConsumptionLiters' => 3.0, 'fuelConsumptionTrips' => 3], $repository->dashboardFuelConsumption($from, $to, false, []));
        self::assertEqualsCanonicalizing([44, 45, 48], $repository->reportQuery($from, $to, '', '', [])->pluck('id')->all());
        self::assertCount(0, $repository->paginateForDashboard($from, $to, 'all', '', tripSource: 'manual', allowedVehicleIds: []));
        self::assertEqualsCanonicalizing([46], $repository->paginateForDashboard($from, $to, 'all', '', tripSource: 'manual', allowedVehicleIds: ['501'])->pluck('id')->all());
        self::assertSame(4, $repository->dashboardCounts(false, ['501'])['todayTrips']);
        self::assertEqualsCanonicalizing([44, 45, 46, 48], $repository->reportQuery($from, $to, '', '', ['501'])->pluck('id')->all());
        self::assertSame(0, $repository->reportQuery($from, $to, '', '10-47', [])->count());
    }

    public function test_route_preview_sampling_runs_in_the_database_and_keeps_endpoints(): void
    {
        $now = now();
        DB::table('trips')->insert([
            'id' => 30,
            'vehicle_plate' => '30-0030',
            'driver_name' => 'سائق المعاينة',
            'status' => 'confirmed',
            'review_status' => 'pending',
            'points_received' => 250,
            'started_at' => $now,
            'ended_at' => $now->copy()->addHour(),
        ]);
        DB::table('trip_points')->insert(
            collect(range(1, 250))->map(fn (int $sequence): array => [
                'trip_id' => 30,
                'sequence_number' => $sequence,
                'latitude' => 31.5 + ($sequence / 100000),
                'longitude' => 34.45,
                'client_is_valid' => $sequence !== 4,
                'quality_reason' => $sequence === 6
                    ? 'stationary_heartbeat'
                    : null,
                'recorded_at' => $now->copy()->addSeconds($sequence),
            ])->all(),
        );

        $preview = app(TripRepository::class)->routePreviewPoints(
            Trip::query()->findOrFail(30),
        );
        $points = $preview['points'];

        $this->assertLessThanOrEqual(100, $points->count());
        $this->assertSame(248, $preview['original_points']);
        $this->assertSame(31.50001, round((float) $points->first()->latitude, 6));
        $this->assertSame(31.5025, round((float) $points->last()->latitude, 6));
    }

    public function test_route_preview_returns_all_small_routes_with_sequence_gaps(): void
    {
        $now = now();
        DB::table('trips')->insert([
            'id' => 31,
            'vehicle_plate' => '30-0031',
            'driver_name' => 'سائق المسار الصغير',
            'status' => 'confirmed',
            'review_status' => 'pending',
            'points_received' => 3,
            'started_at' => $now,
            'ended_at' => $now->copy()->addHour(),
        ]);
        DB::table('trip_points')->insert(collect([1, 10, 100])->map(
            fn (int $sequence): array => [
                'trip_id' => 31,
                'sequence_number' => $sequence,
                'latitude' => 31.5 + ($sequence / 100000),
                'longitude' => 34.45,
                'client_is_valid' => true,
                'quality_reason' => null,
                'recorded_at' => $now->copy()->addSeconds($sequence),
            ]
        )->all());

        $preview = app(TripRepository::class)->routePreviewPoints(
            Trip::query()->findOrFail(31),
        );

        $this->assertSame(3, $preview['original_points']);
        $this->assertCount(3, $preview['points']);
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
                'entry_source' => 'mobile',
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
                'entry_source' => 'mobile',
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
                'entry_source' => 'mobile',
                'started_at' => $now->copy()->addSecond(),
                'ended_at' => null,
            ],
            [
                'id' => 13,
                'vehicle_plate' => '10-0004',
                'driver_name' => 'سائق إدخال يدوي',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'points_received' => 0,
                'entry_source' => 'manual',
                'started_at' => $now->copy()->subSeconds(30),
                'ended_at' => $now->copy()->addMinutes(4),
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
        $manualTrips = $repository->paginateForDashboard(
            $now->copy()->subDay(),
            $now->copy()->addDay(),
            'all',
            '',
            false,
            'manual',
        );

        $this->assertSame([12, 10], $defaultTrips->getCollection()->pluck('id')->all());
        $this->assertSame([12, 10, 11], $allTrips->getCollection()->pluck('id')->all());
        $this->assertSame([13], $manualTrips->getCollection()->pluck('id')->all());
        $this->assertSame(3, $repository->dashboardCounts()['todayTrips']);
        $this->assertSame(1, $repository->dashboardCounts()['manualPendingTrips']);
        $this->assertSame(4, $repository->dashboardCounts(true)['todayTrips']);
    }

    public function test_dashboard_keeps_pending_manual_trips_visible_outside_the_selected_period(): void
    {
        $now = now();
        $outsidePeriod = $now->copy()->subMonth();

        DB::table('trips')->insert([
            [
                'id' => 14,
                'vehicle_plate' => '10-0014',
                'driver_name' => 'سائق رحلة يدوية معلقة',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'entry_source' => 'manual',
                'entered_by_name' => 'المساعد الإداري',
                'started_at' => $outsidePeriod,
                'ended_at' => $outsidePeriod->copy()->addHour(),
            ],
            [
                'id' => 15,
                'vehicle_plate' => '10-0015',
                'driver_name' => 'سائق رحلة يدوية معتمدة',
                'status' => 'confirmed',
                'review_status' => 'approved',
                'entry_source' => 'manual',
                'entered_by_name' => null,
                'started_at' => $outsidePeriod,
                'ended_at' => $outsidePeriod->copy()->addHour(),
            ],
            [
                'id' => 16,
                'vehicle_plate' => '10-0016',
                'driver_name' => 'سائق رحلة يدوية معتمدة حديثاً',
                'status' => 'confirmed',
                'review_status' => 'approved',
                'entry_source' => 'manual',
                'entered_by_name' => null,
                'started_at' => $now->copy()->subMinutes(10),
                'ended_at' => $now->copy()->subMinutes(5),
            ],
        ]);

        $trips = app(TripRepository::class)->paginateForDashboard(
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'all',
            '',
            false,
            'manual',
        );

        $this->assertSame([14, 16], $trips->getCollection()->pluck('id')->all());
        $this->assertSame('المساعد الإداري', $trips->getCollection()->first()->entered_by_name);
    }

    public function test_manual_trip_list_is_scoped_to_its_assistant_and_filters(): void
    {
        $now = now();
        DB::table('trips')->insert([
            [
                'id' => 51,
                'vehicle_plate' => '3-1234',
                'driver_name' => 'سائق المحطة',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'entry_source' => 'manual',
                'entered_by_user_id' => 140,
                'oracle_vehicle_id' => 88,
                'origin_text' => 'المحطة',
                'destination_text' => 'البلدية',
                'mission_type' => 'صيانة',
                'started_at' => $now,
                'ended_at' => $now->copy()->addHour(),
            ],
            [
                'id' => 52,
                'vehicle_plate' => '9-9999',
                'driver_name' => 'سائق آخر',
                'status' => 'confirmed',
                'review_status' => 'pending',
                'entry_source' => 'manual',
                'entered_by_user_id' => 999,
                'oracle_vehicle_id' => 99,
                'origin_text' => 'المحطة',
                'destination_text' => 'مكان آخر',
                'mission_type' => 'صيانة',
                'started_at' => $now,
                'ended_at' => $now->copy()->addHour(),
            ],
        ]);

        $trips = app(TripRepository::class)->paginateManualForAssistant(140, [
            'search' => '3-1234',
            'review_status' => 'pending',
            'oracle_vehicle_id' => 88,
            'date_from' => $now->toDateString(),
            'date_to' => $now->toDateString(),
        ]);

        self::assertSame([51], $trips->getCollection()->pluck('id')->all());
    }

    public function test_manual_trip_create_and_update_are_audited(): void
    {
        $repository = app(TripRepository::class);
        $trip = $repository->createManual([
            'vehicle_plate' => '3-1234',
            'driver_name' => 'سائق المحطة',
            'status' => 'confirmed',
            'review_status' => 'pending',
            'entry_source' => 'manual',
            'entered_by_user_id' => 140,
            'oracle_vehicle_id' => 88,
            'origin_text' => 'المحطة',
            'destination_text' => 'البلدية',
            'mission_type' => 'صيانة',
            'distance_km' => 12.75,
            'started_at' => now(),
            'ended_at' => now()->addHour(),
        ], 'مساعد إداري');

        $repository->updateManual($trip, ['distance_km' => 14.125], 'مساعد إداري');

        self::assertSame('14.125', $trip->refresh()->distance_km);
        self::assertSame(
            ['trip.manual.created', 'trip.manual.updated'],
            DB::table('audit_logs')->orderBy('id')->pluck('event_type')->all(),
        );
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
