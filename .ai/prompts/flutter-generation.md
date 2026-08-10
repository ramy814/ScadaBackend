# Flutter Generation Prompt Rules

When generating Flutter code for the Fleet Management driver app, you MUST adhere to the following rules:

1. **Offline-First Architecture**:
   - The app must never block the UI waiting for a network request during a trip.
   - Trips, points, automatic stops, manual wait, and tracking-state events must be saved to local SQLite first.
   - Active upload is best effort after persistence; an active trip must never be confirmed or deleted.

2. **Idempotency Keys**:
   - Every trip generated locally must have a `uuid` acting as its `local_id`.
   - Every GPS point generated must have an incrementing `sequence_number` scoped to that trip.
   - Every stop and tracking event must have a client UUID so active-trip retries update rather than duplicate rows.
   - These keys must be sent to the backend to ensure retries do not create duplicates.

3. **Checksum Generation**:
   - When calling the `confirm` endpoint, the Flutter app must generate a SHA256 checksum.
   - The checksum string must be a JSON array of objects: `[{"sequence_number": 1, "latitude": 31.5, "longitude": 34.4, "captured_at": "2026-05-19T08:00:00Z"}]`.
   - The types must strictly match: `sequence_number` (int), `latitude` (double), `longitude` (double), `captured_at` (ISO 8601 string).

4. **Location Tracking**:
   - Use a robust foreground service for location tracking so the OS does not kill the app when screen is off.
   - GNSS collection and SQLite persistence must work without internet. Treat connectivity as sync/map-tile availability only; airplane mode is a visible non-blocking warning plus audit event.
   - Request GPS about every 10 seconds. Persist finite `0..1 m/s` fixes only as periodic `stationary_heartbeat` coverage rows, and never include those rows in distance/fuel.
   - Request a bounded immediate first fix alongside the stream. After 45 seconds without callbacks, store one local `tracking_gap`, warn the driver, recreate the stream with Android LocationManager/GNSS, and give it a fresh 45-second acquisition window. Waiting for GPS must not deactivate or block the local trip.
   - Include telemetry like `accuracy`, `speed`, and `heading` in the point payload.
   - Detect automatic waiting only after two continuous stationary minutes at `<= 1 m/s` and within 20 meters; short candidates do not count.
   - Record observed GPS/permission transitions, airplane mode, graceful shutdown/boot, battery, tracking errors, and long gaps. Never describe these events as proof of deliberate tampering.

5. **Active Trip Map**:
   - The approved driver app uses a lightweight `flutter_map` OpenStreetMap view on the active-trip screen only.
   - Render original locally persisted GPS vertices plus wait markers. Do not call a route engine, snap to roads, rewrite points, or depend on tiles for start/end controls.
   - Keep route painting bounded for older Android hardware; the current implementation caps displayed vertices at 1,500.

6. **State Management**:
   - Ensure the driver's authentication token and user profile are persisted securely.
   - If the token expires, prompt the user for an SMS OTP again without losing their locally stored trips.

7. **Rolling Compatibility**:
   - Keep the existing `/api/driver/trips`, points, and confirm endpoints and their required fields.
   - New fields remain optional so `1.0.x` clients continue working. The new app must tolerate an older backend ignoring optional stops/events during a staged rollout.
