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
- `GET /api/wells/sync/wells` delegates to `WellService`; inspector/team filtering belongs in `WellRepository`, and previous diesel inventory lookup belongs in `DieselInventoryRepository`.
- Oracle date values in mobile JSON must be normalized explicitly (for example through Carbon) because OCI8 may return them as strings even when the Eloquent model declares a datetime cast.
- Mobile well readings carry the inspector-selected business date in `READING_DATE`. Preserve that value during sync; never replace it with `now()`, `SYNCED_AT`, or `CREATED_AT`.
- Every new diesel inventory requires a non-negative numeric `GENERATOR_HOURS_READING` and `DIESEL_HEIGHT_CM`. Enforce this in both Flutter form validation and the nested `/api/wells/diesel-inventories/sync` payload validation.
- After saving a diesel inventory to SQLite, Flutter immediately attempts the pending-diesel upload when connectivity is available. A failed or timed-out attempt leaves the row pending for the normal retry flow; the dashboard can only display inventories that have reached Oracle.
- Reading and notification sync responses include `synced_records` entries mapping the mobile `local_id` to the new Oracle `oid`. The Flutter app uses this mapping to upload queued activity images after their parent row exists.
- `WELL_READINGS`, `WELL_NOTIFICATIONS`, and `DIESEL_INVENTORIES` persist the mobile UUID as unique nullable `CLIENT_LOCAL_ID`. Sync retries must resolve the existing row by this key and authenticated inspector rather than insert another row.
- Activity images use private local storage and the idempotent client photo UUID in `WELL_ACTIVITY_MEDIA.CLIENT_LOCAL_ID`. Never expose `FILE_PATH`; dashboard images are served through the authenticated Wells route.

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
│   │       ├── ActivityMediaController.php
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
│   ├── WellActivityMedia.php
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
│   ├── ActivityMediaRepository.php
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
    ├── ActivityMediaService.php
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
| POST | `/api/wells/auth/login` | No | Inspector login |
| GET | `/api/wells/sync/wells` | Yes | Get wells for inspector's team |
| GET | `/api/wells/sync/notification-types` | Yes | Get active notification types |
| POST | `/api/wells/readings/sync` | Yes | Batch upload readings |
| POST | `/api/wells/diesel-inventories/sync` | Yes | Batch upload diesel inventory reports |
| POST | `/api/wells/notifications/sync` | Yes | Batch upload field notifications |
| POST | `/api/wells/activity-media/sync` | Yes | Upload up to 4 compressed images for one synced reading or notification |
| GET | `/api/wells/sync/history` | Yes | Download recent readings, inventories, and notifications |

## Activity Image Contract
- Multipart fields are `activity_type` (`reading` or `notification`), `activity_oid`, `photo_local_ids[]`, and `photos[]`.
- Each request accepts 1–4 JPEG/PNG/WebP files, each no larger than 2 MB. The mobile target is 1.75 MB per file to remain safely below PHP request limits.
- The authenticated inspector must own the parent reading/notification. Do not trust an inspector ID from the payload.
- `CLIENT_LOCAL_ID` is unique and makes a retry return the existing media row without creating a duplicate file.
- Store files on Laravel's private `local` disk under `wells/activity-media/{type}/{oid}`. Admin display uses `GET /wells/activity-media/{media}` behind `auth:admin` and `system.access:wells`.
- Media URLs returned to Inertia must be relative (`/wells/activity-media/{oid}`), never tied to `APP_URL`, localhost, or a development port.
- Reading and notification repository results eager-load `media`. The Wells dashboard and readings index use compact thumbnails. The notifications index renders a labeled attachment gallery and opens images in a same-page lightbox with previous/next, close, counter, and authorized open-original controls.
- Production readiness probe: unauthenticated `POST /api/wells/activity-media/sync` must reach Sanctum and return `401`. HTTP `404` means the media API code is not deployed, so mobile retries cannot upload images.

## Diesel Inventory Input Contract
- Every `inventories[]` item must include `WELL_OID`, `INVENTORY_DATE`, `GENERATOR_HOURS_READING`, and `DIESEL_HEIGHT_CM`.
- Generator hours and diesel height must be numeric and at least zero. Zero represents a valid counter/empty-tank reading and must not be rejected as an empty value.
- Do not restore `?? null` fallbacks for these two fields in new sync writes. Historical Oracle rows may remain nullable for backward compatibility, but current mobile/API submissions may not omit them.
- Flutter must retain an explicit time when the inspector chooses the inventory date. If the timestamp is not newer than the latest known local/server inventory, warn before saving and allow the inspector to confirm it as historical; the backend must continue accepting out-of-order offline sync.

## Reading Date Contract
- The Flutter form defaults `READING_DATE` to today and permits today through 7 calendar days earlier, inclusive.
- `READING_DATE` means when the field reading was taken. `CREATED_AT` means when the server row was created and `SYNCED_AT` means when mobile synchronization occurred.
- Reading lists, recent-reading ordering, "today" statistics, date filters, and production reports must query `READING_DATE`, with `OID` as the tie-breaker when two readings share a timestamp.
- The mobile app sends the selected date in both its local SQLite row and sync payload. Offline synchronization must retain that original date.
- Dashboard reading tables display both `READING_DATE` as **تاريخ القراءة** and `CREATED_AT` as **تاريخ إدخال السجل**. Do not collapse them into one ambiguous "التاريخ" column.

## Reading Production Report Contract
- PDF and Excel exports use the same `ReadingService::getProductionReport()` data and the shared `wells.readings-report` view.
- `ReadingRepository::getReportReadings()` returns at most one row per `WELL_OID`.
- Apply well, inspector, quality, `date_from`, and `date_to` filters first, then select the latest matching reading for each well by `READING_DATE DESC, OID DESC`.
- `ReadingService::getProductionReport()` starts from `WellRepository::getActiveWells()`, then attaches the latest filtered reading. Every active well remains in the report even when no reading matches; only an explicit `well_oid` filter narrows the active-well list.
- Inspector, quality, and date filters apply to reading selection, not active-well inclusion. An unmatched well is returned with null reading/calculation fields and the Arabic status **غير مدخلة** so supervisors can identify missing submissions.
- A reading newer than `date_to` must not suppress the latest reading that is inside the selected period.
- `getPreviousReading()` continues to resolve the immediately preceding chronological reading from full history for operating-hours and production calculations; the previous reading is not restricted to the export date range.
- Apply the export limit only after the one-row-per-well selection.
- Report summaries include active, reported, missing, normal, first-reading, review-required, and completion-percentage counts. A comparable row is normal only when both readings are non-negative and the operating-hours difference is at most 20 hours. Negative readings, reversed meters, and differences above 20 hours are abnormal and included in the review-required count.
- `ReadingController::index()` exposes the report through an optional Inertia `reportPreview` prop plus the server-owned `reportColumns` allowlist. The readings page lazy-loads the preview into a dedicated same-page report tab; it must not use a modal or open another browser window.
- `ReadingService::getExportReport()` filters requested column keys against the fixed registry. The same ordered selection drives the preview table, PDF, and Excel. Reading PDF output uses A2 landscape with print-readable typography and spacing.

## Wells Management Screen Contract
- `WellController::index()` accepts `search`, `damage_status`, and `is_active`; the list does not use `quarter_oid` as a filter.
- `WellRepository::paginate()` owns the `DAMAGE_STATUS` predicate and continues to eager-load generator assignments.
- The index controller resolves assigned generator metadata through `GeneratorService`; Vue displays generator number and name in a dedicated column.
- `WellService::updateWell()` currently permits `WELL_NUM`, `NAME`, and `QUARTER_OID` updates even when transactions exist. Deletion protection for wells with readings or diesel inventories remains unchanged.
- Eloquent hydrates `WELL_GENERATORS.GENERATOR_OID` internally as lowercase `generator_oid`; controller/service PHP code must read the lowercase attribute before generator metadata is serialized as uppercase for Vue.
- The general create/edit form omits installation and last-maintenance dates. Generator cards use hour/liter labels for the latest external generator values.

## Notification Type Management Contract

- `/wells/notification-types` supports creating, editing, and deleting notification types through `NotificationTypeController` → `NotificationTypeService` → `NotificationTypeRepository`.
- A referenced notification type remains editable so administrators can correct its name, icon, color, priority, or active state without breaking existing notifications.
- A referenced type cannot be deleted. The paginated Inertia payload exposes `CAN_DELETE`; this flag must never disable the edit action.
- Store/update validation belongs in `StoreNotificationTypeRequest` and `UpdateNotificationTypeRequest`.
- `ICON` and the legacy compatibility value `EMOJI` use approved ASCII codes, never raw emoji.

## Master Data Deletion Protection Contract

- `InspectorRepository`, `TeamRepository`, and `WellRepository` own the complete related-record counts used for deletion decisions.
- Their services expose `CAN_DELETE` and `DELETE_BLOCKERS`, reject linked deletes before writing, and translate an Oracle `ORA-02292` race into a friendly Arabic error.
- Inspector blockers are teams, readings, diesel inventories, notifications, and tokens. Team blockers are wells and inspectors. Well blockers are readings, inventories, notifications, teams, generators, and media.
- Controllers only catch the domain `RuntimeException` and return the flash message; they never repeat relation queries.
- Oracle migration `2026_07_26_000020` replaces every relevant master-data `CASCADE` rule with `NO ACTION`. The database is the final protection if a caller bypasses Vue or a relation is created between the preflight and delete.
- Use `IS_ACTIVE = 0` instead of physical deletion after an entity has operational history.

## Wells Map Contract
- `WellRepository::getWellsForMap()` is the map data source and returns active and inactive wells with quarter names and generator assignments. Do not reuse the active-only selector for the administrative map.
- `WellService::getWellsForMap()` resolves assigned generator metadata through `GeneratorRepository` and exposes it as `MAP_GENERATORS`; Vue must not query Oracle or infer generator numbers.
- The map uses the installed Leaflet package with OpenStreetMap tiles and starts over Gaza.
- Operational-status and damage-status layer filters intersect. The inactive-record toggle is independent and is enabled by default so inactive wells remain discoverable.
- Marker color represents operational status, the small marker dot represents damage status, and inactive records receive a separate visual treatment.
- Clicking a well selects its marker, changes the well-name label color, and opens the theme-aware RTL detail card.
- The popup shows generator number instead of the power-source label. Never label the `solar` power source as diesel or **سولار** on this map.

## Diesel Inventory Report
- Dashboard routes:
  - `GET /wells/diesel-inventories`
  - `GET /wells/diesel-inventories/report/pdf`
  - `GET /wells/diesel-inventories/report/excel`
- `DieselInventoryReportRequest` validates all screen/export filters, `report_type`, and `columns[]`; controllers contain no calculations.
- `DieselInventoryService::getInternalTankReport()` owns `Daily Tank` fill-to-fill calculations.
- `DieselInventoryService::getInventoryReport()` owns classified external-tank inventory/result rows and geometric balance calculations.
- `DieselInventoryRepository` loads external inventories and their immediately preceding historical row. A classified external well has a non-null `TANK_SHAPE` other than `Daily Tank`.
- When no older chronological inventory exists, `DieselInventoryService` returns null calculation values plus explicit Arabic unavailability reasons. Vue and the shared PDF/Excel view display those reasons; they must not convert an unknown supply interval to numeric zero.
- `GeneratorRepository` resolves per-well generator settings, approved deliveries, and `Daily Tank` fuel-program entries from `G_STORE`. Both report queries require `FUEL_LOG_DETAILS.ASSET_TYPE = 401`, non-null `APPROVED_GALLONS`, `FUEL_LOG.STATUS_OID = 416`, and null `CANCEL_DATE`.
- External approved supply is assigned primarily by the delivery `CURRENT_READING` falling in `(previous inventory meter, current inventory meter]`. `GeneratorRepository` widens the initial lower date bound to the start of the previous inventory day so a date-only midnight `FILL_UP_DATE` reaches this meter comparison. Missing delivery-meter values fall back to a same-day `ENTRY_DATE`, then `FILL_UP_DATE`.
- Internal rate: `approved fill liters / (current fill meter - immediately previous approved fill meter)`.
- External rate: `(previous geometric balance + approved supply - current geometric balance) / operating hours`.
- When a valid external inventory interval has no accepted delivery, approved supply is numeric zero and the service calculates consumed liters as `previous balance - current balance`. For this zero-supply case only, use the absolute non-zero generator-meter difference to calculate the rate and do not classify a reversed or calendar-impossible meter delta as invalid.
- Outside the zero-supply exception, meter differences must be positive and cannot exceed elapsed calendar hours plus the one-hour meter/date precision allowance. Reverse, repeated-time, or impossible results are `invalid`, never `high`. A negative balance result remains `invalid` in every case; retain its signed consumption/rate so the inconsistency is visible.
- Consumption is `high` only when the actual rate is greater than `WELL_GENERATORS.FUEL_CONSUMPTION_LPH + CONSUMPTION_TOLERANCE_LPH`. A null configured rate falls back to `G_STORE.GENERATOR.FUEL_CONSUMPTION`; tolerance defaults to zero.
- The Inertia page receives `internalReport`, `externalReport`, `columnSets`, and `unclassifiedWellCount`. Column keys are selected from the service allowlist and the same selection drives the screen, Excel, and PDF. External result defaults include previous balance, supplied quantity, current balance, consumed liters, and actual rate.
- `resources/views/wells/diesel-inventory-report.blade.php` is the shared dynamic RTL PDF/Excel template.

## Audited Operational Record Deletion

- Routes: `DELETE /wells/readings/{reading}` and `DELETE /wells/diesel-inventories/{inventory}`.
- Both routes use `DeleteOperationalRecordRequest`, require a deletion reason, check their fail-closed action permission through `SystemPermissionRepository`, then delegate to the existing Wells service/repository layer.
- `WellReading` and `DieselInventory` use Laravel `SoftDeletes` with the Oracle `DELETED_AT` column. Repositories write actor ID, actor name, time, and reason in one update; they never issue a physical delete.
- Normal Eloquent queries inherit the global exclusion. Raw chronological subqueries in `ReadingRepository` and `DieselInventoryRepository` explicitly filter `DELETED_AT IS NULL`.
- Sync idempotency methods query `withTrashed()` by authenticated inspector plus `CLIENT_LOCAL_ID` so retries cannot recreate hidden rows. Mobile history keeps the default scope and does not return them.
- The Inertia pages receive server-owned `canDelete` flags and `deleteBase` URLs. Vue only displays the action; server authorization remains authoritative. After a successful delete response, the controller flashes `success`, the Wells layout displays the confirmation, and Vue closes the reason modal; validation or authorization failures keep it open.

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
