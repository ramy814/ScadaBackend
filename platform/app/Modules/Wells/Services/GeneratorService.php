<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Models\WellGenerator;
use App\Modules\Wells\Repositories\GeneratorRepository;
use Illuminate\Database\Eloquent\Collection;

class GeneratorService
{
    public function __construct(
        private readonly GeneratorRepository $generatorRepository,
    ) {}

    public function getActiveGenerators(): array
    {
        return $this->generatorRepository->getActiveGenerators();
    }

    public function getGeneratorsByWell(int $wellId): Collection
    {
        return $this->generatorRepository->getByWell($wellId);
    }

    public function assignGeneratorToWell(int $wellId, int $generatorId): WellGenerator
    {
        return $this->generatorRepository->assignToWell($wellId, $generatorId);
    }

    public function removeGeneratorFromWell(int $wellId, int $generatorId): bool
    {
        return $this->generatorRepository->removeFromWell($wellId, $generatorId);
    }

    public function syncWellGenerators(int $wellId, array $generatorIds): void
    {
        $this->generatorRepository->syncWellGenerators($wellId, $generatorIds);
    }

    public function getGeneratorsByIds(array $ids): array
    {
        return $this->generatorRepository->getGeneratorsByIds($ids);
    }
}
