<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Repositories\TripRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FleetReportService
{
    public function __construct(
        private readonly TripRepository $trips,
        private readonly FleetAccessService $access,
        private readonly TripReviewService $review,
    ) {}

    /** @return array<string, mixed> */
    public function screen(int $userId, array $filters): array
    {
        $query = $this->query($userId, $filters);
        $summary = (clone $query)->reorder()->toBase()
            ->selectRaw('COUNT(*) AS trips, SUM(virtual_fuel_liters) AS fuel, SUM(distance_km) AS distance')->first();

        return [
            'trips' => $query->paginate(30, ['id', 'started_at', 'ended_at', 'driver_name', 'vehicle_plate', 'origin_text', 'destination_text', 'status', 'review_status', 'distance_km', 'virtual_fuel_liters', 'entry_source'])->withQueryString(),
            'summary' => [
                'trips' => (int) ($summary->trips ?? 0),
                'fuel_liters' => round((float) ($summary->fuel ?? 0), 1),
                'distance_km' => round((float) ($summary->distance ?? 0), 1),
            ],
        ];
    }

    /** Same columns and 5,000-row ceiling as the legacy CSV export. */
    public function csv(int $userId, array $filters): StreamedResponse
    {
        $trips = $this->query($userId, $filters)->limit(5000)->get();

        return response()->streamDownload(function () use ($trips): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['trip_id', 'started_at', 'ended_at', 'driver_name', 'driver_phone', 'vehicle_plate', 'vehicle_name', 'origin', 'destination', 'status', 'review_status', 'distance_km', 'virtual_fuel_liters', 'points_received', 'points_expected']);
            foreach ($trips as $t) {
                fputcsv($out, [$t->id, $t->started_at?->format('Y-m-d H:i:s'), $t->ended_at?->format('Y-m-d H:i:s'), $t->driver_name, $t->driver_phone, $t->vehicle_plate, $t->vehicle_name, $t->origin_text, $t->destination_text, $t->status, $t->review_status, $t->distance_km, $t->virtual_fuel_liters, $t->points_received, $t->points_expected]);
            }
            fclose($out);
        }, 'fleet-report-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(int $userId, array $filters)
    {
        [$from, $to] = $this->review->period($filters['period'] ?? 'month', $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return $this->trips->report($from, $to, trim((string) ($filters['driver'] ?? '')), trim((string) ($filters['vehicle'] ?? '')), $this->access->allowedVehicleIds($userId));
    }
}
