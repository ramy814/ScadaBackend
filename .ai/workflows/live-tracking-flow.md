# Driver Live Tracking Flow

This is the authoritative backend contract for driver release `1.1.1+24`. It extends the existing offline trip protocol; it does not replace it.

## End-to-End Flow

1. After disclosure and permission/service checks, the Android app writes the trip to SQLite without waiting for a target GPS accuracy. Tracking starts immediately, each accepted point is persisted before any request, and weak fixes are retained only as throttled audit diagnostics until reception improves.
2. The active screen reads local points every ten seconds and renders the recorded trace plus confirmed automatic-stop markers. OpenStreetMap tiles are optional presentation data.
3. A stationary candidate qualifies after two minutes at `<= 1 m/s` while remaining within 20 meters. Short candidates are deleted locally; confirmed intervals receive a UUID, coordinates, and start/end times.
4. Device/GPS state observations receive UUIDs and are stored locally. Native Android receivers cover airplane mode, graceful shutdown, boot, and low battery when the Dart isolate may be stopped.
5. About every 30 seconds, the client idempotently refreshes `POST /api/driver/trips`, then uploads only new point batches. Network failure leaves SQLite authoritative.
6. The Fleet dashboard polls active trip rows/details every 15 seconds. `last_point_at` and the latest event produce a technical freshness label, while per-trip counts ensure earlier GPS-disable and airplane-on evidence remains visible on active and completed rows.
7. When the driver ends the trip, the client closes an open stop, posts final metadata/points, then performs the unchanged checksum confirmation. Only `can_delete_local: true` permits deletion.

## Wait Contract

- New clients send `manual_wait_minutes` separately from `stops`.
- The backend derives automatic whole minutes from completed stops whose `source` is `automatic`, then stores `manual + automatic` in `TRIPS.WAIT_MINUTES`.
- If `manual_wait_minutes` is absent, preserve the legacy `wait_minutes` behavior and never reduce it below a derived automatic value.

## Event Interpretation

Allowed event types are `gps_disabled`, `gps_enabled`, `airplane_mode_on`, `airplane_mode_off`, `device_shutdown`, `device_boot`, `battery_low`, `location_permission_missing`, `location_permission_restored`, `tracking_error`, `tracking_gap`, and `clock_anomaly`.

These events and stale-point windows are review evidence only. A hard power loss cannot upload at shutdown, and connectivity loss can produce the same immediate stale-point symptom. UI and reports must not claim deliberate driver action without independent review.

## Rolling Deployment Contract

1. Deploy backend code and run migration `2026_07_29_000021_add_live_tracking_telemetry.php`.
2. Deploy the rebuilt `public/build` dashboard assets and clear stale Laravel configuration/OPcache.
3. If environment variables override defaults, set `DRIVER_APP_LATEST_VERSION=1.1.1` and `DRIVER_APP_LATEST_BUILD=24`.
4. Distribute the signed Android APK only after Steps 1–3.

Old apps remain supported because all new request fields are optional and the original trip/point/confirm endpoints are unchanged. During a staged rollout, a new app can still upload the base trip contract to an older backend, but supervisor stop/event visibility requires the upgraded backend.
