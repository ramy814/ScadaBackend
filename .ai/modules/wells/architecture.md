# Backend Architecture

## Pattern: Repository → Service → Controller → Inertia/Vue

```
Request → Controller → Service → Repository → Model → Database
                                                         ↑
                                              Oracle DB (production)
                                              SQLite (development)
```

## Mobile App Binding
- The wells Flutter application is located at `/Volumes/SecondDrive/Wells/wells_app`.
- This backend project is the unified server for that app after merging wells into the fleet/tracking system.
- Keep mobile-facing wells JSON contracts compatible with `/Volumes/SecondDrive/Wells/wells_app/lib/**`.
- Do not create or look for a second Flutter app under this backend repository.

## Rules
1. **Controllers** are thin — only delegate to services
2. **Services** contain all business logic
3. **Repositories** contain all database queries
4. **Models** define relationships and casts
5. No direct SQL in controllers
6. No business logic in controllers
7. All PHP files use `declare(strict_types=1)`

## Directory Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Dashboard Controllers (Inertia responses)
│   │   │   ├── DashboardController.php
│   │   │   ├── WellController.php
│   │   │   ├── WellMediaController.php
│   │   │   ├── TeamController.php
│   │   │   ├── InspectorController.php
│   │   │   ├── ReadingController.php
│   │   │   ├── DieselInventoryController.php
│   │   │   └── NotificationTypeController.php
│   │   └── Api/ (JSON responses for Flutter)
│   │       ├── AuthController.php
│   │       ├── SyncController.php
│   │       ├── ReadingController.php
│   │       └── DieselInventoryController.php
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php
│   └── Requests/
│       ├── StoreWellRequest.php
│       ├── UpdateWellRequest.php
│       ├── StoreTeamRequest.php
│       ├── UpdateTeamRequest.php
│       ├── StoreInspectorRequest.php
│       ├── UpdateInspectorRequest.php
│       └── StoreReadingRequest.php
├── Models/
│   ├── Well.php
│   ├── WellMedia.php
│   ├── Team.php
│   ├── Inspector.php
│   ├── WellReading.php
│   ├── DieselInventory.php
│   ├── NotificationType.php
│   ├── WellNotification.php
│   ├── ReadingGeneratorLog.php
│   ├── WellGenerator.php (pivot)
│   ├── TeamWell.php (pivot)
│   └── InspectorTeam.php (pivot)
├── Repositories/
│   ├── WellRepository.php
│   ├── WellMediaRepository.php
│   ├── TeamRepository.php
│   ├── InspectorRepository.php
│   ├── ReadingRepository.php
│   ├── DieselInventoryRepository.php
│   ├── NotificationTypeRepository.php
│   ├── WellNotificationRepository.php
│   └── GeneratorRepository.php
└── Services/
    ├── WellService.php
    ├── WellMediaService.php
    ├── TeamService.php
    ├── InspectorService.php
    ├── ReadingService.php
    ├── DieselInventoryService.php
    ├── NotificationTypeService.php
    ├── WellNotificationService.php
    ├── GeneratorService.php
    └── DashboardService.php
```

## Model Conventions
- Primary key: `OID` (not `id`)
- Table name: explicitly set via `$table`
- Column names: UPPERCASE (Oracle convention)
- Relationships defined on all models
- Pivot models for many-to-many relationships

## API Endpoints (for Flutter)
These endpoints are served by this unified backend and consumed by `/Volumes/SecondDrive/Wells/wells_app`.

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | `/api/auth/login` | No | Inspector login |
| POST | `/api/auth/logout` | Yes | Inspector logout |
| GET | `/api/sync/wells` | Yes | Get wells for inspector's team |
| GET | `/api/sync/generators` | Yes | Get generators for team wells |
| GET | `/api/sync/quarters` | Yes | Get area lookup |
| POST | `/api/readings/sync` | Yes | Batch upload readings |
| POST | `/api/diesel-inventories/sync` | Yes | Batch upload diesel inventory reports |

## Diesel Inventory Report
- Dashboard routes:
  - `GET /wells/diesel-inventories/report/pdf`
  - `GET /wells/diesel-inventories/report/excel`
- `DieselInventoryController` only validates filters and renders the export response.
- `DieselInventoryService::getInventoryReport()` owns calculations and report row assembly.
- `DieselInventoryRepository` loads filtered/current and preceding inventory data.
- `GeneratorRepository` resolves assigned generator metadata and approved deliveries from `G_STORE`.
- `resources/views/wells/diesel-inventory-report.blade.php` is shared by PDF and Excel exports.

## Frontend Structure
```
resources/
├── css/app.css          (1400+ lines custom CSS)
├── js/
│   ├── app.js           (Inertia setup)
│   ├── Layouts/
│   │   └── AppLayout.vue
│   ├── Pages/
│   │   ├── Dashboard.vue
│   │   ├── Wells/ (Index, Create, Edit, Map)
│   │   ├── Teams/ (Index)
│   │   ├── Inspectors/ (Index)
│   │   ├── Readings/ (Index)
│   │   ├── DieselInventories/ (Index)
│   │   ├── NotificationTypes/ (Index)
│   │   └── Notifications/ (Index)
│   └── Components/
│       ├── Layout/ (Sidebar, TopNav)
│       └── Shared/ (DataTable, StatsCard, Modal, DropdownSelect)
└── views/
    └── app.blade.php     (Inertia root template)
```
