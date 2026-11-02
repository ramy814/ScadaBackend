<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Wells\Models\DieselInventory;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\GeneratorRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Oracle rejects IN lists longer than 1000 expressions (ORA-01795); raw SQL must chunk them. */
class OracleInListLimitTest extends TestCase
{
    public function test_diesel_report_queries_stay_under_the_oracle_in_list_limit(): void
    {
        $start = CarbonImmutable::parse('2026-01-01 08:00:00');
        $rows = [];
        for ($i = 0; $i < 1205; $i++) {
            $at = $start->addHours($i)->format('Y-m-d H:i:s');
            $rows[] = ['well_oid' => $i % 3 + 1, 'inspector_oid' => 1, 'inventory_date' => $at, 'generator_hours_reading' => 100 + $i,
                'diesel_height_cm' => $i, 'available_fuel_liters' => 10 * $i, 'created_at' => $at, 'updated_at' => $at];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            $this->db()->table('diesel_inventories')->insert($chunk);
        }

        $largest = 0;
        DB::listen(function (QueryExecuted $query) use (&$largest): void {
            $largest = max($largest, count($query->bindings));
        });

        $inventories = DieselInventory::query()->orderBy('oid')->get();
        $previous = app(DieselInventoryRepository::class)->previousReadingsFor($inventories);
        app(GeneratorRepository::class)->getApprovedFuelDeliveries(range(1, 1500), Carbon::parse($start), Carbon::parse($start->addYear()));
        app(GeneratorRepository::class)->getReportGenerators(range(1, 1500), range(1, 1500));

        $this->assertLessThanOrEqual(1003, $largest);
        $this->assertCount(1205, $previous);
        // Last inventory of well 1 (index 1203) follows index 1200 of the same well.
        $last = $inventories[1203];
        $this->assertSame(1200, (int) $previous[$last->getKey()]['diesel_height_cm']);
    }
}
