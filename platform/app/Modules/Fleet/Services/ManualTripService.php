<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Modules\Fleet\Repositories\TripRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualTripService
{
    public function __construct(
        private readonly MunicipalityVehicleRepository $vehicles,
        private readonly TripRepository $trips,
    ) {}

    /** @return array<string, mixed> */
    public function formOptions(int $ssoUserId): array
    {
        return [
            'vehicles' => $this->vehicles->forAdministrativeAssistant($ssoUserId),
            ...$this->trips->manualTripOptions(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function indexOptions(int $ssoUserId, array $filters): array
    {
        $trips = $this->trips->paginateManualForAssistant($ssoUserId, $filters);
        $trips->getCollection()->each(function (Trip $trip): void {
            $trip->setAttribute('submit_url', route('fleet.manual.submit', $trip->getKey()));
        });

        return [
            'trips' => $trips,
            'vehicles' => $this->vehicles->forAdministrativeAssistant($ssoUserId),
        ];
    }

    public function approvalOptions(int $ssoUserId, array $filters): array
    {
        $trips = $this->trips->paginateManualForApproval(
            $this->vehicles->vehicleIdsForReviewer($ssoUserId)->all(),
            $filters,
        );
        $trips->getCollection()->each(function (Trip $trip): void {
            $trip->setAttribute('approve_url', route('fleet.manual.approve', $trip->getKey()));
        });

        return ['trips' => $trips, 'vehicles' => $this->vehicles->forManualReviewer($ssoUserId)];
    }

    public function approve(int $ssoUserId, int $tripId, string $actorName, ?string $note): Trip
    {
        $trip = $this->trips->findManualForApproval($tripId)
            ?? throw (new ModelNotFoundException)->setModel(Trip::class, [$tripId]);
        abort_unless($this->vehicles->reviewerCanAccessVehicle($ssoUserId, $trip->oracle_vehicle_id), 403,
            'ليست لديك صلاحية اعتماد الطلب لهذه المركبة.');

        return $this->trips->approveManual(
            $tripId,
            $ssoUserId,
            $actorName,
            $note,
            $this->vehicles->hourlyPriceFor($trip->oracle_vehicle_id),
        );
    }

    /** @return array<string, mixed> */
    public function editOptions(int $ssoUserId, int $tripId): array
    {
        $trip = $this->editableTrip($ssoUserId, $tripId);

        return [
            ...$this->formOptions($ssoUserId),
            'trip' => [
                'id' => $trip->getKey(),
                'oracle_vehicle_id' => $trip->oracle_vehicle_id,
                'started_at' => $trip->started_at?->format('Y-m-d\TH:i:s'),
                'ended_at' => $trip->ended_at?->format('Y-m-d\TH:i:s'),
                'origin_text' => $trip->origin_text,
                'destination_text' => $trip->destination_text,
                'mission_type' => $trip->mission_type,
                'estimated_distance_km' => $trip->distance_km,
                'start_odometer' => $trip->start_odometer,
                'end_odometer' => $trip->end_odometer,
                'wait_minutes' => $trip->wait_minutes,
                'notes' => $trip->notes,
            ],
        ];
    }

    public function create(int $ssoUserId, string $actorName, array $data): Trip
    {
        return $this->trips->createManual([
            'local_id' => (string) Str::uuid(),
            ...$this->attributes($ssoUserId, $data),
            'status' => 'confirmed',
            'review_status' => 'draft',
            'confirmed_at' => now(),
            'points_expected' => 0,
            'points_received' => 0,
            'entry_source' => 'manual',
            'entered_by_user_id' => $ssoUserId,
            'entered_by_name' => $actorName,
        ], $actorName);
    }

    public function update(int $ssoUserId, int $tripId, string $actorName, array $data): Trip
    {
        return $this->trips->updateManual(
            $this->editableTrip($ssoUserId, $tripId),
            $this->attributes($ssoUserId, $data),
            $actorName,
        );
    }

    public function submit(int $ssoUserId, int $tripId, string $actorName): Trip
    {
        $trip = $this->editableTrip($ssoUserId, $tripId);
        abort_unless($this->vehicles->findForAdministrativeAssistant($ssoUserId, (int) $trip->oracle_vehicle_id), 403,
            'المركبة غير متاحة لمحطاتك.');

        return $this->trips->submitManual($tripId, $ssoUserId, $actorName);
    }

    private function editableTrip(int $ssoUserId, int $tripId): Trip
    {
        return $this->trips->findEditableManualForAssistant($tripId, $ssoUserId)
            ?? throw (new ModelNotFoundException)->setModel(Trip::class, [$tripId]);
    }

    /** @return array<string, mixed> */
    private function attributes(int $ssoUserId, array $data): array
    {
        $vehicle = $this->vehicles->findForAdministrativeAssistant(
            $ssoUserId,
            (int) $data['oracle_vehicle_id'],
        );

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'oracle_vehicle_id' => 'المركبة غير متاحة لمحطاتك.',
            ]);
        }

        if (! filled($vehicle['oracle_driver_id'] ?? null)) {
            throw ValidationException::withMessages([
                'oracle_vehicle_id' => 'لا يوجد سائق مسند لهذه المركبة في Oracle.',
            ]);
        }

        $startedAt = CarbonImmutable::parse($data['started_at']);
        $endedAt = CarbonImmutable::parse($data['ended_at']);

        return [
            'oracle_driver_id' => (string) $vehicle['oracle_driver_id'],
            'driver_name' => (string) ($vehicle['driver_name'] ?? 'سائق'),
            'driver_phone' => $vehicle['driver_phone'] ?? null,
            'driver_employee_number' => $vehicle['driver_employee_number'] ?? null,
            'oracle_vehicle_id' => (string) $vehicle['oracle_id'],
            'vehicle_plate' => (string) $vehicle['plate_number'],
            'vehicle_name' => $vehicle['name'] ?? null,
            'vehicle_type' => $vehicle['vehicle_type'] ?? null,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'origin_type' => 'free_text',
            'origin_text' => trim($data['origin_text']),
            'destination_type' => 'free_text',
            'destination_text' => trim($data['destination_text']),
            'mission_type' => trim($data['mission_type']),
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'start_odometer' => $data['start_odometer'] ?? null,
            'end_odometer' => $data['end_odometer'] ?? null,
            'wait_minutes' => (int) ($data['wait_minutes'] ?? 0),
            'distance_km' => filled($data['start_odometer'] ?? null) && filled($data['end_odometer'] ?? null)
                ? (float) ($data['end_odometer'] - $data['start_odometer'])
                : round((float) $data['estimated_distance_km'], 3),
            'duration_minutes' => (int) $startedAt->diffInMinutes($endedAt),
        ];
    }
}
