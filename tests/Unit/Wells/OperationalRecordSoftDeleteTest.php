<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\DieselInventory;
use App\Modules\Wells\Models\WellReading;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\ReadingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalRecordSoftDeleteTest extends TestCase
{
    protected bool $fixtureSchema = false;

    public function test_records_are_hidden_without_physical_deletion_and_keep_the_audit_fields(): void
    {
        Schema::create('well_readings', function ($table): void {
            $table->integer('oid')->primary();
            $table->integer('inspector_oid');
            $table->string('client_local_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_by_user_id')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->string('delete_reason', 1000)->nullable();
            $table->timestamps();
        });
        Schema::create('diesel_inventories', function ($table): void {
            $table->integer('oid')->primary();
            $table->integer('well_oid');
            $table->integer('inspector_oid');
            $table->string('client_local_id')->nullable();
            $table->dateTime('inventory_date');
            $table->decimal('generator_hours_reading')->nullable();
            $table->decimal('diesel_height_cm')->nullable();
            $table->decimal('available_fuel_liters')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_by_user_id')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->string('delete_reason', 1000)->nullable();
            $table->timestamps();
        });

        DB::table('well_readings')->insert([
            'oid' => 11,
            'inspector_oid' => 7,
            'client_local_id' => 'reading-local-id',
        ]);
        DB::table('diesel_inventories')->insert([
            [
                'oid' => 20,
                'well_oid' => 4,
                'inspector_oid' => 7,
                'client_local_id' => 'diesel-old',
                'inventory_date' => '2026-08-01 08:00:00',
                'generator_hours_reading' => 100,
                'diesel_height_cm' => 80,
                'available_fuel_liters' => 500,
            ],
            [
                'oid' => 21,
                'well_oid' => 4,
                'inspector_oid' => 7,
                'client_local_id' => 'diesel-deleted-middle',
                'inventory_date' => '2026-08-02 08:00:00',
                'generator_hours_reading' => 200,
                'diesel_height_cm' => 70,
                'available_fuel_liters' => 400,
            ],
            [
                'oid' => 22,
                'well_oid' => 4,
                'inspector_oid' => 7,
                'client_local_id' => 'diesel-local-id',
                'inventory_date' => '2026-08-03 08:00:00',
                'generator_hours_reading' => 300,
                'diesel_height_cm' => 60,
                'available_fuel_liters' => 300,
            ],
        ]);

        $readingRepository = new ReadingRepository(new WellReading);
        $dieselRepository = new DieselInventoryRepository(new DieselInventory);
        $readingRepository->softDelete(11, 140, 'مسؤول النظام', 'قراءة مكررة');
        $dieselRepository->softDelete(21, 140, 'مسؤول النظام', 'جرد وسيط مكرر');

        $currentInventory = DieselInventory::findOrFail(22);
        $previous = $dieselRepository
            ->previousReadingsFor(new Collection([$currentInventory]))
            ->get(22);
        self::assertSame(100.0, (float) $previous['generator_hours_reading']);

        $dieselRepository->softDelete(22, 140, 'مسؤول النظام', 'جرد مكرر');

        self::assertNull(WellReading::find(11));
        self::assertNull(DieselInventory::find(22));
        self::assertSame(1, DB::table('well_readings')->count());
        self::assertSame(3, DB::table('diesel_inventories')->count());

        $deletedReading = WellReading::withTrashed()->findOrFail(11);
        self::assertSame(140, (int) $deletedReading->getAttribute('deleted_by_user_id'));
        self::assertSame('مسؤول النظام', $deletedReading->getAttribute('deleted_by_name'));
        self::assertSame('قراءة مكررة', $deletedReading->getAttribute('delete_reason'));
        self::assertNotNull($deletedReading->getAttribute('deleted_at'));

        self::assertSame(
            11,
            $readingRepository
                ->findSyncedByLocalIdIncludingDeleted('reading-local-id', 7)
                ?->getKey(),
        );
        self::assertSame(
            22,
            $dieselRepository
                ->findSyncedByLocalIdIncludingDeleted('diesel-local-id', 7)
                ?->getKey(),
        );
    }
}
