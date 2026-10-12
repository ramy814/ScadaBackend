# API Conventions

## Base URL
All driver-facing APIs use the `/api` prefix.
All admin-facing routes are handled via web routes mapped to Inertia.js views.

## Fleet Supervisor Vehicle Scope

- `GET /admin/dashboard` requires base `fleet` access and returns only trips
  whose `ORACLE_VEHICLE_ID` belongs to a live `G_STORE.VEHICLE` row covered by
  the user's `fleet.vehicles.follow-to.125|126|132|133` permissions.
- `GET /admin/trips/{trip}` and the `approve`, `reject`, and `clarify` POST
  routes repeat this authorization against the vehicle's current
  `FOLLOW_TO_OID`; a trip URL is never authorization by itself.
- Fine-grained vehicle permissions fail closed. Missing permissions, unavailable
  Oracle scope, or a missing vehicle match produce no dashboard data and deny
  trip-level actions rather than falling back to all vehicles.

## Manual Admin Trip Entry

- `GET /admin/manual-trips`, `GET /admin/manual-trips/create`,
  `POST /admin/manual-trips`, `GET /admin/manual-trips/{trip}/edit`, and
  `PUT /admin/manual-trips/{trip}` require admin
  authentication, the global operational gate, and the exact fail-closed
  `fleet.trips.create` permission. Base `fleet` access is intentionally not
  required or implied.
- Vehicle options and the submitted vehicle are scoped using the authenticated
  SSO `MOG_USERS.USERS_LIST.ID`, station assignments, and
  `TRACKING.USER_STATION.ACCESS_TYPE >= 3`. The linked
  `G_STORE.VEHICLE_STAGES` row must also have `REMOVED_USER IS NULL`, excluding
  stations removed from a vehicle. The save path must re-run the scope;
  client-provided vehicle details are never authoritative.
- This is an Inertia web workflow, not a mobile sync endpoint. It creates a
  confirmed/pending trip with zero GPS points, manual source/actor snapshots,
  and an audit event.
- Manual trip start and end timestamps must fall on the same calendar day, and
  the end timestamp must be strictly later than the start timestamp. The end is
  intentionally not compared with the current clock time.
- The index is server-paginated at 15 rows and always scoped to the authenticated
  `entered_by_user_id`; filters are search, review status, scoped vehicle, and
  inclusive date bounds. Update also requires the same creator and a still-pending
  review status, and creates `trip.manual.updated` audit history.

## Authentication
Mobile APIs require a Sanctum token with exactly one system ability: `driver` for Fleet Driver, `wells` for Wells, or `scada` for the SCADA/Valves app.
`Authorization: Bearer <token>`

- Protected routes enforce both the token ability and its tokenable model (`Beneficiary`, Wells `Inspector`, or `ValveInspector`). A token from one mobile system must receive HTTP `403` from the other two systems, including a legacy wildcard token.
- Login response shapes and bearer-token transport are unchanged, so released apps do not need a client update. Migration `2026_08_26_000025_scope_mobile_access_tokens.php` narrows current and recognized legacy tokens in place without changing their plaintext value or forcing a new login.
- Never issue new mobile tokens with Sanctum's default `['*']` ability.

## Global Operational Suspension

- `RejectWhenSystemsSuspended` runs before authentication on every mobile entry point under Driver, Wells, Valves, valve maintenance, and the valves-app dashboard endpoints.
- While the global switch is active, these routes return HTTP `503` with `Retry-After: 300`, `Cache-Control: no-store`, and:
```json
{
  "message": "سبب الإيقاف الإداري أو رسالة التوقف الافتراضية",
  "code": "systems_temporarily_suspended",
  "retryable": true
}
```
- No mobile auth, sync, lookup, upload, or action controller runs while suspended. Existing tokens and locally retained records remain unchanged.
- Old clients remain compatible: they already treat non-success responses as retryable/offline failures. Do not change payloads or delete local data because of this 503.
- `/permissions` is the protected recovery control plane and intentionally remains available to configured permission managers while all normal web systems are suspended.
- `.ai/workflows/system-operational-suspension.md` is the authoritative activation, restoration, deployment, and cross-version compatibility workflow.

## Response Formatting
We strictly use Laravel API Resources to format responses. Do not return raw arrays or models from controllers.
```php
// Correct
return new TripResource($trip);

// Incorrect
return response()->json($trip);
```

## The Sync Endpoints

### Transport And TLS Failure Boundary

- A client failure with no HTTP status/response means the request did not reach Laravel. Check device time, DNS, connectivity, and the production TLS certificate before changing request validation or trip business logic.
- Production incident `2026-08-23`: the `scada.mogaza.org` certificate expired at `2026-08-17 08:09:58 UTC`, causing every driver build to fail before HTTP while the API routes themselves remained available.
- Recovery verification `2026-08-24`: normal TLS validation succeeds again. Unauthenticated production `/admin/dashboard` reaches SSO with HTTP 302 and `/api/driver/trips` reaches Sanctum with HTTP 401, confirming the host, certificate, and registered routes are reachable.
- Never solve a certificate outage by disabling TLS verification, accepting an invalid certificate, or changing the app to HTTP. Renew and bind the server certificate so all deployed versions recover without an APK update.
- Offline-first behavior remains authoritative during a transport failure: keep the trip, points, stops, events, `server_id`, and `points_uploaded` progress locally; mark the attempt retryable and resume through the same idempotent endpoints after secure connectivity returns.

### 1. `POST /api/driver/trips` (Create/Refresh Trip)
- **Purpose**: Creates the offline trip snapshot, then refreshes the same active record during live uploads and once more at completion.
- **Payload**: Requires `local_id`, `vehicle_plate`, `started_at`, `origin`, `destination`, `mission_type`. Release `1.1.0+23` may also send `manual_wait_minutes`, `stops[]` with client UUID/coordinates/source, and `tracking_events[]` with client UUID/type/time/optional coordinates.
- **Behavior**: Verifies the vehicle exists in Oracle and is assigned to the driver. Creates the `trips` record with a complete snapshot of the driver and vehicle.
- **Idempotency**: Uses trip `local_id`, stop `local_id` when supplied (legacy stops fall back to `started_at`), and tracking-event `local_id`. If retried, existing records are updated instead of duplicated.
- **Wait Calculation**: With `manual_wait_minutes`, the server uses `manual whole minutes + completed automatic-stop whole minutes`. Without it, the legacy `wait_minutes` contract remains supported.

### 2. `POST /api/driver/trips/{trip}/points` (Upload GPS Points)
- **Purpose**: Called periodically as the vehicle moves, sending batches of GPS coordinates.
- **Payload**: An array of `points`, each containing a `sequence_number`, `latitude`, `longitude`, `captured_at`, etc.
- **Batch Limit**: The existing request contract remains 1–500 points so every released app remains compatible.
- **Behavior**: `TripSyncService` locks only the target trip row, groups points by their supplied field shape, and performs Oracle-compatible upserts in internal chunks of 100. It counts the stored rows once after the batch and refreshes server-owned `TRIPS.POINTS_RECEIVED`.
- **Idempotency**: Uses the unique `(trip_id, sequence_number)` key. If a batch is sent twice, supplied fields on existing sequence numbers are updated without creating duplicates; omitted optional fields are not erased.
- **Performance Rule**: Never restore per-point `updateOrCreate` loops. A 500-point request must remain a small bounded number of database statements rather than roughly 1,000 select/write statements.
- **Concurrency Rule**: The per-trip row lock serializes overlapping uploads for the same trip, preventing stale `points_received` values and Oracle merge races without blocking other trips.

### 3. `POST /api/driver/trips/{trip}/confirm` (End Trip)
- **Purpose**: Called when the driver presses "End Trip".
- **Payload**: Requires `points_expected` (the total number of points the app attempted to send) and `checksum` (a SHA256 hash of the points generated by the mobile app).
- **Behavior**: The server compares `points_expected` against the number of points it actually received. It also recalculates the checksum based on the points in its database and compares it to the client's checksum. If they match and all points are received, the trip status changes to `confirmed`. Otherwise, it remains `uploaded`.
- **Active-trip rule**: Live uploads use only Steps 1–2. Confirmation remains the final finished-trip integrity gate and is never performed for an unfinished trip.
- **Driver Summary Response**: A confirmed response includes `distance_km`, `distance_is_filtered`, `duration_minutes`, `fuel_liters`, `fuel_is_provisional`, and `quality_flags` in addition to `can_delete_local`. `fuel_liters` may be a clearly flagged review-only estimate when the stored official fuel is withheld by a severe quality rule.

### Driver Trip History Summary

- `GET /api/driver/trips/{trip}/route-preview` is authenticated, driver-owned, and confirmed-trip-only. It is requested only when server trip details need a map, never by the history list. `TripRepository` first reads eligible count/min/max, then asks Oracle for at most 100 evenly distributed sequence numbers through the indexed `(TRIP_ID, SEQUENCE_NUMBER)` key; PHP never loads the full route. Client-invalid and `stationary_heartbeat` points are excluded, ordered endpoints are preserved, and `DriverTripRoutePreviewService` only rounds the returned latitude/longitude pairs to six decimals. The preview is presentation-only and never changes authoritative points or metrics.
- `GET /api/driver/trips` and `GET /api/driver/trips/reconcile` expose the same driver summary fields.
- Confirmation locks the target trip row, validates count/checksum, calculates the summary once, and persists `TRIPS.DISTANCE_KM`, `TRIPS.DURATION_MINUTES`, and official `TRIPS.VIRTUAL_FUEL_LITERS` before changing the status to `confirmed`. A repeated confirmation returns the stored row without recalculation.
- `GET /api/driver/trips` and reconciliation never query `TRIP_POINTS` to build summaries. Legacy rows without persisted distance/duration return unavailable (`null`) values until an explicit offline backfill is run; a history request must never calculate or rewrite them.
- `App\Services\DriverTripSummaryService` only shapes persisted metrics. When official fuel is null and persisted distance is positive, it may calculate the existing lightweight provisional `distance × 0.12` response without reading points or storing it as official fuel. Controllers must not duplicate these rules.

### Admin Trip Gap Estimate

- `GET /admin/trips/{trip}` may include transient Inertia props for conservative gap review: `estimatedGapDistanceKm`, `estimatedDistanceWithGapsKm`, `estimatedFuelWithGapsLiters`, `estimatedGapCount`, and `unestimatedGapCount`.
- These props are calculated read-only by `VirtualFuelService` from internal gaps over 60 seconds. A gap estimate is the direct endpoint distance and is usable only when its implied speed does not exceed 55 m/s.
- This is an admin presentation contract, not a driver API or persistence contract. Do not add these estimates to confirmation/history payloads, store them in `TRIPS`, or replace official distance/fuel without a separately approved audited workflow.

### Driver App Presence

- Current client contract version: driver app `1.1.19+42`; authoritative release defaults are in `config/driver_app.php`. It requires background location access, requests Android's battery-optimization exemption, resumes an unfinished tracker after reboot, and keeps stale-tracker decisions based on the latest provider callback/raw point while accepted points remain the only source for route distance and fuel.
- `POST /api/driver/app-presence` is authenticated with the existing driver Sanctum token.
- Payload: `device_id` (random installation UUID), `app_version`, `build_number`, `package_name`, optional `installer_store`, `installed_at`, and `app_updated_at`.
- Driver identity and snapshots always come from the authenticated `Beneficiary`; never accept a driver ID, name, phone, or vehicle assignment from this payload.
- The endpoint is idempotent for `(oracle_driver_id, device_id)` and refreshes `last_seen_at`.
- The response uses `DriverAppPresenceResource` and includes the configured current release plus `update_required`.
- Presence is operational telemetry only. It contains no GPS coordinates and a failure must never block trip sync.
- The primary client trigger is authenticated `DriverAppShell` creation/app resume, not OTP verification. This covers persisted sessions.
- Deployment diagnostic: an unauthenticated request should reach the authentication layer. HTTP 404 means this route is absent from the deployed backend, so no client retry can create a dashboard record.

### 4. `POST /api/driver/trips/{trip}/clarification-response` (Clarification)
- **Purpose**: Allows the driver to respond to a clarification request sent by the admin.
- **Payload**: Requires `response_text`.
- **Behavior**: Updates the trip with the driver's response, allowing the admin to proceed with approval or rejection.

## Phone Normalization Pattern
When searching for entities (like Vehicles) by the driver's phone number, the API automatically normalizes the input to handle various formats used in Palestine:
- `0599123456` (Local format)
- `970599123456` (International format)
- `599123456` (No leading zero)
- Fallback: Comparing the last 9 digits.
This is implemented in `MunicipalityVehicleRepository`.

## Driver Vehicle Lookup
Driver-facing vehicle endpoints must be scoped to the authenticated beneficiary:
- `GET /api/driver/vehicles` returns only vehicles assigned to the current authenticated beneficiary.
- `GET /api/driver/my-vehicles` uses the same scoped logic and is kept for compatibility.
- Do not expose the general Oracle vehicle search to the driver app.
- Vehicle assignment in the `CONTROL_UNIT` schema is resolved through `FLEET_USERS`: the authenticated driver is `FLEET_USERS.ID`, and the assigned vehicle is read from `VEHICLE_ID`, `VEHICLE_NO`, `VEHICLE_MODEL`, and `VEHICLE_TYPE`.
- When resolving by mobile number, use the equivalent of:
```sql
select fu.vehicle_no
from fleet_users fu
where fu.id in (
    select driver.id
    from fleet_users driver
    where driver.user_mobile = :mobile_num
)
```
- Keep phone normalization support for local, international, and last-9-digit matching, but the result must remain beneficiary-scoped.

## Valves Mobile API

Valve inspector app accounts are separate from fleet drivers and wells inspectors.
- Login endpoint: `POST /api/valves/auth/login`
- Payload: `phone`, `password`
- `PHONE` is the username and must match a row in `VALVE_INSPECTORS`.
- `IS_ACTIVE` must be enabled for login.
- Successful login returns a Sanctum token with only the `scada` ability plus the inspector profile and assigned valve teams.
- Authenticated valve inspector API access is scoped to valves assigned through the inspector's valve teams.
- If any assigned valve team has `ASSIGN_ALL_VALVES = true`, the inspector receives and can act on all valves.
- The legacy SCADA Flutter app in `/Volumes/SecondDrive/Scada/scada_flutter` must call `POST /api/valves/auth/login` with `phone` and `password`, then call `GET /api/valves` with the returned Sanctum token.
- Do not authenticate valve inspectors against wells `INSPECTORS` or fleet driver tables.

## Wells Deleted-Record Sync Contract

- Dashboard deletion of a well reading or diesel inventory is audited soft deletion; no mobile delete endpoint is exposed.
- Normal Wells history/sync-list responses exclude rows with `DELETED_AT`.
- Upload idempotency lookup must include soft-deleted rows by authenticated inspector plus `CLIENT_LOCAL_ID`. A retry acknowledges the retained OID/UUID and must not insert a replacement or return the deleted row in history.

## Bounded Mobile Sync Contract

- Driver point uploads accept 1–500 points per request and persist through 100-row Oracle upserts keyed by `(TRIP_ID, SEQUENCE_NUMBER)`. Trip metadata accepts at most 50 passengers, 500 stops, and 500 tracking events; repeated sequence numbers and local IDs inside one request are rejected. Driver Flutter repeats the idempotent trip-create request in those same metadata batch sizes before uploading points.
- Wells reading, diesel-inventory, and notification uploads accept 1–500 records per request. `MobileSyncService` processes them in independent 50-record transactions and bulk-loads existing `CLIENT_LOCAL_ID` values once per chunk instead of performing one lookup per row.
- Wells Flutter sends pending parent records in 100-record requests. The API normalizes legacy lowercase record keys, keeps `LOCAL_ID` optional, and derives a deterministic server idempotency key when an older app omits it.
- Activity-media sync remains limited to four files and rejects duplicate photo IDs in one request. A retry may reuse an existing photo ID only for the same activity type and parent OID.
- `POST /api/valves/{valve}/action` accepts optional `local_id` (up to 64 characters). New SCADA builds send the existing pending-action ID on the first attempt and every retry. `VALVE_LOGS` enforces uniqueness per inspector; replay returns the existing action and never creates a second maintenance report. Requests from older builds without `local_id` remain valid.

### Valves Mobile Map Source
- This subsection defines the mobile API contract. The authoritative web-map runtime contract is in `.ai/modules/valves/project-overview.md` under “Current Authoritative Web Map Contract”. Do not merge the two client implementations by assumption.
- The Flutter inspector app uses OpenStreetMap as its only tile source.
- Authenticated `GET /api/valves` is the only source of mobile valve markers.
- The response is scoped to valves assigned through the inspector's teams. A team with `ASSIGN_ALL_VALVES = true` retains the existing all-valves behavior.
- Each returned valve includes WGS84 `latitude` and `longitude`. `ValveRepository` extracts vertices from `EME.WATER_VALVES.GEOMETRY` and transforms Oracle Spatial SRID 2039 to EPSG:4326 through `SDO_CS.TRANSFORM`.
- The mobile routes `/api/dashboard/map-data`, `/api/dashboard/map-settings`, and `/api/dashboard/map-features` do not exist and must not be restored.
- The mobile app must not call GeoServer, WMS/WFS, `/wms.php`, or the authenticated web `/valves/map-features` endpoint.
- Assigned valve JSON and OpenStreetMap tiles may be cached by the mobile app for offline use; the assignment scope remains the backend `/api/valves` response.

### Valves Web Map Contract
- Page: `GET /valves/map`.
- OpenStreetMap is the default mode and displays interactive markers sourced directly from `EME.WATER_VALVES` through the page's `mapValves` Inertia prop; the payload excludes coordinates outside the Gaza bounding box. GeoServer is a separate mutually exclusive mode with its own WMS layer selector.
- Feature data for dedicated GeoServer-mode popups: authenticated `GET /valves/map-features?layers=...&lat=...&lng=...&zoom=...`. `zoom` is optional and narrows the server-side GetFeatureInfo tolerance; the URL is passed as `mapFeaturesUrl`.
- GeoServer mode uses the same-origin tile proxy. The default `mog:gaza general map` layer calls `/wms.php?g_page=/wmts`, which forwards authenticated WMTS `GetTile` requests to `https://geo.mogaza.org/geoserver/gwc/service/wmts` with the server-published `EPSG:900913` matrix set. This is the Web Mercator equivalent of the advertised `EPSG:4326` demo and keeps Leaflet/OSM alignment while reading GeoWebCache tiles directly. Optional detail overlays continue through `/wms.php?g_page=/wms` and cached WMS.
- The `/gwc/demo/...` URL is not a tile endpoint. It can return HTTP 200 while actual WMTS tiles return HTTP 401. Browser callers must stay on the same-origin proxy, and deployments must provide valid `GEOSERVER_USERNAME`/`GEOSERVER_PASSWORD`; never expose Basic credentials to JavaScript.
- The new GeoServer mode uses the published opaque `mog:gaza general map` layer as its default background. Optional detail overlays are `mog:V_BUILDING_GEOSRV`, `mog:V_CENTERLINE_SEGMENTS`, and `mog:V_LANDMARKS_V2`. The server does not currently publish `WATER_VALVES` or `WATER_PIPE`; web valve markers and the team selector therefore use Oracle-backed WGS84 points over OpenStreetMap.
- Web browser code must not issue direct `GetFeatureInfo`, WFS, or GeoServer `fetch` calls.
- `ValvesMapService` sends an authenticated WMS `GetFeatureInfo` request to `https://geo.mogaza.org/geoserver/wms` using the shared `config/geoserver.php` settings. It queries the visible `mog:*` layers, including the default `mog:gaza general map` group, and returns an empty feature array on invalid/unreachable upstream responses.
- Do not modify `public/wms.php` while fixing web callers unless the user explicitly scopes the request to that file.

### Hiraf Inspector Offline Tile Contract

- Client repository: `/Volumes/SecondDrive/hiraf_inspector`; API origin: `https://apps.mogaza.org/api/account-follow-up`.
- The Android client plans exactly 2,447 `EPSG:900913` tiles for the Gaza City bounds `34.39912130969776..34.51853774196185` longitude and `31.45464230411222..31.55032467219495` latitude at zoom levels 11–17.
- The client may visually zoom cached tiles to level 19, but the gateway accepts
  and downloads native tiles only at zoom 11–17.
- Tile endpoint: `GET https://scada.mogaza.org/api/map/tiles/{zoom}/{x}/{y}.png`.
- This backend validates the existing inspector bearer token server-to-server
  against the account-follow-up profile endpoint and caches successful
  validation for five minutes.
- Only the single layer, matrix set, bounds, and zoom range above are allowed.
- Never return or embed GeoServer Basic credentials in JSON, Dart, query strings, APK assets, or browser/mobile headers. The application backend owns upstream authentication and should return only `image/png` tiles with appropriate cache headers.
- A client refresh deliberately re-fetches the same fixed 2,447-tile plan; it
  does not expand the gateway coverage.
