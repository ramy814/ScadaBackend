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

## Current Authoritative Web Map Contract (2026-07-16)

This section is the source of truth for the working web map. It supersedes every older map implementation note in `.ai/changelog.md`. Do not invent another proxy, client-side WFS flow, map engine, cache, health endpoint, or GeoServer host unless the product owner explicitly requests a redesign.

### Entry Points And Owners
- Dedicated page: `GET /valves/map` → `resources/js/Pages/Valves/Map.vue`.
- Team map selector: `resources/js/Pages/Valves/Teams/ValveMapSelector.vue`.
- Server-side feature lookup: `GET /valves/map-features?layers=...&lat=...&lng=...` → `App\Http\Controllers\Api\Valves\MapController::features()` → `App\Services\Valves\ValvesMapService::features()`.
- The route URL is passed to Vue as `mapFeaturesUrl`; do not hard-code a replacement feature endpoint in Vue.
- `public/wms.php` is an externally fixed file for this project. Do not edit, refactor, harden, replace, or add behavior to it unless the user explicitly asks to change that exact file.

### Tile Loading
- Local HTTP development (`http://127.0.0.1:*`): Leaflet image tiles load directly from `http://appdev-01.mog.org:8080/geoserver/cite/wms`.
- HTTPS production: Leaflet image tiles load through `/wms.php?g_page=/cite/wms` to avoid Mixed Content.
- The default base is `بدون OpenStreetMap`; OpenStreetMap is optional in the layer control.
- Keep tiled WMS requests: `tileSize: 256`, `tiled: true`, `updateWhenZooming: false`, `keepBuffer: 2`.
- Keep the transparent `errorTileUrl`; individual tile errors must not create broken-image icons or a persistent outage state.
- Do not use browser `fetch` to call local GeoServer. Cross-origin fetch is blocked by CORS, while `<img>` tile requests work.

### Layers And Order
- Enabled: `cite:V_CENTERLINE_SEGMENTS` (WMS 1.1.0), `cite:V_LANDMARKS_V2` (1.1.0), `cite:WATER_PIPE` (1.1.0), and `cite:WATER_VALVES` (1.1.1).
- Available but disabled by default: `cite:V_BUILDING_GEOSRV` (1.1.0).
- Context layers use `municipalContextPane`; pipes use `networkPane`; valves use `valvesPane`.
- Pipes and valves remain above municipal context layers. Do not invent a `dbMarkersPane`; the current page only uses `mapValves` to fit the initial bounds.

### Click And Feature Data
- Browser clicks never call GeoServer or `wms.php` for feature data.
- `Map.vue` queries visible layers through authenticated `/valves/map-features` in this order: valves, pipes, landmarks, centerlines, buildings.
- `ValveMapSelector.vue` queries the same endpoint for `cite:WATER_VALVES`.
- `ValvesMapService` performs the GeoServer WFS query server-side and returns a JSON array of feature-property objects.
- If a water-valve feature omits `W4_GENERAL_ID` from properties, the service derives it from GeoServer `feature.id`. This ID maps to `EME.WATER_VALVES.W4_GENERAL_ID` and is required for team assignment.
- Keep the `AbortController` behavior so a new click cancels an older lookup.
- Never display raw error/HTML text as popup data. An empty/failed lookup produces no feature popup.

### Health And Cache
- Local development does not run a `GetCapabilities` browser health probe because CORS rejects it.
- HTTPS production checks `GetCapabilities` through `/wms.php?g_page=/cite/wms` every 30 seconds; recent successful tile loads also mark the map healthy.
- There is no dependency on `/wms.php?health=1` in the current web map.
- `public/valves-map-sw.js` is network-first for same-origin production `GetMap` requests. It verifies the PNG signature, caches up to 800 successful tiles, and uses a cached tile for up to 30 days only when the network response fails or is not a valid PNG.
- Local direct GeoServer tiles do not pass through this service-worker cache.

### Evidence-First Troubleshooting
- First identify whether the failure is page rendering, tile loading, health probing, or feature lookup; these are separate paths.
- Inspect the actual failing URL and response before editing code.
- Local tile URL must start with `http://appdev-01.mog.org:8080/geoserver/cite/wms?`.
- Production tile URL must start with `/wms.php?g_page=/cite/wms&`.
- Web feature lookup must use `/valves/map-features`; seeing browser `GetFeatureInfo`, raw `error`, or direct GeoServer fetch means the client is stale or the contract regressed.
- After frontend changes, run `npm run build` because production may serve committed `public/build` assets, then hard-refresh so the updated asset and service worker take control.
- Do not propose OSRM, map matching, a new database table, importing GeoServer features, changing Oracle geometry, or another mapping library for a WMS display/lookup issue.

Backend valve records are read from `EME.WATER_VALVES`. Because its `GEOMETRY` column is Oracle Spatial, do not select it with ordinary Eloquent queries; the map display remains WMS-backed and feature lookup remains server-side through `ValvesMapService`.

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
- Team create/edit forms support both list selection and `ValveMapSelector.vue`. Map clicks use authenticated `/valves/map-features`, resolve `W4_GENERAL_ID` through `ValvesMapService`, and update the same `valve_ids` form array used by list selection.
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
- Mobile and web share layer names and `ValvesMapService`, but they are different clients. Do not copy the web's local-direct tile rule into Flutter or infer Flutter behavior without inspecting `/Volumes/SecondDrive/Scada/scada_flutter` and `GET /api/dashboard/map-settings`.
