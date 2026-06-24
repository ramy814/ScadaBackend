# Changelog & Recent Actions

## [2026-06-24] CONTROL_UNIT Production Schema Switch

- Production Oracle connection moved to the `CONTROL_UNIT` schema on `gisdb`.
- Laravel migrations were verified on `CONTROL_UNIT`; the pending `FLEET_ADMIN_ALLOWLIST` migration was run successfully.
- Direct hard-coded old-schema references were removed from active Models and Repositories.
- Oracle source table/column names are now configured through `config/fleet.php` and `.env` keys.
- Driver and assigned vehicle lookup now use `FLEET_USERS` in `CONTROL_UNIT`:
  - Driver: `ID`, `USER_MOBILE`, `USER_NAME`, `USER_ID`.
  - Assigned vehicle: `VEHICLE_ID`, `VEHICLE_NO`, `VEHICLE_MODEL`, `VEHICLE_TYPE`.
- `MOG_VEHICLES` exists as a general vehicle reference table, but driver-scoped vehicle lookup must continue to use `FLEET_USERS` unless a verified assignment relationship is added.
- Added `php artisan oracle:copy-schema-data {source_schema}` for Oracle-to-Oracle managed-data migration.
  - Default mode is dry-run and shows source readability, source counts, target counts, and shared columns.
  - Use `--execute` to copy rows and `--replace` to clear `CONTROL_UNIT` target rows before copying.
  - The command requires `CONTROL_UNIT` to have `SELECT` grants on the old source schema tables.

## [2026-06-22] Original GPS Route Readability

- Final deployment decision: production runs on Windows and must not require Docker or additional map-matching software.
- The admin trip map displays only the authoritative original `trip_points`; no road-matching service or external routing API is required.
- Removed the original/corrected route toggle and all OSRM configuration and server calls.
- Added clear green start and red end markers with permanent Arabic labels.
- Added a white casing around the GPS trace so it remains readable over streets, buildings, and map labels.
- Repeating direction arrows show chronological movement.
- Return legs are detected conservatively and colored orange when the trip comes materially back toward its start; the outbound leg remains blue.
- Checksums, distance, virtual fuel calculations, and audit data remain unchanged.
- Future work must not reintroduce route snapping or transmit municipal vehicle traces to a third party without explicit product-owner approval.

## [2026-05-24] SSO Logout Fix & Error Logs Button Integration

### 1. Unified SSO Logout Route Usage
- **Context**: The driver app and admin dashboard use MOG SSO for authentication.
- **Requirement**: Use the library's routes directly without any external overrides in the main application's `routes/web.php` or `AdminAuthController.php`.
- **Status**: Reverted the custom logout override route and method to ensure the application delegates directly to the package's `Mog\ProjectHelper\AuthHelpers@logout` controller/action via the registered library routes.

### 2. Guard Static Analysis & IDE Error Resolution
- **Context**: Calls like `Auth::guard('admin')->logout()` and `Auth::guard('admin')->loginUsingId($id)` triggered static analysis warnings/errors in PHPStan (`Call to undefined method Illuminate\Contracts\Auth\Guard::...()`) and IDE squiggly lines because the base `Guard` contract does not define these stateful methods.
- **Solution**:
  - Implemented safe type checking against `\Illuminate\Contracts\Auth\StatefulGuard` before invoking `logout()` and `loginUsingId()` in `routes/web.php` and `AdminAuthController.php`. This eliminated IDE warnings and resolved static analysis compiler errors.

### 3. Server Logs Dashboard Integration
- **Context**: Integrated a server log inspection dashboard available on production/local development via `/mog/logs` routes from the helper package.
- **Actions**:
  - Updated `app/Http/Middleware/HandleInertiaRequests.php` to safely resolve the `mog.logs.index` route within a `try-catch` block, preventing crash loops during route caching/boot-time commands if the helper package's routes are not loaded.
  - Dynamically shared `auth.can_view_logs` status to only allow authorized user IDs (configured in `mog-helpers.logs_allowed_user_ids`).
  - Added a responsive "Error Log (سجل الأخطاء)" button to the dashboard layout (`Dashboard.vue`) to allow authorized administrators to inspect production log traces directly.
