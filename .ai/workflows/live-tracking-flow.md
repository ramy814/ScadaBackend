# Driver Live Tracking Flow

This is the authoritative backend contract for driver release `1.1.16+39`. It extends the existing offline trip protocol; it does not replace it.

## End-to-End Flow

1. After disclosure and permission/service checks, the Android app writes the trip to SQLite without waiting for a target GPS accuracy. The main isolate sends an unconditional foreground-service start plus `startTracking` and requires a background health acknowledgement, retrying once after eight silent seconds. The acknowledged service opens the 10-second stream and makes one bounded immediate first-fix request. After 45 seconds without a callback, the watchdog stores one `tracking_gap`, warns the driver, recreates the stream through Android LocationManager/GNSS, retries the immediate request, and gives the restarted stream a fresh 45-second acquisition window. Meaningful movement is persisted before any request; finite speeds from 0 through 1 m/s are compressed into periodic `stationary_heartbeat` rows that provide coverage but never distance/fuel. The open-trip guard measures liveness from `tracking_last_sample_at` or the latest raw persisted point, not the latest accepted-quality point: weak callbacks continue the trip while remaining excluded from distance/fuel. A 20-minute driver decision is reserved for actual tracker inactivity.
2. The active screen reads local points every ten seconds and renders the recorded trace plus confirmed automatic-stop markers. OpenStreetMap tiles are optional presentation data.
3. A stationary candidate qualifies after two minutes at `<= 1 m/s` while remaining within 20 meters. Short candidates are deleted locally; confirmed intervals receive a UUID, coordinates, and start/end times.
4. Device/GPS state observations receive UUIDs and are stored locally. The active foreground tracker subscribes to location-service changes for immediate GPS enabled/disabled events, retains its periodic watchdog, and performs a final disabled-state audit before trip closure. Native Android receivers cover airplane mode, graceful shutdown, boot, and low battery when the Dart isolate may be stopped.
   - Airplane mode is received through a process-wide context registration rather than a manifest-only implicit receiver. A mode already enabled at creation is inserted atomically with the local trip. The app warns the driver without blocking the trip; GNSS/SQLite continue and the event/trip upload when connectivity returns.
5. About every 30 seconds, the client idempotently refreshes `POST /api/driver/trips`, then uploads only new point batches. The public batch remains at most 500 points for every released client. The backend serializes only that trip, applies 100-row upserts, counts once, and never runs `updateOrCreate` per point. Network failure leaves SQLite authoritative.
6. The Fleet dashboard polls active trip rows/details every 15 seconds. `last_point_at` and the latest event produce a technical freshness label, while per-trip counts ensure earlier GPS-disable, airplane-on, and explicit tracking-gap evidence remains visible on active and completed rows. Active trips stay visible regardless of their current point count. Once ended, server-owned `POINTS_RECEIVED < 50` marks the trip experimental and hides it from the default dashboard list/statistics; `show_experimental=1` includes it.
   - Trip details keep the filtered route split at every interval over 60 seconds. For each internal gap with eligible coordinates on both sides, `VirtualFuelService` calculates the direct Haversine distance and accepts it as review guidance only when `distance / gap seconds <= 55 m/s`.
   - The page displays the summed estimable gap distance, filtered distance plus that sum, fuel at `0.12 liters/km`, and estimable/unestimable gap counts. A start gap, tail gap, missing endpoint, or implausible implied speed remains unestimated.
   - Gap estimates are not road routes and are never persisted, included in official driver summaries/reports, or substituted for confirmed `DISTANCE_KM`/`VIRTUAL_FUEL_LITERS`. The existing approve/reject/clarify workflow remains the supervisor decision mechanism.
7. When the driver ends the trip, the client closes an open stop, posts final metadata/points, then performs the unchanged checksum confirmation. Only `can_delete_local: true` permits deletion.

## Wait Contract

- New clients send `manual_wait_minutes` separately from `stops`.
- The backend derives automatic whole minutes from completed stops whose `source` is `automatic`, then stores `manual + automatic` in `TRIPS.WAIT_MINUTES`.
- If `manual_wait_minutes` is absent, preserve the legacy `wait_minutes` behavior and never reduce it below a derived automatic value.

## Event Interpretation

Allowed event types are `gps_disabled`, `gps_enabled`, `airplane_mode_on`, `airplane_mode_off`, `device_shutdown`, `device_boot`, `battery_low`, `location_permission_missing`, `location_permission_restored`, `tracking_error`, `tracking_gap`, and `clock_anomaly`.

These events and stale-point windows are review evidence only. A hard power loss cannot upload at shutdown, and connectivity loss can produce the same immediate stale-point symptom. UI and reports must not claim deliberate driver action without independent review.

## Rolling Deployment Contract

1. Deploy backend code and run migrations `2026_07_29_000021_add_live_tracking_telemetry.php` and `2026_08_24_000024_add_trip_tracking_performance_indexes.php`. Create the new indexes in a DBA-approved maintenance window if `TRIP_POINTS` is large.
2. Deploy the rebuilt `public/build` dashboard assets and clear stale Laravel configuration/OPcache.
3. If environment variables override defaults, set `DRIVER_APP_LATEST_VERSION=1.1.16` and `DRIVER_APP_LATEST_BUILD=39`.
4. Distribute the signed Android APK only after Steps 1–3.

Old apps remain supported because all new request fields are optional and the original trip/point/confirm endpoints are unchanged. During a staged rollout, a new app can still upload the base trip contract to an older backend, but supervisor stop/event visibility requires the upgraded backend.

## Oracle Load Incident And Recovery

- On 2026-08-24 the local environment first recorded Oracle TCP timeouts (`ORA-12170`) and then repeated `ORA-28000` for the configured `CONTROL_UNIT` account. While locked, the account prevents SSO user hydration before `AdminAuthController::dashboard()` executes, so the Fleet page cannot open even when its route and Vue bundle are valid.
- `ORA-28000` requires a DBA to unlock the account and verify the deployed password/profile; application code cannot safely bypass Oracle authentication.
- A later same-day recheck connected successfully. The live dataset contained 1,698 trips, 2,784,917 points, 590 tracking events, and 26 open trips. Trip `850` alone reported 80,500 received points and 135,216 expected points, demonstrating why per-point select/write amplification was unsafe.
- With the optimized repository/service code and before applying the performance-index migration, local SSO login redirected normally and `/admin/dashboard` returned HTTP 200 with a 58 KB Inertia page in about 0.95 seconds.
- On 2026-08-24 commit `0c7622b` and its Vite assets were verified on production. Migrations 23 and 24 ran in batches 29 and 30 during a temporary global suspension; all six tracking indexes were `VALID` before reopening services.
- Post-deployment probes verified `/admin/dashboard` returns the expected SSO 302, unauthenticated `/api/driver/trips` returns 401, and empty Wells/Valves login requests return validation 422. Continue observing Oracle sessions/CPU under real mobile upload load; transport probes do not replace authenticated upload monitoring.
- Do not clear local trips or change mobile endpoints during recovery. Released apps resume from `server_id` and `points_uploaded` through the same idempotent contract.

## Secure Transport Outage Contract

- A TLS/DNS/socket failure with no HTTP response occurs before Laravel validation and must not be diagnosed as an incompatible trip payload.
- The client keeps the local trip retryable and preserves any saved server ID or uploaded-point offset. It must never delete or weaken integrity checks after a transport failure.
- Certificate renewal on `scada.mogaza.org` is the cross-version recovery path because all released clients use the same HTTPS host and unchanged idempotent endpoints. Never ship certificate-verification bypasses or an HTTP fallback.
- After renewing the certificate, verify it normally (without `-k`/insecure mode), verify an unauthenticated trip request reaches Sanctum with HTTP 401, then allow the existing client retry loop to upload retained trips.

## Planned Administrative Suspension Contract

- A deliberate global suspension is distinct from a TLS outage: the server responds with HTTP 503, `code = systems_temporarily_suspended`, `retryable = true`, and `Retry-After: 300` before driver authentication or trip controllers.
- Active/finished trips, points, stops, events, offsets, checksums, and tokens remain local and unchanged. Old and new clients continue their established retry/offline behavior.
- Reopening from `/permissions` restores the unchanged trip endpoints; no client migration, checksum reset, or local trip recreation is allowed.
