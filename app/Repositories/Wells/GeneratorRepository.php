<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\WellGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GeneratorRepository
{
    public function __construct(
        private readonly WellGenerator $model
    ) {
    }

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
            ->select("
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
            ");

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
                    g.CAPACITY,
                    g.STATUS
                FROM G_STORE.GENERATOR g
                WHERE g.OID IN ({$placeholders})
                ORDER BY g.GENERATOR_NUM
            ", $ids);

        return array_map(function ($row) {
            return (object) array_change_key_case((array) $row, CASE_UPPER);
        }, $generators);
    }
}
