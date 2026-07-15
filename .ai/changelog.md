# Changelog & Recent Actions

## [2026-07-15] Inspector Wells Mobile Sync Regression Fix

- Fixed `GET /api/wells/sync/wells` returning HTTP 500 when an assigned well had a previous diesel inventory. Oracle returned `INVENTORY_DATE` as a string, so the endpoint now normalizes it through Carbon before emitting ISO 8601 JSON.
- Moved inspector-well filtering and latest-inventory queries out of `SyncController` into `WellRepository`, `DieselInventoryRepository`, and `WellService`.
- Replaced the per-well latest-inventory query with one Oracle `ROW_NUMBER()` query for all assigned wells.
- Verified live Oracle responses for every active inspector: Rami Ghayada receives 12 active assigned wells, and all inspector responses return HTTP 200.
- Added `WellSyncServiceTest` to guard Oracle string-date formatting in the Flutter sync contract.

## [2026-07-14] Wells Diesel Inventory Report

- Added PDF and Excel exports to the Wells diesel inventory screen using the active well, inspector, generator, and date filters.
- Matched the supplied `تقرير الابار اليومي28-06-2026.xlsx` structure with well/generator data, previous/current meter readings, operating hours, previous/current diesel heights, approved fuel deliveries, actual consumption rate, tank capacity, water production, and calculated diesel consumption.
- Generator capacity and nominal fuel consumption come from `G_STORE.GENERATOR.ENGINE_CAPACITY` and `FUEL_CONSUMPTION`; approved deliveries come from `G_STORE.FUEL_LOG` and `FUEL_LOG_DETAILS.APPROVED_GALLONS` between consecutive inventories.
- Inventory rows without `GENERATOR_OID` use the well's assigned generator only when exactly one generator is assigned; ambiguous assignments remain explicit in report notes.
- Added unit coverage for the report calculations and visually verified the Oracle-backed PDF as a single A3 landscape page.

## [2026-07-14] Wells Equipment Fields, Media, And Project Name

- Renamed the application UI to `نظام ادارة آبار بلدية غزة`.
- Updated pump types to `submersible` and `vertical`, added pump horsepower, and added `generator` as a power source.
- Updated fuel tank choices with `HDPE` material and `Daily Tank`, and removed concrete/reinforced-plastic choices from new edits.
- Changed chlorination availability to `يوجد`/`لا يوجد`, added `يعمل`/`لا يعمل` status, constrained chlorine tank types/materials, and removed the chlorine pump model from the form.
- Added cooling tank capacity/type, cooling-system status, and cooling-line type choices.
- Added named, removable private images for pumps, fuel tanks, cooling systems, valves/parts, and well rooms through `WELL_MEDIA`.
- Normalized safe legacy values (`centrifugal` to `vertical`, reinforced plastic to `HDPE`, and legacy chlorination types to binary availability) without deleting historical concrete-tank data.
- Ran migrations `2026_07_14_000011_extend_wells_equipment_data` and `2026_07_14_000012_normalize_well_equipment_values`, the PHP test suite, and the Vite production build successfully.

## [2026-07-14] Wells Diesel Inventory Calculated Balance

- Calculated the dashboard's actual diesel balance and latest-balance summary from the tank shape, tank dimensions, and measured diesel height instead of trusting the submitted liters value alone.
- Supported vertical cylinders, horizontal cylinders (circular-segment volume), and cube/square-base tanks.
- Added the previous chronological fuel reading for each well to the diesel inventory table using one Oracle `LAG` query, independent of the active dashboard filters.
- Added unit coverage for all three tank shapes and verified the result against current Oracle inventory data.

## [2026-07-14] Active SSO Users Only In Permission Search

- Added `MOG_USERS.USERS_LIST.IS_ACTIVE = 1` to the central permission-screen user search.
- Grouped the name, SSO ID, and employee ID alternatives so numeric `OR` conditions cannot bypass the active-user filter.
- Existing permission assignments remain visible even if an SSO user later becomes inactive, allowing administrators to revoke stale access.

## [2026-07-14] Unified Local And Production SSO User

- Set local development login through `MOG_LOCAL_ADMIN_USER_ID=140`, matching the SSO user ID used in production.
- Removed the stale hard-coded fallback user ID from `config/mog-helpers.php`; local environments must configure the ID explicitly.
- Moved `fleet`, `wells`, and `valves` permissions from the duplicate legacy SSO row `1061` to the canonical user `140`.
- Removed the `user_id` request override from `/admin/dev-login`, preventing old development URLs from logging back into the inactive duplicate SSO row.
- Normalized stale authenticated local sessions in `EnsureAdminIsAllowlisted`, so existing browser cookies are switched to the configured development SSO user before system permissions are checked.

## [2026-07-08] Wells Teams/Inspectors — Oracle PDO Lowercase Pluck Fix

- **Problem**: `TeamRepository::syncWells()` and `InspectorRepository::syncTeams()` used `pluck('WELL_OID')` / `pluck('TEAM_OID')` with uppercase column names on raw `DB::table(...)` queries. Oracle's PDO driver always returns column names in **lowercase**, so the pluck returned empty collections — making every sync think it was starting from scratch and causing duplicate INSERT errors.
- **Fix**: Changed `pluck('WELL_OID')` → `pluck('well_oid')` and `pluck('TEAM_OID')` → `pluck('team_oid')`.
- **Files**: `app/Repositories/Wells/TeamRepository.php`, `app/Repositories/Wells/InspectorRepository.php`
- **Rule added**: When using `DB::table()` raw queries on Oracle, always use **lowercase** column names in `pluck()`, `value()`, and `where()` calls — PDO normalizes Oracle column names to lowercase on fetch.

---

## [2026-07-08] Valves Map Building WMS 500 Noise Suppressed

- Hid the `cite:V_BUILDING_GEOSRV` buildings layer by default on the Valves map and mobile map settings because the upstream WMS currently returns HTTP 500 for building `GetMap` tiles.
- Kept the buildings layer available in the map layer control for manual testing after the upstream WMS is fixed.
- Hardened `public/wms.php` so missing PHP cURL support returns a controlled fallback instead of a fatal 500.
- Added a shutdown fallback for `GetMap` requests so fatal proxy errors return a transparent PNG where possible instead of filling the browser console with tile 500 errors.
- Removed custom `console.warn` logging for WMS tile failures in `Map.vue`; the UI health banner remains the user-facing outage signal.

## [2026-07-08] Fleet Dashboard Search, Pagination, and Report Filtering

- Added vehicle/driver search to the Fleet dashboard using trip snapshot fields (`vehicle_plate`, `driver_name`) so admins can find trips without querying live Oracle asset tables.
- Changed the Fleet dashboard trip list from a fixed latest-30 view to paginated results (`30` per page) while preserving period, clarification-response, and search filters across page navigation and review actions.
- Updated the dashboard table title to clarify that rows belong to the selected period.
- Reworked Fleet reports filters from driver/vehicle dropdowns to direct text inputs:
  - `driver_search` filters by `driver_name`, `driver_phone`, `driver_employee_number`, or `oracle_driver_id`.
  - `vehicle_search` filters by `vehicle_plate`, `vehicle_name`, or `oracle_vehicle_id`.
- Reports now filter reactively after typing, preserve filters for pagination and CSV/PDF exports, and include a row-level "عرض" link to open the trip detail page.
- Rebuilt Vite assets under `public/build` because production deploys may use committed frontend assets.
- Verified with `php -l app/Http/Controllers/AdminAuthController.php`, `npm run build`, and `php artisan test`.

## [2026-07-05] Wells Module — Oracle Pivot & Auth Fixes

### 1. Auth: Multi-System Gate Fix (`EnsureAdminIsAllowlisted`)
- **Problem**: `admin.allowlisted` middleware checked the old `FLEET_ADMIN_ALLOWLIST` table, blocking Wells/Valves-only users even when they had permissions in `SYSTEM_PERMISSIONS`.
- **Fix**: Rewrote `EnsureAdminIsAllowlisted` to check `SYSTEM_PERMISSIONS` — any user with at least one system permission passes the gate; per-page `EnsureSystemAccess` handles fine-grained access.
- **File**: `app/Http/Middleware/EnsureAdminIsAllowlisted.php`

### 2. Wells Teams: 500 on Create (`$team->OID = null`)
- **Problem**: After `create()` on Oracle, Eloquent stores the primary key in lowercase (`oid`), so `$team->OID` returns `null`. Passing `null` to `syncWells()` triggered a `findOrFail(null)` exception.
- **Fix**: Use `$team->getKey()` everywhere after `create()`.
- **Files**: `app/Services/Wells/TeamService.php`

### 3. Wells Teams: 500 on sync (`Eloquent sync()` vs Oracle)
- **Problem**: `BelongsToMany::sync()` conflicts with Oracle uppercase column names in pivot tables (`CREATED_AT` vs `created_at`), causing Eloquent errors.
- **Fix**: Replaced `->sync()` with raw SQL (`DB::connection('oracle')->statement(...)`) for manual INSERT/DELETE on `team_wells`.
- **Files**: `app/Repositories/Wells/TeamRepository.php`

### 4. Wells Inspectors: Same two issues (#2 & #3)
- Applied identical fix: `getKey()` in service, raw SQL in repository for `inspector_teams`.
- **Files**: `app/Services/Wells/InspectorService.php`, `app/Repositories/Wells/InspectorRepository.php`

### 5. Wells: 500 on `withPivot('created_at')` when loading relations
- **Problem**: `->withPivot('created_at')` in `BelongsToMany` definitions caused a 500 when Eloquent tried to hydrate pivot rows — OCI8 returns `CREATED_AT` (uppercase) but Eloquent expects `created_at` (lowercase).
- **Fix**: Removed `->withPivot('created_at')` from all four relations (`Team::wells`, `Team::inspectors`, `Inspector::teams`, `Well::teams`). The pivot timestamp is not used in the UI.
- **Files**: `app/Models/Wells/Team.php`, `app/Models/Wells/Inspector.php`, `app/Models/Wells/Well.php`

### Key Lesson Documented
Two permanent rules added to `.ai/coding-rules.md` and `.ai/modules/wells/coding-rules.md`:
- Always use `$model->getKey()` after Oracle `create()`, never `$model->OID`.
- Never use `->withPivot()`, `->withTimestamps()`, or `->sync()` on Oracle-backed pivot tables; use raw SQL instead.

---

## [2026-07-02] Wells Generator Assignment Filter


- Filtered the Wells add/edit generator dropdown to active generator assets only from `G_STORE.GENERATOR` using `IS_ACTIVE = 1` and `TYPE_OID = 128`.
- Kept the generator card data contract unchanged: `OID`, `NAME`, `GENERATOR_NUM`, latest `CURRENT_READING`, and `GALLONS`.
- Changed the latest fuel-log join to `LEFT JOIN` so valid active generators still appear even when they do not yet have a fuel-log reading.
- Verified the Oracle-backed repository returns 37 filtered generators with card fields populated where available.

## [2026-07-02] Wells Readings Production Report Export

- Added `date_from` / `date_to` filters to the Wells readings monitoring page.
- Added PDF and Excel export actions for the filtered Wells readings report.
- Implemented production report calculations from readings: previous reading, current reading, operating-hours difference, and `production = difference * well.CAPACITY`.
- Added `wells/readings-report` Blade template matching the daily wells report structure from the provided Excel sample.
- Added backend report routes under `/wells/readings/report/pdf` and `/wells/readings/report/excel`.
- Hardened Wells reading date filtering so `date_to` includes the full selected day.

## [2026-07-02] Wells Dashboard Broken Panels Removed

- Removed the Wells dashboard `توزيع الجودة` panel because the quality fields are no longer reliable for dashboard reporting.
- Removed the Wells dashboard `آخر جرد السولار` and `آخر القراءات المدخلة` tables because their latest-record summaries were not working correctly.
- Simplified `App\Services\Wells\DashboardService` so it no longer queries recent readings, recent diesel inventories, quality distribution, or poor-quality alerts for the dashboard.
- Removed unused quality-distribution/poor-quality helper code from the Wells reading repository/service path while keeping the standalone readings and diesel inventory pages intact.

## [2026-07-02] Production Permission Data Correction

- Verified production SSO login for `رامي عيد سليم غياضة` resolves to `mog_users.users_list.id = 140` and `emp_id = 2541`.
- Found old permissions on a different SSO row (`id = 1061`, display name `رامي غياضة`), which made production show all systems as locked despite development access working.
- Granted `fleet`, `wells`, and `valves` permissions to the actual production SSO user row `SYSTEM_PERMISSIONS.USER_ID = 140`.
- Keep future portal permissions tied to the exact `mog_users.users_list.id` returned by SSO login, not similar names and not `emp_id`.

## [2026-07-02] Production Portal Permission ID Fix

- Fixed production portal cards showing locked for an authorized SSO user after login.
- `PortalController` and `EnsureSystemAccess` now resolve per-system permissions using `mog_users.users_list.id` via `getAuthIdentifier()`, not `emp_id`.
- Added `tests/Feature/SystemPermissionUserIdTest.php` to guard the `id` vs `emp_id` distinction for portal card rendering and system access middleware.
- Keep `emp_id` scoped to the global admin allowlist flow; `CONTROL_UNIT.SYSTEM_PERMISSIONS.USER_ID` stores the SSO internal `id`.

## [2026-07-02] Production Blank Page Deployment Fix

- Diagnosed the production blank page after MOG SSO login where the returned HTML body rendered without the Inertia root element.
- Confirmed the local Inertia root Blade view renders `id="app"`, `data-page`, and Vite `/build/assets/app-*` links.
- Added `tests/Feature/InertiaRootViewTest.php` to catch regressions where the Inertia root view renders empty.
- Removed `/public/build` from `.gitignore` so git-based production deploys can include Vite build assets.
- Rebuilt frontend assets locally; production deploys must either commit the complete `public/build` directory or run `npm install && npm run build` on the server, followed by Laravel cache clearing.

## [2026-07-01] Valves Map Performance, CORS Fixes & Theme Variables Cleanup

- Fixed valves map freezing by introducing `AbortController` in `Map.vue` to cancel pending `GetFeatureInfo` requests when clicking or unmounting.
- Resolved single-threaded Laravel server blocking by loading WMS tiles directly from `GEOSERVER_WMS_URL` (direct browser network access).
- Routed WMS `GetFeatureInfo` requests through `/wms.php` proxy to bypass browser CORS restrictions.
- Tuned `public/wms.php` proxy: added `set_time_limit(0)` / `ignore_user_abort(true)`, and set `MAX_GETMAP_DIMENSION = 256` for faster slow-network responses.
- Cleaned up Light/Dark theme variables across `valves.css`, `wells.css`, and `AppLayout.vue` by removing hardcoded dark overlays (`rgba(17, 24, 39, ...)` etc.) to ensure a clean, bright, and legible light mode.
- Removed the raw tracking points log table ("السجل الكامل لنقاط التتبع") from the trip detail view (`TripShow.vue`).
- Removed the `'fltr_citizens'` layer from the allowed WMS layers list in `public/wms.php` and cleaned up its references in the AI overview docs.
- Configured all GeoServer layers (Buildings, Streets, Pipes, Valves) to load and be visible by default on the valves map.
- Set the default base map to "بدون OpenStreetMap" (noBaseLayer) on startup, with OSM remaining as an optional selector.
- Replaced the gear (cog) icon for the map link in the valves system Sidebar.vue with a dedicated map SVG icon, and changed its label to "خريطة المحابس".

## [2026-07-01] SCADA Flutter Map Source Alignment

- Added backend map endpoints for the mobile SCADA app:
  - `GET /api/dashboard/map-settings` returns the canonical backend `/wms.php` URL and valves map layers.
  - `GET /api/dashboard/map-features` proxies feature lookup through the backend instead of exposing direct GeoServer calls to Flutter.
- Implemented those endpoints through `App\Http\Controllers\Api\Valves\MapController` and `App\Services\Valves\ValvesMapService` so route files stay thin.
- Updated `/Volumes/SecondDrive/Scada/scada_flutter` so the main map, dashboard map preview, and fallback settings all read WMS tiles from this backend's `/wms.php` proxy.
- Kept the SCADA Flutter default backend port at `8000`; use `--dart-define=API_PORT=8002` only when the local Laravel server is actually running on `8002`.
- Reduced `public/wms.php` GetMap behavior to one upstream attempt with a 25 second timeout so slow GeoServer tiles can still render while staying below the local PHP execution limit.
- Reduced initial map pressure by disabling `cite:V_CENTERLINE_SEGMENTS` by default and setting Leaflet WMS `keepBuffer` to `0`; the layer remains available in the control for manual enabling.
- Removed mobile direct dependency on old external map hosts from Flutter `lib/`.
- Kept `cite:WATER_PIPE` available but hidden by default in mobile, matching the stable web valves map behavior.
- Verified `GET /api/dashboard/map-settings` on the running local backend and confirmed Laravel registers both dashboard map routes.

## [2026-07-01] Valves Map Stabilized

- Confirmed the valves map is stable after the WMS proxy and Leaflet adjustments.
- Keep the outage banner suppressed when GeoServer WMS tiles are loading successfully, even if the health probe is stale or intermittently fails.
- Keep `GetFeatureInfo` popups on a solid white readable background independent of dark/light page theme.
- Keep `cite:WATER_PIPE` disabled by default until the GeoServer/Oracle Spatial issue `ETYPE 4 inconsistent with expected LINE` is fixed upstream.

## [2026-07-01] Valves Map Open Fix

- Fixed the broken valves sidebar link that pointed to `/valves/valves`; it now opens the existing valves map route.
- Pointed the maintenance reports sidebar entry back to the valves dashboard instead of the missing `/valves/maintenance` route.
- Added backend redirects for the old `/valves/valves` and `/valves/maintenance` paths so stale browser assets no longer produce 404s.
- Updated `public/wms.php?health=1` to always return a JSON response with HTTP 200 and `ok: false` when GeoServer is unreachable, so the UI can show the Arabic outage message without a browser-level failed-resource error.

## [2026-07-01] GeoServer Oversized WMS Protection

- Reviewed GeoServer logs showing repeated full-viewport WMS `GetMap` requests such as `WIDTH=1060` and `HEIGHT=597` with `Tiled=false`.
- Added a `public/wms.php` guard that blocks oversized `GetMap` requests above `512x512` and returns a transparent PNG before they reach GeoServer.
- Updated the valves Leaflet WMS overlays to explicitly request tiled `256x256` images, disable zoom-time tile refreshes, and keep a smaller tile buffer.
- Restored the valves map default base layer to `بدون OpenStreetMap`; OSM remains available as an optional base layer.
- Fixed normal-sized WMS tile failures so `GetMap` now returns a transparent PNG instead of HTTP `502` when GeoServer returns an error or exception body.
- Disabled `cite:WATER_PIPE` by default because GeoServer currently returns `ETYPE 4 inconsistent with expected LINE`; the layer remains available in the layer control for manual testing after GeoServer data/style repair.
- Suppressed the GeoServer outage banner when WMS tiles are successfully loading, and made `GetFeatureInfo` popups use a solid white readable background independent of the page theme.

## [2026-07-01] Valves Source Table Correction Follow-up

- Corrected the valves authoritative source from `EME.WATER_PIPE` to `EME.WATER_VALVES`.
- Updated `App\Models\Valves\Valve` to use `W4_GENERAL_ID` and `W4_*` valve columns.
- Updated valve team validation to check `oracle.EME.WATER_VALVES.W4_GENERAL_ID`.
- Verified `EME.WATER_VALVES` reads through the model with `1847` records.

## [2026-07-01] Valves Source Table Correction

- Removed the incorrectly-created local `VALVES` migration file and added `2026_06_30_000009_drop_wrong_valves_table.php` to drop the wrong table.
- Ran `php artisan migrate`; confirmed local `VALVES` table count is `0`.
- Updated `App\Models\Valves\Valve` to read from the external EME source table instead of local `VALVES`.
- Avoided selecting Oracle Spatial `GEOMETRY` by default because it breaks normal PDO fetches.
- Removed `php artisan valves:sync-geoserver` because it populated the wrong local table.
- Removed API routes for creating/updating valves directly; valves are read from the external EME source table, while field actions are recorded in `VALVE_LOGS` and maintenance requests in `VALVE_MAINTENANCE_REPORTS`.
- Verified no pending migrations.

## [2026-06-30] Unused Temporary File Cleanup

- Removed one-off root CSS repair scripts that were not referenced anywhere:
  - `fix_css.py`
  - `fix_wells_css.py`
  - `fix_applayout_css.py`
  - `fix_themes.py`
- Removed macOS `.DS_Store` files from project directories.
- Added `/.venv` to `.gitignore` so local Python tooling does not appear in project cleanup scans.
- Did not delete Oracle/database rows because no table, retention rule, or deletion criteria were specified; data deletion must be explicit and auditable.
- Verified cleanup with `php artisan test`, `npm run build`, `php artisan route:list`, and `php artisan oracle:check`.

## [2026-06-30] Ponytail Skills Installed And Applied

- Verified local Codex skills from `DietrichGebert/ponytail`: `ponytail`, `ponytail-review`, `ponytail-audit`, `ponytail-debt`, `ponytail-gain`, and `ponytail-help`.
- Added Ponytail usage rules to `.ai/coding-rules.md`.
- Ran a Ponytail debt scan; no `ponytail:` deferral markers currently exist in the project.
- Applied the Ponytail review to the portal permissions flow and moved SSO user search out of `SystemPermissionController` into `SystemPermissionRepository`.
- Removed the stale fake SSO callback assertion from `tests/Feature/ExampleTest.php`; the route exists, but dummy callback tokens correctly return package-level failure instead of redirecting.
- Ran a broad Ponytail/system check over the backend workspace:
  - Counted 250 non-vendor/non-build files and 192 PHP files in scope.
  - Ran PHP syntax checks across scoped PHP files successfully.
  - Verified 116 Laravel routes with `php artisan route:list`.
  - Verified Oracle connectivity with `php artisan oracle:check`.
  - Verified migration state with `php artisan migrate:status`.
  - Ran `php artisan test` successfully after removing the stale callback assertion.
  - Ran `npm run build` successfully.
- Ponytail audit notes left for future cleanup:
  - Several legacy controllers still contain direct Eloquent/DB queries and should be refactored gradually behind repositories/services when those flows are next touched.
  - Temporary root scripts `fix_css.py`, `fix_wells_css.py`, `fix_applayout_css.py`, and `fix_themes.py` appear removable if no longer needed.
  - `app/Services/SmsAuthService.php` still contains the expected TODO for integrating the real municipality SMS provider.

## [2026-06-30] WMS Proxy Local Cache Removal

- Removed local disk caching from `public/wms.php` per project owner direction; the proxy no longer reads or writes `storage/app/wms-cache/`.
- Kept the required cURL proxy header `geo@2022_mogwinO: admin`.
- `GetMap` requests now retry once, then return a transparent PNG on upstream GeoServer failures so Leaflet does not show broken tile icons.
- `GetFeatureInfo` failures return `204 No Content` to keep map click inspection quiet when a layer cannot identify a feature.
- Added diagnostic headers (`X-WMS-Proxy-Fallback`, `X-WMS-Proxy-Reason`) to fallback responses so missing tiles can be traced from browser Network tools.
- Added `/wms.php?health=1` and a valves map outage banner so users see a clear Arabic warning when GeoServer is unreachable.

## [2026-06-29] GeoServer Valve Import for Team Assignment

- Fixed the empty valve selector in the valve teams modal by importing WMS/WFS valves into the managed `VALVES` table.
- Added `php artisan valves:sync-geoserver` in `routes/console.php`.
  - Source: GeoServer WFS `cite:WATER_VALVES`.
  - Header: `geo@2022_mogwinO: admin`.
  - Stable local code format: `WV-{GeoServer feature id}`.
  - Maps geometry coordinates into `LATITUDE` and `LONGITUDE`.
- Ran the importer successfully:
  - Synced 1805 valves.
  - Skipped 42 GeoServer features without valid coordinates.
- Added search to the valve selector in `resources/js/Pages/Valves/Teams/Index.vue`, with “تحديد المعروض” so admins can filter and assign groups efficiently.
- Verified `VALVES=1805`, `php -l routes/console.php`, and `npm run build`.

## [2026-06-29] Valve Team All-Valves Assignment & Flutter Binding

- Added migration `2026_06_29_000008_add_assign_all_valves_to_valve_teams_table.php` with `VALVE_TEAMS.ASSIGN_ALL_VALVES`.
- Updated valve team create/update flows so dashboard admins can either select a specific group of valves or enable `إسناد كل المحابس لهذا الفريق`.
- When `ASSIGN_ALL_VALVES` is enabled, individual `VALVE_TEAM_VALVES` rows are cleared/ignored and assigned inspectors can access all current and future valves.
- Updated valve inspector API scoping:
  - `GET /api/valves` returns all valves if any of the inspector's teams has `ASSIGN_ALL_VALVES`.
  - Valve actions/show/logs allow access under the same rule.
- Login now returns each inspector team with `assign_all_valves`.
- Updated `/Volumes/SecondDrive/Scada/scada_flutter/lib/services/api_service.dart` so the Flutter app logs in through this backend's `POST /api/valves/auth/login` using `phone` and `password`, then keeps consuming `GET /api/valves` with the Sanctum token.
- Ran `php artisan migrate`, PHP syntax checks, `npm run build`, `dart format`, and `flutter analyze` for the Flutter app. Flutter analyze reports pre-existing warnings/infos only.

## [2026-06-29] Valves Map Default Base Layer

- Changed the valves map default base layer to `بدون OpenStreetMap`.
- OpenStreetMap remains available in the Leaflet base-layer control but is no longer added on initial map load.
- Verified with `npm run build`.

## [2026-06-29] Valves Map GetFeatureInfo Error Fix

- Removed the `propertyName` parameter from valves WMS `GetFeatureInfo` requests because `cite:WATER_VALVES` returned `PropertyDescriptor is null` for non-matching requested fields.
- Updated the HTML fallback parser to ignore GeoServer exception/error text instead of displaying it inside the popup.
- Added simple table-row parsing for HTML `GetFeatureInfo` responses so real feature fields render as label/value rows when JSON is unavailable.
- Verified with `npm run build`.

## [2026-06-29] Valves Map Background & Popup Readability

- Restored municipal/background layers on the valves map to near-natural opacity so buildings, centerlines, landmarks, and the municipal base layer remain readable behind pipes and valves.
- Kept `cite:WATER_PIPE` and `cite:WATER_VALVES` above all context layers using Leaflet panes, while preserving the visual clarity of lower layers.
- Raised the Leaflet popup pane above map overlays and layer controls.
- Updated `.v-map-popup` to use clear RTL alignment, readable light styling, and better field/value layout.
- Improved WMS feature inspection by requesting JSON first and falling back to `text/html` parsing when a GeoServer layer does not return JSON.
- Verified with `npm run build`.

## [2026-06-29] Valves Map Dedicated Page

- Removed the Leaflet/GeoServer map from `resources/js/Pages/Valves/Dashboard.vue`; the dashboard now focuses on KPIs, recent actions, and maintenance reports.
- Added `resources/js/Pages/Valves/Map.vue` as the dedicated valves map page.
- Added `GET /valves/map` named `valves.map` in `routes/valves.php`.
- Added `ValvesDashboardController::map()` to render the map page with `mapValves`.
- Updated the valves sidebar so `خريطة المحابس` links to `/valves/map`.
- Removed inactive sidebar items that pointed to `#`, including `المحابس` and `تقارير الصيانة`.
- Verified with `php -l` for the controller/routes and `npm run build`.

## [2026-06-29] Valves Dashboard Map Layer UX

- Reworked `resources/js/Pages/Valves/Dashboard.vue` so water network layers are visually prioritized:
  - `cite:WATER_VALVES` renders in the highest WMS pane.
  - `cite:WATER_PIPE` renders above municipal context layers.
  - Buildings, centerlines, and landmarks stay as lower-opacity context layers.
  - Database valve markers render above all GeoServer WMS layers.
- Improved the Leaflet layer control Arabic labels and kept the `بدون OpenStreetMap` base-layer option.
- Added WMS `GetFeatureInfo` click handling through `public/wms.php`; map clicks query visible layers in the order valves → pipes → landmarks → centerlines → buildings.
- Added a shared, RTL-friendly `.v-map-popup` design in `resources/css/valves.css` for readable feature details and local valve marker details.
- Verified the frontend build with `npm run build`.

## [2026-06-29] Valves Dashboard WMTS Proxy Fix

- `public/wms.php` was updated so the valves dashboard map tiles load successfully again.
- The working GeoServer WMTS proxy header is `geo@2022_mogwinO: admin`.
- The proxy uses cURL because Laravel `Http` rejects the non-standard `@` header name.
- `TileOutOfRange` responses now return a transparent PNG instead of a visible tile error.
- Added WMS proxy support and Leaflet overlays for `cite:WATER_VALVES`, `cite:WATER_PIPE`, `cite:V_BUILDING_GEOSRV`, `cite:V_CENTERLINE_SEGMENTS`, and `cite:V_LANDMARKS_V3`.
- Added a `بدون OpenStreetMap` base-layer option so OpenStreetMap can be turned off.
- Removed the persistent GeoServer warning banner that was triggered by individual tile errors even when layers were visible.
- Keep the valves dashboard map using the `public/wms.php` proxy for GeoServer tiles; do not revert to the older documented header because it prevents tiles from loading.

## [2026-06-29] Valves Teams & Inspectors Separation

- Added valve-only field team management, fully separated from the wells module.
- Created and migrated the dedicated valves tables:
  - `VALVE_TEAMS`
  - `VALVE_TEAM_VALVES`
  - `VALVE_INSPECTORS`
  - `VALVE_INSPECTOR_TEAMS`
- Added dashboard pages under the valves system:
  - `/valves/teams` for creating valve field teams and assigning valves to each team.
  - `/valves/inspectors` for creating valve inspector app accounts, using `PHONE` as username, hashed `PASSWORD`, `IS_ACTIVE`, and multiple team assignment.
- Added backend layers for the valves module:
  - Models: `ValveTeam`, `ValveInspector`, `ValveTeamValve`, `ValveInspectorTeam`
  - Repositories: `ValveRepository`, `ValveTeamRepository`, `ValveInspectorRepository`
  - Services: `ValveService`, `ValveTeamService`, `ValveInspectorService`
  - FormRequests under `App\Http\Requests\Valves`
  - Dashboard controllers: `ValveTeamController`, `ValveInspectorController`
- Added `POST /api/valves/auth/login` for valve inspector app login.
- Scoped authenticated valve inspectors to valves assigned to their teams in the valves API.
- Important rule: never reuse wells tables (`TEAMS`, `INSPECTORS`, `TEAM_WELLS`, `INSPECTOR_TEAMS`) for valves workflows.

## [2026-06-25] Wells App Mobile API Sync Integration

- Added new backend API controllers (`AuthController` and `SyncController` in `App\Http\Controllers\Api\Wells`) to support the Wells mobile application.
- Registered mobile API routes under the `wells` prefix in `routes/api.php` (`/api/wells/auth/login`, `/api/wells/sync/wells`, `/api/wells/readings/sync`).
- Created and ran a migration to make `CHLORINE`, `SALINITY`, and `QUALITY` fields nullable in the `well_readings` table, as the mobile app no longer records them.
- Updated the Flutter app `ApiConstants` to point to the new `/api/wells/...` routes.
- Identified and resolved a 500 server error caused by the `App\Models\Inspector` namespace change. Authenticated inspectors are now properly mapped to `App\Models\Wells\Inspector`.
- Fixed `ORA-01400` constraint violation on `INSPECTOR_OID` during sync by ensuring lowercase model properties (`$inspector->oid`) are used when accessing primary keys mapped by the `UppercaseKeys` trait.
- Fixed `ORA-00904` invalid identifier errors by stripping `notes` and `local_id` from the sync payload since these columns don't exist in the `well_readings` Oracle table.
- Fixed `ORA-01858` date formatting errors by wrapping the mobile app's ISO 8601 `READING_DATE` string in `Carbon::parse()` to properly strip the 'T' character and format it for Oracle DB.
- Implemented **Diesel Inventory** feature end-to-end:
  - Created Mobile UI for data entry (DieselScreen) and Controller (DieselController).
  - Designed local SQLite storage for pending/historical records (DieselModel, DieselService) and upgraded database schema version.
  - Implemented offline-first synchronization logic within `SyncService` to upload pending records in batch (`POST /api/wells/diesel-inventories/sync`).
  - Corrected analyzer errors in `DieselScreen` (`super.key`, `initialValue`, `InputDecoration`).
- Fixed data visibility issue in `SyncController@wells`: The API now correctly filters active wells by matching the authenticated Inspector's assigned team(s) using the `teams` relationship, rather than returning all wells in the system.
- **UI Redesign (Mobile App)**: Migrated the `HomeView` layout from a vertical card list into a modern `DefaultTabController` with three top tabs: "Readings", "Diesel Inventory", and "Signals/Notifications". This greatly cleans up the inspector's workflow in the field.
- **Implemented Notifications/Signals Sync Feature**:
  - Added new backend sync routes (`GET /api/wells/sync/notification-types`, `POST /api/wells/notifications/sync`) to support offline-first notifications in the field.
  - Upgraded SQLite schema to v5 and created `NotificationModel` & `NotificationTypeModel`.
  - Built out `NotificationScreen` to allow inspectors to write messages, pick a well, and pick a predefined notification type offline. 
  - Overhauled `SyncService` to upload offline notifications to the backend.
## [2026-06-30] Central Portal System Permissions

- Removed the hard-coded personal identity reference from the local dev-login helper; local login now uses `MOG_LOCAL_ADMIN_USER_ID`.
- Moved system access management out of the Fleet dashboard and into the unified portal at `/permissions`.
- Added a central per-system permission UI for SSO users:
  - `fleet` => تتبع المركبات
  - `wells` => إدارة الآبار
  - `valves` => متابعة المحابس
- The permission-management screen itself is limited by `MOG_PERMISSIONS_ADMIN_USER_IDS` outside local development.
- `PortalController` and `EnsureSystemAccess` now resolve system access through `SystemPermissionRepository` in every environment.
- `FLEET_ADMIN_ALLOWLIST` remains only a global admin gate, not the per-system permission source.
- **SSO ID resolution and permissions search update:**
  - Diagnosed a critical issue where MOG SSO logs users in using their internal database `id` from `mog_users.users_list` (e.g. 1061), while admins were manually typing the `emp_id` (e.g. 2541) into the permissions input field.
  - Reverted the backend `usersById` query to strictly use `id` as the primary key instead of `emp_id` to ensure permissions are checked against the exact SSO session ID.
  - Built an AJAX user search auto-complete inside `Permissions.vue` (`/permissions/search-users`), allowing administrators to find SSO users by `full_name` or numeric `id` and visually mapping the correct `id` to the permission record without guessing.

- **Premium UI/UX Redesign & Typography**:
  - Implemented a "Glassmorphism" design system using a new custom `GlassCard` widget to give the app a modern, translucent, premium feel.
  - Replaced native `TabBar` and `DefaultTabController` in all forms (`HomeView`, `DieselScreen`, `NotificationScreen`) with a custom `AnimatedSegmentedTab` micro-animation component.
  - Form fields were restyled to be borderless with subtle filled backgrounds.
  - Applied the **Cairo** font globally across the entire application by integrating the `google_fonts` package and updating all text/app bar/navigation themes in `app_theme.dart`.

## [2026-06-25] Local Dev Login Portal Shortcuts

- `/admin/dev-login` is the local development entry point for the unified portal card selector.
- Optional direct shortcuts are supported:
  - `/admin/dev-login?system=wells`
  - `/admin/dev-login?system=fleet`
  - `/admin/dev-login?system=valves`
- Superseded on 2026-06-30: system cards are now governed by `CONTROL_UNIT.SYSTEM_PERMISSIONS`; repository fail-open behavior only applies when Oracle or the permission table is unavailable.

## [2026-06-24] CONTROL_UNIT Production Schema Switch

- Production Oracle connection moved to the `CONTROL_UNIT` schema on `gisdb`.
- Laravel migrations were verified on `CONTROL_UNIT`; the pending `FLEET_ADMIN_ALLOWLIST` migration was run successfully.
- Direct hard-coded old-schema references were removed from active Models and Repositories.
- Oracle source table/column names are now configured through `config/fleet.php` and `.env` keys.
- Driver and assigned vehicle lookup now use `FLEET_USERS` in `CONTROL_UNIT`:
  - Driver: `ID`, `USER_MOBILE`, `USER_NAME`, `USER_ID`.
  - Assigned vehicle: `VEHICLE_ID`, `VEHICLE_NO`, `VEHICLE_MODEL`, `VEHICLE_TYPE`.
- `MOG_VEHICLES` exists as a general vehicle reference table, but driver-scoped vehicle lookup must continue to use `FLEET_USERS` unless a verified assignment relationship is added.
- Added `php artisan oracle:copy-schema-data {source_schema}` for Oracle-to-Oracle managed-data migration.
  - Default mode is dry-run and shows source readability, source counts, target counts, and shared columns.
  - Use `--execute` to copy rows and `--replace` to clear `CONTROL_UNIT` target rows before copying.
  - The command requires `CONTROL_UNIT` to have `SELECT` grants on the old source schema tables.

## [2026-06-22] Original GPS Route Readability

- Final deployment decision: production runs on Windows and must not require Docker or additional map-matching software.
- The admin trip map displays only the authoritative original `trip_points`; no road-matching service or external routing API is required.
- Removed the original/corrected route toggle and all OSRM configuration and server calls.
- Added clear green start and red end markers with permanent Arabic labels.
- Added a white casing around the GPS trace so it remains readable over streets, buildings, and map labels.
- Repeating direction arrows show chronological movement.
- Return legs are detected conservatively and colored orange when the trip comes materially back toward its start; the outbound leg remains blue.
- Checksums, distance, virtual fuel calculations, and audit data remain unchanged.
- Future work must not reintroduce route snapping or transmit municipal vehicle traces to a third party without explicit product-owner approval.

## [2026-05-24] SSO Logout Fix & Error Logs Button Integration

### 1. Unified SSO Logout Route Usage
- **Context**: The driver app and admin dashboard use MOG SSO for authentication.
- **Requirement**: Use the library's routes directly without any external overrides in the main application's `routes/web.php` or `AdminAuthController.php`.
- **Status**: Reverted the custom logout override route and method to ensure the application delegates directly to the package's `Mog\ProjectHelper\AuthHelpers@logout` controller/action via the registered library routes.

### 2. Guard Static Analysis & IDE Error Resolution
- **Context**: Calls like `Auth::guard('admin')->logout()` and `Auth::guard('admin')->loginUsingId($id)` triggered static analysis warnings/errors in PHPStan (`Call to undefined method Illuminate\Contracts\Auth\Guard::...()`) and IDE squiggly lines because the base `Guard` contract does not define these stateful methods.
- **Solution**:
  - Implemented safe type checking against `\Illuminate\Contracts\Auth\StatefulGuard` before invoking `logout()` and `loginUsingId()` in `routes/web.php` and `AdminAuthController.php`. This eliminated IDE warnings and resolved static analysis compiler errors.

### 3. Server Logs Dashboard Integration
- **Context**: Integrated a server log inspection dashboard available on production/local development via `/mog/logs` routes from the helper package.
- **Actions**:
  - Updated `app/Http/Middleware/HandleInertiaRequests.php` to safely resolve the `mog.logs.index` route within a `try-catch` block, preventing crash loops during route caching/boot-time commands if the helper package's routes are not loaded.
  - Dynamically shared `auth.can_view_logs` status to only allow authorized user IDs (configured in `mog-helpers.logs_allowed_user_ids`).
  - Added a responsive "Error Log (سجل الأخطاء)" button to the dashboard layout (`Dashboard.vue`) to allow authorized administrators to inspect production log traces directly.

## [2026-06-29] GeoServer WMS Tile Caching & Oracle Spatial Coordinate Fixes

### 1. `php artisan serve` Blocking Fix via WMS Disk Cache
- **Context**: The `Valves/Map.vue` loaded 5 heavy WMS tiles (256x256) per GeoServer layer. `php artisan serve` is single-threaded, and layers like `fltr_citizens` could take 22 seconds to render, causing a pileup of 100+ requests. The browser would time out the requests, returning blank/transparent tiles.
- **Solution**: Completely rewrote `public/wms.php` to include an MD5-based disk cache.
  - Tiles are fetched from GeoServer once and saved to `storage/app/wms-cache/`.
  - Subsequent requests are served instantly (under 0.05 seconds), preventing server thread exhaustion.
- **Superseded on 2026-06-30**: Local disk cache was removed from `public/wms.php`; upstream/server-side GeoServer caching is now expected.

### 2. Oracle Column Case-Sensitivity Bug (`LATITUDE` vs `latitude`)
- **Context**: Map markers (all 1,805 valves) were defaulting to the Atlantic Ocean (`[0, 0]`) instead of Gaza.
- **Cause**: Laravel Models accessed Oracle DB columns strictly with `$v->LATITUDE`. Since Oracle columns were mapped by Laravel to lowercase `latitude`, `$v->LATITUDE` returned `null`, which was float-casted to `0.0`.
- **Solution**: Updated `ValvesDashboardService.php` to map `$v->latitude` correctly, resolving the Null Island rendering bug.

### 3. WGS84 Datum Offset & Toggleable Interactive Markers
- **Context**: Because the interactive Leaflet Database markers and GeoServer WMS pipes originate from differently transformed Spatial Reference Systems (WGS84 vs EPSG:28191 Palestine 1923), the interactive Leaflet markers suffered a 50m datum offset visually compared to the WMS tile streets. Furthermore, the markers were unconditionally overlaid via `addTo(map)`, leading users to believe the WMS layer control was broken because turning off "Water Valves" in Leaflet did not hide the 1,805 interactive dots.
- **Solution**:
  - Grouped the interactive marker dots into a distinct `L.layerGroup` and exposed it in the Leaflet controls as **"علامات المحابس (تفاعلية)"**.
  - Added a **"بدون OpenStreetMap"** empty base layer group so administrators can shut off the OSM background map completely to solely inspect the WMS pipe layers.
