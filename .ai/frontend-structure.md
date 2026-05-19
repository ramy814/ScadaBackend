# Frontend Folder Structure (Admin Dashboard)

The frontend is an Inertia.js Single Page Application built with Vue 3.

```text
resources/
├── css/
│   └── app.css                 # Custom CSS, variables, and utility classes
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
│       └── Auth/
│           └── Login.vue
```

## Key Frontend Conventions
1. **No Hardcoded API Calls**: Vue components generally do not make `axios` calls directly unless absolutely necessary. Most data fetching is handled by Inertia visiting a new page or via `useForm` for submissions.
2. **Dynamic URLs**: Because `ziggy-js` is removed, components must read URLs passed from the server in `props.urls`.
3. **Map Rendering**: We use `leaflet` for rendering maps in `Dashboard.vue` and `TripShow.vue`. Leaflet is initialized in `onMounted`.
4. **Export Buttons**: CSV and PDF generation is handled by standard `<button>` clicks that trigger a programmatic file download, rather than `<a>` tags, because Inertia intercepts `<a>` tags.
