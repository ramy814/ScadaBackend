<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Well;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\GeneratorRepository;
use App\Repositories\Wells\WellRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WellService
{
    public function __construct(
        private readonly WellRepository $wellRepository,
        private readonly GeneratorRepository $generatorRepository,
        private readonly WellMediaService $wellMediaService,
        private readonly DieselInventoryRepository $dieselInventoryRepository,
    ) {}

    public function getAllWells(): Collection
    {
        return $this->wellRepository->all();
    }

    public function getPaginatedWells(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $wells = $this->wellRepository->paginate($perPage, $filters);
        $wells->getCollection()
            ->each(fn (Well $well): Well => $this->applyDeletionState($well));

        return $wells;
    }

    public function getWell(int $id): Well
    {
        return $this->wellRepository->findOrFail($id);
    }

    public function createWell(array $data): Well
    {
        return DB::transaction(function () use ($data): Well {
            $generatorAssignments = $this->extractGeneratorAssignments($data);
            $media = $this->extractMedia($data);
            $well = $this->wellRepository->create($data);

            if ($generatorAssignments !== null) {
                $this->generatorRepository->syncWellGenerators(
                    (int) $well->getKey(),
                    $generatorAssignments,
                );
            }

            $this->wellMediaService->storeMany((int) $well->getKey(), $media);

            return $well->fresh(['teams', 'generators', 'media']);
        });
    }

    public function updateWell(int $id, array $data): Well
    {
        return DB::transaction(function () use ($id, &$data): Well {
            $generatorAssignments = $this->extractGeneratorAssignments($data);
            $media = $this->extractMedia($data);
            $updatedWell = $this->wellRepository->update($id, $data);

            if ($generatorAssignments !== null) {
                $this->generatorRepository->syncWellGenerators($id, $generatorAssignments);
            }

            $this->wellMediaService->storeMany($id, $media);

            return $updatedWell->fresh(['teams', 'generators', 'media']);
        });
    }

    public function deleteWell(int $id): bool
    {
        $well = $this->wellRepository->findForDeletion($id);
        $blockers = $this->deletionBlockers($well);

        if ($blockers !== []) {
            throw new RuntimeException(
                'لا يمكن حذف البئر لوجود سجلات مرتبطة به: '.implode('، ', $blockers).'. أزل الارتباطات غير التاريخية أو عطّل البئر.'
            );
        }

        try {
            return $this->wellRepository->delete($id);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'ORA-02292')) {
                throw $exception;
            }

            throw new RuntimeException(
                'لا يمكن حذف البئر لأن قاعدة البيانات وجدت سجلات مرتبطة به.',
                previous: $exception,
            );
        }
    }

    public function getActiveWells(): Collection
    {
        return $this->wellRepository->getActiveWells();
    }

    public function getSyncWellsForInspector(int $inspectorId): array
    {
        $wells = $this->wellRepository->getActiveForInspector($inspectorId);
        $latestInventories = $this->dieselInventoryRepository->getLatestForWells(
            $wells->modelKeys(),
        );

        return $wells->map(function (Well $well) use ($latestInventories): array {
            $previousDiesel = $latestInventories->get((int) $well->getKey());
            $tankDiameter = $well->getAttribute('TANK_DIAMETER') ?? $well->getAttribute('tank_diameter');
            $tankHeight = $well->getAttribute('TANK_HEIGHT') ?? $well->getAttribute('tank_height');

            return [
                'oid' => (int) $well->oid,
                'name' => $well->name,
                'quarter_oid' => $well->quarter_oid === null ? null : (int) $well->quarter_oid,
                'quarter_name' => null,
                'pump_type' => $well->pump_type ?? 'Unknown',
                'capacity' => (float) $well->capacity,
                'power_source' => $well->power_source ?? 'Unknown',
                'tank_shape' => $well->getAttribute('TANK_SHAPE') ?? $well->getAttribute('tank_shape'),
                'tank_material' => $well->getAttribute('TANK_MATERIAL') ?? $well->getAttribute('tank_material'),
                'tank_diameter' => $tankDiameter === null ? null : (float) $tankDiameter,
                'tank_height' => $tankHeight === null ? null : (float) $tankHeight,
                'previous_generator_hours_reading' => $this->nullableFloat($previousDiesel['generator_hours_reading'] ?? null),
                'previous_diesel_height_cm' => $this->nullableFloat($previousDiesel['diesel_height_cm'] ?? null),
                'previous_available_fuel_liters' => $this->nullableFloat($previousDiesel['available_fuel_liters'] ?? null),
                'previous_inventory_date' => $this->isoDate($previousDiesel['inventory_date'] ?? null),
                'latitude' => (float) $well->latitude,
                'longitude' => (float) $well->longitude,
            ];
        })->all();
    }

    public function getWellsByTeam(int $teamId): Collection
    {
        return $this->wellRepository->findByTeam($teamId);
    }

    public function assignGenerators(int $wellId, array $generatorIds): void
    {
        $this->generatorRepository->syncWellGenerators($wellId, $generatorIds);
    }

    private function extractGeneratorAssignments(array &$data): ?array
    {
        $hasLowercaseKey = array_key_exists('generators', $data);
        $hasUppercaseKey = array_key_exists('GENERATORS', $data);

        if (! $hasLowercaseKey && ! $hasUppercaseKey) {
            return null;
        }

        $generatorAssignments = $data['generators'] ?? $data['GENERATORS'] ?? [];
        unset($data['generators']);
        unset($data['GENERATORS']);

        return collect($generatorAssignments)
            ->map(function (mixed $generator): array {
                if (is_array($generator)) {
                    return [
                        'GENERATOR_OID' => $generator['GENERATOR_OID']
                            ?? $generator['generator_oid']
                            ?? $generator['OID']
                            ?? $generator['oid']
                            ?? null,
                        'FUEL_CONSUMPTION_LPH' => $generator['FUEL_CONSUMPTION_LPH']
                            ?? $generator['fuel_consumption_lph']
                            ?? null,
                        'CONSUMPTION_TOLERANCE_LPH' => $generator['CONSUMPTION_TOLERANCE_LPH']
                            ?? $generator['consumption_tolerance_lph']
                            ?? null,
                    ];
                }

                if (is_object($generator)) {
                    return [
                        'GENERATOR_OID' => $generator->GENERATOR_OID
                            ?? $generator->generator_oid
                            ?? $generator->OID
                            ?? $generator->oid
                            ?? null,
                        'FUEL_CONSUMPTION_LPH' => $generator->FUEL_CONSUMPTION_LPH
                            ?? $generator->fuel_consumption_lph
                            ?? null,
                        'CONSUMPTION_TOLERANCE_LPH' => $generator->CONSUMPTION_TOLERANCE_LPH
                            ?? $generator->consumption_tolerance_lph
                            ?? null,
                    ];
                }

                return [
                    'GENERATOR_OID' => $generator,
                    'FUEL_CONSUMPTION_LPH' => null,
                    'CONSUMPTION_TOLERANCE_LPH' => null,
                ];
            })
            ->filter(fn (array $assignment): bool => $assignment['GENERATOR_OID'] !== null
                && $assignment['GENERATOR_OID'] !== '')
            ->map(fn (array $assignment): array => [
                'GENERATOR_OID' => (int) $assignment['GENERATOR_OID'],
                'FUEL_CONSUMPTION_LPH' => $this->nullableFloat(
                    $assignment['FUEL_CONSUMPTION_LPH'],
                ),
                'CONSUMPTION_TOLERANCE_LPH' => $this->nullableFloat(
                    $assignment['CONSUMPTION_TOLERANCE_LPH'],
                ),
            ])
            ->unique('GENERATOR_OID')
            ->values()
            ->all();
    }

    private function extractMedia(array &$data): array
    {
        $media = $data['media'] ?? [];
        unset($data['media']);

        return $media;
    }

    public function getWellWithGenerators(int $wellId): Well
    {
        return $this->wellRepository->findWithGenerators($wellId);
    }

    public function getWellsForMap(): Collection
    {
        $wells = $this->wellRepository->getWellsForMap();
        $generatorIds = $wells
            ->flatMap(fn (Well $well) => $well->generators->pluck('generator_oid'))
            ->filter()
            ->map(fn (mixed $generatorId): int => (int) $generatorId)
            ->unique()
            ->values()
            ->all();
        $generators = collect($this->generatorRepository->getGeneratorsByIds($generatorIds))
            ->keyBy(fn (object $generator): int => (int) $generator->OID);

        $wells->each(function (Well $well) use ($generators): void {
            $mapGenerators = $well->generators
                ->map(function ($assignment) use ($generators): ?array {
                    $generatorId = (int) $assignment->getAttribute('generator_oid');
                    $generator = $generators->get($generatorId);

                    if (! $generator) {
                        return null;
                    }

                    return [
                        'oid' => $generatorId,
                        'number' => $generator->GENERATOR_NUM ?? null,
                        'name' => $generator->NAME ?? null,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $well->setAttribute('MAP_GENERATORS', $mapGenerators);
        });

        return $wells;
    }

    public function getQuarters(): array
    {
        return $this->wellRepository->getQuarters();
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }

    private function applyDeletionState(Well $well): Well
    {
        $blockers = $this->deletionBlockers($well);
        $well->setAttribute('CAN_DELETE', $blockers === []);
        $well->setAttribute('DELETE_BLOCKERS', $blockers);

        return $well;
    }

    /** @return array<int, string> */
    private function deletionBlockers(Well $well): array
    {
        $relations = [
            'readings_count' => 'قراءات آبار',
            'diesel_inventories_count' => 'سجلات جرد سولار',
            'notifications_count' => 'إشعارات',
            'teams_count' => 'فرق ميدانية',
            'generators_count' => 'مولدات',
            'media_count' => 'صور',
        ];

        $blockers = [];
        foreach ($relations as $attribute => $label) {
            $count = (int) ($well->getAttribute($attribute) ?? 0);
            if ($count > 0) {
                $blockers[] = "{$label} ({$count})";
            }
        }

        return $blockers;
    }
}
