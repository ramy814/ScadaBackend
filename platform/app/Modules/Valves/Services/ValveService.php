<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Valves\Repositories\ValveRepository;
use Illuminate\Database\Eloquent\Collection;

class ValveService
{
    private const GAZA_BOUNDS = [
        'min_latitude' => 31.15,
        'max_latitude' => 31.65,
        'min_longitude' => 34.15,
        'max_longitude' => 34.65,
    ];

    public function __construct(
        private readonly ValveRepository $valveRepository,
    ) {}

    public function getAllValves(): Collection
    {
        return $this->valveRepository->all();
    }

    public function getAssignedValves(?ValveInspector $inspector, array $filters = []): Collection
    {
        return $this->valveRepository->assignedToInspector($inspector, $filters);
    }

    public function getMapValves(): array
    {
        return $this->valveRepository->forMap()
            ->filter(function (Valve $valve): bool {
                $latitude = $valve->latitude;
                $longitude = $valve->longitude;

                return $latitude !== null
                    && $longitude !== null
                    && $latitude >= self::GAZA_BOUNDS['min_latitude']
                    && $latitude <= self::GAZA_BOUNDS['max_latitude']
                    && $longitude >= self::GAZA_BOUNDS['min_longitude']
                    && $longitude <= self::GAZA_BOUNDS['max_longitude'];
            })
            ->map(fn (Valve $valve): array => [
                'oid' => $valve->oid,
                'code' => $valve->code,
                'name' => $valve->name,
                'latitude' => $valve->latitude,
                'longitude' => $valve->longitude,
                'street_no' => $valve->getAttribute('w4_street_no'),
                'valve_type' => $valve->getAttribute('w4_valve_type'),
                'valve_diameter' => $valve->getAttribute('w4_valve_diameter'),
                'manufacturer' => $valve->getAttribute('w4_manufacturer'),
                'operation' => $valve->getAttribute('w4_operation'),
                'status' => $valve->getAttribute('w4_status'),
                'project_no' => $valve->getAttribute('w4_project_no'),
                'source' => $valve->getAttribute('w4_source'),
                'quality' => $valve->getAttribute('w4_quality'),
                'valve_no' => $valve->getAttribute('w4_valve_no'),
                'valve_open' => $valve->getAttribute('w4_valve_open'),
            ])
            ->values()
            ->all();
    }
}
