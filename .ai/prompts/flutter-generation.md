# Flutter Generation Prompt Rules

When generating Flutter code for the Fleet Management driver app, you MUST adhere to the following rules:

1. **Offline-First Architecture**:
   - The app must never block the UI waiting for a network request during a trip.
   - All trips and points must be saved to a local SQLite database (using `sqflite` or `drift`) first.
   - A background sync service or worker should attempt to upload `uploaded` trips and points.

2. **Idempotency Keys**:
   - Every trip generated locally must have a `uuid` acting as its `local_id`.
   - Every GPS point generated must have an incrementing `sequence_number` scoped to that trip.
   - These keys must be sent to the backend to ensure retries do not create duplicates.

3. **Checksum Generation**:
   - When calling the `confirm` endpoint, the Flutter app must generate a SHA256 checksum.
   - The checksum string must be a JSON array of objects: `[{"sequence_number": 1, "latitude": 31.5, "longitude": 34.4, "captured_at": "2026-05-19T08:00:00Z"}]`.
   - The types must strictly match: `sequence_number` (int), `latitude` (double), `longitude` (double), `captured_at` (ISO 8601 string).

4. **Location Tracking**:
   - Use a robust foreground service for location tracking so the OS does not kill the app when screen is off.
   - Include telemetry like `accuracy`, `speed`, and `heading` in the point payload.

5. **State Management**:
   - Ensure the driver's authentication token and user profile are persisted securely.
   - If the token expires, prompt the user for an SMS OTP again without losing their locally stored trips.
