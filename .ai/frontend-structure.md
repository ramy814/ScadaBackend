# Frontend Folder Structure (Admin Dashboard)

- The permission editor hides the Wells system-entry checkbox. Existing Wells
  grants stay in the form payload when editing other permissions; hiding the
  control must not revoke existing access. Wells action permissions remain visible.

The frontend is an Inertia.js Single Page Application built with Vue 3.

```text
resources/
├── css/
│   ├── app.css                 # Custom CSS, variables, and utility classes
│   ├── valves.css              # Valves module dashboard styling
│   └── wells.css               # Wells module dashboard styling
├── js/
│   ├── app.js                  # Vue 3 App Initialization (no ZiggyVue)
│   ├── Components/
│   │   ├── PrimaryButton.vue
│   │   ├── Modal.vue
│   │   ├── Dropdown.vue
│   │   └── TripStatusBadge.vue # Reusable UI components
│   ├── Layouts/
│   │   ├── AppLayout.vue       # Main dashboard shell (Sidebar, Navbar)
│   │   └── GuestLayout.vue     # Login screens
│   └── Pages/
│       ├── Admin/
│       │   ├── Dashboard.vue   # Map view and pending trips
│       │   ├── Reports.vue     # Filtering and exporting data
│       │   ├── DriverApps.vue  # Driver app adoption/version monitoring
│       │   ├── ManualTripCreate.vue # Restricted assistant manual-entry form
│       │   ├── ManualTrips.vue # Assistant-owned paginated/filterable register
│       │   ├── TripShow.vue    # Detailed view of a single trip (map + points)
│       │   ├── Locations.vue   # CRUD for mission locations
│       │   └── MissionTypes.vue# CRUD for mission types
│       ├── WellsSystem/DieselInventories/
│       │   └── Monthly.vue     # Independent monthly diesel summary/report
│       ├── Portal.vue          # Unified system card selector
│       ├── Portal/
│       │   └── Permissions.vue # Central per-system permission screen
│       ├── Auth/
│       │   └── Login.vue
│       └── Valves/
│           ├── Dashboard.vue
│           ├── Map.vue
│           ├── Teams/Index.vue
│           └── Inspectors/Index.vue
```

## Key Frontend Conventions
1. **No Hardcoded API Calls**: Vue components generally do not make `axios` calls directly unless absolutely necessary. Most data fetching is handled by Inertia visiting a new page or via `useForm` for submissions.
2. **Dynamic URLs**: Because `ziggy-js` is removed, components must read URLs passed from the server in `props.urls`.
3. **Map Rendering**: We use `leaflet` for rendering maps in `Dashboard.vue` and `TripShow.vue`. Leaflet is initialized in `onMounted`.
4. **Export Buttons**: CSV and PDF generation is handled by standard `<button>` clicks that trigger a programmatic file download, rather than `<a>` tags, because Inertia intercepts `<a>` tags.
5. **Valves Module Pages**: Valves pages live under `resources/js/Pages/Valves/**` and use `resources/js/ValvesSystem/Layouts/AppLayout.vue`.
6. **Portal Permissions**: System permissions are managed from `resources/js/Pages/Portal/Permissions.vue` via `/permissions`, not from the Fleet dashboard navigation.
    - The editor is organized into independent Fleet, Wells, and Valves cards. Base access, standalone roles, vehicle-review scopes, and sensitive actions must remain visually distinct rather than appearing in one flat permission list.
    - Selecting an existing user or a user returned by `/permissions/search-users` loads that user's complete current permission keys into the same editor. The search response therefore includes `systems`; saving replaces the user's stored permission set with the visible selections, enabling both grants and revocations in one operation.
    - The same page also manages `wells.readings.delete` and `wells.diesel-inventories.delete`. Both options require the base `wells` permission; revoking Wells access also removes its action permissions.
    - Fleet entry/review are read-only Oracle-derived `fleet_access` values. The editor grants system access only; the assistant-screen checkbox is removed. Assistants enter automatically through live entry/review scope. Standalone entry/both users land in `Admin/ManualTrips.vue`, review-only users in its approval queue, and multi-system users retain the portal. The register uses runtime `fleet.trips.enter` to show entry/edit actions.
    - The emergency operating-state controls are collapsed by default behind an internal status button above per-user permissions. Expanding them reveals the reason/action panel; it requires a 3–1000 character reason before suspension, confirms the high-impact action, displays current state/last actor/time, and can reopen every system.
    - Suspended web routes use the standalone `resources/views/system-suspended.blade.php` HTTP 503 view so the maintenance message does not depend on a functioning SPA bundle.
7. **Wells Layout**: Wells pages own the single `.content-wrapper`; `WellsSystem/Layouts/AppLayout.vue` must not wrap the slot in a second content wrapper. Wide tables scroll inside `.data-table-wrapper`, while `.app-content` keeps `min-width: 0` so they never expand or cover the fixed sidebar.
8. **Wells Sidebar State**: `/wells` and `/` use exact active-link matching. Deeper items match their exact path or child paths, so only one sidebar entry is active on `/wells/diesel-inventories` and `/wells/wells-map`.
9. **Unified Theme Contract**: The authoritative cross-system surface, text, border, status-background, and shadow tokens live in `resources/css/app.css` as `--theme-*`. Light mode follows the Fleet dashboard palette and dark mode follows the Valves dashboard palette. Fleet `AppLayout.vue`, `wells.css`, and `valves.css` may alias these tokens but must not redefine a competing base palette. Module identity accents remain separate: Fleet/Wells use blue and Valves uses orange. All systems share `localStorage.theme` and `html[data-theme]`; the Blade shell initializes it before rendering. Keep solid light map controls/popups when needed for map legibility.
   Valves pages must use the defined `--color-*` aliases from `valves.css`; legacy/undefined `--v-*` theme variables are not part of this contract.
10. **Diesel Generator Label**: The diesel inventory page reads `RESOLVED_GENERATOR` and shows its generator number as a non-wrapping LTR line with the Arabic name below it. The backend resolves a null inventory `GENERATOR_OID` only from a single unambiguous `WELL_GENERATORS` assignment.
11. **Valves Web Map Modes**: `Valves/Map.vue` defaults to an OpenStreetMap base containing only Oracle-backed valve markers. GeoServer is a mutually exclusive mode whose opaque default background is `mog:gaza general map`; buildings, centerlines, and landmarks are optional detail overlays. The general map reads direct GeoWebCache WMTS tiles through same-origin `/wms.php?g_page=/wmts` with the server-published `EPSG:900913` matrix set, which matches Leaflet Web Mercator; detail overlays retain cached WMS through `/wms.php?g_page=/wms`. Every visible GeoServer layer, including the default general-map group, is sent to same-origin `/valves/map-features`; the backend performs authenticated WMS GetFeatureInfo and returns JSON popups. Switching to OSM aborts that lookup. `ValveMapSelector.vue` independently uses OpenStreetMap plus Oracle-backed valve coordinates because the new GeoServer does not publish `WATER_VALVES`.
12. **Fleet Dashboard Trip Search**: `Admin/Dashboard.vue` displays `#id` as the user-facing trip number. Its shared search field matches an exact numeric trip ID as well as partial vehicle plates and driver names, while retaining the selected period and clarification-status filters.
    - Fleet system access keeps mobile/legacy trips and their details visible independently of manual station assignments. Only manual rows are vehicle-scoped. Review actions for every source remain live-Oracle scoped; table `can_review` and detail `canReview` control available commands without blocking read-only app-trip inspection. No editable operation-role selector is exposed.
    - The dashboard list and trip-derived statistics use the server-maintained `points_received` count to hide completed trips below 50 points by default while always retaining active trips for live supervision. `show_experimental=1` exposes the completed short trips with an Arabic **تجريبية** badge; the prominent toggle lives in the trip-table header and survives search, status/period filters, pagination, live refresh, and dashboard review actions.
13. **Driver App Monitoring**: `Admin/DriverApps.vue` combines presence telemetry and trip-history users, then displays live Oracle vehicle assignments, version/build, installer source, and last presence. Search and filters use the globally shared `urls.driverApps`; do not hard-code the route or use Ziggy.
    - `not_reported` means no accepted presence record exists yet. It can indicate an older/not-opened app or an undeployed backend endpoint; never label it as a confirmed refusal to update without checking production `/api/driver/app-presence`.
    - `Admin/Dashboard.vue` reloads only `recentTrips` every 15 seconds while an active trip is present. Its tracking badge uses last-point age plus the latest technical event, and every active/completed row displays server-counted `gps_disabled`, `airplane_mode_on`, and explicit `tracking_gap` evidence when present.
    - `Admin/TripShow.vue` reloads active details every 15 seconds, plots automatic stop markers, lists stop duration/location/source, and shows allowlisted tracking events plus readable provider-silence/airplane-start details with an explicit no-proof-of-intent warning.
    - Fleet performance cards label the adoption-filtered metrics as **المركبات الفعالة على التطبيق** and **السائقون الفعالون على التطبيق**. Vue displays server-owned counts and must not recompute app eligibility.
    - The official fuel KPI is **استهلاك السولار للفترة**, displays liters with English digits, and shows the contributing confirmed-trip count. Its custom native date inputs live below the fuel value inside the performance card, not in the top toolbar; the fuel row spans the card width with its own spacing and divider.
    - Daily/weekly/monthly tabs clear a previous custom range. Search, response filters, pagination, experimental visibility, and review actions preserve an active custom `date_from`/`date_to` range.
    - The supervisor table has mutually exclusive **رحلات التطبيق** and **الإدخال اليدوي** tabs backed by `trip_source=mobile|manual`; rows from the two sources must never be mixed. The manual tab shows a pending counter, entry actor, notes, and pending requests first. Pending manual entries remain visible regardless of the selected period so they cannot be missed; approved/rejected manual rows follow the normal period filter. `TripShow.vue` omits route, stop, telemetry, and clarification UI for them while retaining approval/rejection.
    - `Admin/ManualTrips.vue` lists only the current assistant's entries with server-side pagination and search/status/vehicle/date filters. It links to the shared create/edit form only for drafts; a native confirmation dialog submits drafts for approval, after which editing is locked. The form uses separate native date inputs and visible hour/minute/second selectors, closes the date picker after day selection, and includes a required estimated-kilometers field. New entries default both dates to the current local day, while all manual-trip date/time controls and register timestamps use English digits; server authorization remains authoritative.
14. **Wells Management List**: `WellsSystem/Wells/Index.vue` filters by damage status rather than quarter, keeps the quarter name as display data, and shows assigned generator number/name in a dedicated column using the diesel generator layout. Pump-type badges must visibly distinguish **غاطس** from **رأسي**.
15. **Wells General Editor**: The general section omits installation and last-maintenance dates. The edit page allows well number, name, and quarter edits until the workflow is formally approved. Generator cards label their latest values in hours and liters, never gallons.
16. **Wells Map Layers**: `WellsSystem/Wells/Map.vue` uses locally installed Leaflet, includes active and inactive wells, and applies intersecting operational-status and damage-status controls plus an independent inactive-record toggle. Custom water-well markers change the name-label color when selected. Theme-aware RTL popups show assigned generator numbers from `MAP_GENERATORS` and never use **سولار** as a power-source label.
17. **Diesel Inventory Tabs And Columns**: `WellsSystem/DieselInventories/Index.vue` renders internal `Daily Tank` and external-tank primary tabs, with inventory/results subtabs for external tanks. It consumes server-owned `columnSets`, stores the user's per-report selection in local storage, and sends the same allowlisted `columns[]` with `report_type` to both exports. Unclassified wells remain outside both tabs and the warning uses `urls.wells`.
    - Each of the three report views paginates its visible rows independently at 15 rows per page. Switching tabs preserves the page for that report during the current visit; tab counts, summary totals, filters, and exports remain based on the full filtered result.
    - The external inventory and calculated-results tabs show `inventory_date` by default with the Arabic label **تاريخ الجرد**; `created_at` remains a separate insertion timestamp.
    - Null previous-reading and approved-supply cells display the service-provided Arabic reason; they must not look like an unexplained missing import or be rendered as zero.
    - `WellsSystem/DieselInventories/Monthly.vue` is reached from the existing
      diesel page but uses its own route, service payload, month filter, tabs,
      pagination, and exports. It must not reuse or mutate the current screen's
      per-record calculation state.
    - External calculated-result defaults include previous balance, supplied quantity, current balance, consumed liters, and actual rate. A valid no-delivery interval displays supply `0` and calculates its rate from the absolute non-zero meter difference without marking a reversed meter invalid; negative fuel-consumption formula values stay visible with an invalid status.
18. **Signal Image Gallery**: `WellsSystem/Notifications/Index.vue` renders notification media through `Shared/MediaThumbnails.vue` in `gallery` mode, with a labeled attachment count and larger previews. Selection opens a teleported, theme-aware lightbox with close, previous/next, counter, and open-original actions. Reading tables continue to use the component's compact mode.
19. **Wells Protected Deletion**: Wells inspector, team, and well pages consume server-owned `CAN_DELETE` and `DELETE_BLOCKERS`. A linked record renders a disabled lock with the Arabic blocker list; Vue must never infer safety from only one count or re-enable deletion client-side.
20. **Reading Report Tab And Columns**: `WellsSystem/Readings/Index.vue` lazy-loads the optional `reportPreview` Inertia prop into a dedicated same-page tab rather than a modal or separate window. The tab displays every active-well report row, highlights **غير مدخلة** wells, and shows server-owned completion/review indicators. It consumes the `reportColumns` allowlist, persists the selection locally, and sends the same ordered `columns[]` with current filters to PDF and Excel. The previous/current report columns are explicitly generator readings, and all values come from the server's generator-only calculation; do not substitute pump/flow values or calculate report status/totals again in Vue.
21. **Wells Audited Delete UI**: The readings register and external diesel inventory/results tables show a delete action only when their server-owned `canDelete` prop is true. A shared modal requires the reason and explains that the row remains stored for audit. On success, the Wells layout displays the server `flash.success` message and the modal closes; failures keep it open with errors. `Daily Tank` rows never expose this action.

## Assistant Approval Screen

- Manual-screen return navigation follows granted system access only: users with `fleet` return to `admin.dashboard` even when Oracle currently grants no review vehicles. Users without Fleet access return to the portal only if another system is granted; standalone assistants receive no unauthorized dashboard link.
- The assistant register shows entry/approval commands only when their server-owned Oracle capability permits them. No saved screen grant is needed, and retired grants cannot substitute for live vehicle assignments.

- `Admin/ManualTrips.vue` has an assistant-owned register and a separate `approvalMode` queue. The register receives a server-owned `approvalUrl` only when Oracle permits manual approval and shows **اعتماد الطلبات**. The queue links back to the register, includes requester/notes, defaults to pending, and preserves server filters/pagination.
- Each pending approval row opens a native dialog with complete request details, an optional note, loading/error states, and explicit confirmation. `approve_url` comes from Laravel. Never infer approval scope from ownership or entry scope alone.

## Trip Map Display Contract

- `TripShow.vue` always displays the recorded distance and also displays the filtered distance when quality filtering is available. Quality-v2 trips are always filtered; legacy trips are filtered only when at least 80% of their samples contain usable GPS accuracy metadata. The map then uses accepted segments without changing stored points; legacy trips with insufficient metadata retain their original trace.
- Solid blue segments are included in filtered distance/fuel. Amber dashed connectors make GPS coverage gaps understandable but are display-only and never contribute to distance or fuel. Duplicate-time and stationary samples are reported as collapsed, not falsely labeled as invalid or out-of-order.
- When internal gaps have two eligible endpoints and a plausible implied speed, trip details show their summed straight-line distance, filtered distance plus that estimate, and the corresponding 0.12 liters/km fuel estimate in a clearly labeled supervisor-review panel. These values are conservative read-only guidance and never replace persisted official metrics; gaps without two usable endpoints are counted as unestimated.
- The trip map does not provide an original/corrected toggle and does not request a server-side or third-party road-matched route.
- No Docker, OSRM service, Windows service, map-matching environment variables, or external routing API is required for production deployment.
- Start is a green marker and end is a red marker, both with permanent Arabic labels.
- The original route uses a white casing to remain legible over streets, buildings, and map labels.
- Repeating arrows show chronological travel direction.
- A conservatively detected outbound leg is blue; a detected return leg is orange.
- Direction and return-leg coloring are visual aids only. They do not modify `trip_points`, checksums, distance, virtual fuel, or audit data.
- Apparent travel across buildings can result from GPS accuracy. Do not snap, smooth, or rewrite points in a way that could imply false data accuracy.
- Automatic waiting points are orange markers derived from `trip_stops`; they do not alter the route polyline or calculated distance.
