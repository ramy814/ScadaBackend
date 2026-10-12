# Architecture & Coding Rules

## 1. The Separation of Concerns
The project strictly enforces the separation of concerns:
- **Controllers**: Must be extremely thin. Their only responsibilities are injecting dependencies, validating input (via FormRequests), calling a Service, and returning an API Resource or an Inertia response.
- **Services**: Contain all the business logic. For example, `TripSyncService` handles the complex logic of assembling trip snapshots, falling back on database columns, ensuring idempotency, and calculating checksums.
- **Repositories**: Encapsulate all database queries, especially those targeting the external Oracle database. Controllers and Services must *never* execute raw queries or `DB::table('oracle_table')` calls directly.
- **Models**: Handle relationships, casting, and accessors. For example, `Trip.php` uses `$appends = ['driver', 'vehicle']` to dynamically append snapshot data to JSON responses.

## 2. Offline-First Design
The Flutter mobile application is designed to function entirely offline during a trip. The backend APIs must support this paradigm:
- **Idempotency Keys**: Trips use `local_id`, while points use the unique `(trip_id, sequence_number)` key through batch upsert. This allows the mobile app to safely retry requests if a network connection drops mid-flight without restoring per-row `updateOrCreate` traffic.
- **Eventual Consistency**: Trips are created in an `uploaded` state. They are only marked as `confirmed` once the mobile app sends a final payload with a `client_checksum` that matches the server's calculated `server_checksum`.
- **Active Uploads**: Repeated `POST /api/driver/trips` calls refresh the same `local_id`; point, stop, and telemetry identifiers remain idempotent. An active trip may be visible in the dashboard but cannot be confirmed until it has ended and the full checksum arrives.
- **Bounded Point Writes**: Point requests retain the public 500-point contract, while `TripSyncService` performs Oracle `MERGE`/upsert work in 100-row chunks under a per-trip lock. Database work scales by chunks, not by two queries for every GPS sample.
- **Snapshot Data**: Because drivers and vehicles might change in the Oracle database over time, the `trips` table stores a complete snapshot of the driver and vehicle details at the exact moment the trip was initiated.

## 3. Administrative Review Constraints
Once a trip is `confirmed` by the system, it enters the administrative review pipeline.
- The default state is `pending`.
- Administrators can transition the state to `approved`, `rejected`, or `needs_clarification`.
- **Immutability Rule**: Once a trip reaches `approved` or `rejected`, its `review_status` cannot be changed. This is an audit compliance requirement.
- **Audit Trails**: Every state change MUST be recorded in the `audit_logs` table, including the name of the administrator and their notes.
- **Manual Entry**: An assistant-created trip is already complete, so it is stored as `confirmed` with `review_status = pending`, no GPS points/checksum, `entry_source = manual`, second-precision timestamps, and an explicitly estimated `distance_km`. Its vehicle must be reachable through the assistant's station scope with `USER_STATION.ACCESS_TYPE >= 3` and an active `VEHICLE_STAGES` link (`REMOVED_USER IS NULL`); the same repository scope is applied when loading options and before create/update. It may be approved or rejected, but never sent to driver clarification because it has no driver-app interaction. Its creator can list and filter only their own manual entries and edit only while `review_status = pending`; approved/rejected rows remain immutable. Supervisors access these rows through the dashboard's separate `trip_source=manual` tab; the default `mobile` tab excludes them.
- **Supervisor Vehicle Scope**: Base `fleet` permission opens the module, while four dependent action permissions map to live `G_STORE.VEHICLE.FOLLOW_TO_OID` values: `125`, `126`, `132`, and `133`. `FleetVehicleAccessService` resolves the permitted vehicle IDs for dashboard lists/KPIs; `EnsureFleetTripAccess` independently checks the current vehicle category for detail, approval, rejection, and clarification routes. Empty or unavailable fine-grained scope denies data and actions.

## 4. Frontend Architecture (Admin Dashboard)
- **Framework**: Vue 3 + Inertia.js.
- **Routing**: The project *does not* use Ziggy. Instead, all necessary backend URLs are injected globally into the Inertia shared props via `HandleInertiaRequests.php` (`$page.props.urls`). Vue components must use these URLs for navigation and API calls.
- **State**: The `usePage()` hook is the primary way to access global state (like the authenticated user or flash messages) in the frontend.
- **Trip Map Source of Truth**: `TripShow.vue` renders only the original ordered `trip_points`. Do not introduce road matching, route snapping, OSRM, Docker, or an external routing API unless the product owner explicitly reverses this decision.
- **Map Interpretation**: The map may show GPS segments crossing buildings because it visualizes recorded coordinates directly. Never move or overwrite authoritative GPS points merely to make the route look aligned with streets.
- **Gap Estimate Boundary**: `VirtualFuelService` may calculate a dashboard-only conservative estimate for an internal gap by measuring the direct Haversine distance between its two eligible endpoints. The estimate is usable only when its implied speed is at most 55 m/s. Do not use road matching, routing, a distance multiplier, or rejected/raw samples to invent the missing path. Do not write the estimate to `TRIPS.DISTANCE_KM` or `TRIPS.VIRTUAL_FUEL_LITERS`.
- **Lightweight Driver History Maps**: The confirmed-trip list remains summary-only. `GET /api/driver/trips/{trip}/route-preview` authorizes ownership and returns at most 100 accurate ordered coordinates through `DriverTripRoutePreviewService` and `TripRepository`. Sampling is performed by two bounded Oracle queries: eligible count/min/max followed by indexed sequence-number lookup; never restore full-route hydration followed by PHP downsampling. This preview is never used for distance, fuel, checksum, or stored route mutation.
- **Live Monitoring**: Dashboard and active `TripShow` Inertia props may reload every 15 seconds. `last_point_at` plus the latest tracking event drive a technical freshness badge; do not label it as proven deliberate tampering. Active trips remain visible even before reaching 50 points. After a trip ends, fewer than 50 stored points classifies it as experimental dashboard display data, hidden by default and available through `show_experimental=1`.
- **Dashboard Query Boundary**: Experimental visibility uses the exact server-maintained `TRIPS.POINTS_RECEIVED` counter instead of repeating correlated counts over `TRIP_POINTS`. Dashboard status KPIs use one conditional aggregate query, and period fuel uses one aggregate query.
- **Fleet KPI Sources**: The dashboard active-driver and active-vehicle KPIs are app-adoption intersections, not raw Oracle totals. Start with distinct `DRIVER_APP_INSTALLATIONS.ORACLE_DRIVER_ID` values, then require the matching `G_STORE.BENEFICIARIES.IS_ACTIVE = 1` driver or assigned `G_STORE.VEHICLE.IS_ACTIVE = 1` vehicle through their repositories. Official period fuel is the sum of non-null `TRIPS.VIRTUAL_FUEL_LITERS` on confirmed, dashboard-visible trips whose `STARTED_AT` is inside the selected range; never include provisional gap fuel.
- **Wells Production Source**: `ReadingService::getProductionReport()` is the single calculation owner for the on-screen Wells production preview and both exports. It derives current/previous readings exclusively from `GENERATOR_HOURS_READING`; never fall back to `PUMP_HOURS_READING` or `FLOW_RATE`, and never recalculate production in Vue or Blade.
- **Valves Tile Transport**: The dedicated Valves GeoServer mode loads `mog:gaza general map` as its opaque default background through direct `EPSG:900913` WMTS tiles at same-origin `/wms.php?g_page=/wmts`. Published `mog:*` detail layers remain cached WMS overlays through `/wms.php?g_page=/wms`. `public/wms.php` owns both authenticated GeoWebCache upstream connections; Vue must not restore a direct browser GeoServer host. Oracle-backed valve markers and the team selector use OpenStreetMap because the new GeoServer does not publish water-valve or water-pipe layers.
- **Hiraf Offline Map Boundary**: The external `hiraf_inspector` app stores downloaded raster tiles in Android application documents and resolves local files before network tiles. This backend exposes only `api/map/tiles/{z}/{x}/{y}.png`, validates the existing Hiraf token against the account-follow-up profile API, and proxies allowlisted Gaza WMTS coordinates. The endpoint is not public and GeoServer credentials never reach Flutter.

## 5. Security & Authentication
- **Admin**: Uses the Municipality's MOG SSO package. Authentication state is stored in the session.
- **Local Dev Login**: `/admin/dev-login` is the supported local entry point. It always logs in the configured `MOG_LOCAL_ADMIN_USER_ID`; request parameters cannot override the user. Optional `system` query shortcuts are supported: `?system=wells`, `?system=fleet`, and `?system=valves`.
- **Local Session Identity**: `EnsureAdminIsAllowlisted` replaces stale authenticated local admin sessions with the configured `MOG_LOCAL_ADMIN_USER_ID` before portal permissions are resolved. This prevents an old session ID from showing locked system cards after the development identity changes.
- **Unified Portal**: `/` renders the system card selector after admin authentication. Card access is resolved from `SystemPermissionRepository` and `CONTROL_UNIT.SYSTEM_PERMISSIONS`.
- **Portal Permissions**: `/permissions` is the central Inertia screen for granting per-system access. It replaces the Fleet-dashboard permission screen for system access decisions. A user may be granted `fleet`, `wells`, `valves`, or any combination. Managers of this screen are controlled by `MOG_PERMISSIONS_ADMIN_USER_IDS`.
- **Manual Trip Permission**: `fleet.trips.create` is a standalone, fail-closed action permission. It admits the user through the global admin gate only to the dedicated manual-trip page; it does not imply the base `fleet` permission. Both form loading and saving must reapply the station-scoped Oracle vehicle query for the authenticated SSO ID, including `VEHICLE_STAGES.REMOVED_USER IS NULL` so removed assignments never grant access.
- **Permission User Search**: New permission assignments may only search active SSO users from `MOG_USERS.USERS_LIST` where `IS_ACTIVE = 1`. Existing assignments remain visible so administrators can revoke stale access.
- **Global Admin Gate**: `FLEET_ADMIN_ALLOWLIST` is still a broad allowlist for entering protected admin routes, but it must not be treated as the per-system permission model.
- **Mobile separation**: Sanctum issues one exact ability per app: `driver`, `wells`, or `scada`. Protected mobile route groups require that ability and `EnsureMobilePrincipal` verifies the authenticated model belongs to the same subsystem, so wildcard or cross-system tokens cannot cross boundaries.
- **Driver**: Uses a custom SMS OTP flow. The driver requests a code, which is saved in `sms_login_codes`. Upon verification, Laravel Sanctum issues a persistent API token with the `driver` ability.
- **Bounded offline sync**: Public mobile arrays have explicit ceilings. Wells parent queues enter `MobileSyncService` in 50-record transactions with set-based idempotency lookups; Driver points retain 100-row Oracle upserts; SCADA action side effects are transactionally deduplicated by the persisted client operation ID.

## 6. Naming Conventions
- **Database Tables**: Plural snake_case (`trip_points`, `audit_logs`).
- **Models**: Singular StudlyCase (`TripPoint`, `AuditLog`).
- **Controllers**: StudlyCase with `Controller` suffix (`TripSyncController`).
- **Services**: StudlyCase with `Service` suffix (`TripSyncService`).
- **Repositories**: StudlyCase with `Repository` suffix (`MunicipalityVehicleRepository`).
- **Vue Components**: StudlyCase (`TripShow.vue`, `Dashboard.vue`).
- **API Endpoints**: Kebab-case plural (`/api/mission-types`).

## 7. Driver App Usage Monitoring

- `DriverAppPresenceController` validates and delegates authenticated telemetry to `DriverAppUsageService`.
- `DriverAppInstallationRepository` owns queries for `driver_app_installations`.
- `TripRepository::latestUsageByDriver()` supplies legacy users who have trips but no new telemetry.
- `MunicipalityVehicleRepository::findManyByDriverOracleIds()` supplies live Oracle vehicle assignments in one query.
- `DriverAppUsageController` renders `Admin/DriverApps`; it must not duplicate classification or merge logic.

## 8. Global Operational Control

- Permission managers control one global emergency switch from `/permissions`; it is separate from per-user system permissions.
- `SystemOperationalStateRepository` is the only database layer for `SYSTEM_OPERATIONAL_STATES`. `SystemOperationalStateService` owns state changes and audit logging. `RejectWhenSystemsSuspended` enforces the state before normal web/mobile controllers.
- Operational-state reads use a five-second file-cache entry, refreshed immediately after a successful manager update. This prevents one extra Oracle query on every web/mobile request while bounding cross-worker staleness.
- Suspended mobile routes return retryable HTTP 503 JSON. Suspended Fleet, Wells, Valves, and portal routes return the standalone Arabic maintenance view.
- `/permissions` plus administrative authentication/logout infrastructure remain exempt so an authorized manager cannot lock out the recovery control plane.
- State reads fail open when Oracle/the table is unavailable; update attempts fail with a visible error. This prevents an infrastructure fault from silently becoming a global shutdown.
- This control blocks server access only. It never remotely deletes mobile SQLite data, disables device GNSS, or bypasses offline-first retention; queued records upload normally after restoration.
- See `.ai/workflows/system-operational-suspension.md` for the authoritative coverage, recovery, deployment, and legacy-client contract.
