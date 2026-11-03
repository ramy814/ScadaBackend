<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\WellReading;
use App\Modules\Wells\Repositories\ReadingRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ReadingRepositoryTest extends TestCase
{
    protected bool $fixtureSchema = false;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_paginated_readings_are_ordered_by_latest_submission(): void
    {
        $model = Mockery::mock(WellReading::class);
        $query = Mockery::mock(Builder::class);
        $paginator = new LengthAwarePaginator([], 0, 15);

        $model->shouldReceive('with')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($query);
        $query->shouldReceive('orderByDesc')
            ->once()
            ->with('CREATED_AT')
            ->andReturnSelf();
        $query->shouldReceive('orderByDesc')
            ->once()
            ->with('OID')
            ->andReturnSelf();
        $query->shouldReceive('paginate')
            ->once()
            ->with(15)
            ->andReturn($paginator);

        $result = (new ReadingRepository($model))->paginate();

        self::assertSame($paginator, $result);
    }

    public function test_report_returns_only_the_latest_reading_per_well_inside_the_selected_period(): void
    {
        Schema::create('wells', function ($table): void {
            $table->integer('oid')->primary();
            $table->string('well_num')->nullable();
            $table->string('name');
            $table->decimal('capacity')->nullable();
            $table->decimal('latitude')->nullable();
            $table->decimal('longitude')->nullable();
        });
        Schema::create('inspectors', function ($table): void {
            $table->integer('oid')->primary();
            $table->string('name');
        });
        Schema::create('well_readings', function ($table): void {
            $table->integer('oid')->primary();
            $table->integer('well_oid');
            $table->integer('inspector_oid');
            $table->decimal('pump_hours_reading')->nullable();
            $table->decimal('flow_rate')->nullable();
            $table->string('quality')->nullable();
            $table->dateTime('reading_date')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_by_user_id')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->string('delete_reason', 1000)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('well_activity_media', function ($table): void {
            $table->integer('oid')->primary();
            $table->string('activity_type');
            $table->integer('activity_oid');
        });
        // The platform fixture connection already attaches DSS_CORE.
        if (! collect(DB::select('PRAGMA database_list'))->contains(fn ($db) => strtolower($db->name) === 'dss_core')) {
            DB::statement("ATTACH DATABASE ':memory:' AS DSS_CORE");
        }
        DB::statement('CREATE TABLE DSS_CORE.WTR_WELLS (WELL_ID VARCHAR(100), SOURCE_WELL_CODE VARCHAR(100))');

        DB::table('wells')->insert([
            ['oid' => 1, 'well_num' => 'W-1', 'name' => 'البئر الأول', 'capacity' => 50],
            ['oid' => 2, 'well_num' => 'W-2', 'name' => 'البئر الثاني', 'capacity' => 60],
        ]);
        DB::table('inspectors')->insert([
            ['oid' => 1, 'name' => 'المفتش'],
        ]);
        DB::table('well_readings')->insert([
            ['oid' => 1, 'well_oid' => 1, 'inspector_oid' => 1, 'pump_hours_reading' => 10, 'reading_date' => '2026-07-01 08:00:00'],
            ['oid' => 2, 'well_oid' => 1, 'inspector_oid' => 1, 'pump_hours_reading' => 20, 'reading_date' => '2026-07-10 08:00:00'],
            ['oid' => 3, 'well_oid' => 1, 'inspector_oid' => 1, 'pump_hours_reading' => 22, 'reading_date' => '2026-07-10 08:00:00'],
            ['oid' => 4, 'well_oid' => 1, 'inspector_oid' => 1, 'pump_hours_reading' => 30, 'reading_date' => '2026-07-20 08:00:00'],
            ['oid' => 5, 'well_oid' => 2, 'inspector_oid' => 1, 'pump_hours_reading' => 8, 'reading_date' => '2026-07-05 08:00:00'],
            ['oid' => 6, 'well_oid' => 2, 'inspector_oid' => 1, 'pump_hours_reading' => 14, 'reading_date' => '2026-07-12 08:00:00'],
        ]);
        DB::table('well_readings')->insert([
            'oid' => 7,
            'well_oid' => 1,
            'inspector_oid' => 1,
            'pump_hours_reading' => 24,
            'reading_date' => '2026-07-11 08:00:00',
            'deleted_at' => '2026-07-12 08:00:00',
        ]);

        $readings = (new ReadingRepository(new WellReading))->getReportReadings([
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-15',
        ]);

        self::assertSame([3, 6], $readings->modelKeys());
        self::assertCount(2, $readings);
        self::assertCount(2, $readings->pluck('well_oid')->unique());
    }
}
