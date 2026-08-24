# Global Operational Suspension Workflow

This is the authoritative operational and compatibility contract for the emergency switch managed from `/permissions`.

## Purpose And Scope

- The switch is global. It suspends the unified portal and normal Fleet, Wells, and Valves web routes.
- It also suspends every backend entry point used by the Driver, Wells Inspector, and Valves Inspector mobile applications, including login, lookup, sync, upload, and action endpoints.
- The switch blocks server requests only. It cannot and must not remotely stop GNSS, Android foreground services, local SQLite persistence, or other on-device offline work.
- It is independent of per-user system permissions. Only users authorized by `MOG_PERMISSIONS_ADMIN_USER_IDS` may change it outside local development.

## Activation

1. An authorized manager opens `/permissions`.
2. The manager enters a user-facing reason of at least three characters and confirms suspension.
3. `UpdateSystemOperationalStateRequest` validates the command.
4. `SystemPermissionController` delegates the change to `SystemOperationalStateService`.
5. `SystemOperationalStateRepository` persists the single `scope = global` row in `system_operational_states`, including the actor and change time.
6. Subsequent protected requests are rejected by `RejectWhenSystemsSuspended` before authentication and business controllers execute.

The UI must never claim success when persistence fails. Operational-state updates fail closed and return a visible administrative error.

## Suspended Response Contract

Field-app APIs return HTTP `503 Service Unavailable` with:

```json
{
  "message": "سبب الإيقاف الذي أدخله المسؤول",
  "code": "systems_temporarily_suspended",
  "retryable": true
}
```

Responses also include `Retry-After: 300` and `Cache-Control: no-store`. Normal web routes return the standalone Arabic `system-suspended` view with HTTP 503.

Administrative authentication/logout infrastructure and `/permissions` are intentionally exempt. This is the recovery control plane and must remain reachable to an authorized manager.

## Mobile And Legacy Compatibility

- Never revoke Sanctum tokens, alter request payloads, reset upload offsets, or change trip/checksum identifiers as part of suspension.
- Never delete or confirm locally queued trips, points, stops, tracking events, well readings, or valve actions because of HTTP 503.
- Old clients that treat non-success/network responses as retryable continue to retain data and retry the same idempotent endpoints.
- New clients may recognize `code = systems_temporarily_suspended` and show the supplied reason, but this recognition is optional and cannot be required for safe recovery.
- After reopening, all clients resume through the unchanged routes. No app migration, new login, checksum reset, or local record recreation is required.

## Restoration

1. An authorized manager opens the still-available `/permissions` page.
2. The manager confirms restoration; a reason is not required.
3. The service updates the global row to `is_suspended = false`, clears the suspension reason, and records the actor and time.
4. Middleware immediately permits normal web and API requests again.

## Storage And Failure Semantics

- Migration: `2026_08_24_000023_create_system_operational_states_table.php`.
- Model: `SystemOperationalState` on the Oracle connection.
- Repository: `SystemOperationalStateRepository`; no controller or service may query this table directly.
- Middleware reads are cached for five seconds in Laravel's file cache and a successful manager update refreshes the entry immediately. Do not use the database cache store or issue an Oracle state query on every protected request.
- Reads fail open if Oracle or the table is unavailable, preventing a database incident from silently shutting down every system.
- Updates fail visibly if persistence is unavailable. A failed shutdown command must not be reported as active.
- The latest actor/reason/time snapshot is stored in the operational-state row; state changes are also written to application logs.

## Deployment And Verification

1. Deploy the backend code.
2. Unlock/verify the configured Oracle account, then run `php artisan migrate` before relying on the switch.
3. Deploy the rebuilt `public/build` assets and clear stale Laravel configuration and OPcache.
4. While operational, verify a protected API reaches its normal authentication layer and the portal loads.
5. Activate suspension with a test reason and verify one Driver, Wells, and Valves API route each returns the documented 503 contract before authentication.
6. Verify a normal web-system route returns the Arabic maintenance page while `/permissions` remains accessible to an authorized manager.
7. Restore service and verify the same routes resume without token or local-data changes.

Do not activate the switch in production until the migration exists and the recovery manager can reach `/permissions`.

Production verification on 2026-08-24 applied migration 23 in batch 29, activated the switch for the tracking-index maintenance window, observed the documented Driver API 503 response, then restored service. Subsequent Driver, Wells, Valves, and admin probes reached their normal authentication/validation layers.
