<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Well;
use App\Repositories\Wells\WellRepository;
use App\Repositories\Wells\GeneratorRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WellService
{
    public function __construct(
        private readonly WellRepository $wellRepository,
        private readonly GeneratorRepository $generatorRepository,
    ) {
    }

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
            $well = $this->wellRepository->create($data);

            if ($generatorIds !== null) {
                $this->generatorRepository->syncWellGenerators((int) $well->getKey(), $generatorIds);
            }

            return $well->fresh(['teams', 'generators']);
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
            $updatedWell = $this->wellRepository->update($id, $data);

            if ($generatorIds !== null) {
                $this->generatorRepository->syncWellGenerators($id, $generatorIds);
            }

            return $updatedWell->fresh(['teams', 'generators']);
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

    public function getWellWithGenerators(int $wellId): Well
    {
        return $this->wellRepository->findWithGenerators($wellId);
    }

    public function getWellsForMap(): Collection
    {
        return $this->wellRepository->getActiveWells();
    }

    /**
     * Get quarters list - in production queries GAZADATA.QUARTERS via Oracle.
     * In development (SQLite) returns an empty array; populate via seeder if needed.
     */
    public function getQuarters(): array
    {
        $quarters = \Illuminate\Support\Facades\DB::connection('oracle')->select("
            SELECT q.OId, q.NAME 
            FROM GAZADATA.QUARTERS q
        ");

        return array_map(function ($row) {
            return (object) array_change_key_case((array) $row, CASE_UPPER);
        }, $quarters);
    }
}
