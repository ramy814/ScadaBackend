# Valves Module — Project Overview
> وحدة المحابس البلدية (Scada Backend Migration)

## Core Purpose
The Valves module handles the municipality's water valves, tracking their geographical locations, operational status, user actions (opening/closing), and maintenance reports. It was originally a standalone application (`scada-backend`) and was migrated to the unified Fleet Management system.

## Architectural Principles
- **Oracle Native**: Valve source data is read from `EME.WATER_VALVES` using `W4_GENERAL_ID` as the identifier. Do not create, sync, or depend on a local `VALVES` table.
- **Managed Operational Tables**: `VALVE_LOGS`, `VALVE_MAINTENANCE_REPORTS`, `VALVE_TEAMS`, `VALVE_TEAM_VALVES`, `VALVE_INSPECTORS`, and `VALVE_INSPECTOR_TEAMS` live in the application Oracle schema and use `OID` primary keys.
- **SSO Integration**: The module utilizes `MogSsoUser` (mapped to `mog_users.users_list` via ID) for assigning actions and maintenance resolution, removing the need for a dedicated `users` table.
- **UppercaseKeys Trait**: Eloquent models use `App\Traits\Wells\UppercaseKeys` to smoothly map uppercase Oracle columns.
- **API & Dashboard**: API routes are defined in `routes/valves.php`. The frontend uses Inertia under the `/valves` prefix.

## Frontend
- Layout: `resources/js/ValvesSystem/Layouts/AppLayout.vue`
- CSS: `resources/css/valves.css` — aliases the global `--theme-*` surfaces from `resources/css/app.css` and keeps the Valves orange identity through `--valves-primary`. The Valves dark palette is the authoritative shared dark theme for every system; do not fork a separate base dark palette here.
- Dashboard page: `resources/js/Pages/Valves/Dashboard.vue`
- Map page: `resources/js/Pages/Valves/Map.vue`
- Teams page: `resources/js/Pages/Valves/Teams/Index.vue`
- Inspectors page: `resources/js/Pages/Valves/Inspectors/Index.vue`
- Dashboard renders: status distribution bars, recent inspector actions, and maintenance reports table. Do not render the Leaflet map inside the dashboard.

## Current Authoritative Web Map Contract (updated 2026-09-23)

This section is the source of truth for the working web map. It supersedes every older map implementation note in `.ai/changelog.md`. Do not invent another proxy, client-side GeoServer feature flow, map engine, cache, health endpoint, or GeoServer host unless the product owner explicitly requests a redesign.

### Entry Points And Owners
- Dedicated page: `GET /valves/map` → `resources/js/Pages/Valves/Map.vue`; it exposes mutually exclusive OpenStreetMap and GeoServer modes and receives Oracle valve markers through `mapValves`.
- Team map selector: `resources/js/Pages/Valves/Teams/ValveMapSelector.vue`; it receives Oracle-backed WGS84 valve points and does not depend on GeoServer.
- Server-side feature lookup: `GET /valves/map-features?layers=...&lat=...&lng=...&zoom=...` → `App\Http\Controllers\Api\Valves\MapController::features()` → `App\Services\Valves\ValvesMapService::features()`.
- `mapFeaturesUrl` is passed only to the dedicated valves map, which calls it only while GeoServer mode is active.
- `public/wms.php` owns upstream authentication for the GeoWebCache WMS and WMTS services, accepts only `g_page=/wms` or `g_page=/wmts`, allows production plus `localhost` and `127.0.0.1` referrers, exposes the upstream cache result as `X-WMS-Cache`, and returns a transparent PNG fallback when the upstream fails.
- The public GeoWebCache demo URL is metadata/UI, not a browser tile template. Actual `GetTile` calls target `/geoserver/gwc/service/wmts` and require valid Basic authentication through the same-origin proxy; never place GeoServer credentials in Vue or a public URL.

### Tile Loading
- OpenStreetMap is the default mode and contains only `https://tile.openstreetmap.org/{z}/{x}/{y}.png` plus interactive markers loaded directly from `EME.WATER_VALVES` through the `mapValves` Inertia prop.
- GeoServer is a separate base-map mode. Switching to it removes OpenStreetMap and the Oracle marker group, enables the GeoServer layer selector, and loads the configured WMTS/WMS layers. Returning to OpenStreetMap removes every GeoServer layer and hides that selector.
- The default `mog:gaza general map` background uses direct WMTS `GetTile` requests through `/wms.php?g_page=/wmts` and the server-published `EPSG:900913` matrix set. It is the Web Mercator counterpart of the supplied `EPSG:4326` demo and aligns with Leaflet/OSM. Optional detail overlays retain cached WMS through `/wms.php?g_page=/wms`.
- Keep 256-pixel tiles, `updateWhenZooming: false`, and `keepBuffer: 2` for both the WMTS background and tiled WMS overlays.
- Keep the transparent `errorTileUrl`; individual tile errors must not create broken-image icons or a persistent outage state.
- Do not use browser `fetch` or a direct browser tile URL for the upstream GeoServer. WMTS and WMS image tiles must stay behind the same-origin proxy.

### Layers And Order
- Oracle valve markers are Leaflet circle markers rendered only in OpenStreetMap mode from WGS84 coordinates attached by `ValveRepository` and filtered by `ValveService` to the Gaza bounding box.
- GeoServer mode enables the opaque `mog:gaza general map` layer as its default background. `mog:V_BUILDING_GEOSRV`, `mog:V_CENTERLINE_SEGMENTS`, and `mog:V_LANDMARKS_V2` remain optional detail overlays disabled by default, avoiding an apparently blank canvas made only from sparse transparent layers.
- The new server does not advertise `WATER_VALVES` or `WATER_PIPE` in WMS capabilities. Interactive valve markers and the team selector use WGS84 coordinates from `EME.WATER_VALVES` over OpenStreetMap.

### Click And Feature Data
- Browser clicks never call GeoServer or `wms.php` for feature data.
- Clicking an interactive OpenStreetMap valve marker reads the already-loaded `mapValves` prop and displays its `EME.WATER_VALVES` fields directly; it does not make another request.
- While GeoServer mode is active, `Map.vue` sends the click latitude, longitude, zoom, and every currently visible layer name to authenticated same-origin `/valves/map-features`; this includes the default composite `mog:gaza general map` background. Switching to OpenStreetMap aborts an active lookup and closes its popup.
- `ValvesMapService` performs an authenticated WMS `GetFeatureInfo` query against the new GeoServer and returns a JSON array of feature-property objects with internal `_feature_layer` and `_feature_id` labels. Its geographic search buffer narrows with Leaflet zoom, and duplicate features returned by a group plus an overlay are removed by feature ID.
- `config/geoserver.php` is the shared source for the GeoServer base URL and credentials used by `public/wms.php` and `ValvesMapService`. The removed `appdev-01` WFS hosts and `cite:*` aliases must not be restored. PHP HTTP streams remain sufficient; an unreachable/invalid upstream returns an empty collection instead of HTTP 500.
- Operational check on 2026-09-23: the demo pages for both `EPSG:4326` and `EPSG:900913` returned HTTP 200, but actual WMTS `GetTile` and protected WMS requests returned HTTP 401 with the credentials currently configured in this checkout. A blank transparent map can therefore be an authentication failure rather than a cache or Leaflet failure. Update `GEOSERVER_USERNAME`/`GEOSERVER_PASSWORD` in the deployment environment and clear Laravel configuration after the GeoServer administrator provides valid credentials; do not disable authentication or forward it to the browser.
- If a water-valve feature omits `W4_GENERAL_ID` from properties, the service derives it from GeoServer `feature.id`. This ID maps to `EME.WATER_VALVES.W4_GENERAL_ID` and is required for team assignment.
- In `Map.vue`, keep the `AbortController` behavior so a new click cancels an older lookup.
- The dedicated map must not display raw error/HTML text and shows localized loading, empty, and failure popups.

### Health And Cache
- OpenStreetMap mode performs no GeoServer health probe or WMS request.
- `public/valves-map-sw.js` and WMS health behavior are not dependencies of OpenStreetMap mode.

### Evidence-First Troubleshooting
- First identify whether the failure is page rendering, tile loading, health probing, or feature lookup; these are separate paths.
- Treat a successful `/gwc/demo/...` page as proof only that the grid metadata exists. Verify one real `/gwc/service/wmts?...REQUEST=GetTile...` response through the proxy; HTTP 401 means credentials/access, while `X-WMS-Proxy-Fallback: 1` means the proxy hid an upstream failure behind the transparent PNG fallback.
- Inspect the actual failing URL and response before editing code.
- While OpenStreetMap mode is selected, the `/valves/map` browser network log must contain OpenStreetMap tile requests and no GeoServer, WMS, `/wms.php`, or `/valves/map-features` requests.
- Dedicated GeoServer-map feature lookup must use `/valves/map-features`; seeing browser-side `GetFeatureInfo`, raw `error`, or direct GeoServer fetch means the client is stale or regressed.
- After frontend changes, run `npm run build` because production may serve committed `public/build` assets, then hard-refresh so the updated asset and service worker take control.
- Do not propose OSRM, map matching, a new database table, importing GeoServer features, changing Oracle geometry, or another mapping library for a WMS display/lookup issue.

Backend valve records are read from `EME.WATER_VALVES`. Because its `GEOMETRY` column is Oracle Spatial, do not select it with ordinary Eloquent queries. `ValveRepository::forMap()` reads normal attributes through the model and attaches WGS84 coordinates using the existing Oracle Spatial transform; `ValveService::getMapValves()` excludes missing/outlier coordinates outside the Gaza bounding box before shaping the Inertia payload. GeoServer/WMS feature lookup remains server-side through `ValvesMapService` for the dedicated GeoServer mode and the separate team selector.

## Current Authoritative Inspector Mobile Map Contract (2026-07-16)

- App: `/Volumes/SecondDrive/Scada/scada_flutter`.
- OpenStreetMap is the only mobile tile/base-map source.
- Authenticated `GET /api/valves` is the only mobile marker source and returns only valves assigned to the inspector's teams, except the explicit `ASSIGN_ALL_VALVES` team rule.
- `ValveController::index()` delegates to `ValveService` and `ValveRepository`; do not move assignment or Oracle Spatial logic back into the controller.
- `ValveRepository` reads coordinates from `EME.WATER_VALVES.GEOMETRY`, whose SRID is 2039, and transforms them to WGS84/EPSG:4326 for `latitude` and `longitude` response attributes.
- The mobile routes `/api/dashboard/map-data`, `/api/dashboard/map-settings`, and `/api/dashboard/map-features` were removed and must not be recreated.
- The mobile app must not call GeoServer, WMS/WFS, `/wms.php`, or web `/valves/map-features`. Marker taps use the valve data already returned by `/api/valves`.
- This mobile contract does not change the separate authoritative web-map contract above. `ValvesMapService`, `/valves/map-features`, web WMS callers, and the externally fixed `public/wms.php` remain web-only.

## Backend Service & Controller
- `App\Services\Valves\ValvesDashboardService` — aggregates stats for the dashboard
- `App\Http\Controllers\Valves\ValvesDashboardController` — renders `Valves/Dashboard` via Inertia
- `App\Http\Controllers\Valves\ValveController` — read-only valve listing/details plus action/log endpoints
- `App\Http\Controllers\Valves\ValveMaintenanceController` — API maintenance reports
- `App\Http\Controllers\Valves\ValveTeamController` — dashboard CRUD for valve-only field teams
- `App\Http\Controllers\Valves\ValveInspectorController` — dashboard CRUD for valve-only inspectors and app accounts
- `App\Http\Controllers\Api\Valves\AuthController` — valve inspector mobile login using phone/password
- `App\Services\Valves\ValveService` and `App\Repositories\Valves\ValveRepository` — assigned mobile valve list plus Oracle Spatial coordinate transformation
- `App\Services\Valves\ValveTeamService` — team creation/update and valve assignment
- `App\Services\Valves\ValveInspectorService` — inspector account creation/update, activation, team assignment, and authentication
- The old `php artisan valves:sync-geoserver` importer was removed because it populated the wrong local `VALVES` table.

## Teams & Inspectors
- Valves teams and inspectors are intentionally separate from the wells module.
- Use `VALVE_TEAMS`, `VALVE_TEAM_VALVES`, `VALVE_INSPECTORS`, and `VALVE_INSPECTOR_TEAMS`; never reuse wells tables (`TEAMS`, `INSPECTORS`, `TEAM_WELLS`, `INSPECTOR_TEAMS`) for valves.
- A valve inspector can belong to multiple valve teams.
- Valve teams support `ASSIGN_ALL_VALVES`. When true, the team grants all current and future valves to assigned inspectors and the individual `VALVE_TEAM_VALVES` pivot list is cleared/ignored for access.
- The teams dashboard supports either selecting a specific group of valves or enabling “إسناد كل المحابس لهذا الفريق”.
- The teams dashboard valve selector reads from `EME.WATER_VALVES` through `ValveRepository`.
- Team create/edit forms support both list selection and `ValveMapSelector.vue`. The selector renders the filtered Oracle-backed map payload on OpenStreetMap; marker clicks update the same `valve_ids` form array used by list selection.
- Team valve lists always show currently assigned/selected valves first so administrators can revoke assignments without searching through the full list.
- If a map click returns overlapping valves, the selector must show all matching valves and let the administrator toggle each one explicitly rather than silently choosing the first feature.
- `VALVE_TEAM_VALVES.VALVE_OID`, `VALVE_LOGS.VALVE_OID`, and `VALVE_MAINTENANCE_REPORTS.VALVE_OID` store `EME.WATER_VALVES.W4_GENERAL_ID`.
- The inspector mobile username is `PHONE`; `PASSWORD` is hashed by Eloquent casts; `IS_ACTIVE` controls login eligibility.
- Dashboard routes:
  - `/valves`
  - `/valves/map`
  - `/valves/teams`
  - `/valves/inspectors`
- API login route: `POST /api/valves/auth/login`
- SCADA login issues only the Sanctum `scada` ability. All `/api/dashboard`, `/api/valves`, and `/api/valve-maintenance` endpoints require both that ability and a `ValveInspector` principal; Fleet Driver and Wells tokens are rejected before controllers run.
- Valve actions accept optional `local_id`. New SCADA builds send their persisted pending-action ID on both initial send and retry; `VALVE_LOGS` has unique `(USER_OID, CLIENT_LOCAL_ID)`, and `ValveActionService` atomically reuses an existing action without duplicating its maintenance report. Keep the field optional for older builds.
- Authenticated valve inspectors are scoped to valves assigned to their teams.
- The SCADA Flutter app lives outside this repo at `/Volumes/SecondDrive/Scada/scada_flutter`; keep it pointed at this backend's `/api/valves/auth/login` and `/api/valves` endpoints.
- The SCADA Flutter map must use OpenStreetMap plus team-scoped markers from `/api/valves`; it does not share web GeoServer layers or `ValvesMapService`.
- Inspect the Flutter app and the “Current Authoritative Inspector Mobile Map Contract” before proposing mobile map changes. Never infer mobile behavior from the web map.
