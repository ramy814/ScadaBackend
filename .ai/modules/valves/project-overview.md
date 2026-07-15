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
- CSS: `resources/css/valves.css` — uses `--valves-primary: #e8590c` orange theme
- Dashboard page: `resources/js/Pages/Valves/Dashboard.vue`
- Map page: `resources/js/Pages/Valves/Map.vue`
- Teams page: `resources/js/Pages/Valves/Teams/Index.vue`
- Inspectors page: `resources/js/Pages/Valves/Inspectors/Index.vue`
- Dashboard renders: status distribution bars, recent inspector actions, and maintenance reports table. Do not render the Leaflet map inside the dashboard.
- The valves map is a dedicated page at `/valves/map` and renders the Leaflet/- **Map Architecture**: 
  - The default map base is `بدون OpenStreetMap` added on initial load (`noBaseLayer.addTo(map)`). OpenStreetMap is optional in the layer control.
  - GeoServer WMS tiles are loaded **directly from the GeoServer URL** (`GEOSERVER_WMS_URL`) by the browser to avoid single-threaded PHP/Laravel server blocking issues.
  - `public/wms.php` is only used as a CORS proxy for `GetFeatureInfo` requests (using fetch API) because direct browser requests are blocked by CORS.
  - `public/wms.php` does not use local disk cache; GeoServer/server-side caching is expected upstream.
  - `public/wms.php?health=1` is the map health endpoint. The valves map checks it on load and every 30 seconds; if GeoServer is unreachable and no GeoServer tile loaded recently, show a clear Arabic outage banner to the user. If WMS tiles are loading, suppress the outage banner even when the health probe is stale or fails.
  - `public/wms.php` blocks oversized WMS `GetMap` requests above `256x256` and returns a transparent PNG before reaching GeoServer.
  - Leaflet WMS overlays must stay tiled (`tileSize: 256`, `tiled: true`) and should avoid zoom-time refresh storms (`updateWhenZooming: false`, `keepBuffer: 2`). Do not switch back to single-image/full-viewport WMS rendering.
  - WMS operational overlays are loaded directly and exposed in the Leaflet layer control:
    - `cite:WATER_VALVES` — Water Valves — WMS `1.1.1`
    - `cite:WATER_PIPE` — Water Pipe — WMS `1.1.0` — enabled by default.
    - `cite:V_BUILDING_GEOSRV` — Buildings — WMS `1.1.0` — available but disabled by default.
    - `cite:V_CENTERLINE_SEGMENTS` — Centerline Segments — WMS `1.1.0` — enabled by default.
    - `cite:V_LANDMARKS_V2` — Landmarks — WMS `1.1.0` — enabled by default.

  - Layer drawing order is intentional:
    - Context layers (`Buildings`, `Centerline Segments`, `Landmarks`) stay in `municipalContextPane` with high opacity (near natural colors) but lower z-index than the water network. Buildings are disabled by default.
    - `cite:WATER_PIPE` is drawn above context layers in `networkPane` and is enabled by default.
    - `cite:WATER_VALVES` is drawn above pipes in `valvesPane`.
    - Database valve circle markers are above all WMS layers in `dbMarkersPane`.
  - Keep pipes and valves visually dominant; do not raise buildings, landmarks, or centerlines above the water network.
  - Map page clicks use WMS `GetFeatureInfo` through `public/wms.php` (CORS proxy), querying visible layers in priority order: valves, pipes, `cite:V_LANDMARKS_V2`, centerlines, buildings. The popup uses the shared `.v-map-popup` styling in `resources/css/valves.css`, is forced RTL, must use a solid readable background, and must float above WMS layers and the layer control.
  - `GetFeatureInfo` uses an `AbortController` in `Map.vue` to cancel previous pending requests on subsequent clicks or component unmounting to avoid map freezing.
  - `GetFeatureInfo` should request JSON first and fall back to `text/html`/text parsing because some GeoServer layers may not consistently support JSON responses.
  - Do not pass `propertyName` in valves WMS `GetFeatureInfo` requests; `cite:WATER_VALVES` can return `PropertyDescriptor is null` when requested fields do not exactly match the GeoServer feature type.
  - `public/wms.php` retries WMS `GetMap` requests, then always returns a transparent PNG for failed map tiles, including `TileOutOfRange`, unreachable upstream, 5xx responses, and GeoServer exception bodies. Do not return HTTP `502` for `GetMap`; browser tile failures create noisy console errors and make the map feel broken.
  - `public/valves-map-sw.js` keeps up to 800 successful WMS tiles in browser Cache Storage for 30 days. It never caches responses marked with `X-WMS-Proxy-Fallback`, and it must not require changes to `public/wms.php`.
  - The map uses a transparent `errorTileUrl` to prevent broken image icons if the GeoServer times out (502 Bad Gateway).
  - Do not show a persistent GeoServer outage banner based on individual Leaflet `tileerror` events; some out-of-range or empty tiles can fail while the configured layers are still displaying correctly. Use the `/wms.php?health=1` check for user-facing GeoServer outage state.
  - Backend valve records are read from `EME.WATER_VALVES`. Because its geometry is Oracle Spatial (`GEOMETRY`), do not select it with normal Eloquent queries; map display should rely on WMS layers unless a repository explicitly extracts coordinates safely.

## Backend Service & Controller
- `App\Services\Valves\ValvesDashboardService` — aggregates stats for the dashboard
- `App\Http\Controllers\Valves\ValvesDashboardController` — renders `Valves/Dashboard` via Inertia
- `App\Http\Controllers\Valves\ValveController` — read-only valve listing/details plus action/log endpoints
- `App\Http\Controllers\Valves\ValveMaintenanceController` — API maintenance reports
- `App\Http\Controllers\Valves\ValveTeamController` — dashboard CRUD for valve-only field teams
- `App\Http\Controllers\Valves\ValveInspectorController` — dashboard CRUD for valve-only inspectors and app accounts
- `App\Http\Controllers\Api\Valves\AuthController` — valve inspector mobile login using phone/password
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
- Team create/edit forms support both list selection and `ValveMapSelector.vue`. Map clicks query `cite:WATER_VALVES` through `public/wms.php`, derive `W4_GENERAL_ID` from the GeoServer feature ID, and update the same `valve_ids` form array used by list selection.
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
- Authenticated valve inspectors are scoped to valves assigned to their teams.
- The SCADA Flutter app lives outside this repo at `/Volumes/SecondDrive/Scada/scada_flutter`; keep it pointed at this backend's `/api/valves/auth/login` and `/api/valves` endpoints.
- The SCADA Flutter app must use this backend as the single map source:
  - `GET /api/dashboard/map-settings` for WMS URL and layer definitions.
  - `GET /api/dashboard/map-features` for map click feature lookup.
  - WMS tiles must be loaded through the returned backend `/wms.php` URL, never direct GeoServer, `geo.mogaza`, or `cs.mogaza` URLs.
  - Mobile map layer defaults must match the web valves map: valves, pipes, landmarks (`cite:V_LANDMARKS_V2`), and centerlines are visible by default; buildings are disabled.
