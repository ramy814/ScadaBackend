<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Well;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\GeneratorRepository;
use App\Repositories\Wells\WellRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        return $this->wellRepository->paginate($perPage, $filters);
    }

    public function getWell(int $id): Well
    {
        return $this->wellRepository->findOrFail($id);
    }

    public function createWell(array $data): Well
    {
        return DB::transaction(function () use ($data): Well {
            $generatorIds = $this->extractGeneratorIds($data);
            $media = $this->extractMedia($data);
            $well = $this->wellRepository->create($data);

            if ($generatorIds !== null) {
                $this->generatorRepository->syncWellGenerators((int) $well->getKey(), $generatorIds);
            }

            $this->wellMediaService->storeMany((int) $well->getKey(), $media);

            return $well->fresh(['teams', 'generators', 'media']);
        });
    }

    public function updateWell(int $id, array $data): Well
    {
        return DB::transaction(function () use ($id, &$data): Well {
            $well = $this->wellRepository->findOrFail($id);
            $hasTransactions = $well->readings()->exists() || $well->dieselInventories()->exists();

            if ($hasTransactions) {
                unset($data['NAME'], $data['QUARTER_OID']);
            }

            unset($data['WELL_NUM']);

            $generatorIds = $this->extractGeneratorIds($data);
            $media = $this->extractMedia($data);
            $updatedWell = $this->wellRepository->update($id, $data);

            if ($generatorIds !== null) {
                $this->generatorRepository->syncWellGenerators($id, $generatorIds);
            }

            $this->wellMediaService->storeMany($id, $media);

            return $updatedWell->fresh(['teams', 'generators', 'media']);
        });
    }

    public function deleteWell(int $id): bool
    {
        $well = $this->wellRepository->findOrFail($id);
        $hasTransactions = $well->readings()->exists() || $well->dieselInventories()->exists();

        if ($hasTransactions) {
            throw new \Exception('لا يمكن حذف البئر لوجود قراءات أو جرد مرتبط به.');
        }

        return $this->wellRepository->delete($id);
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

    private function extractGeneratorIds(array &$data): ?array
    {
        $hasLowercaseKey = array_key_exists('generators', $data);
        $hasUppercaseKey = array_key_exists('GENERATORS', $data);

        if (! $hasLowercaseKey && ! $hasUppercaseKey) {
            return null;
        }

        $generatorIds = $data['generators'] ?? $data['GENERATORS'] ?? [];
        unset($data['generators']);
        unset($data['GENERATORS']);

        return collect($generatorIds)
            ->map(function ($generator) {
                if (is_array($generator)) {
                    return $generator['GENERATOR_OID'] ?? $generator['generator_oid'] ?? $generator['OID'] ?? $generator['oid'] ?? null;
                }

                if (is_object($generator)) {
                    return $generator->GENERATOR_OID ?? $generator->generator_oid ?? $generator->OID ?? $generator->oid ?? null;
                }

                return $generator;
            })
            ->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->unique()
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
        return $this->wellRepository->getActiveWells();
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
}
