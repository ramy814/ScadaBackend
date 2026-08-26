# Database Business Rules

## 1. Trip Confirmation Rules
A trip is considered complete and ready for admin review only when its `status` changes from `uploaded` to `confirmed`.
This transition happens strictly when:
1. `points_received` >= `points_expected`. (The server has stored all the points the app generated).
2. OR `points_expected` is exactly `0` (This handles a rare edge case where a trip finishes immediately without tracking any GPS data).
3. AND The `client_checksum` exactly matches the `server_checksum`.

Point ingestion preserves this integrity contract while using batch upserts. `POINTS_RECEIVED` is refreshed from the actual unique stored-row count after each accepted batch and again during confirmation; it is never trusted from a mobile claim.

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

The driver API exposes these persisted values after upload. Driver history and reconciliation never rescan `TRIP_POINTS`; missing legacy summary columns remain unavailable until an explicit offline backfill. If official fuel is withheld but a positive persisted distance remains usable, `DriverTripSummaryService` may expose `distance × 0.12 liters/km` with `fuel_is_provisional = true`; it must not persist that fallback as official fuel. No-valid-point trips return unavailable fuel rather than an invented zero.

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
- `not_reported` is not proof that the driver failed to update: first confirm the production presence endpoint is deployed. If `/api/driver/app-presence` returns 404, every installation will remain unreported regardless of its local version.

## 6.1 Global Operational Suspension

- Only configured permission managers may change the global state, and enabling suspension requires a reason.
- Suspension applies before authentication/business controllers to portal, Fleet, Wells, Valves, and all mobile API services. APIs return retryable HTTP 503; web systems return the Arabic maintenance page.
- `/permissions` and administrative authentication/logout remain available so service can be restored without database manipulation.
- Suspension must not revoke tokens, mutate trips/readings/actions, confirm uploads, or delete mobile data. Offline queues resume normally after restoration.
- Missing/unavailable operational-state storage reads as operational. A failed update must be shown to the manager and must not pretend that shutdown succeeded.
- The operational-state middleware must use the repository's five-second non-database cache. A successful manager change refreshes that cache immediately; do not query Oracle once per protected request.

## 6.2 Mobile Token Isolation

- Issue exactly one Sanctum ability per mobile identity: `driver`, `wells`, or `scada`. Never use the default wildcard ability for newly issued mobile tokens.
- Every protected mobile route must enforce both its ability and the corresponding principal model. Ability checks alone are insufficient because legacy wildcard tokens can satisfy every Sanctum ability.
- Narrow existing recognized tokens in place during deployment; do not revoke them or change plaintext tokens, because older offline-first app versions must continue uploading without a forced login.

## 7. Automatic Wait And Live Tracking Evidence

- For clients that send `manual_wait_minutes`, `TRIPS.WAIT_MINUTES` is recalculated as manual minutes plus whole minutes from completed `trip_stops` whose source is `automatic`.
- Clients that omit `manual_wait_minutes` keep the legacy rule: preserve the greater of submitted `wait_minutes` and computed automatic duration. This is required for rolling deployment compatibility.
- Stop and telemetry UUIDs are idempotency keys. Repeated active-trip uploads must update existing rows.
- Every mobile sync request is bounded before entering a transaction. Driver points are limited to 500 per request and 100 per Oracle upsert; trip passengers/stops/events are bounded and reject duplicate client identities. Wells parent records are limited to 500 per request and 50 per transaction.
- Wells and SCADA retries must reuse the persisted client operation ID. A repeated ID acknowledges the existing row and must not repeat side effects. Legacy clients that omit the newly optional SCADA `local_id` continue to work, but only upgraded clients receive retry deduplication.
- Dashboard freshness combines `last_point_at` with the latest device-state event. Every dashboard trip row also receives separate server-side counts for `gps_disabled` and `airplane_mode_on`, including completed trips, so recovery/end events cannot hide earlier interruptions. These counts require review but cannot prove deliberate action or distinguish every offline/power scenario.
- `stationary_heartbeat` points prove recent GPS coverage but are excluded from filtered distance and fuel. Finite client speeds from 0 through 1 m/s are treated as stationary by driver build 26 and later, preventing low-speed coordinate drift from becoming vehicle movement.
- Any interval over 60 seconds splits the filtered route before `stationary_heartbeat` handling. The dashboard must retain a gap connector even when the first post-gap point is stationary, and the connector never contributes to distance or fuel. Dashboard trip rows count explicit `tracking_gap` events alongside GPS-disable and airplane-mode events.
- Trip details may show a read-only conservative gap estimate: the straight-line distance between the last eligible point before an internal gap and the first eligible point after it. Reject an estimate whose implied speed exceeds the existing 55 m/s guardrail. Show estimated gap distance, filtered distance plus the estimate, and fuel at 0.12 liters/km as supervisor-review information only; never persist or substitute these values for official distance/fuel automatically. Start/tail gaps without two coordinate endpoints remain explicitly unestimated.

## 8. Fleet Dashboard Experimental Trip Visibility

- A completed trip with fewer than 50 actual rows in `trip_points` is experimental display data for the Fleet dashboard.
- The default dashboard list and all trip-derived dashboard statistics exclude these completed short trips.
- An active trip is never classified as experimental while `ended_at` is null, even when its current point count is below 50. It must remain visible for live supervision.
- Query parameter `show_experimental=1` includes completed short trips. The page labels them **تجريبية** and preserves the option across period/status filters, search, pagination, live refresh, and dashboard review actions.
- Classification uses server-maintained `TRIPS.POINTS_RECEIVED`, which is derived from actual unique `trip_points` rows after batch upload/confirmation and is not a client claim. The rule is presentation-only and must not delete rows, alter confirmation/checksum state, change reports, or block direct trip-detail access.

## 8.1 Fleet Dashboard Adoption And Fuel KPIs

- An active app driver is a distinct driver with at least one accepted `DRIVER_APP_INSTALLATIONS` row whose matching `G_STORE.BENEFICIARIES` row has `IS_ACTIVE = 1`. Presence does not require the latest build, a recognized store, or recent activity unless a future requirement explicitly adds that rule.
- An active app vehicle is a distinct `G_STORE.VEHICLE.OID` with `IS_ACTIVE = 1`, a non-null plate, and `BENEFICIARY_OID` belonging to a driver with at least one accepted app-presence row.
- Dashboard diesel consumption uses only confirmed trips with non-null persisted `TRIPS.VIRTUAL_FUEL_LITERS` and `STARTED_AT` inside the selected range. Rejected trips still represent consumed fuel and remain included; uploaded trips, null official fuel, provisional driver-summary fuel, and gap-review estimates are excluded.
- The default dashboard visibility rule still applies: completed trips with fewer than 50 stored points are excluded unless `show_experimental=1`; enabling that toggle also includes their official fuel in the KPI.
- Daily, weekly, and monthly use the dashboard's existing rolling ranges. A custom range includes the entire `date_from` and `date_to` calendar days using start-of-day and end-of-day boundaries.

## 9. Wells Diesel Consumption Status

- `Daily Tank` wells use approved fuel-program fills; classified non-Daily tanks use field inventory geometry. Wells without `TANK_SHAPE` belong to neither report until classified.
- Generator fuel rows used by either diesel report require `G_STORE.FUEL_LOG_DETAILS.ASSET_TYPE = 401`, non-null approved quantity, `G_STORE.FUEL_LOG.STATUS_OID = 416`, and null cancellation date; `ASSET_OID` alone is not unique across asset types.
- Internal actual rate is approved liters divided by the positive difference between consecutive fill meter readings.
- External actual rate is `(previous geometric balance + approved supply - current geometric balance) / operating hours`; use validated positive operating hours when supply exists and the absolute non-zero generator-meter difference when supply is `0`.
- External supply belongs to an inventory interval when its numeric generator reading is greater than the previous inventory reading and no greater than the current inventory reading. Timestamp matching is fallback-only for delivery rows without a usable meter reading.
- The alert threshold is the well-generator approved rate plus its allowed difference. Actual rate greater than the threshold is high; equal or lower is normal.
- Missing inputs produce unavailable results. Reverse/impossible meters are invalid when supply exists; the zero-supply exception uses their absolute non-zero difference. Zero meter differences, non-increasing inventory times, and negative consumption remain invalid. Invalid data must never be displayed as high consumption.
- Missing chronological history keeps approved supply null and must be labeled as not calculable. Numeric zero is reserved for a valid inventory interval in which no approved delivery occurred; in that case calculate consumed liters as `previous balance - current balance` and calculate the rate with the absolute non-zero generator-meter difference. Reversal or excess over elapsed time does not invalidate this zero-supply exception.
- Keep a negative calculated consumption/rate visible for diagnosis while classifying the row as invalid.

## 10. Wells Master Data Deletion Safety

- Inspectors, teams, and wells may be physically deleted only when they have no related records or assignments.
- Inspector blockers include teams, readings, diesel inventories, notifications, and access tokens.
- Team blockers include assigned wells and inspectors.
- Well blockers include readings, diesel inventories, notifications, team assignments, generator assignments, and media.
- The service must perform the friendly preflight check and expose `CAN_DELETE` plus `DELETE_BLOCKERS`; the database remains the authoritative final guard.
- Foreign keys from these master records use `NO ACTION`, never `ON DELETE CASCADE`. A direct or racing delete must fail instead of deleting operational history.
- Prefer `IS_ACTIVE = 0` for entities that have ever participated in operations.

## 11. Wells Reading Production Status

- The production-report indicator evaluates meter consistency only; it is not a water-quality assessment.
- Comparable current and previous readings must both be non-negative.
- Operating hours are `current reading - previous reading`. A difference from 0 through 20 hours inclusive is normal; a difference above 20 hours is abnormal.
- A negative reading or reversed meter is abnormal. A missing previous row is a first reading, while missing current data requires review.
- Abnormal rows are included in the report's review-required summary count.

## 12. Wells Operational Record Soft Deletion

- A dashboard deletion of `WELL_READINGS` or `DIESEL_INVENTORIES` is logical only; the physical Oracle row is retained.
- Every deletion requires the correct per-user action permission plus a reason and stores actor ID/name, deletion time, and reason on the row.
- Deleted rows are outside all normal operational counts, lists, history, reports, exports, balances, and current/previous chronology.
- A deleted row remains visible only to explicit audit/idempotency queries. A repeated mobile UUID is acknowledged from the retained row and must never create a replacement.
- Soft-deleted rows still block physical deletion of their linked well or inspector because the foreign-key-backed history remains present.
