# Backend Folder Structure

The backend follows a customized but standard Laravel 11 structure.

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            # Controllers for the Vue SPA (Dashboard, Reports)
│   │   ├── Api/              # Controllers for the Flutter App
│   │   ├── Valves/           # Valves dashboard controllers
│   │   ├── Wells/            # Wells dashboard controllers
│   │   ├── PortalController.php
│   │   ├── SystemPermissionController.php
│   │   └── Auth/             # Controllers handling SSO and SMS Login
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php # Injects global URLs into Vue
│   └── Requests/             # FormRequests for validation
├── Models/                   # Trip, TripPoint, TripStop, TripTrackingEvent, etc.
├── Repositories/             # Encapsulates all Database Queries
│   └── MunicipalityVehicleRepository.php # Only place where Oracle is queried
├── Services/                 # Business Logic layer
│   ├── TripSyncService.php   # Idempotent snapshots, stops/events, point batches, confirmation
│   ├── DriverAppUsageService.php # Driver/version/store adoption overview
│   ├── VirtualFuelService.php# Estimates fuel usage based on GPS
│   └── AdminTripReviewService.php # Handles approve/reject/clarify logic
└── Providers/
    └── AppServiceProvider.php

config/
└── database.php              # Includes the Oracle connection configuration

database/
└── migrations/               # Oracle-managed schema for application data

routes/
├── web.php                   # Inertia SPA routes & SSO
├── api.php                   # Sanctum protected routes for Flutter
├── wells.php                 # Wells dashboard/API module routes
└── valves.php                # Valves dashboard/API module routes
```

## Portal Permissions

- `PortalController` renders `/` and shows the system cards based on `SystemPermissionRepository::accessibleSystems()`.
- `SystemPermissionController` renders `/permissions` and delegates grant/revoke/replace operations to `SystemPermissionRepository`.
- `SystemPermissionController` is manager-gated by `MOG_PERMISSIONS_ADMIN_USER_IDS` outside local development.
- `EnsureSystemAccess` protects Fleet, Wells, and Valves route groups with `system.access:{key}` middleware.
- Valid system keys are `fleet`, `wells`, and `valves`.
- The same `SYSTEM_PERMISSIONS` table stores fail-closed Wells action keys `wells.readings.delete` and `wells.diesel-inventories.delete`; they do not count as portal systems and require base Wells access.
- `FLEET_ADMIN_ALLOWLIST` is only the global admin gate; per-system access lives in `CONTROL_UNIT.SYSTEM_PERMISSIONS`.
- Wells reading/inventory delete routes validate a reason, authorize through `SystemPermissionRepository`, and delegate to their existing service/repository. Models provide the global soft-delete exclusion, while raw analytic queries add an explicit null-deletion predicate.
- `/permissions` also exposes the global operational switch through `SystemPermissionController::updateOperationalState()` and `UpdateSystemOperationalStateRequest`.
- Operational persistence belongs to `SystemOperationalStateRepository`; business/audit behavior belongs to `SystemOperationalStateService`; request enforcement belongs to `RejectWhenSystemsSuspended` through the `systems.operational` middleware alias.
- Normal portal/Fleet/Wells/Valves web routes and all field-app APIs use this middleware. Permission routes remain exempt as the authenticated recovery control plane.

## Valves Module Structure

The valves module uses dedicated namespaces and tables separate from wells:
- Controllers: `App\Http\Controllers\Valves\*`
- API controllers: `App\Http\Controllers\Api\Valves\*`
- Requests: `App\Http\Requests\Valves\*`
- Models: `App\Models\Valves\*`
- Repositories: `App\Repositories\Valves\*`
- Services: `App\Services\Valves\*`

Do not reuse wells `Team` or `Inspector` classes for valves features.

## Driver App Usage Monitoring

- API: `POST /api/driver/app-presence` → `Api/DriverAppPresenceController`.
- Admin: `GET /admin/driver-apps` → `DriverAppUsageController` → `Admin/DriverApps.vue`.
- Managed data: `DriverAppInstallation` through `DriverAppInstallationRepository`.
- Release policy: `config/driver_app.php`; change the configured version/build for every published Android release.
- Fleet KPI adoption: `DriverAppInstallationRepository::reportingDriverIds()` supplies distinct reporting drivers. `DriverAppUsageService::dashboardActiveCounts()` sends those IDs to `MunicipalityDriverRepository::countActiveUsingApp()` and `MunicipalityVehicleRepository::countActiveAssignedToDrivers()`.
- Fleet period fuel: `TripRepository::dashboardFuelConsumption()` returns the official liters total and contributing confirmed-trip count for the selected date range while reusing the dashboard experimental visibility rule.
- `DashboardIndexRequest` validates daily/weekly/monthly/custom period inputs. A custom range requires `date_from` and `date_to`; `AdminAuthController` only resolves the range and coordinates repository/service results.

## Driver Live Tracking

- `StoreTripRequest` accepts the unchanged legacy trip payload plus optional `manual_wait_minutes`, stop UUID/coordinates/source, and allowlisted tracking events.
- `TripSyncService` owns idempotent trip, stop, and event persistence and recomputes automatic waiting without trusting a client-computed automatic total.
- `TripSyncService::uploadPoints()` uses a per-trip `FOR UPDATE` lock and shape-preserving 100-row upserts. The public endpoint still accepts up to 500 points, and omitted optional fields remain untouched on a retry.
- Trip metadata arrays are bounded at 50 passengers, 500 stops, and 500 tracking events, with duplicate stop/event UUIDs and point sequence numbers rejected before persistence.
- `TripSyncService::confirm()` serializes on the trip row, validates count/checksum, calculates summary metrics once, and persists distance, duration, and official fuel. Driver history/reconciliation only shape those stored columns and never scan points; missing legacy summaries remain null until an explicit offline backfill.
- `TripRepository::routePreviewPoints()` owns the only driver-history coordinate sampling path. It reads eligible count/min/max, generates at most 100 evenly distributed sequence targets, and lets Oracle resolve them through `(TRIP_ID, SEQUENCE_NUMBER)`; `DriverTripRoutePreviewService` must not reload or downsample the full collection.
- `TripRepository::paginateForDashboard()` adds `last_point_at`, eager-loads the latest tracking event, counts GPS-disable, airplane-on, and explicit tracking-gap events per trip, and keeps active trips visible outside the selected completed-trip period.
- `TripRepository` owns the shared dashboard visibility rule used by pagination, counts, and fuel: active trips are always visible; completed trips require server-maintained `points_received >= 50` unless `show_experimental=1` is requested. Do not reintroduce relationship counts over the full points table for this filter.
- `dashboardCounts()` is one conditional aggregate query and `dashboardFuelConsumption()` is one aggregate query. Keep these bounded instead of cloning and executing one full query per card.
- The experimental filter never deletes or updates a trip and does not apply to reports or direct trip-detail routes.
- `AdminAuthController::showTrip()` loads stop locations and ordered technical events for `Admin/TripShow.vue`.
- The same trip-details flow requests segment metrics from `VirtualFuelService`. With segment output enabled, the service returns `estimated_gap_distance_km`, `estimated_distance_with_gaps_km`, `estimated_fuel_with_gaps_liters`, `estimated_gap_count`, and `unestimated_gap_count`. These are transient Inertia props only; no migration or database write belongs in this workflow.
- `Admin/Dashboard.vue` and `Admin/TripShow.vue` refresh active data every 15 seconds. This is polling over the existing Inertia/API data; there is no WebSocket service.
- The schema change is `2026_07_29_000021_add_live_tracking_telemetry.php`. See `.ai/workflows/live-tracking-flow.md` for the authoritative end-to-end and rollout contract.

## Mobile Sync Resource Boundaries

- `App\Services\Wells\MobileSyncService` owns Wells parent-record synchronization. It chunks each accepted request into 50-record transactions, performs one bulk idempotency lookup per chunk through repositories, and acknowledges existing records on retry.
- Wells controllers validate and delegate only; do not restore row-by-row writes or direct sync queries in controllers.
- `App\Services\Valves\ValveActionService` records SCADA actions and any maintenance report in one transaction. `CLIENT_LOCAL_ID` is optional for released-app compatibility and idempotent when supplied.
- Tracking performance indexes are added by `2026_08_24_000024_add_trip_tracking_performance_indexes.php`; schedule this migration only after Oracle connectivity/account health is restored.
