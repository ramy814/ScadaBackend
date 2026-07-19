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

## Trip Map Display Contract

- `TripShow.vue` displays the original GPS trace from `trip_points`, ordered by `sequence_number`.
- The trip map does not provide an original/corrected toggle and does not request a server-side or third-party road-matched route.
- No Docker, OSRM service, Windows service, map-matching environment variables, or external routing API is required for production deployment.
- Start is a green marker and end is a red marker, both with permanent Arabic labels.
- The original route uses a white casing to remain legible over streets, buildings, and map labels.
- Repeating arrows show chronological travel direction.
- A conservatively detected outbound leg is blue; a detected return leg is orange.
- Direction and return-leg coloring are visual aids only. They do not modify `trip_points`, checksums, distance, virtual fuel, or audit data.
- Apparent travel across buildings can result from GPS accuracy. Do not snap, smooth, or rewrite points in a way that could imply false data accuracy.
