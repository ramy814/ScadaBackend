# Architecture & Coding Rules

## 1. The Separation of Concerns
The project strictly enforces the separation of concerns:
- **Controllers**: Must be extremely thin. Their only responsibilities are injecting dependencies, validating input (via FormRequests), calling a Service, and returning an API Resource or an Inertia response.
- **Services**: Contain all the business logic. For example, `TripSyncService` handles the complex logic of assembling trip snapshots, falling back on database columns, ensuring idempotency, and calculating checksums.
- **Repositories**: Encapsulate all database queries, especially those targeting the external Oracle database. Controllers and Services must *never* execute raw queries or `DB::table('oracle_table')` calls directly.
- **Models**: Handle relationships, casting, and accessors. For example, `Trip.php` uses `$appends = ['driver', 'vehicle']` to dynamically append snapshot data to JSON responses.

## 2. Offline-First Design
The Flutter mobile application is designed to function entirely offline during a trip. The backend APIs must support this paradigm:
- **Idempotency Keys**: Both `trips` (`local_id`) and `trip_points` (`sequence_number`) use client-generated identifiers as unique constraints (`updateOrCreate`). This allows the mobile app to safely retry requests if a network connection drops mid-flight.
- **Eventual Consistency**: Trips are created in an `uploaded` state. They are only marked as `confirmed` once the mobile app sends a final payload with a `client_checksum` that matches the server's calculated `server_checksum`.
- **Active Uploads**: Repeated `POST /api/driver/trips` calls refresh the same `local_id`; point, stop, and telemetry identifiers remain idempotent. An active trip may be visible in the dashboard but cannot be confirmed until it has ended and the full checksum arrives.
- **Snapshot Data**: Because drivers and vehicles might change in the Oracle database over time, the `trips` table stores a complete snapshot of the driver and vehicle details at the exact moment the trip was initiated.

## 3. Administrative Review Constraints
Once a trip is `confirmed` by the system, it enters the administrative review pipeline.
- The default state is `pending`.
- Administrators can transition the state to `approved`, `rejected`, or `needs_clarification`.
- **Immutability Rule**: Once a trip reaches `approved` or `rejected`, its `review_status` cannot be changed. This is an audit compliance requirement.
- **Audit Trails**: Every state change MUST be recorded in the `audit_logs` table, including the name of the administrator and their notes.

## 4. Frontend Architecture (Admin Dashboard)
- **Framework**: Vue 3 + Inertia.js.
- **Routing**: The project *does not* use Ziggy. Instead, all necessary backend URLs are injected globally into the Inertia shared props via `HandleInertiaRequests.php` (`$page.props.urls`). Vue components must use these URLs for navigation and API calls.
- **State**: The `usePage()` hook is the primary way to access global state (like the authenticated user or flash messages) in the frontend.
- **Trip Map Source of Truth**: `TripShow.vue` renders only the original ordered `trip_points`. Do not introduce road matching, route snapping, OSRM, Docker, or an external routing API unless the product owner explicitly reverses this decision.
- **Map Interpretation**: The map may show GPS segments crossing buildings because it visualizes recorded coordinates directly. Never move or overwrite authoritative GPS points merely to make the route look aligned with streets.
- **Lightweight Driver History Maps**: The confirmed-trip list remains summary-only. `GET /api/driver/trips/{trip}/route-preview` authorizes ownership and returns at most 100 accurate ordered coordinates through `DriverTripRoutePreviewService` and `TripRepository`; this preview is never used for distance, fuel, checksum, or stored route mutation.
- **Live Monitoring**: Dashboard and active `TripShow` Inertia props may reload every 15 seconds. `last_point_at` plus the latest tracking event drive a technical freshness badge; do not label it as proven deliberate tampering. Active trips remain visible even before reaching 50 points. After a trip ends, fewer than 50 stored points classifies it as experimental dashboard display data, hidden by default and available through `show_experimental=1`.

## 5. Security & Authentication
- **Admin**: Uses the Municipality's MOG SSO package. Authentication state is stored in the session.
- **Local Dev Login**: `/admin/dev-login` is the supported local entry point. It always logs in the configured `MOG_LOCAL_ADMIN_USER_ID`; request parameters cannot override the user. Optional `system` query shortcuts are supported: `?system=wells`, `?system=fleet`, and `?system=valves`.
- **Local Session Identity**: `EnsureAdminIsAllowlisted` replaces stale authenticated local admin sessions with the configured `MOG_LOCAL_ADMIN_USER_ID` before portal permissions are resolved. This prevents an old session ID from showing locked system cards after the development identity changes.
- **Unified Portal**: `/` renders the system card selector after admin authentication. Card access is resolved from `SystemPermissionRepository` and `CONTROL_UNIT.SYSTEM_PERMISSIONS`.
- **Portal Permissions**: `/permissions` is the central Inertia screen for granting per-system access. It replaces the Fleet-dashboard permission screen for system access decisions. A user may be granted `fleet`, `wells`, `valves`, or any combination. Managers of this screen are controlled by `MOG_PERMISSIONS_ADMIN_USER_IDS`.
- **Permission User Search**: New permission assignments may only search active SSO users from `MOG_USERS.USERS_LIST` where `IS_ACTIVE = 1`. Existing assignments remain visible so administrators can revoke stale access.
- **Global Admin Gate**: `FLEET_ADMIN_ALLOWLIST` is still a broad allowlist for entering protected admin routes, but it must not be treated as the per-system permission model.
- **Driver**: Uses a custom SMS OTP flow. The driver requests a code, which is saved in `sms_login_codes`. Upon verification, Laravel Sanctum issues a persistent API token.

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
