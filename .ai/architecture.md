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

## 5. Security & Authentication
- **Admin**: Uses the Municipality's MOG SSO package. Authentication state is stored in the session.
- **Local Dev Login**: `/admin/dev-login` is the supported local entry point. It logs into the local admin session and redirects to the unified portal card selector. Optional `system` query shortcuts are supported: `?system=wells`, `?system=fleet`, and `?system=valves`.
- **Unified Portal**: `/` renders the system card selector after admin authentication. Card access is resolved from `SystemPermissionRepository` and `CONTROL_UNIT.SYSTEM_PERMISSIONS`.
- **Portal Permissions**: `/permissions` is the central Inertia screen for granting per-system access. It replaces the Fleet-dashboard permission screen for system access decisions. A user may be granted `fleet`, `wells`, `valves`, or any combination. Managers of this screen are controlled by `MOG_PERMISSIONS_ADMIN_USER_IDS`.
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
