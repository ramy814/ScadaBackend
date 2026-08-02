<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MissionLocation;
use App\Models\MissionType;
use App\Models\Trip;
use App\Repositories\MunicipalityDriverRepository;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\TripRepository;
use App\Services\VirtualFuelService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Mog\ProjectHelper\AuthHelpers;
use Mpdf\Mpdf;

class AdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        if ($request->has('sso_error')) {
            return response(
                '<html><head><meta charset="utf-8"><title>خطأ في تسجيل الدخول</title></head>'
                .'<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#1a1a2e;color:#fff;">'
                .'<div style="text-align:center;">'
                .'<h2>⚠️ فشل تسجيل الدخول</h2>'
                .'<p>تعذّر التحقق من رمز SSO. الرجاء المحاولة مرة أخرى.</p>'
                .'<a href="/admin/login" style="color:#6c9ef8;">إعادة المحاولة</a>'
                .'</div></body></html>',
                200
            );
        }

        return AuthHelpers::login($request);
    }

    public function dashboard(
        MunicipalityVehicleRepository $vehicleRepository,
        MunicipalityDriverRepository $driverRepository,
        TripRepository $tripRepository,
    ): InertiaResponse {
        $period = request()->query('period', 'daily');
        $responseStatus = request()->query('response_status', 'all');
        $search = trim((string) request()->query('search', ''));
        [$from, $to] = $this->periodRange($period);

        $todayTrips = Trip::query()->whereDate('started_at', now()->toDateString())->count();
        $confirmedTrips = Trip::query()->where('status', 'confirmed')->count();
        $activeVehicles = DB::connection('oracle')->table('g_store.VEHICLE')->where('IS_ACTIVE', 1)->count();
        $activeDrivers = DB::connection('oracle')->table('g_store.BENEFICIARIES')->where('IS_ACTIVE', 1)->count();
        $approvedTrips = Trip::query()->where('review_status', 'approved')->count();
        $rejectedTrips = Trip::query()->where('review_status', 'rejected')->count();
        $clarificationTrips = Trip::query()->where('review_status', 'needs_clarification')->count();

        $recentTrips = $tripRepository->paginateForDashboard(
            $from,
            $to,
            $responseStatus,
            $search,
        );
        $this->hydrateTripDrivers($recentTrips->getCollection(), $driverRepository);

        $clarificationPendingResponse = Trip::query()
            ->where('review_status', 'needs_clarification')
            ->whereNull('clarification_response')
            ->count();

        $clarificationResponded = Trip::query()
            ->whereNotNull('clarification_response')
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'todayTrips' => $todayTrips,
            'confirmedTrips' => $confirmedTrips,
            'activeVehicles' => $activeVehicles,
            'activeDrivers' => $activeDrivers,
            'approvedTrips' => $approvedTrips,
            'rejectedTrips' => $rejectedTrips,
            'clarificationTrips' => $clarificationTrips,
            'clarificationPendingResponse' => $clarificationPendingResponse,
            'clarificationResponded' => $clarificationResponded,
            'period' => $period,
            'responseStatus' => $responseStatus,
            'search' => $search,
            'recentTrips' => $recentTrips,
        ]);
    }

    public function locations(): InertiaResponse
    {
        $items = MissionLocation::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Locations', ['items' => $items]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:origin,destination,both'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        MissionLocation::query()->create([
            'name' => $data['name'],
            'category' => $data['category'],
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return redirect()->route('admin.locations')->with('ok', 'تمت إضافة الموقع.');
    }

    public function updateLocation(Request $request, MissionLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:origin,destination,both'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $location->update([
            'name' => $data['name'],
            'category' => $data['category'],
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        return redirect()->route('admin.locations')->with('ok', 'تم تعديل الموقع.');
    }

    public function missionTypes(): InertiaResponse
    {
        try {
            $items = MissionType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 942 || str_contains($exception->getMessage(), 'ORA-00942')) {
                return Inertia::render('Admin/MissionTypes', [
                    'items' => collect(),
                    'tableMissing' => true,
                ]);
            }
            throw $exception;
        }

        return Inertia::render('Admin/MissionTypes', ['items' => $items]);
    }

    public function storeMissionType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        try {
            MissionType::query()->create([
                'name' => $data['name'],
                'sort_order' => $data['sort_order'] ?? 0,
                'active' => (bool) ($data['active'] ?? true),
            ]);
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 942 || str_contains($exception->getMessage(), 'ORA-00942')) {
                return redirect()->route('admin.mission-types')
                    ->with('error', 'جدول أنواع المهام غير موجود في Oracle. نفّذ الترحيلات أولاً.');
            }
            throw $exception;
        }

        return redirect()->route('admin.mission-types')->with('ok', 'تمت إضافة نوع المهمة.');
    }

    public function updateMissionType(Request $request, MissionType $missionType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        try {
            $missionType->update([
                'name' => $data['name'],
                'sort_order' => $data['sort_order'] ?? 0,
                'active' => (bool) ($data['active'] ?? false),
            ]);
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 942 || str_contains($exception->getMessage(), 'ORA-00942')) {
                return redirect()->route('admin.mission-types')
                    ->with('error', 'جدول أنواع المهام غير موجود في Oracle. نفّذ الترحيلات أولاً.');
            }
            throw $exception;
        }

        return redirect()->route('admin.mission-types')->with('ok', 'تم تعديل نوع المهمة.');
    }

    public function showTrip(Trip $trip, VirtualFuelService $fuel): InertiaResponse
    {
        $this->hydrateTripDrivers(collect([$trip]), app(MunicipalityDriverRepository::class));

        $trip->load([
            'stops:id,trip_id,local_id,started_at,ended_at,latitude,longitude,source,reason,notes',
            'trackingEvents:id,trip_id,event_type,detected_at,latitude,longitude,details_json',
            'passengers:id,trip_id,employee_id,name,source',
        ]);

        $pointsQuery = $trip->points()
            ->select(
                'id',
                'trip_id',
                'sequence_number',
                'latitude',
                'longitude',
                'accuracy',
                'speed',
                'captured_at',
                'recorded_at',
                'client_is_valid',
                'quality_reason',
            );
        $pointsCount = (clone $pointsQuery)->count();
        $qualityVersion = (int) ($trip->quality_version ?? 0);
        $filteredMetrics = $fuel->metricsFromPoints(
            $pointsQuery->orderBy('sequence_number')->cursor(),
            $trip->started_at,
            $trip->ended_at,
            true,
        );
        $hasFilteredDistance = $qualityVersion >= 2
            || $fuel->hasSufficientQualityData($filteredMetrics);

        if ($hasFilteredDistance) {
            $metrics = $filteredMetrics;
            $uploadComplete = $trip->status === 'confirmed'
                && (int) $trip->points_received === (int) $trip->points_expected;
            if ($qualityVersion >= 2 && ! $uploadComplete) {
                $metrics['quality_flags'][] = 'incomplete_upload';
                $metrics['quality_flags'] = array_values(array_unique($metrics['quality_flags']));
            }
            $estimatedFuel = $qualityVersion >= 2
                ? ($uploadComplete ? $fuel->estimateFromMetrics($trip, $metrics) : null)
                : $trip->virtual_fuel_liters;
        } else {
            // Old trips without enough accuracy metadata keep their original
            // display and calculation policy.
            $metrics = $this->legacyTripMetrics(
                $pointsQuery->orderBy('sequence_number')->cursor(),
                $trip,
            );
            $estimatedFuel = $trip->virtual_fuel_liters;
        }

        $fuelEstimateIsProvisional = false;
        if ($estimatedFuel === null && $hasFilteredDistance) {
            $estimatedFuel = $fuel->estimateProvisionalFromMetrics($metrics);
            $fuelEstimateIsProvisional = $estimatedFuel !== null;
        }

        $stopsMinutes = $trip->stops->sum(function ($stop) {
            if (! $stop->started_at || ! $stop->ended_at) {
                return 0;
            }

            return max(0, $stop->started_at->diffInMinutes($stop->ended_at));
        });

        return Inertia::render('Admin/TripShow', [
            'trip' => $trip,
            'qualityVersion' => $qualityVersion,
            'hasFilteredDistance' => $hasFilteredDistance,
            'pointsCount' => $pointsCount,
            'mappedSegments' => $metrics['accepted_segments'],
            'gapConnectors' => $metrics['gap_connectors'] ?? [],
            'recordedStart' => $metrics['recorded_start'] ?? null,
            'recordedEnd' => $metrics['recorded_end'] ?? null,
            'distanceKm' => round((float) $metrics['distance_km'], 2),
            'filteredDistanceKm' => $hasFilteredDistance
                ? round((float) $metrics['distance_km'], 2)
                : null,
            'recordedDistanceKm' => round((float) $metrics['raw_distance_km'], 2),
            'rawDistanceKm' => round((float) $metrics['raw_distance_km'], 2),
            'stopsMinutes' => $stopsMinutes,
            'tripDurationMinutes' => $metrics['duration_minutes'],
            'reportedTripDurationMinutes' => $metrics['reported_duration_minutes'],
            'qualityFlags' => $metrics['quality_flags'],
            'rejectedPoints' => $metrics['rejected_points'],
            'collapsedPoints' => $metrics['collapsed_points'] ?? 0,
            'rejectionCounts' => $metrics['rejection_counts'] ?? [],
            'estimatedFuelLiters' => $estimatedFuel,
            'fuelEstimateIsProvisional' => $fuelEstimateIsProvisional,
            'fuelRatePerKm' => $fuel->litersPerKilometer(),
        ]);
    }

    public function reports(Request $request): InertiaResponse
    {
        [$filters, $trips] = $this->buildReportData($request);

        $paginatedTrips = $trips->paginate(30)->withQueryString();
        $this->hydrateTripDrivers($paginatedTrips->getCollection(), app(MunicipalityDriverRepository::class));

        return Inertia::render('Admin/Reports', [
            ...$filters,
            'trips' => $paginatedTrips,
            'summary' => [
                'trips_count' => (clone $trips)->count(),
                'sum_virtual_fuel' => round((float) ((clone $trips)->sum('virtual_fuel_liters') ?? 0), 2),
            ],
        ]);
    }

    public function reportsCsv(Request $request)
    {
        [$filters, $tripsQuery] = $this->buildReportData($request);
        $trips = $tripsQuery->limit(5000)->get();
        $this->hydrateTripDrivers($trips, app(MunicipalityDriverRepository::class));
        $fileName = 'fleet-report-'.$filters['period'].'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($trips): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'trip_id', 'started_at', 'ended_at', 'driver_name', 'driver_phone',
                'vehicle_plate', 'vehicle_name', 'origin', 'destination', 'status',
                'review_status', 'estimated_virtual_fuel_liters',
                'points_received', 'points_expected',
            ]);
            foreach ($trips as $trip) {
                fputcsv($handle, [
                    $trip->id,
                    optional($trip->started_at)->format('Y-m-d H:i:s'),
                    optional($trip->ended_at)->format('Y-m-d H:i:s'),
                    $trip->driver->name ?? '',
                    $trip->driver->phone ?? '',
                    $trip->vehicle->plate_number ?? '',
                    $trip->vehicle->name ?? '',
                    $trip->origin_text,
                    $trip->destination_text,
                    $trip->status,
                    $trip->review_status,
                    $trip->virtual_fuel_liters,
                    $trip->points_received,
                    $trip->points_expected,
                ]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportsPdf(Request $request)
    {
        [$filters, $tripsQuery] = $this->buildReportData($request);
        $trips = $tripsQuery->limit(2000)->get();
        $this->hydrateTripDrivers($trips, app(MunicipalityDriverRepository::class));
        $summary = [
            'trips_count' => $trips->count(),
            'sum_virtual_fuel' => round((float) ($trips->sum('virtual_fuel_liters') ?? 0), 2),
        ];

        $html = view('admin.reports-pdf', [
            ...$filters,
            'trips' => $trips,
            'summary' => $summary,
            'generatedAt' => now(),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->WriteHTML($html);

        $fileName = 'fleet-report-'.$filters['period'].'-'.now()->format('Ymd_His').'.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function approve(Request $request, Trip $trip): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->review($request, $trip, 'approved', 'تمت الموافقة على الرحلة بنجاح.');
    }

    public function reject(Request $request, Trip $trip): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->review($request, $trip, 'rejected', 'تم رفض الرحلة.');
    }

    public function clarify(Request $request, Trip $trip): RedirectResponse
    {
        $request->validate(
            ['note' => ['required', 'string', 'max:1000']],
            ['note.required' => 'يجب إدخال نص السؤال المطلوب من السائق قبل إرسال طلب التوضيح.']
        );

        return $this->review($request, $trip, 'needs_clarification', 'تم إرسال طلب التوضيح إلى السائق.');
    }

    public function logout(Request $request)
    {
        return AuthHelpers::logout($request, 'admin');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function review(Request $request, Trip $trip, string $status, string $message = 'تم تحديث حالة المراجعة.'): RedirectResponse
    {
        $updatePayload = [
            'review_status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('admin')->id() ?? 'admin',
        ];

        if ($request->has('note')) {
            $newNote = $request->input('note');
            if ($newNote && $trip->review_note && $trip->clarification_response) {
                $newNote = 'الإجراء النهائي: '.$newNote."\n---\nسؤال التوضيح: ".$trip->review_note;
            }
            $updatePayload['review_note'] = $newNote ?: null;
        }

        $trip->update($updatePayload);

        AuditLog::query()->create([
            'event_type' => 'trip.reviewed',
            'entity_type' => 'trip',
            'entity_id' => $trip->id,
            'actor' => Auth::guard('admin')->id() ?? 'admin',
            'payload' => [
                'review_status' => $status,
                'note' => $request->has('note') ? $request->input('note') : '(unchanged)',
            ],
        ]);

        $fromShow = $request->input('_from') === 'show';

        return redirect()
            ->route(
                $fromShow ? 'admin.trips.show' : 'admin.dashboard',
                $fromShow
                    ? ['trip' => $trip->id]
                    : [
                        'period' => $request->input('period', $request->query('period', 'daily')),
                        'response_status' => $request->input('response_status', $request->query('response_status', 'all')),
                        'search' => $request->input('search', $request->query('search', '')),
                        'page' => $request->input('page', $request->query('page', 1)),
                    ]
            )
            ->with('ok', $message);
    }

    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'weekly' => [$now->copy()->subWeek(), $now],
            'monthly' => [$now->copy()->subMonth(), $now],
            default => [$now->copy()->subDay(), $now],
        };
    }

    private function buildReportData(Request $request): array
    {
        $period = $request->query('period', 'daily');
        $driverSearch = trim((string) $request->query('driver_search', $request->query('driver_id', '')));
        $vehicleSearch = trim((string) $request->query('vehicle_search', $request->query('vehicle_id', '')));
        [$from, $to] = $this->periodRange($period);

        $trips = Trip::query()
            ->whereBetween('started_at', [$from, $to])
            ->latest('started_at');

        if ($driverSearch !== '') {
            $driverLikeSearch = "%{$driverSearch}%";
            $trips->where(function ($query) use ($driverLikeSearch, $driverSearch): void {
                $query->where('driver_name', 'like', $driverLikeSearch)
                    ->orWhere('driver_phone', 'like', $driverLikeSearch)
                    ->orWhere('driver_employee_number', 'like', $driverLikeSearch)
                    ->orWhere('oracle_driver_id', $driverSearch);
            });
        }

        if ($vehicleSearch !== '') {
            $vehicleLikeSearch = "%{$vehicleSearch}%";
            $trips->where(function ($query) use ($vehicleLikeSearch, $vehicleSearch): void {
                $query->where('vehicle_plate', 'like', $vehicleLikeSearch)
                    ->orWhere('vehicle_name', 'like', $vehicleLikeSearch)
                    ->orWhere('oracle_vehicle_id', $vehicleSearch);
            });
        }

        return [[
            'period' => $period,
            'driverSearch' => $driverSearch,
            'vehicleSearch' => $vehicleSearch,
            'from' => $from,
            'to' => $to,
        ], $trips];
    }

    private function oracleDriverRows(MunicipalityDriverRepository $driverRepository, ?string $q = null, int $limit = 25): Collection
    {
        $tripCounts = Trip::query()
            ->selectRaw('oracle_driver_id, count(*) as trips_count')
            ->whereNotNull('oracle_driver_id')
            ->groupBy('oracle_driver_id')
            ->pluck('trips_count', 'oracle_driver_id');

        return $driverRepository
            ->search($q, $limit)
            ->map(fn (array $driver): object => (object) [
                'id' => $driver['oracle_id'] ?? null,
                'oracle_driver_id' => $driver['oracle_id'] ?? null,
                'name' => $driver['name'] ?? null,
                'phone' => $driver['phone'] ?? null,
                'employee_number' => $driver['employee_number'] ?? null,
                'active' => true,
                'updated_at' => null,
                'trips_count' => (int) ($tripCounts[$driver['oracle_id'] ?? ''] ?? 0),
            ]);
    }

    private function oracleVehicleRows(MunicipalityVehicleRepository $vehicleRepository, ?string $q = null, int $limit = 25): Collection
    {
        $tripCounts = Trip::query()
            ->selectRaw('vehicle_plate, count(*) as trips_count')
            ->whereNotNull('vehicle_plate')
            ->groupBy('vehicle_plate')
            ->pluck('trips_count', 'vehicle_plate');

        return $vehicleRepository
            ->search($q, $limit)
            ->map(fn (array $vehicle): object => (object) [
                'id' => $vehicle['oracle_id'] ?? $vehicle['plate_number'] ?? null,
                'oracle_vehicle_id' => $vehicle['oracle_id'] ?? null,
                'plate_number' => $vehicle['plate_number'] ?? null,
                'name' => $vehicle['name'] ?? null,
                'vehicle_type' => $vehicle['vehicle_type'] ?? null,
                'engine_capacity' => null,
                'fuel_rate_per_km' => null,
                'idle_fuel_rate_per_hour' => null,
                'odometer_enabled' => false,
                'active' => true,
                'trips_count' => (int) ($tripCounts[$vehicle['plate_number'] ?? ''] ?? 0),
            ]);
    }

    private function hydrateTripDrivers(Collection $trips, MunicipalityDriverRepository $driverRepository): void
    {
        $missingDriverSnapshots = $trips->filter(function (Trip $trip): bool {
            return filled($trip->oracle_driver_id)
                && (! filled($trip->driver_name)
                    || ! filled($trip->driver_phone)
                    || ! filled($trip->driver_employee_number));
        });

        if ($missingDriverSnapshots->isEmpty()) {
            return;
        }

        $drivers = $driverRepository->findManyByIds(
            $missingDriverSnapshots->pluck('oracle_driver_id')->all(),
        );

        foreach ($missingDriverSnapshots as $trip) {
            $driver = $drivers->get((string) $trip->oracle_driver_id);
            if (! $driver) {
                continue;
            }

            $updates = array_filter([
                'driver_name' => $trip->driver_name ?: ($driver['name'] ?? null),
                'driver_phone' => $trip->driver_phone ?: ($driver['phone'] ?? null),
                'driver_employee_number' => $trip->driver_employee_number ?: ($driver['employee_number'] ?? null),
            ], fn ($value): bool => $value !== null && $value !== '');

            if ($updates === []) {
                continue;
            }

            $trip->forceFill($updates);

            if ($trip->exists) {
                $trip->saveQuietly();
            }
        }
    }

    private function legacyTripMetrics(iterable $points, Trip $trip): array
    {
        $distanceKm = 0.0;
        $previous = null;
        $coordinates = [];

        foreach ($points as $point) {
            $current = [
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
            ];
            if ($previous !== null) {
                $distanceKm += $this->haversineKm(
                    $previous['lat'],
                    $previous['lng'],
                    $current['lat'],
                    $current['lng'],
                );
            }
            $coordinates[] = $current;
            $previous = $current;
        }

        $duration = ($trip->started_at && $trip->ended_at)
            ? $trip->started_at->diffInMinutes($trip->ended_at)
            : null;

        return [
            'accepted_segments' => $coordinates === [] ? [] : [$coordinates],
            'gap_connectors' => [],
            'recorded_start' => $coordinates[0] ?? null,
            'recorded_end' => $coordinates === [] ? null : $coordinates[array_key_last($coordinates)],
            'distance_km' => round($distanceKm, 3),
            'raw_distance_km' => round($distanceKm, 3),
            'duration_minutes' => $duration,
            'reported_duration_minutes' => $duration,
            'rejected_points' => 0,
            'collapsed_points' => 0,
            'rejection_counts' => [],
            'collapsed_counts' => [],
            'quality_flags' => [],
        ];
    }

    private function haversineKm(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2,
    ): float {
        $earthKm = 6371.0;
        $deltaLatitude = deg2rad($latitude2 - $latitude1);
        $deltaLongitude = deg2rad($longitude2 - $longitude1);
        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($deltaLongitude / 2) ** 2;
        $a = min(1.0, max(0.0, $a));

        return $earthKm * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
