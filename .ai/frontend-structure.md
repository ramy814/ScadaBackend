# Frontend Folder Structure (Admin Dashboard)

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
│       │   ├── TripShow.vue    # Detailed view of a single trip (map + points)
│       │   ├── Locations.vue   # CRUD for mission locations
│       │   └── MissionTypes.vue# CRUD for mission types
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
7. **Wells Layout**: Wells pages own the single `.content-wrapper`; `WellsSystem/Layouts/AppLayout.vue` must not wrap the slot in a second content wrapper. Wide tables scroll inside `.data-table-wrapper`, while `.app-content` keeps `min-width: 0` so they never expand or cover the fixed sidebar.
8. **Wells Sidebar State**: `/wells` and `/` use exact active-link matching. Deeper items match their exact path or child paths, so only one sidebar entry is active on `/wells/diesel-inventories` and `/wells/wells-map`.
9. **Unified Theme Contract**: The authoritative cross-system surface, text, border, status-background, and shadow tokens live in `resources/css/app.css` as `--theme-*`. Light mode follows the Fleet dashboard palette and dark mode follows the Valves dashboard palette. Fleet `AppLayout.vue`, `wells.css`, and `valves.css` may alias these tokens but must not redefine a competing base palette. Module identity accents remain separate: Fleet/Wells use blue and Valves uses orange. All systems share `localStorage.theme` and `html[data-theme]`; the Blade shell initializes it before rendering. Keep solid light map controls/popups when needed for map legibility.
   Valves pages must use the defined `--color-*` aliases from `valves.css`; legacy/undefined `--v-*` theme variables are not part of this contract.
10. **Diesel Generator Label**: The diesel inventory page reads `RESOLVED_GENERATOR` and shows its generator number as a non-wrapping LTR line with the Arabic name below it. The backend resolves a null inventory `GENERATOR_OID` only from a single unambiguous `WELL_GENERATORS` assignment.
11. **Valves Web Map Modes**: `Valves/Map.vue` defaults to an OpenStreetMap base containing only Oracle-backed valve markers. GeoServer is a mutually exclusive base mode with a separate WMS layer selector that is hidden in OpenStreetMap mode. GeoServer clicks query visible-layer properties through same-origin `/valves/map-features` and render localized RTL popups; switching to OSM aborts that lookup. The map uses Gaza bounds (`31.15..31.65` latitude, `34.15..34.65` longitude) and `minZoom = 10`; never fit the view to unfiltered Oracle Spatial coordinates.
12. **Fleet Dashboard Trip Search**: `Admin/Dashboard.vue` displays `#id` as the user-facing trip number. Its shared search field matches an exact numeric trip ID as well as partial vehicle plates and driver names, while retaining the selected period and clarification-status filters.
13. **Driver App Monitoring**: `Admin/DriverApps.vue` combines presence telemetry and trip-history users, then displays live Oracle vehicle assignments, version/build, installer source, and last presence. Search and filters use the globally shared `urls.driverApps`; do not hard-code the route or use Ziggy.
    - `not_reported` means no accepted presence record exists yet. It can indicate an older/not-opened app or an undeployed backend endpoint; never label it as a confirmed refusal to update without checking production `/api/driver/app-presence`.
    - `Admin/Dashboard.vue` reloads only `recentTrips` every 15 seconds while an active trip is present. Its tracking badge uses last-point age plus the latest technical event.
    - `Admin/TripShow.vue` reloads active details every 15 seconds, plots automatic stop markers, lists stop duration/location/source, and shows allowlisted tracking events with an explicit no-proof-of-intent warning.
14. **Wells Management List**: `WellsSystem/Wells/Index.vue` filters by damage status rather than quarter, keeps the quarter name as display data, and shows assigned generator number/name in a dedicated column using the diesel generator layout. Pump-type badges must visibly distinguish **غاطس** from **رأسي**.
15. **Wells General Editor**: The general section omits installation and last-maintenance dates. The edit page allows well number, name, and quarter edits until the workflow is formally approved. Generator cards label their latest values in hours and liters, never gallons.
16. **Wells Map Layers**: `WellsSystem/Wells/Map.vue` uses locally installed Leaflet, includes active and inactive wells, and applies intersecting operational-status and damage-status controls plus an independent inactive-record toggle. Custom water-well markers change the name-label color when selected. Theme-aware RTL popups show assigned generator numbers from `MAP_GENERATORS` and never use **سولار** as a power-source label.
17. **Diesel Inventory Tabs And Columns**: `WellsSystem/DieselInventories/Index.vue` renders internal `Daily Tank` and external-tank primary tabs, with inventory/results subtabs for external tanks. It consumes server-owned `columnSets`, stores the user's per-report selection in local storage, and sends the same allowlisted `columns[]` with `report_type` to both exports. Unclassified wells remain outside both tabs and the warning uses `urls.wells`.
18. **Signal Image Gallery**: `WellsSystem/Notifications/Index.vue` renders notification media through `Shared/MediaThumbnails.vue` in `gallery` mode, with a labeled attachment count and larger previews. Selection opens a teleported, theme-aware lightbox with close, previous/next, counter, and open-original actions. Reading tables continue to use the component's compact mode.
19. **Wells Protected Deletion**: Wells inspector, team, and well pages consume server-owned `CAN_DELETE` and `DELETE_BLOCKERS`. A linked record renders a disabled lock with the Arabic blocker list; Vue must never infer safety from only one count or re-enable deletion client-side.
20. **Reading Export Preview**: `WellsSystem/Readings/Index.vue` requests the optional `reportPreview` Inertia prop before either PDF or Excel download. The modal displays every active-well report row, highlights **غير مدخلة** wells, shows server-owned completion/review indicators, and downloads only after confirmation using the same current filters. Do not calculate report status or totals again in Vue.

## Trip Map Display Contract

- `TripShow.vue` always displays the recorded distance and also displays the filtered distance when quality filtering is available. Quality-v2 trips are always filtered; legacy trips are filtered only when at least 80% of their samples contain usable GPS accuracy metadata. The map then uses accepted segments without changing stored points; legacy trips with insufficient metadata retain their original trace.
- Solid blue segments are included in filtered distance/fuel. Amber dashed connectors make GPS coverage gaps understandable but are display-only and never contribute to distance or fuel. Duplicate-time and stationary samples are reported as collapsed, not falsely labeled as invalid or out-of-order.
- The trip map does not provide an original/corrected toggle and does not request a server-side or third-party road-matched route.
- No Docker, OSRM service, Windows service, map-matching environment variables, or external routing API is required for production deployment.
- Start is a green marker and end is a red marker, both with permanent Arabic labels.
- The original route uses a white casing to remain legible over streets, buildings, and map labels.
- Repeating arrows show chronological travel direction.
- A conservatively detected outbound leg is blue; a detected return leg is orange.
- Direction and return-leg coloring are visual aids only. They do not modify `trip_points`, checksums, distance, virtual fuel, or audit data.
- Apparent travel across buildings can result from GPS accuracy. Do not snap, smooth, or rewrite points in a way that could imply false data accuracy.
- Automatic waiting points are orange markers derived from `trip_stops`; they do not alter the route polyline or calculated distance.
