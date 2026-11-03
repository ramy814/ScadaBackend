<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Beneficiary;
use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\CatalogRepository;
use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Modules\Fleet\Repositories\TripRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Driver app API logic that the legacy controllers ran inline (Api\TripSyncController index/
 * reconcile/respondClarification, BeneficiaryProfileController, VehicleLookupController and
 * the lookup controllers). Payloads, limits, fallbacks and messages are unchanged.
 */
class DriverApiService
{
    public function __construct(
        private readonly TripRepository $trips,
        private readonly CatalogRepository $catalogs,
        private readonly MunicipalityVehicleRepository $vehicles,
        private readonly DriverTripSummaryService $summaries,
    ) {}

    public function profile(Beneficiary $driver): array
    {
        return ['driver' => $driver->toAuthPayload(), 'oracle_available' => extension_loaded('oci8')];
    }

    public function clarifications(Beneficiary $driver): array
    {
        $items = $this->trips->driverClarifications((string) $driver->getAuthIdentifier())
            ->map(fn ($trip): array => [
                'trip_id' => $trip->id,
                'local_id' => $trip->local_id,
                'vehicle_plate' => $trip->vehicle_plate,
                'origin_text' => $trip->origin_text,
                'destination_text' => $trip->destination_text,
                'review_note' => $trip->review_note,
                'reviewed_at' => optional($trip->reviewed_at)?->toIso8601String(),
                'clarification_response' => $trip->clarification_response,
                'clarification_responded_at' => optional($trip->clarification_responded_at)?->toIso8601String(),
            ])
            ->values();

        return ['clarifications' => $items];
    }

    /** GET /api/driver/trips — same filters, page-size rules and row shape as legacy. */
    public function trips(Beneficiary $driver, Request $request): array
    {
        $perPage = (int) $request->query('per_page', $request->query('limit', 20));
        $perPage = min(max($perPage, 1), $request->has('per_page') ? 50 : 300);

        $paginator = $this->trips->paginateForDriver((string) $driver->getAuthIdentifier(), [
            'status' => (string) $request->query('status', ''),
            'review_status' => (string) $request->query('review_status', ''),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'q' => trim((string) $request->query('q', '')),
        ], $perPage, max((int) $request->query('page', 1), 1));

        $trips = $paginator->getCollection()->map(fn (Trip $trip): array => [
            'id' => $trip->id,
            'local_id' => $trip->local_id,
            'vehicle_plate' => $trip->vehicle_plate,
            'origin_text' => $trip->origin_text,
            'destination_text' => $trip->destination_text,
            'mission_type' => $trip->mission_type,
            'started_at' => optional($trip->started_at)?->toIso8601String(),
            'ended_at' => optional($trip->ended_at)?->toIso8601String(),
            'status' => $trip->status,
            'review_status' => $trip->review_status,
            'virtual_fuel_liters' => $trip->virtual_fuel_liters,
            'points_expected' => $trip->points_expected,
            'points_received' => $trip->points_received,
            'confirmed_at' => optional($trip->confirmed_at)?->toIso8601String(),
            ...$this->summaries->summarize($trip),
        ])->values();

        return [
            'trips' => $trips,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** GET /api/driver/trips/reconcile?local_ids=a,b — tells the app which local trips the server holds. */
    public function reconcile(Beneficiary $driver, string $localIdsCsv): array
    {
        $localIds = collect(explode(',', trim($localIdsCsv)))->map(fn ($id) => trim($id))->filter()->values();

        if ($localIds->isEmpty()) {
            return ['items' => [], 'count' => 0];
        }

        $rows = $this->trips->driverTripsByLocalIds((string) $driver->getAuthIdentifier(), $localIds->all());

        $items = $localIds->map(function (string $localId) use ($rows): array {
            /** @var Trip|null $trip */
            $trip = $rows->get($localId);
            if (! $trip) {
                return ['local_id' => $localId, 'uploaded' => false, 'status' => 'missing'];
            }

            return [
                'local_id' => $localId,
                'uploaded' => true,
                'server_id' => $trip->id,
                'status' => $trip->status,
                'review_status' => $trip->review_status,
                'confirmed_at' => optional($trip->confirmed_at)?->toIso8601String(),
                'points_expected' => $trip->points_expected,
                'points_received' => $trip->points_received,
                'virtual_fuel_liters' => $trip->virtual_fuel_liters,
                'vehicle_plate' => $trip->vehicle_plate,
                ...$this->summaries->summarize($trip),
            ];
        })->values();

        return ['items' => $items, 'count' => $items->count()];
    }

    public function respondClarification(Beneficiary $driver, Trip $trip, string $response): array
    {
        abort_unless((string) $trip->oracle_driver_id === (string) $driver->getAuthIdentifier(), 403, 'غير مسموح.');
        abort_unless($trip->review_status === 'needs_clarification', 422, 'لا يوجد طلب توضيح نشط.');

        $trip = $this->trips->recordClarificationResponse($trip, $response, 'driver:'.$driver->getAuthIdentifier());

        return ['trip_id' => $trip->id, 'status' => 'responded', 'clarification_responded_at' => $trip->clarification_responded_at];
    }

    /** Vehicles assigned to the driver by Oracle beneficiary id or mobile (legacy VehicleLookupController). */
    public function vehicles(Beneficiary $user, ?string $search = null): array
    {
        $phone = $user->phoneNumber();
        $items = collect()->merge($this->vehicles->findByDriverOracleId((string) $user->getAuthIdentifier()));
        if ($phone) {
            $items = $items->merge($this->vehicles->findByDriverMobile($phone));
        }
        $items = $items
            ->filter(fn (array $item) => ! empty($item['plate_number']))
            ->unique(fn (array $item) => (string) $item['plate_number'])
            ->values();

        Log::info('driver.my-vehicles', [
            'beneficiary_id' => $user->getAuthIdentifier(),
            'phone_from_beneficiary' => $phone,
            'phone_used' => $phone,
            'oracle_driver_id' => $user->getAuthIdentifier(),
            'vehicles_count' => $items->count(),
        ]);

        if (is_string($search) && $search !== '') {
            $items = $items->filter(fn (array $item): bool => str_contains((string) ($item['plate_number'] ?? ''), $search))->values();
        }

        return ['vehicles' => $items];
    }

    /** Active origins/destinations with the legacy hard-coded fallback when the table is unreadable. */
    public function locations(): array
    {
        try {
            $all = $this->catalogs->activeLocationsForDriverApp();
            $origins = $all->whereIn('category', ['origin', 'both'])->values();
            $destinations = $all->whereIn('category', ['destination', 'both'])->values();
        } catch (QueryException) {
            $origins = $destinations = $this->fallbackLocations();
        }

        return ['origins' => $origins, 'destinations' => $destinations];
    }

    public function missionTypes(): array
    {
        try {
            $items = $this->catalogs->activeMissionTypesForDriverApp();
        } catch (QueryException) {
            $items = collect([
                ['id' => 1, 'name' => 'نقل موظفين'],
                ['id' => 2, 'name' => 'نقل معدات'],
                ['id' => 3, 'name' => 'مهمة ميدانية'],
                ['id' => 4, 'name' => 'طوارئ'],
                ['id' => 5, 'name' => 'صيانة'],
            ]);
        }

        return ['mission_types' => $items];
    }

    private function fallbackLocations(): Collection
    {
        return collect([
            ['id' => 1, 'name' => 'مجمّع البلدية الرئيس', 'category' => 'both'],
            ['id' => 2, 'name' => 'المبنى الأثري', 'category' => 'both'],
            ['id' => 3, 'name' => 'مبنى السرايا', 'category' => 'both'],
            ['id' => 4, 'name' => 'مبنى أبو أيوب الأنصاري', 'category' => 'both'],
            ['id' => 5, 'name' => 'مبنى الكهرباء والمخازن', 'category' => 'both'],
            ['id' => 6, 'name' => 'كراج البلدية والطوارئ', 'category' => 'both'],
            ['id' => 7, 'name' => 'مقر التفتيش والمتابعة', 'category' => 'both'],
            ['id' => 8, 'name' => 'محكمة صلح بلدية غزة', 'category' => 'both'],
            ['id' => 9, 'name' => 'المسلخ البلدي', 'category' => 'both'],
            ['id' => 10, 'name' => 'المراكز المجتمعية الفرعية', 'category' => 'both'],
            ['id' => 11, 'name' => 'مكتب الصحة والبيئة – الشجاعية', 'category' => 'both'],
        ]);
    }
}
