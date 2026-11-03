<?php

declare(strict_types=1);

namespace App\Modules\Wells\Repositories;

use App\Modules\Wells\Models\WellGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GeneratorRepository
{
    private const APPROVED_DELIVERY_STATUS_OID = 416;

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

    public function syncWellGenerators(int $wellId, array $assignments): void
    {
        $assignments = collect($assignments)
            ->map(function (mixed $assignment): array {
                if (! is_array($assignment)) {
                    return [
                        'GENERATOR_OID' => (int) $assignment,
                        'FUEL_CONSUMPTION_LPH' => null,
                        'CONSUMPTION_TOLERANCE_LPH' => null,
                    ];
                }

                return [
                    'GENERATOR_OID' => (int) (
                        $assignment['GENERATOR_OID']
                        ?? $assignment['generator_oid']
                        ?? $assignment['OID']
                        ?? $assignment['oid']
                    ),
                    'FUEL_CONSUMPTION_LPH' => $assignment['FUEL_CONSUMPTION_LPH']
                        ?? $assignment['fuel_consumption_lph']
                        ?? null,
                    'CONSUMPTION_TOLERANCE_LPH' => $assignment['CONSUMPTION_TOLERANCE_LPH']
                        ?? $assignment['consumption_tolerance_lph']
                        ?? null,
                ];
            })
            ->filter(fn (array $assignment): bool => $assignment['GENERATOR_OID'] > 0)
            ->unique('GENERATOR_OID')
            ->values();

        $generatorIds = $assignments->pluck('GENERATOR_OID')->all();
        $existing = $this->model->where('WELL_OID', $wellId);

        if ($generatorIds === []) {
            $existing->delete();

            return;
        }

        $existing->whereNotIn('GENERATOR_OID', $generatorIds)->delete();

        foreach ($assignments as $assignment) {
            $model = $this->model->firstOrNew([
                'WELL_OID' => $wellId,
                'GENERATOR_OID' => $assignment['GENERATOR_OID'],
            ]);
            $model->fill([
                'FUEL_CONSUMPTION_LPH' => $assignment['FUEL_CONSUMPTION_LPH'],
                'CONSUMPTION_TOLERANCE_LPH' => $assignment['CONSUMPTION_TOLERANCE_LPH'],
            ]);

            if (! $model->exists) {
                $model->setAttribute('CREATED_AT', now());
            }

            $model->save();
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
                    GALLONS,
                    FUEL_CONSUMPTION
                FROM (
                    SELECT
                        g.OID,
                        g.NAME,
                        g.GENERATOR_NUM,
                        f.CURRENT_READING,
                        f.GALLONS,
                        g.FUEL_CONSUMPTION,
                        ROW_NUMBER() OVER (
                            PARTITION BY g.OID
                            ORDER BY f.OID DESC
                        ) rn
                    FROM G_STORE.GENERATOR g
                         LEFT JOIN G_STORE.FUEL_LOG_DETAILS f
                           ON g.OID = f.ASSET_OID
                          AND f.ASSET_TYPE = 401
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

        // Oracle allows at most 1000 expressions per IN list (ORA-01795).
        $generators = [];
        foreach (array_chunk(array_values($ids), 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            array_push($generators, ...DB::connection('oracle')
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
            ", $chunk));
        }
        if (count($ids) > 1000) {
            usort($generators, fn (object $a, object $b): int => strcmp((string) ($a->generator_num ?? $a->GENERATOR_NUM ?? ''), (string) ($b->generator_num ?? $b->GENERATOR_NUM ?? '')));
        }

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

        sort($wellIds);
        if ($wellIds !== []) {
            // Sorted chunks of 1000 keep the WELL_OID ordering and respect ORA-01795.
            $rows = [];
            foreach (array_chunk($wellIds, 1000) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                array_push($rows, ...DB::connection('oracle')->select(<<<SQL
                SELECT WELL_OID,
                       GENERATOR_OID,
                       FUEL_CONSUMPTION_LPH,
                       CONSUMPTION_TOLERANCE_LPH
                FROM WELL_GENERATORS
                WHERE WELL_OID IN ($placeholders)
                ORDER BY WELL_OID, OID
            SQL, $chunk));
            }
            $assignments = collect($rows)->map(function (object $row): array {
                $values = array_change_key_case((array) $row, CASE_LOWER);

                return [
                    'well_oid' => (int) $values['well_oid'],
                    'generator_oid' => (int) $values['generator_oid'],
                    'fuel_consumption_lph' => is_numeric($values['fuel_consumption_lph'] ?? null)
                        ? (float) $values['fuel_consumption_lph']
                        : null,
                    'consumption_tolerance_lph' => is_numeric($values['consumption_tolerance_lph'] ?? null)
                        ? (float) $values['consumption_tolerance_lph']
                        : null,
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

        $byWellGenerator = $assignments
            ->groupBy('well_oid')
            ->map(function ($rows) use ($byId): array {
                return $rows->mapWithKeys(function (array $assignment) use ($byId): array {
                    $generator = $byId->get($assignment['generator_oid']);

                    return $generator
                        ? [$assignment['generator_oid'] => array_merge($generator, [
                            'configured_fuel_consumption_lph' => $assignment['fuel_consumption_lph'],
                            'consumption_tolerance_lph' => $assignment['consumption_tolerance_lph'],
                        ])]
                        : [];
                })->all();
            });

        $byWell = $assignments
            ->groupBy('well_oid')
            ->filter(fn ($rows): bool => $rows->count() === 1)
            ->mapWithKeys(function ($rows, int $wellId) use ($byWellGenerator): array {
                $generator = $byWellGenerator->get($wellId)[(int) $rows->first()['generator_oid']] ?? null;

                return $generator ? [$wellId => $generator] : [];
            });

        return [
            'by_id' => $byId->all(),
            'by_well' => $byWell->all(),
            'by_well_generator' => $byWellGenerator->all(),
        ];
    }

    /**
     * Fuel-program fills for generators assigned to wells classified as Daily Tank.
     *
     * The date filter is intentionally outside the analytic subquery so the first
     * selected row can still use the immediately preceding approved fill. Missing
     * or malformed meter readings remain in the sequence and are rejected by the
     * service instead of being skipped to manufacture a misleading interval.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDailyTankFuelEntries(array $filters = [], int $limit = 5000): array
    {
        $conditions = [];
        $bindings = [self::APPROVED_DELIVERY_STATUS_OID];

        if (! empty($filters['well_oid'])) {
            $conditions[] = 'fuel_entries.WELL_OID = ?';
            $bindings[] = (int) $filters['well_oid'];
        }

        if (! empty($filters['generator_oid'])) {
            $conditions[] = 'fuel_entries.GENERATOR_OID = ?';
            $bindings[] = (int) $filters['generator_oid'];
        }

        if (! empty($filters['date_from'])) {
            $conditions[] = "fuel_entries.FILL_UP_DATE >= TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS')";
            $bindings[] = Carbon::parse($filters['date_from'])->startOfDay()->format('Y-m-d H:i:s');
        }

        if (! empty($filters['date_to'])) {
            $conditions[] = "fuel_entries.FILL_UP_DATE <= TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS')";
            $bindings[] = Carbon::parse($filters['date_to'])->endOfDay()->format('Y-m-d H:i:s');
        }

        $where = $conditions === [] ? '' : ' AND '.implode(' AND ', $conditions);
        $limit = max(1, min($limit, 5000));
        $rows = DB::connection('oracle')->select(<<<SQL
            SELECT *
            FROM (
                SELECT fuel_entries.*,
                       ROW_NUMBER() OVER (
                           ORDER BY fuel_entries.FILL_UP_DATE DESC,
                                    fuel_entries.FUEL_DETAIL_OID DESC
                       ) AS RESULT_ROW_NUM
                FROM (
                    SELECT w.OID AS WELL_OID,
                           w.NAME AS WELL_NAME,
                           w.WELL_NUM,
                           g.OID AS GENERATOR_OID,
                           g.NAME AS GENERATOR_NAME,
                           g.GENERATOR_NUM,
                           g.FUEL_CONSUMPTION,
                           wg.FUEL_CONSUMPTION_LPH,
                           wg.CONSUMPTION_TOLERANCE_LPH,
                           fd.OID AS FUEL_DETAIL_OID,
                           fl.FILL_UP_DATE,
                           fd.CURRENT_READING,
                           fd.APPROVED_GALLONS,
                           LAG(fl.FILL_UP_DATE) OVER (
                               PARTITION BY g.OID
                               ORDER BY fl.FILL_UP_DATE, fd.OID
                           ) AS PREVIOUS_FILL_UP_DATE,
                           LAG(fd.CURRENT_READING) OVER (
                               PARTITION BY g.OID
                               ORDER BY fl.FILL_UP_DATE, fd.OID
                           ) AS PREVIOUS_CURRENT_READING
                    FROM WELLS w
                    JOIN WELL_GENERATORS wg ON wg.WELL_OID = w.OID
                    JOIN G_STORE.GENERATOR g ON g.OID = wg.GENERATOR_OID
                    JOIN G_STORE.FUEL_LOG_DETAILS fd ON fd.ASSET_OID = g.OID
                    JOIN G_STORE.FUEL_LOG fl ON fl.OID = fd.FUEL_LOG_OID
                    WHERE UPPER(TRIM(w.TANK_SHAPE)) = 'DAILY TANK'
                      AND fd.APPROVED_GALLONS IS NOT NULL
                      AND fd.ASSET_TYPE = 401
                      AND fl.STATUS_OID = ?
                      AND fl.FILL_UP_DATE IS NOT NULL
                      AND fl.CANCEL_DATE IS NULL
                ) fuel_entries
                WHERE 1 = 1{$where}
            )
            WHERE RESULT_ROW_NUM <= {$limit}
            ORDER BY FILL_UP_DATE DESC, FUEL_DETAIL_OID DESC
        SQL, $bindings);

        return array_map(
            fn (object $row): array => array_change_key_case((array) $row, CASE_LOWER),
            $rows,
        );
    }

    /** @return array<int, array{generator_oid: int, supplied_at: mixed, entered_at: mixed, meter_reading: mixed, liters: float}> */
    public function getApprovedFuelDeliveries(array $generatorIds, Carbon $from, Carbon $to): array
    {
        $generatorIds = array_values(array_unique(array_map('intval', array_filter($generatorIds))));

        if ($generatorIds === []) {
            return [];
        }

        // Sorted chunks of 1000 keep the ASSET_OID ordering and respect ORA-01795.
        sort($generatorIds);
        $rows = [];
        foreach (array_chunk($generatorIds, 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, [
                self::APPROVED_DELIVERY_STATUS_OID,
                $from->copy()->startOfDay()->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ]);
            array_push($rows, ...DB::connection('oracle')->select(<<<SQL
            SELECT fd.ASSET_OID,
                   fl.FILL_UP_DATE,
                   fl.ENTRY_DATE,
                   fd.CURRENT_READING,
                   fd.APPROVED_GALLONS
            FROM G_STORE.FUEL_LOG_DETAILS fd
            JOIN G_STORE.FUEL_LOG fl ON fl.OID = fd.FUEL_LOG_OID
            WHERE fd.ASSET_OID IN ($placeholders)
              AND fd.ASSET_TYPE = 401
              AND fd.APPROVED_GALLONS IS NOT NULL
              AND fl.STATUS_OID = ?
              AND fl.CANCEL_DATE IS NULL
              AND fl.FILL_UP_DATE BETWEEN TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS')
                                      AND TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS')
            ORDER BY fd.ASSET_OID, fl.FILL_UP_DATE, fd.OID
        SQL, $bindings));
        }

        return array_map(function (object $row): array {
            $values = array_change_key_case((array) $row, CASE_LOWER);

            return [
                'generator_oid' => (int) $values['asset_oid'],
                'supplied_at' => $values['fill_up_date'],
                'entered_at' => $values['entry_date'],
                'meter_reading' => $values['current_reading'],
                'liters' => (float) $values['approved_gallons'],
            ];
        }, $rows);
    }
}
