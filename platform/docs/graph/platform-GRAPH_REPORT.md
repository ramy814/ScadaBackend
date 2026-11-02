# Graph Report - platform-graph  (2026-11-02)

## Corpus Check
- 226 files · ~63,136 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1780 nodes · 4106 edges · 111 communities (47 shown, 64 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 329 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- Illuminate\Database\Eloquent\Builder
- useI18n
- AccessService
- Inspector
- WellsRepository
- Team
- Assets.vue
- ValvesRepository
- Wells/Map.vue
- WellForm.vue
- Illuminate\Support\Collection
- Illuminate\Database\Eloquent\Model
- FleetAccessService
- AppShell.vue
- Controller
- Illuminate\Foundation\Http\FormRequest
- Trip
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Valve
- DieselInventoryService
- SystemPermissionRepository.php
- TripRepository
- SystemPermissionRepository
- ValveInspector
- SignalTypes.vue
- ValveTeam
- ref_vue
- ReportTable.vue
- Illuminate\Validation\Rule
- VirtualFuelService
- MasterDataController
- WellReading
- Illuminate\Http\Request
- MonthlyDieselReportService.php
- WellActivityMedia
- DieselInventoryRepository
- Illuminate\Database\Eloquent\Collection
- FieldManager.vue
- Inertia\Response
- SystemOperationalStateRepository
- UppercaseKeys
- Illuminate\Http\RedirectResponse
- Reviews.vue
- FleetReportService
- DieselInventory
- MunicipalityVehicleRepository
- ReportsController.php
- GeneratorRepository
- WellMedia
- Access.vue
- Illuminate\Database\Eloquent\Relations\Pivot
- ReadingService
- WellRepository
- MunicipalityVehicleRepository.php
- Illuminate\Http\Response
- OperationalStateService
- Well
- WellService.php
- i18n.js
- ReviewController
- ReportsController
- Illuminate\Support\Carbon
- ReadingRepository.php
- StatusBadge.vue
- WellService
- AppServiceProvider.php
- CommandPalette.vue
- Illuminate\Support\Facades\DB
- MonthlyDieselReportService
- Controllers/OperationsController.php
- MonthlyDieselReportRequest
- {closure#2}
- .findOrFail
- StoreNotificationTypeRequest
- StoreWellRequest
- UpdateWellRequest
- DecisionForm.vue
- UpdateValveInspectorRequest
- UpdateValveTeamRequest
- ValvesMapService
- StoreValveInspectorRequest
- ValveReportService.php
- DieselInventoryReportRequest
- StoreTeamRequest
- UpdateInspectorRequest
- UpdateTeamRequest
- TripStop
- WellsListRequest
- .update

## God Nodes (most connected - your core abstractions)
1. `Trip` - 55 edges
2. `TripRepository` - 48 edges
3. `useI18n()` - 46 edges
4. `AccessService` - 41 edges
5. `SystemPermissionRepository` - 41 edges
6. `Well` - 39 edges
7. `UppercaseKeys` - 35 edges
8. `MasterDataController` - 33 edges
9. `Controller` - 31 edges
10. `DieselInventory` - 31 edges

## Surprising Connections (you probably didn't know these)
- `{closure#11}()` --references--> `Well`  [EXTRACTED]
  app/Modules/Wells/Services/WellService.php → app/Modules/Wells/Models/Well.php
- `{closure#8}()` --references--> `Well`  [EXTRACTED]
  app/Modules/Wells/Services/WellService.php → app/Modules/Wells/Models/Well.php
- `{closure#7}()` --references--> `WellActivityMedia`  [EXTRACTED]
  app/Modules/Wells/Repositories/WellsRepository.php → app/Modules/Wells/Models/WellActivityMedia.php
- `ManualTripController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/ManualTripController.php → app/Http/Controllers/Controller.php
- `OperationsController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/OperationsController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (111 total, 64 thin omitted)

### Community 0 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.06
Nodes (9): {closure#5}(), NotificationType, WellNotification, NotificationTypeRepository, WellNotificationRepository, {closure#1}(), NotificationTypeService, WellNotificationService (+1 more)

### Community 1 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.07
Nodes (36): {closure#10}(), {closure#11}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#20}() (+28 more)

### Community 2 - "useI18n"
Cohesion: 0.08
Nodes (26): props, { t, num }, intlLocale(), useI18n(), { t }, page, { t }, { t, date } (+18 more)

### Community 3 - "AccessService"
Cohesion: 0.10
Nodes (12): AccessService, ApplicationRegistry, {closure#1}(), EnsureApplicationAccess, EnsurePlatformAdmin, EnsurePlatformUser, {closure#3}(), {closure#4}() (+4 more)

### Community 4 - "Inspector"
Cohesion: 0.08
Nodes (6): {closure#4}(), Inspector, InspectorRepository, {closure#1}(), {closure#2}(), InspectorService

### Community 5 - "WellsRepository"
Cohesion: 0.08
Nodes (4): WellsController, WellsRepository, WellsService, WellsModule

### Community 6 - "Team"
Cohesion: 0.11
Nodes (6): {closure#3}(), Team, TeamRepository, {closure#1}(), {closure#2}(), TeamService

### Community 7 - "Assets.vue"
Cohesion: 0.07
Nodes (26): apply, props, term, debounce(), visit(), bars, props, { t, num, relative } (+18 more)

### Community 8 - "ValvesRepository"
Cohesion: 0.10
Nodes (9): ValvesController, ValvesListRequest, ValvesRepository, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), ValvesService (+1 more)

### Community 9 - "Wells/Map.vue"
Cohesion: 0.06
Nodes (30): draw(), el, props, emit, m, manual, props, severe (+22 more)

### Community 10 - "WellForm.vue"
Cohesion: 0.06
Nodes (26): crumb, useCrumb(), [, endTime], form, fromOdometer, props, seconds(), [startDate, startTime] (+18 more)

### Community 11 - "Illuminate\Support\Collection"
Cohesion: 0.09
Nodes (8): DriverAppInstallation, DriverAppInstallationRepository, {closure#10}(), {closure#3}(), {closure#6}(), {closure#8}(), {closure#9}(), DriverAppUsageService

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.11
Nodes (13): MissionLocation, MissionType, TripTrackingEvent, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+5 more)

### Community 13 - "FleetAccessService"
Cohesion: 0.12
Nodes (5): FleetModule, FleetAccessService, FleetLiveService, TripReviewService, Module

### Community 14 - "AppShell.vue"
Cohesion: 0.07
Nodes (19): standalone, activeItem, apps, collapsed, currentApp, lastVisited, navigation, open (+11 more)

### Community 15 - "Controller"
Cohesion: 0.13
Nodes (6): Controller, AccessController, ApplicationsController, AuditController, LocaleController, OverviewController

### Community 16 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.10
Nodes (7): ApproveManualTripRequest, AppUsageRequest, CatalogItemRequest, DecisionRequest, ReviewQueueRequest, StoreInspectorRequest, AuditFilterRequest

### Community 17 - "Trip"
Cohesion: 0.11
Nodes (10): Trip, {closure#12}(), {closure#21}(), {closure#22}(), {closure#29}(), {closure#30}(), {closure#1}(), {closure#1}() (+2 more)

### Community 18 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.10
Nodes (4): TripPassenger, TripPoint, ValveLog, ValveMaintenanceReport

### Community 19 - "Valve"
Cohesion: 0.09
Nodes (4): Valve, {closure#4}(), {closure#1}(), {closure#2}()

### Community 20 - "DieselInventoryService"
Cohesion: 0.13
Nodes (5): DieselFuelCalculator, {closure#12}(), {closure#13}(), {closure#14}(), DieselInventoryService

### Community 21 - "SystemPermissionRepository.php"
Cohesion: 0.11
Nodes (11): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}() (+3 more)

### Community 24 - "ValveInspector"
Cohesion: 0.17
Nodes (3): ValveInspector, ValveInspectorRepository, ValveInspectorService

### Community 25 - "SignalTypes.vue"
Cohesion: 0.09
Nodes (15): emit, onKey(), { t }, emit, { t }, editing, form, props (+7 more)

### Community 26 - "ValveTeam"
Cohesion: 0.16
Nodes (3): ValveTeam, ValveTeamRepository, ValveTeamService

### Community 27 - "ref_vue"
Cohesion: 0.10
Nodes (14): dialog, emit, { t }, dialog, form, props, { t }, url (+6 more)

### Community 28 - "ReportTable.vue"
Cohesion: 0.09
Nodes (17): emit, page, pages, picker, props, selected, slice, { t, num, date } (+9 more)

### Community 29 - "Illuminate\Validation\Rule"
Cohesion: 0.11
Nodes (5): {closure#1}(), StoreManualTripRequest, StoreValveTeamRequest, {closure#1}(), UpdateAccessRequest

### Community 31 - "MasterDataController"
Cohesion: 0.14
Nodes (3): MasterDataController, ValveRepository, ValveService

### Community 32 - "WellReading"
Cohesion: 0.12
Nodes (4): WellReading, {closure#1}(), {closure#2}(), {closure#4}()

### Community 34 - "MonthlyDieselReportService.php"
Cohesion: 0.15
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 35 - "WellActivityMedia"
Cohesion: 0.19
Nodes (3): WellActivityMedia, ActivityMediaRepository, ActivityMediaService

### Community 38 - "FieldManager.vue"
Cohesion: 0.13
Nodes (16): close(), dialog, doomed, editing, editInspector(), editTeam(), filter, idsOf() (+8 more)

### Community 40 - "SystemOperationalStateRepository"
Cohesion: 0.20
Nodes (3): {closure#1}(), SystemOperationalStateRepository, OracleReadiness

### Community 43 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 45 - "DieselInventory"
Cohesion: 0.24
Nodes (10): DieselInventory, {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+2 more)

### Community 49 - "GeneratorRepository"
Cohesion: 0.22
Nodes (3): WellGenerator, GeneratorRepository, GeneratorService

### Community 50 - "WellMedia"
Cohesion: 0.28
Nodes (3): WellMedia, WellMediaRepository, WellMediaService

### Community 51 - "Access.vue"
Cohesion: 0.13
Nodes (10): editing, filter, form, listed, props, query, results, search (+2 more)

### Community 53 - "Illuminate\Database\Eloquent\Relations\Pivot"
Cohesion: 0.19
Nodes (4): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell

### Community 59 - "OperationalStateService"
Cohesion: 0.22
Nodes (3): RejectWhenSuspended, OperationalStateService, OperationsController

### Community 61 - "WellService.php"
Cohesion: 0.18
Nodes (4): {closure#11}(), {closure#4}(), {closure#7}(), {closure#8}()

### Community 62 - "i18n.js"
Cohesion: 0.20
Nodes (7): ar, dictionaries, en, find(), lookup(), merge(), translate()

### Community 66 - "ReadingRepository.php"
Cohesion: 0.29
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 67 - "StatusBadge.vue"
Cohesion: 0.18
Nodes (9): label, props, { t }, tone, tones, props, { t, num, relative }, tones (+1 more)

### Community 71 - "CommandPalette.vue"
Cohesion: 0.27
Nodes (9): active, emit, go(), input, onKey(), props, query, results (+1 more)

### Community 82 - "DecisionForm.vue"
Cohesion: 0.33
Nodes (6): emit, form, pending, props, submit(), { t }

## Knowledge Gaps
- **64 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Trip` connect `Trip` to `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `FleetAccessService`, `VirtualFuelService.php`, `Controller`, `Illuminate\Database\Eloquent\Relations\HasMany`, `TripRepository`, `SystemPermissionRepository`, `TripDetailService.php`, `VirtualFuelService`, `ReviewController`?**
  _High betweenness centrality (0.064) - this node is a cross-community bridge._
- **Should `Illuminate\Contracts\Pagination\LengthAwarePaginator` be split into smaller, more focused modules?**
  _Cohesion score 0.055299539170506916 - nodes in this community are weakly interconnected._
- **Why does `TripRepository` connect `TripRepository` to `Illuminate\Database\Eloquent\Builder`, `Illuminate\Support\Collection`, `FleetReportService`, `FleetAccessService`, `VirtualFuelService.php`, `Illuminate\Foundation\Http\FormRequest`, `Trip`, `TripDetailService.php`, `Illuminate\Validation\Rule`, `VirtualFuelService`?**
  _High betweenness centrality (0.046) - this node is a cross-community bridge._
- **Should `Illuminate\Database\Eloquent\Builder` be split into smaller, more focused modules?**
  _Cohesion score 0.06588235294117648 - nodes in this community are weakly interconnected._
- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `ReportsController`, `Illuminate\Http\Request`, `AccessService`, `SystemOperationalStateRepository`, `FleetAccessService`, `Controller`, `ReportsController.php`, `MunicipalityVehicleRepository`, `DieselInventoryService`, `SystemPermissionRepository.php`, `Illuminate\Http\Response`, `ReviewController`?**
  _High betweenness centrality (0.044) - this node is a cross-community bridge._
- **Should `useI18n` be split into smaller, more focused modules?**
  _Cohesion score 0.07529411764705882 - nodes in this community are weakly interconnected._
- **Should `AccessService` be split into smaller, more focused modules?**
  _Cohesion score 0.09620721554116558 - nodes in this community are weakly interconnected._