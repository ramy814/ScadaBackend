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
├── Models/                   # Eloquent Models (Trip, TripPoint)
├── Repositories/             # Encapsulates all Database Queries
│   └── MunicipalityVehicleRepository.php # Only place where Oracle is queried
├── Services/                 # Business Logic layer
│   ├── TripSyncService.php   # Idempotency, snapshots, point batching
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
- `FLEET_ADMIN_ALLOWLIST` is only the global admin gate; per-system access lives in `CONTROL_UNIT.SYSTEM_PERMISSIONS`.

## Valves Module Structure

The valves module uses dedicated namespaces and tables separate from wells:
- Controllers: `App\Http\Controllers\Valves\*`
- API controllers: `App\Http\Controllers\Api\Valves\*`
- Requests: `App\Http\Requests\Valves\*`
- Models: `App\Models\Valves\*`
- Repositories: `App\Repositories\Valves\*`
- Services: `App\Services\Valves\*`

Do not reuse wells `Team` or `Inspector` classes for valves features.
