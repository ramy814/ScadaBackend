# Coding Rules & Conventions

## 1. PHP Version & Laravel
- **PHP**: 8.2 (Strictly, due to production server limitations).
- **Laravel**: 11.x
- Avoid using PHP 8.3+ features.

## 2. Controllers
- Keep controllers thin. Max 50-70 lines.
- Use dependency injection in the constructor for Services.
- All request validation must happen in a `FormRequest` class, not in the controller.
- Example:
```php
public function store(TripStoreRequest $request, TripSyncService $service)
{
    $trip = $service->storeTrip($request->user(), $request->validated());
    return new TripResource($trip);
}
```

## 3. Inertia & Vue 3
- We do **not** use the `ziggy-js` routing library.
- Instead, URLs are passed from Laravel to Vue via the `HandleInertiaRequests` middleware using `$page.props.urls`.
- In Vue 3, access URLs like this:
```javascript
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const loginUrl = page.props.urls.login;
```
- For forms handling, use Inertia's `useForm`.
- Implement visual loading states via `form.processing`.
- **External Redirects (SSO)**: Never return a standard `RedirectResponse` (e.g., `redirect()->away(...)`) when redirecting to external services like MOG SSO, as Inertia XHR requests will fail due to CORS. Instead, **always** return `\Inertia\Inertia::location($url)`. Helpers must return `Inertia::location()` directly, and controllers must return the result without modifying it (do NOT call `getTargetUrl()`).

## 4. Repositories
- Any query touching the Oracle Database must go into a Repository class (e.g., `MunicipalityVehicleRepository`).
- Repositories should return Collections or simple arrays, not raw Query Builders, to the Service layer.

## 5. Security
- Never trust input from the mobile app regarding its state. Always verify server-side (e.g., the points checksum, calculating wait times vs relying on app wait times).
- Never trust a submitted manual-trip vehicle ID. Options and create/update/submit authorization must use `MunicipalityVehicleRepository`, station membership, active `VEHICLE_STAGES.ACCESS_TYPE IN (1, 3)`, and `REMOVED_USER IS NULL`. `fleet.trips.create` derives from Oracle entry OR review scope; no saved assistant grant is required. Runtime entry requires `(1, 3)`, manual approval `(2, 3)`, and dashboard review additionally requires base `fleet`. Never substitute editable roles, ownership categories, or `USER_STATION.ACCESS_TYPE` for Oracle operation scope.
- For release `1.1.0+23`, recompute automatic wait from completed `trip_stops` intervals and combine it with the separately supplied manual wait. Keep the old `wait_minutes` fallback when `manual_wait_minutes` is absent so older clients remain valid.
- Device-state events and stale points are technical evidence only. Never encode or display a conclusion that the driver acted deliberately without an independent review process.
- Wells production calculations must use consecutive `WELL_READINGS.GENERATOR_HOURS_READING` values only. `PUMP_HOURS_READING` and `FLOW_RATE` must never be fallback sources for `ReadingService::getProductionReport()` or its screen/PDF/Excel consumers.
- All admin actions must use POST/PUT/DELETE and be protected by the `auth` middleware.
- Valves Vue code must load the `mog:gaza general map` background through `/wms.php?g_page=/wmts` with the `EPSG:900913` WMTS matrix and optional published `mog:*` detail overlays through `/wms.php?g_page=/wms`; never use a hard-coded browser GeoServer host. The proxy owns both authenticated GeoWebCache upstream connections; feature lookup remains a server-side authenticated WMS GetFeatureInfo request through `/valves/map-features`. Do not restore the unavailable internal `appdev-01` WFS dependency or the old `cite:*` aliases.
- Never treat a working GeoWebCache demo page as a successful tile health check. Verify `GetTile` through `/wms.php?g_page=/wmts`; keep `GEOSERVER_USERNAME` and `GEOSERVER_PASSWORD` server-side and diagnose HTTP 401 as an access/credential problem, not a reason to bypass the proxy or disable authentication.

## 6. Language
- The source code (variables, functions, classes, database tables) is exclusively in **English**.
- The User Interface (Views, Vue Components, Validation Messages, PDF generation) is exclusively in **Arabic**.

## 7. Ponytail Skills
- The local Codex install includes Ponytail skills from `DietrichGebert/ponytail`: `ponytail`, `ponytail-review`, `ponytail-audit`, `ponytail-debt`, `ponytail-gain`, and `ponytail-help`.
- Use Ponytail as a simplicity guard during code changes: prefer existing project patterns, native platform features, and the smallest working diff.
- For reviews, use Ponytail only to find removable complexity; do not mix it with correctness/security findings unless the user asks for a normal review too.
- If a deliberate shortcut is added, mark it with a `ponytail:` comment that names the ceiling and the trigger for revisiting it.

## 8. Oracle-Specific Rules

### Primary Key After INSERT
After `Model::create()` on Oracle, **never** read `$model->OID` (uppercase) — Oracle PDO returns attributes in lowercase, so `->OID` is `null`. Use `$model->getKey()` instead.

```php
// WRONG ❌
$team->OID  // null!

// CORRECT ✅
(int) $team->getKey()
```

### BelongsToMany Pivot Tables
Do NOT use `->withPivot('created_at')` or `->withTimestamps()` on any `BelongsToMany` relation backed by Oracle. OCI8 returns column names in uppercase (`CREATED_AT`), but Eloquent pivot hydration expects lowercase (`created_at`) → 500 error on relation load.

Do NOT use `->sync($ids)` on Oracle pivot tables — use raw `DB::connection('oracle')->statement()` for INSERT/DELETE instead.

### Auth Middleware — Multi-System Access
The `admin.allowlisted` middleware (mapped to `EnsureAdminIsAllowlisted`) must check `SYSTEM_PERMISSIONS` for ANY permission, not a legacy allowlist table. A user who only has `wells` or `valves` permission must still pass the gate — the per-page middleware (`system.access:wells`) handles fine-grained system access.

### Raw DB Queries — Column Name Casing
When using `DB::table()` or `DB::connection('oracle')` for raw queries (not Eloquent), always use **lowercase** column names in `pluck()`, `value()`, `where()`, and `get()` calls. Oracle's PDO driver normalizes fetched column names to lowercase. Uppercase names only work in Eloquent because it handles the case mapping.

```php
// WRONG ❌ — DB::table raw query returns lowercase keys
DB::connection('oracle')->table('team_wells')->pluck('WELL_OID');  // empty!

// CORRECT ✅
DB::connection('oracle')->table('team_wells')->pluck('well_oid');
```

> This applies ONLY to raw `DB::table()` / `DB::statement()` results.
> Eloquent models with `UppercaseKeys` trait handle the mapping internally.
