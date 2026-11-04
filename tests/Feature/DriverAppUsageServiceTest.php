<?php

namespace Tests\Feature;

use App\Models\DriverAppInstallation;
use App\Repositories\DriverAppInstallationRepository;
use App\Repositories\MunicipalityDriverRepository;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\TripRepository;
use App\Services\DriverAppUsageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DriverAppUsageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'driver_app.latest_version' => '1.0.20',
            'driver_app.latest_build' => 21,
            'driver_app.recent_days' => 14,
        ]);

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('oracle_driver_id');
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('driver_employee_number')->nullable();
            $table->string('oracle_vehicle_id')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_name')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->timestamp('started_at');
        });

        Schema::create('driver_app_installations', function (Blueprint $table): void {
            $table->id();
            $table->string('oracle_driver_id');
            $table->string('device_id');
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('driver_employee_number')->nullable();
            $table->string('app_version');
            $table->unsignedInteger('build_number');
            $table->string('package_name');
            $table->string('installer_store')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('app_updated_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('driver_app_installations');
        Schema::dropIfExists('trips');
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_includes_legacy_drivers_and_classifies_store_updates(): void
    {
        DB::table('trips')->insert([
            [
                'id' => 1,
                'oracle_driver_id' => '101',
                'driver_name' => 'سائق محدث',
                'driver_phone' => '0599000001',
                'driver_employee_number' => 'E-101',
                'oracle_vehicle_id' => '501',
                'vehicle_plate' => '10-1001',
                'vehicle_name' => 'مركبة أولى',
                'vehicle_type' => '1',
                'started_at' => now()->subDay(),
            ],
            [
                'id' => 2,
                'oracle_driver_id' => '102',
                'driver_name' => 'سائق قديم',
                'driver_phone' => '0599000002',
                'driver_employee_number' => 'E-102',
                'oracle_vehicle_id' => '502',
                'vehicle_plate' => '10-1002',
                'vehicle_name' => 'مركبة ثانية',
                'vehicle_type' => '1',
                'started_at' => now()->subDays(2),
            ],
        ]);

        DriverAppInstallation::query()->create([
            'oracle_driver_id' => '101',
            'device_id' => '9e184956-76ab-4f84-9d20-1e10acaf7320',
            'driver_name' => 'سائق محدث',
            'driver_phone' => '0599000001',
            'driver_employee_number' => 'E-101',
            'app_version' => '1.0.20',
            'build_number' => 21,
            'package_name' => 'ps.gov.gaza.driver',
            'installer_store' => 'com.android.vending',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
        ]);

        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('findManyByDriverOracleIds')
            ->once()
            ->andReturn(collect([
                '101' => collect([[
                    'oracle_driver_id' => '101',
                    'oracle_id' => '501',
                    'plate_number' => '10-1001',
                    'name' => 'مركبة أولى',
                    'vehicle_type' => '1',
                ]]),
            ]));

        $result = (new DriverAppUsageService(
            new DriverAppInstallationRepository,
            new TripRepository,
            $vehicles,
            Mockery::mock(MunicipalityDriverRepository::class),
        ))->overview([], '/admin/driver-apps');

        $rows = collect($result['usage']->items())->keyBy('oracle_driver_id');

        $this->assertSame(2, $result['summary']['drivers']);
        $this->assertSame(1, $result['summary']['current']);
        $this->assertSame(1, $result['summary']['not_reported']);
        $this->assertSame('updated_from_store', $rows['101']['store_update_status']);
        $this->assertSame('not_reported', $rows['102']['store_update_status']);
        $this->assertSame('10-1002', $rows['102']['last_trip_vehicle']['plate_number']);
    }

    public function test_it_filters_outdated_versions(): void
    {
        foreach ([20, 21] as $build) {
            DriverAppInstallation::query()->create([
                'oracle_driver_id' => (string) $build,
                'device_id' => sprintf('00000000-0000-4000-8000-%012d', $build),
                'app_version' => $build === 21 ? '1.0.20' : '1.0.19',
                'build_number' => $build,
                'package_name' => 'ps.gov.gaza.driver',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('findManyByDriverOracleIds')
            ->once()
            ->andReturn(collect());

        $result = (new DriverAppUsageService(
            new DriverAppInstallationRepository,
            new TripRepository,
            $vehicles,
            Mockery::mock(MunicipalityDriverRepository::class),
        ))->overview(
            ['version_status' => 'outdated'],
            '/admin/driver-apps',
        );

        $this->assertSame(1, $result['usage']->total());
        $this->assertSame(20, $result['usage']->items()[0]['build_number']);
    }

    public function test_dashboard_active_counts_require_app_presence_and_active_records(): void
    {
        foreach (['101', '102'] as $driverId) {
            DriverAppInstallation::query()->create([
                'oracle_driver_id' => $driverId,
                'device_id' => sprintf('00000000-0000-4000-8000-%012d', (int) $driverId),
                'app_version' => '1.0.20',
                'build_number' => 21,
                'package_name' => 'ps.gov.gaza.driver',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('countActiveAssignedToDrivers')
            ->once()
            ->with(['101', '102'])
            ->andReturn(2);

        $drivers = Mockery::mock(MunicipalityDriverRepository::class);
        $drivers->shouldReceive('countActiveUsingApp')
            ->once()
            ->with(['101', '102'])
            ->andReturn(2);

        $counts = (new DriverAppUsageService(
            new DriverAppInstallationRepository,
            new TripRepository,
            $vehicles,
            $drivers,
        ))->dashboardActiveCounts();

        $this->assertSame([
            'activeVehicles' => 2,
            'activeDrivers' => 2,
        ], $counts);
    }

    public function test_dashboard_active_counts_are_scoped_to_allowed_vehicles(): void
    {
        foreach (['101', '102'] as $driverId) {
            DriverAppInstallation::query()->create([
                'oracle_driver_id' => $driverId,
                'device_id' => sprintf('10000000-0000-4000-8000-%012d', (int) $driverId),
                'app_version' => '1.0.20',
                'build_number' => 21,
                'package_name' => 'ps.gov.gaza.driver',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('activeDriverIdsAssignedToVehicles')
            ->once()
            ->with(['101', '102'], ['501'])
            ->andReturn(['101']);
        $vehicles->shouldReceive('countActiveAssignedToDrivers')
            ->once()
            ->with(['101', '102'], ['501'])
            ->andReturn(1);

        $drivers = Mockery::mock(MunicipalityDriverRepository::class);
        $drivers->shouldReceive('countActiveUsingApp')
            ->once()
            ->with(['101'])
            ->andReturn(1);

        $counts = (new DriverAppUsageService(
            new DriverAppInstallationRepository,
            new TripRepository,
            $vehicles,
            $drivers,
        ))->dashboardActiveCounts(['501']);

        self::assertSame(['activeVehicles' => 1, 'activeDrivers' => 1], $counts);
    }
}
