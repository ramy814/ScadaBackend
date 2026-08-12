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

## Driver Live Tracking

- `StoreTripRequest` accepts the unchanged legacy trip payload plus optional `manual_wait_minutes`, stop UUID/coordinates/source, and allowlisted tracking events.
- `TripSyncService` owns idempotent trip, stop, and event persistence and recomputes automatic waiting without trusting a client-computed automatic total.
- `TripRepository::paginateForDashboard()` adds `last_point_at`, eager-loads the latest tracking event, counts GPS-disable, airplane-on, and explicit tracking-gap events per trip, and keeps active trips visible outside the selected completed-trip period.
- `TripRepository` owns the shared dashboard visibility rule used by both pagination and `dashboardCounts()`: active trips are always visible; completed trips require at least 50 stored `trip_points` unless `show_experimental=1` is requested.
- The experimental filter never deletes or updates a trip and does not apply to reports or direct trip-detail routes.
- `AdminAuthController::showTrip()` loads stop locations and ordered technical events for `Admin/TripShow.vue`.
- `Admin/Dashboard.vue` and `Admin/TripShow.vue` refresh active data every 15 seconds. This is polling over the existing Inertia/API data; there is no WebSocket service.
- The schema change is `2026_07_29_000021_add_live_tracking_telemetry.php`. See `.ai/workflows/live-tracking-flow.md` for the authoritative end-to-end and rollout contract.
