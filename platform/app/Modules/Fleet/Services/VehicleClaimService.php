<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Modules\Fleet\Repositories\TripRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * المطالبة المالية للمركبات:
 * مبلغ المركبة = مجموع (مدة كل رحلة معتمدة بالساعات × سعر الساعة المثبّت لحظة اعتمادها).
 * الرحلات المعتمدة قبل تفعيل التثبيت تُسعَّر بالسعر الحالي من G_STORE.VEHICLE مع تمييزها.
 *
 * Ported verbatim from the legacy VehicleClaimService (commit 72f4cfe); CSV/PDF exports
 * moved here from the legacy controller so the platform controller stays thin.
 */
class VehicleClaimService
{
    public const PRICE_SOURCE_APPROVAL = 'approval';

    public const PRICE_SOURCE_CURRENT = 'current';

    public function __construct(
        private readonly TripRepository $trips,
        private readonly MunicipalityVehicleRepository $vehicles,
        private readonly FleetAccessService $vehicleAccess,
    ) {}

    /** @param array<string, mixed> $filters */
    public function build(int $userId, array $filters): array
    {
        [$from, $to, $month] = $this->range($filters);
        $vehicleSearch = trim((string) ($filters['vehicle_search'] ?? ''));
        $includeUnpriced = filter_var($filters['include_unpriced'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $trips = $this->trips->approvedTripsForClaims(
            $from,
            $to,
            $vehicleSearch,
            $this->vehicleAccess->allowedVehicleIds($userId),
        );

        $rows = $this->rows($trips);
        $hiddenUnpriced = $includeUnpriced ? 0 : $rows->where('priced_trips', 0)->count();
        if (! $includeUnpriced) {
            $rows = $rows->where('priced_trips', '>', 0)->values();
        }

        $workSeconds = (int) $rows->sum('work_seconds');

        return [
            'filters' => [
                'month' => $month,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'vehicle_search' => $vehicleSearch,
                'include_unpriced' => $includeUnpriced,
            ],
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'rows' => $rows->all(),
            'totals' => [
                'vehicles' => $rows->count(),
                'trips' => (int) $rows->sum('trips_count'),
                'work_seconds' => $workSeconds,
                'work_hours' => round($workSeconds / 3600, 2),
                'work_time' => $this->formatDuration($workSeconds),
                'amount' => round((float) $rows->sum('amount'), 2),
                'current_price_trips' => (int) $rows->sum('current_price_trips'),
                'unpriced_trips' => (int) $rows->sum('unpriced_trips'),
                'hidden_unpriced_vehicles' => $hiddenUnpriced,
            ],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    public function csvRows(array $claim): array
    {
        $lines = [[
            'رقم المركبة', 'اسم المركبة', 'السائق', 'عدد الرحلات', 'ساعات العمل (س:د)',
            'ساعات العمل (عشري)', 'سعر الساعة', 'المبلغ (شيكل)', 'رحلات بالسعر الحالي', 'رحلات بدون سعر',
        ]];

        foreach ($claim['rows'] as $row) {
            $lines[] = [
                $row['plate_number'], $row['vehicle_name'], $row['driver_name'], $row['trips_count'],
                $row['work_time'], $row['work_hours'], $row['price_label'], $row['amount'],
                $row['current_price_trips'], $row['unpriced_trips'],
            ];
        }

        $totals = $claim['totals'];
        $lines[] = [
            'الإجمالي', '', '', $totals['trips'], $totals['work_time'], $totals['work_hours'], '',
            $totals['amount'], $totals['current_price_trips'], $totals['unpriced_trips'],
        ];

        return $lines;
    }

    public function csv(array $claim): StreamedResponse
    {
        $lines = $this->csvRows($claim);

        return response()->streamDownload(function () use ($lines): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            foreach ($lines as $line) {
                fputcsv($handle, $line);
            }
            fclose($handle);
        }, $this->fileName($claim, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(array $claim): Response
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            // DejaVu Sans shapes Arabic itself; automatic script tagging reverses plates such as "3-1234".
            'default_font' => 'dejavusans',
            'tempDir' => storage_path('framework/cache/mpdf'),
        ]);
        $mpdf->WriteHTML(view('fleet.vehicle-claims-pdf', [...$claim, 'generatedAt' => now()])->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName($claim, 'pdf').'"',
        ]);
    }

    private function fileName(array $claim, string $extension): string
    {
        $period = $claim['filters']['month']
            ?? $claim['filters']['date_from'].'_'.$claim['filters']['date_to'];

        return 'vehicle-claims-'.$period.'-'.now()->format('Ymd_His').'.'.$extension;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: ?string} */
    private function range(array $filters): array
    {
        if (filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)) {
            return [
                CarbonImmutable::createFromFormat('Y-m-d', $filters['date_from'])->startOfDay(),
                CarbonImmutable::createFromFormat('Y-m-d', $filters['date_to'])->endOfDay(),
                null,
            ];
        }

        $month = filled($filters['month'] ?? null)
            ? CarbonImmutable::createFromFormat('!Y-m', $filters['month'])
            : CarbonImmutable::now()->startOfMonth();

        return [$month->startOfMonth(), $month->endOfMonth(), $month->format('Y-m')];
    }

    /** @param Collection<int, Trip> $trips */
    private function rows(Collection $trips): Collection
    {
        $vehicleIdsWithoutSnapshot = $trips
            ->filter(fn (Trip $trip): bool => $trip->hourly_price === null)
            ->pluck('oracle_vehicle_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $currentPrices = $vehicleIdsWithoutSnapshot === []
            ? []
            : $this->vehicles->hourlyPricesFor($vehicleIdsWithoutSnapshot);

        return $trips
            ->groupBy(fn (Trip $trip): string => (string) $trip->oracle_vehicle_id)
            ->map(fn (Collection $vehicleTrips, string $vehicleId): array => $this->vehicleRow(
                $vehicleId,
                $vehicleTrips,
                $currentPrices[$vehicleId] ?? null,
            ))
            ->sortBy('plate_number', SORT_NATURAL)
            ->values();
    }

    /** @param Collection<int, Trip> $trips */
    private function vehicleRow(string $vehicleId, Collection $trips, ?float $currentPrice): array
    {
        $latest = $trips->last();
        $days = [];
        $prices = [];
        $amount = 0.0;
        $workSeconds = 0;
        $counts = ['priced' => 0, self::PRICE_SOURCE_CURRENT => 0, 'unpriced' => 0];

        foreach ($trips as $trip) {
            $seconds = max(0, (int) $trip->started_at->diffInSeconds($trip->ended_at, false));
            [$price, $source] = $trip->hourly_price !== null
                ? [(float) $trip->hourly_price, self::PRICE_SOURCE_APPROVAL]
                : [$currentPrice, $currentPrice !== null ? self::PRICE_SOURCE_CURRENT : null];
            $tripAmount = $price !== null ? $seconds / 3600 * $price : null;

            $workSeconds += $seconds;
            if ($price === null) {
                $counts['unpriced']++;
            } else {
                $counts['priced']++;
                $prices[] = $price;
                $amount += $tripAmount;
                if ($source === self::PRICE_SOURCE_CURRENT) {
                    $counts[self::PRICE_SOURCE_CURRENT]++;
                }
            }

            $day = $trip->started_at->toDateString();
            $days[$day] ??= ['date' => $day, 'trips_count' => 0, 'work_seconds' => 0, 'amount' => 0.0];
            $days[$day]['trips_count']++;
            $days[$day]['work_seconds'] += $seconds;
            $days[$day]['amount'] += $tripAmount ?? 0.0;
        }

        $prices = array_values(array_unique($prices, SORT_REGULAR));
        sort($prices);

        return [
            'oracle_vehicle_id' => $vehicleId,
            'plate_number' => $latest->vehicle_plate,
            'vehicle_name' => $latest->vehicle_name,
            'driver_name' => $latest->driver_name,
            'trips_count' => $trips->count(),
            'work_seconds' => $workSeconds,
            'work_hours' => round($workSeconds / 3600, 2),
            'work_time' => $this->formatDuration($workSeconds),
            'prices' => $prices,
            'price_label' => $this->priceLabel($prices),
            'amount' => round($amount, 2),
            'priced_trips' => $counts['priced'],
            'current_price_trips' => $counts[self::PRICE_SOURCE_CURRENT],
            'unpriced_trips' => $counts['unpriced'],
            'days' => array_map(fn (array $day): array => [
                ...$day,
                'work_hours' => round($day['work_seconds'] / 3600, 2),
                'work_time' => $this->formatDuration($day['work_seconds']),
                'amount' => round($day['amount'], 2),
            ], array_values($days)),
        ];
    }

    /** @param array<int, float> $prices */
    private function priceLabel(array $prices): string
    {
        return match (count($prices)) {
            0 => '—',
            1 => $this->money($prices[0]),
            default => $this->money($prices[0]).' – '.$this->money(end($prices)),
        };
    }

    private function money(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function formatDuration(int $seconds): string
    {
        $minutes = intdiv($seconds + 30, 60);

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
