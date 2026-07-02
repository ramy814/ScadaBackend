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
