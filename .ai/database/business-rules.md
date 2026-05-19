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

## 5. Audit Immutability
Once a trip is `approved` or `rejected`, its `review_status` cannot be altered. It is locked forever.
The `audit_logs` table must never have rows updated or deleted. It is append-only.
