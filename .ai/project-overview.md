# نظام ادارة آبار بلدية غزة — Project Overview
> بلدية غزة | الباك اند الموحد للآبار والأسطول والمحابس

## Core Purpose
The Fleet Management System is an enterprise application designed for Gaza Municipality to track, manage, and audit municipality vehicle trips. It consists of an administrative dashboard for monitoring and a driver-facing mobile application for trip execution.

## System Components
1. **Backend API (Laravel 11)**: The core server providing data storage, synchronization logic, and integration points.
2. **Admin Dashboard (Vue 3 + Inertia.js)**: A single-page application (SPA) embedded within the Laravel backend for administrators to review trips, manage locations, and generate reports.
3. **Driver Application (Flutter)**: A mobile application used by drivers to record trips and GPS paths, built with an Offline-First philosophy.
4. **Oracle Database**: The source of truth for backend/dashboard data, municipality assets, employees, SSO, trips, and the integrated wells and valves modules.
5. **Mobile Local Storage**: Driver/field apps may use local SQLite for offline-first capture before syncing to Oracle-backed APIs.

The Fleet admin area also monitors driver-app adoption at `/admin/driver-apps`. The page combines historical trip users with authenticated app-presence records so management can distinguish current, outdated, and not-yet-reporting installations and inspect their current Oracle vehicle assignments.

## Current Driver App Release And Presence Contract

- Current approved Android release: `1.0.20+21` (`versionName 1.0.20`, `versionCode 21`).
- Presence is triggered primarily when an authenticated driver main shell opens or resumes; it must not depend on OTP login because sessions are retained locally.
- The backend endpoint is `POST /api/driver/app-presence`, and the admin screen is `/admin/driver-apps`.
- Production must deploy the endpoint and `driver_app_installations` migration before distributing the matching app. An unauthenticated production probe returning HTTP 404 proves the route is missing; a deployed protected route should reach authentication instead.

## Architectural Principles
- **Offline-First Resilience**: Drivers often operate in areas without network coverage. All driver-facing endpoints are designed to accept batches of data out-of-order and resolve them without requiring constant connectivity.
- **Idempotency**: All trip data and GPS batches use unique identifiers (`local_id` for trips, `sequence_number` for points) to prevent duplicates during intermittent network retries.
- **Auditable Operations**: All administrative actions (approving, rejecting, clarifying trips) are logged in the `audit_logs` table.
- **Thin Controllers**: Controllers only handle HTTP request routing and basic validation. Complex business logic resides in Services (e.g., `TripSyncService`).
- **Repository Pattern**: All queries to the external Oracle database are strictly encapsulated within Repositories.
- **Authoritative Trip Maps**: Administrative trip maps show the recorded GPS points without road matching. Visual direction aids may be derived in the browser, but stored coordinates and calculated trip data remain unchanged.
- **Unified Dashboard Themes**: Fleet, Wells, Valves, the portal, and permissions share the semantic `--theme-*` tokens in `resources/css/app.css`. Light mode is based on Fleet and dark mode is based on Valves; individual systems retain only their identity accents. The selected `light`/`dark` value is shared through `localStorage.theme` and applied as `html[data-theme]`.

## Tech Stack
- **Framework**: Laravel 11.x
- **PHP**: 8.2
- **Database**: Oracle via `yajra/laravel-oci8` for backend/dashboard data; mobile apps use local SQLite only for offline capture.
- **Frontend Framework**: Vue 3 with Composition API
- **Frontend Integration**: Inertia.js (No Ziggy, URLs passed via Shared Props)
- **CSS Framework**: Custom CSS (No Tailwind)
- **PDF Generation**: mPDF
- **Authentication (Admin)**: MOG SSO (External SAML/OAuth via package)
- **Authentication (Driver)**: Custom SMS OTP issuing Sanctum Tokens
- **Authentication (Valve Inspectors)**: Phone/password against `VALVE_INSPECTORS`, issuing Sanctum Tokens for valves app APIs.

## Typical User Flow
1. A driver logs into the mobile app via SMS OTP.
2. The driver creates a new trip offline, starting the GPS tracker.
3. As the driver moves, GPS points are batched and uploaded to the server whenever the internet is available.
4. The driver ends the trip. The mobile app sends a confirmation payload with a checksum.
5. The backend verifies the checksum and marks the trip as `confirmed`.
6. Confirmation persists the trusted distance and duration, calculates official fuel when quality permits, and returns a driver-facing summary. The driver app caches the summary and shows distance, fuel, and duration per trip.
7. An administrator logs into the dashboard via SSO.
8. The administrator reviews the trip path on a map, checks the points, and approves/rejects the trip.

## Integrated Field Modules
- **Wells**: Has its own wells, teams, inspectors, readings, diesel inventories, and notifications context under `.ai/modules/wells/`.
- **Valves**: Has its own valves, valve teams, valve inspectors, actions, and maintenance context under `.ai/modules/valves/`. Valve teams/inspectors are intentionally separate from wells teams/inspectors. The dedicated web map defaults to OpenStreetMap and draws valve markers from `EME.WATER_VALVES` through the repository/service layers. OpenStreetMap and GeoServer are mutually exclusive modes, and the viewport plus accepted marker coordinates are constrained to Gaza so corrupt Oracle Spatial outliers cannot force a world view. The only authoritative contract for the current web map is the section `Current Authoritative Web Map Contract` in `.ai/modules/valves/project-overview.md`; older map notes are historical and must not be used to redesign or guess the integration.

## Local Development Login
- Use `http://127.0.0.1:8000/admin/dev-login` to log in locally and open the unified portal card selector.
- The dev-login route logs in the configured `MOG_LOCAL_ADMIN_USER_ID` and redirects to the portal by default. Do not document or hard-code a real person's identity for this helper.
- The local user ID cannot be overridden through a `user_id` query parameter; change `MOG_LOCAL_ADMIN_USER_ID` explicitly when a different development identity is required.
- Direct card shortcuts are supported:
  - `http://127.0.0.1:8000/admin/dev-login?system=wells` opens the wells dashboard directly.
  - `http://127.0.0.1:8000/admin/dev-login?system=fleet` opens the fleet dashboard directly.
  - `http://127.0.0.1:8000/admin/dev-login?system=valves` opens the valves dashboard directly.
- System access is controlled by `CONTROL_UNIT.SYSTEM_PERMISSIONS` for every environment, including local. If Oracle or the permission table is unavailable during setup, `SystemPermissionRepository` fails open to avoid a hard lockout.

## Portal System Permissions
- The unified portal (`/`) is the entry point for Fleet, Wells, and Valves.
- The central permission screen is `/permissions`; it is not part of the Fleet tracking dashboard.
- Each SSO user can be granted access independently to `fleet`, `wells`, and/or `valves`.
- Access to the permission-management screen itself is limited to `MOG_PERMISSIONS_ADMIN_USER_IDS` outside local development.
- The legacy `FLEET_ADMIN_ALLOWLIST` remains only a global admin gate for entering the backend area; it is not the per-system permission source.
