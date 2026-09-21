# نظام ادارة آبار بلدية غزة — Project Overview
> بلدية غزة | الباك اند الموحد للآبار والأسطول والمحابس

## Core Purpose
The Fleet Management System is an enterprise application designed for Gaza Municipality to track, manage, and audit municipality vehicle trips. It consists of an administrative dashboard for monitoring and a driver-facing mobile application for trip execution.

## System Components
1. **Backend API (Laravel 13)**: The core server providing data storage, synchronization logic, and integration points.
2. **Admin Dashboard (Vue 3 + Inertia.js)**: A single-page application (SPA) embedded within the Laravel backend for administrators to review trips, manage locations, and generate reports.
3. **Driver Application (Flutter)**: A mobile application used by drivers to record trips and GPS paths, built with an Offline-First philosophy.
4. **Oracle Database**: The source of truth for backend/dashboard data, municipality assets, employees, SSO, trips, and the integrated wells and valves modules.
5. **Mobile Local Storage**: Driver/field apps may use local SQLite for offline-first capture before syncing to Oracle-backed APIs.

The Fleet admin area also monitors driver-app adoption at `/admin/driver-apps`. The page combines historical trip users with authenticated app-presence records so management can distinguish current, outdated, and not-yet-reporting installations and inspect their current Oracle vehicle assignments.

The Fleet dashboard performance card uses the same authenticated presence source for adoption-filtered operational counts: a displayed active driver must also be active in `G_STORE.BENEFICIARIES`, and a displayed active vehicle must also be active in `G_STORE.VEHICLE` and assigned to a reporting driver. Its diesel KPI sums persisted official fuel from confirmed dashboard-visible trips for the daily, weekly, monthly, or inclusive custom date range. Provisional and gap-review fuel are never included.

## Current Driver App Release And Presence Contract

- Current approved Android release: `1.1.19+42` (`versionName 1.1.19`, `versionCode 42`).
- This release keeps the non-blocking GPS-quality flow, requires **Allow all the time** location access, requests exclusion from Android battery optimization, and resumes the foreground tracker after reboot only for a retained unfinished trip. Disclosure, permissions, precise mode, and an enabled location service remain prerequisites; weak reception is filtered during tracking instead of preventing field work.
- Provider liveness is measured from the latest callback/raw sample rather than the latest quality-accepted point. Repeated weak fixes remain excluded from distance/fuel but keep the active tracker alive; only real callback inactivity may reach the 20-minute driver-decision guard.
- Presence is triggered primarily when an authenticated driver main shell opens or resumes; it must not depend on OTP login because sessions are retained locally.
- The backend endpoint is `POST /api/driver/app-presence`, and the admin screen is `/admin/driver-apps`.
- Production must deploy the endpoint and `driver_app_installations` migration before distributing the matching app. An unauthenticated production probe returning HTTP 404 proves the route is missing; a deployed protected route should reach authentication instead.

## Architectural Principles
- **Offline-First Resilience**: Drivers often operate in areas without network coverage. All driver-facing endpoints are designed to accept batches of data out-of-order and resolve them without requiring constant connectivity.
- **Idempotency**: All trip data and GPS batches use unique identifiers (`local_id` for trips, `sequence_number` for points) to prevent duplicates during intermittent network retries.
- **Bounded Oracle Load**: The unchanged 1–500 point API request is persisted through 100-row upserts under a per-trip lock. Dashboard visibility reads the server-maintained point counter and dashboard cards use aggregate queries, avoiding point-table rescans and per-sample query amplification.
- **Auditable Operations**: All administrative actions (approving, rejecting, clarifying trips) are logged in the `audit_logs` table.
- **Thin Controllers**: Controllers only handle HTTP request routing and basic validation. Complex business logic resides in Services (e.g., `TripSyncService`).
- **Repository Pattern**: All queries to the external Oracle database are strictly encapsulated within Repositories.
- **Authoritative Trip Maps**: Administrative trip maps show the recorded GPS points without road matching. Visual direction aids may be derived in the browser, but stored coordinates and calculated trip data remain unchanged.
- **Conservative Gap Guidance**: Trip details may supplement the authoritative filtered distance with a read-only straight-line estimate for internal GPS gaps that have two eligible endpoints and imply no more than 55 m/s. The dashboard labels estimated gap distance, filtered-plus-estimated distance, and fuel at 0.12 liters/km as supervisor guidance only. These values never replace persisted distance/fuel, confirmation, reports, or review decisions automatically; start/tail gaps without two endpoints remain unestimated.
- **Live Trip Visibility**: Active driver builds may idempotently upload already-persisted metadata, point batches, automatic stops, and device-state events about every 30 seconds. The Fleet dashboard refreshes active rows/details every 15 seconds and always keeps active trips visible regardless of their current point count; final checksum confirmation and deletion rules are unchanged.
- **Experimental Trip Display**: A completed trip whose exact server-maintained `TRIPS.POINTS_RECEIVED` value is below 50 is hidden from the Fleet dashboard list and trip statistics by default. `show_experimental=1` reveals these rows with an Arabic experimental badge. This is display filtering only: Oracle data, reports, direct trip details, checksums, and review history remain unchanged.
- **Technical Evidence Only**: GPS/permission changes, airplane mode, graceful shutdown/boot, tracking errors, and stale point gaps are supervisor review evidence. They do not prove driver intent, and a sudden power loss or network outage cannot be conclusively distinguished while the phone is offline.
- **Emergency Operational Switch**: Authorized permission managers can suspend the portal and every Fleet/Wells/Valves mobile API from `/permissions`. Normal routes return HTTP 503 while the recovery control plane remains available. Mobile offline records and tokens are retained and resume through unchanged endpoints after reopening. `.ai/workflows/system-operational-suspension.md` is the authoritative operating and deployment reference.
- **Live Tracking Reference**: `.ai/workflows/live-tracking-flow.md` is the authoritative cross-version flow and deployment-order reference; its current activity/quality separation and lock-screen/reboot resilience apply to driver release `1.1.19+42`.
- **Unified Dashboard Themes**: Fleet, Wells, Valves, the portal, and permissions share the semantic `--theme-*` tokens in `resources/css/app.css`. Light mode is based on Fleet and dark mode is based on Valves; individual systems retain only their identity accents. The selected `light`/`dark` value is shared through `localStorage.theme` and applied as `html[data-theme]`.
- **Audited Wells Corrections**: Authorized administrators can logically delete Wells readings and external diesel inventories. The physical Oracle row and actor/time/reason remain for audit, while default operational queries and calculations exclude it.
- **Generator-Only Wells Production**: The Wells production screen and its PDF/Excel exports calculate operating hours only from consecutive `WELL_READINGS.GENERATOR_HOURS_READING` values, then multiply the difference by `WELLS.CAPACITY`. Pump readings and flow rate are display/input data only and must never be calculation fallbacks.

## Tech Stack
- **Framework**: Laravel 13.x
- **PHP**: 8.3+
- **Database**: Oracle via `yajra/laravel-oci8` for backend/dashboard data; mobile apps use local SQLite only for offline capture.
- **Frontend Framework**: Vue 3 with Composition API
- **Frontend Integration**: Inertia.js (No Ziggy, URLs passed via Shared Props)
- **CSS Framework**: Custom CSS (No Tailwind)
- **PDF Generation**: mPDF
- **Authentication (Admin)**: MOG SSO (External SAML/OAuth via package)
- **Authentication (Mobile)**: Sanctum tokens are isolated by exact ability and account model: `driver` for Fleet, `wells` for Wells, and `scada` for Valves/SCADA. Cross-system use is rejected.
- **Authentication (Driver)**: Custom SMS OTP issuing `driver`-only Sanctum tokens.
- **Authentication (Valve Inspectors)**: Phone/password against `VALVE_INSPECTORS`, issuing `scada`-only Sanctum tokens for valves app APIs.

## Typical User Flow
1. A driver logs into the mobile app via SMS OTP.
2. The driver creates a new trip offline, starting the GPS tracker.
3. As the driver moves, locally persisted GPS points, automatic wait intervals, and technical tracking events are uploaded best effort whenever internet is available; the dashboard can display the active trace and freshness.
4. The driver ends the trip. The mobile app sends a confirmation payload with a checksum.
5. The backend verifies the checksum and marks the trip as `confirmed`.
6. Confirmation persists the trusted distance and duration, calculates official fuel when quality permits, and returns a driver-facing summary. The driver app caches the summary and shows distance, fuel, and duration per trip.
7. Confirmed server-history details request a separate lightweight route preview only when opened. Oracle reads eligible count/min/max and then fetches at most 100 indexed sequence numbers; PHP never hydrates the full route, and the normal history list remains summary-only.
8. Offline upload queues use stable client IDs. Driver and Wells requests are sent with bounded arrays, and SCADA valve retries reuse the same operation ID so a lost response cannot repeat an action or maintenance report.
9. An administrator logs into the dashboard via SSO.
10. The administrator reviews the trip path, wait markers/durations, and tracking-state evidence on a map, then approves/rejects the trip. When estimable internal GPS gaps exist, the details page also shows their conservative straight-line distance and resulting review-only distance/fuel totals. The supervisor may use this context when approving, rejecting, or requesting clarification, but the system does not persist that judgment as an official metric.

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
- Wells access may additionally grant `wells.readings.delete` and `wells.diesel-inventories.delete`; these destructive action checks fail closed and never replace the base `wells` permission.
- Access to the permission-management screen itself is limited to `MOG_PERMISSIONS_ADMIN_USER_IDS` outside local development.
- The legacy `FLEET_ADMIN_ALLOWLIST` remains only a global admin gate for entering the backend area; it is not the per-system permission source.
