# Changelog & Recent Actions

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
