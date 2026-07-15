<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\WellGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GeneratorRepository
{
    public function __construct(
        private readonly WellGenerator $model
    ) {}

    public function getByWell(int $wellId): Collection
    {
        return $this->model->where('WELL_OID', $wellId)->get();
    }

    public function assignToWell(int $wellId, int $generatorId): WellGenerator
    {
        return $this->model->create([
            'WELL_OID' => $wellId,
            'GENERATOR_OID' => $generatorId,
            'CREATED_AT' => now(),
        ]);
    }

    public function removeFromWell(int $wellId, int $generatorId): bool
    {
        return (bool) $this->model
            ->where('WELL_OID', $wellId)
            ->where('GENERATOR_OID', $generatorId)
            ->delete();
    }

    public function syncWellGenerators(int $wellId, array $generatorIds): void
    {
        $this->model->where('WELL_OID', $wellId)->delete();

        foreach ($generatorIds as $generatorId) {
            $this->model->create([
                'WELL_OID' => $wellId,
                'GENERATOR_OID' => $generatorId,
                'CREATED_AT' => now(),
            ]);
        }
    }

    /**
     * Get active generators from Oracle G_STORE schema.
     * This query runs against the Oracle connection only.
     */
    public function getActiveGenerators(): array
    {
        $generators = DB::connection('oracle')
            ->select('
                SELECT
                    OID,
                    NAME,
                    GENERATOR_NUM,
                    CURRENT_READING,
                    GALLONS
                FROM (
                    SELECT
                        g.OID,
                        g.NAME,
                        g.GENERATOR_NUM,
                        f.CURRENT_READING,
                        f.GALLONS,
                        ROW_NUMBER() OVER (
                            PARTITION BY g.OID
                            ORDER BY f.OID DESC
                        ) rn
                    FROM G_STORE.GENERATOR g
                         LEFT JOIN G_STORE.FUEL_LOG_DETAILS f
                           ON g.OID = f.ASSET_OID
                    WHERE g.IS_ACTIVE = 1
                      AND g.TYPE_OID = 128
                )
                WHERE rn = 1
                ORDER BY GENERATOR_NUM
            ');

        return array_map(function ($row) {
            return (object) array_change_key_case((array) $row, CASE_UPPER);
        }, $generators);
    }

    public function getGeneratorsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $generators = DB::connection('oracle')
            ->select("
                SELECT
                    g.OID,
                    g.NAME,
                    g.GENERATOR_NUM,
                    g.ENGINE_CAPACITY,
                    g.FUEL_CONSUMPTION
                FROM G_STORE.GENERATOR g
                WHERE g.OID IN ({$placeholders})
                ORDER BY g.GENERATOR_NUM
            ", $ids);

        return array_map(function ($row) {
            return (object) array_change_key_case((array) $row, CASE_UPPER);
        }, $generators);
    }

    /**
     * Resolve explicit inventory generators and the single generator assigned to a well.
     *
     * @return array{by_id: array<int, array<string, mixed>>, by_well: array<int, array<string, mixed>>}
     */
    public function getReportGenerators(array $wellIds, array $generatorIds): array
    {
        $wellIds = array_values(array_unique(array_map('intval', array_filter($wellIds))));
        $generatorIds = array_values(array_unique(array_map('intval', array_filter($generatorIds))));
        $assignments = collect();

        if ($wellIds !== []) {
            $placeholders = implode(',', array_fill(0, count($wellIds), '?'));
            $assignments = collect(DB::connection('oracle')->select(<<<SQL
                SELECT WELL_OID, GENERATOR_OID
                FROM WELL_GENERATORS
                WHERE WELL_OID IN ($placeholders)
                ORDER BY WELL_OID, OID
            SQL, $wellIds))->map(function (object $row): array {
                $values = array_change_key_case((array) $row, CASE_LOWER);

                return [
                    'well_oid' => (int) $values['well_oid'],
                    'generator_oid' => (int) $values['generator_oid'],
                ];
            });
        }

        $allGeneratorIds = array_values(array_unique(array_merge(
            $generatorIds,
            $assignments->pluck('generator_oid')->all(),
        )));
        $byId = collect($this->getGeneratorsByIds($allGeneratorIds))
            ->mapWithKeys(function (object $generator): array {
                $data = [
                    'oid' => (int) $generator->OID,
                    'name' => $generator->NAME ?? null,
                    'number' => $generator->GENERATOR_NUM ?? null,
                    'capacity_kva' => is_numeric($generator->ENGINE_CAPACITY ?? null)
                        ? (float) $generator->ENGINE_CAPACITY
                        : null,
                    'fuel_consumption_lph' => is_numeric($generator->FUEL_CONSUMPTION ?? null)
                        ? (float) $generator->FUEL_CONSUMPTION
                        : null,
                ];

                return [$data['oid'] => $data];
            });

        $byWell = $assignments
            ->groupBy('well_oid')
            ->filter(fn ($rows): bool => $rows->count() === 1)
            ->mapWithKeys(function ($rows, int $wellId) use ($byId): array {
                $generator = $byId->get((int) $rows->first()['generator_oid']);

                return $generator ? [$wellId => $generator] : [];
            });

        return [
            'by_id' => $byId->all(),
            'by_well' => $byWell->all(),
        ];
    }

    /** @return array<int, array{generator_oid: int, supplied_at: mixed, liters: float}> */
    public function getApprovedFuelDeliveries(array $generatorIds, Carbon $from, Carbon $to): array
    {
        $generatorIds = array_values(array_unique(array_map('intval', array_filter($generatorIds))));

        if ($generatorIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($generatorIds), '?'));
        $bindings = array_merge($generatorIds, [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
        ]);
        $rows = DB::connection('oracle')->select(<<<SQL
            SELECT fd.ASSET_OID,
                   fl.FILL_UP_DATE,
                   fd.APPROVED_GALLONS
            FROM G_STORE.FUEL_LOG_DETAILS fd
            JOIN G_STORE.FUEL_LOG fl ON fl.OID = fd.FUEL_LOG_OID
            WHERE fd.ASSET_OID IN ($placeholders)
              AND fd.APPROVED_GALLONS IS NOT NULL
              AND fl.FILL_UP_DATE BETWEEN TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS')
                                      AND TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS')
            ORDER BY fd.ASSET_OID, fl.FILL_UP_DATE, fd.OID
        SQL, $bindings);

        return array_map(function (object $row): array {
            $values = array_change_key_case((array) $row, CASE_LOWER);

            return [
                'generator_oid' => (int) $values['asset_oid'],
                'supplied_at' => $values['fill_up_date'],
                'liters' => (float) $values['approved_gallons'],
            ];
        }, $rows);
    }
}
