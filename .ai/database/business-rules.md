# Database Business Rules

## 1. Trip Confirmation Rules
A trip is considered complete and ready for admin review only when its `status` changes from `uploaded` to `confirmed`.
This transition happens strictly when:
1. `points_received` >= `points_expected`. (The server has stored all the points the app generated).
2. OR `points_expected` is exactly `0` (This handles a rare edge case where a trip finishes immediately without tracking any GPS data).
3. AND The `client_checksum` exactly matches the `server_checksum`.

## 2. Oracle Data Encapsulation
Oracle data is volatile from the perspective of this application. A driver's assigned vehicle may change tomorrow. 
Therefore:
- We never store Foreign Keys directly pointing to Oracle tables inside the `trips` table expecting them to be permanently valid for historical reference.
- Instead, the `trips` table stores a **"Snapshot"** of the Oracle data: `oracle_driver_id`, `driver_name`, `driver_phone`, `oracle_vehicle_id`, `vehicle_plate`, `vehicle_name`.
- Any historical report must rely on the snapshot data inside the `trips` table, not by joining back to the live Oracle database.

## 3. Checksum Calculation
The checksum ensures that the ordered list of GPS points was perfectly transmitted without data corruption or missing packets.
**Rule:**
The checksum is calculated as a SHA256 hash of a JSON encoded array of points.
Each point in the array must be an object with exactly these keys in this format:
- `sequence_number` (int)
- `latitude` (float)
- `longitude` (float)
- `captured_at` (ISO 8601 string)

## 4. Virtual Fuel Calculation
The application calculates fuel usage not by physical sensors, but by estimating it based on the GPS path.
This is stored in `virtual_fuel_liters`.
It is calculated by the `VirtualFuelService` at the moment the trip is `confirmed`.

For a complete quality-v2 upload, confirmation also stores:
- `distance_km`: filtered GPS distance only.
- `duration_minutes`: trusted trip duration capped by the 12-hour policy.

The driver API exposes these persisted values after upload. If official fuel is withheld but a positive filtered distance remains usable, `DriverTripSummaryService` may expose `distance × 0.12 liters/km` with `fuel_is_provisional = true`; it must not persist that fallback as official fuel. No-valid-point trips return unavailable fuel rather than an invented zero.

The trip details page may calculate a read-only provisional value when
`virtual_fuel_liters` is null and a filtered GPS distance is available. This
fallback uses `filtered distance × 0.12 liters/km`, is labeled as an estimate
for review, and must not overwrite the stored trip value or be included in
approved report totals automatically.

## 5. Audit Immutability
Once a trip is `approved` or `rejected`, its `review_status` cannot be altered. It is locked forever.
The `audit_logs` table must never have rows updated or deleted. It is append-only.

## 6. Driver App Version Status

- The configured numeric build is authoritative for update status; human-readable version text is display-only.
- `build_number >= DRIVER_APP_LATEST_BUILD` is current, a smaller reported build is outdated, and no presence record is `not_reported`.
- A historical-trip driver with no presence record must remain visible. `not_reported` means the new telemetry has not arrived, not proof that the app is uninstalled.
- “Updated from store” requires both a current build and a recognized Android installer-store identifier. Do not infer a store update from the version number alone.
