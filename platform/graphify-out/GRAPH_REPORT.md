# Graph Report - platform  (2026-11-03)

## Corpus Check
- 235 files · ~63,151 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 6 file(s) not represented in the graph (top: (none) 4, .xml 1, .css 1)

## Summary
- 1947 nodes · 4407 edges · 121 communities (62 shown, 59 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 349 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `efdab6f1`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- VehicleClaimService
- @inertiajs/vue3
- ReportsController
- Wells/Map.vue
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- Illuminate\Foundation\Http\FormRequest
- Well
- i18n.js
- Wells/Http/Controllers/MasterDataController.php
- vue
- Illuminate\Support\Collection
- Trip
- SystemPermissionRepository
- Illuminate\Database\Eloquent\Builder
- AppShell.vue
- Inertia\Response
- ValvesRepository
- TripPanel.vue
- ReadingService
- FleetAccessService
- Valve
- WellNotification
- ReadingRepository
- ApplicationRegistry
- SystemPermissionRepository.php
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Illuminate\Http\Request
- NotificationType
- GeneratorRepository
- TripRepository
- WellsRepository
- VirtualFuelService
- ValveInspector
- Illuminate\Http\RedirectResponse
- DieselInventoryRepository
- ValveTeam
- WellRepository
- WellsService
- DieselInventory
- DieselInventoryService
- MonthlyDieselReportService.php
- FieldManager.vue
- AccessService
- ManualTrips.vue
- Reviews.vue
- Illuminate\Database\Eloquent\Model
- MunicipalityVehicleRepository.php
- UppercaseKeys
- FleetReportService
- MunicipalityVehicleRepository
- .app
- Diesel.vue
- Access.vue
- SystemOperationalStateRepository
- ReportTable.vue
- StatusBadge.vue
- ManualTripService
- StoreManualTripRequest
- MasterDataController
- Illuminate\Database\Eloquent\Relations\Pivot
- web.php
- Icon.vue
- fleet.php
- ReadingRepository.php
- ValveLog
- ValvesRepository.php
- Illuminate\Support\Carbon
- composer.json
- Valves/Map.vue
- ManualTripController
- ValveRepository
- MonthlyDieselReportService
- AppServiceProvider.php
- config
- CommandPalette.vue
- Illuminate\Support\Facades\DB
- TripReviewService
- scripts
- SignalTypes.vue
- UpdateInspectorRequest
- WellsRepository.php
- require-dev
- OperationsController
- TripDetailService.php
- StoreWellRequest
- UpdateWellRequest
- .__invoke
- require
- ValvesMapService
- StoreNotificationTypeRequest
- UpdateValveTeamRequest
- StoreValveInspectorRequest
- StoreInspectorRequest
- StoreTeamRequest
- UpdateTeamRequest
- psr-4
- autoload-dev
- extra

## God Nodes (most connected - your core abstractions)
1. `Trip` - 58 edges
2. `TripRepository` - 52 edges
3. `useI18n()` - 48 edges
4. `@inertiajs/vue3` - 44 edges
5. `SystemPermissionRepository` - 43 edges
6. `AccessService` - 41 edges
7. `MunicipalityVehicleRepository` - 39 edges
8. `Well` - 39 edges
9. `vue` - 39 edges
10. `UppercaseKeys` - 35 edges

## Surprising Connections (you probably didn't know these)
- `{closure#7}()` --references--> `WellActivityMedia`  [EXTRACTED]
  app/Modules/Wells/Repositories/WellsRepository.php → app/Modules/Wells/Models/WellActivityMedia.php
- `ManualTripController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/ManualTripController.php → app/Http/Controllers/Controller.php
- `OperationsController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/OperationsController.php → app/Http/Controllers/Controller.php
- `ReviewController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/ReviewController.php → app/Http/Controllers/Controller.php
- `VehicleClaimController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/VehicleClaimController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (121 total, 59 thin omitted)

### Community 0 - "VehicleClaimService"
Cohesion: 0.06
Nodes (9): VehicleClaimController, VehicleClaimIndexRequest, VehicleClaimService, WellActivityMedia, WellMedia, ActivityMediaRepository, WellMediaRepository, ActivityMediaService (+1 more)

### Community 1 - "@inertiajs/vue3"
Cohesion: 0.06
Nodes (38): @inertiajs/vue3, { t, num }, apply, props, term, debounce(), visit(), bars (+30 more)

### Community 2 - "ReportsController"
Cohesion: 0.06
Nodes (6): GeoTileProxyService, ReportsController, DeleteOperationalRecordRequest, DieselInventoryReportRequest, MonthlyDieselReportRequest, ReadingReportRequest

### Community 3 - "Wells/Map.vue"
Cohesion: 0.05
Nodes (45): dependencies, @fontsource/ibm-plex-sans-arabic@^5.2.6, @fontsource/inter@^5.2.6, @inertiajs/vue3@^3.7.1, leaflet@^1.9.4, leaflet-polylinedecorator@^1.6.0, lucide-vue-next@^0.544.0, vue@^3.5.42 (+37 more)

### Community 4 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.09
Nodes (6): {closure#3}(), Team, TeamRepository, {closure#1}(), {closure#2}(), TeamService

### Community 5 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.06
Nodes (10): ApproveManualTripRequest, AppUsageRequest, CatalogItemRequest, DecisionRequest, ManualTripIndexRequest, ReviewQueueRequest, StoreValveTeamRequest, UpdateValveInspectorRequest (+2 more)

### Community 6 - "Well"
Cohesion: 0.08
Nodes (9): Well, {closure#1}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}() (+1 more)

### Community 7 - "i18n.js"
Cohesion: 0.07
Nodes (24): emit, onKey(), { t }, emit, { t }, ar, dictionaries, en (+16 more)

### Community 8 - "Wells/Http/Controllers/MasterDataController.php"
Cohesion: 0.11
Nodes (6): {closure#4}(), Inspector, InspectorRepository, {closure#1}(), {closure#2}(), InspectorService

### Community 9 - "vue"
Cohesion: 0.07
Nodes (26): vue, useCrumb(), [, endTime], form, fromOdometer, props, seconds(), [startDate, startTime] (+18 more)

### Community 10 - "Illuminate\Support\Collection"
Cohesion: 0.09
Nodes (8): DriverAppInstallation, DriverAppInstallationRepository, {closure#10}(), {closure#3}(), {closure#6}(), {closure#8}(), {closure#9}(), DriverAppUsageService

### Community 11 - "Trip"
Cohesion: 0.08
Nodes (13): Trip, {closure#14}(), {closure#23}(), {closure#24}(), {closure#31}(), {closure#32}(), {closure#1}(), {closure#1}() (+5 more)

### Community 12 - "SystemPermissionRepository"
Cohesion: 0.14
Nodes (4): AccessController, SystemPermissionRepository, EnsureFleetAction, OracleReadiness

### Community 13 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.11
Nodes (21): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}() (+13 more)

### Community 14 - "AppShell.vue"
Cohesion: 0.06
Nodes (22): standalone, items, page, activeItem, apps, collapsed, currentApp, lastVisited (+14 more)

### Community 15 - "Inertia\Response"
Cohesion: 0.15
Nodes (7): Controller, ValvesController, ValvesListRequest, ApplicationsController, AuditController, OperationsController, OverviewController

### Community 16 - "ValvesRepository"
Cohesion: 0.14
Nodes (7): ValvesRepository, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), ValvesService, ValvesModule

### Community 17 - "TripPanel.vue"
Cohesion: 0.07
Nodes (22): emit, form, pending, props, submit(), { t }, emit, fuelLabel (+14 more)

### Community 18 - "ReadingService"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), {closure#4}(), ReadingService

### Community 19 - "FleetAccessService"
Cohesion: 0.14
Nodes (4): FleetModule, FleetAccessService, FleetLiveService, Module

### Community 20 - "Valve"
Cohesion: 0.09
Nodes (4): Valve, {closure#4}(), {closure#1}(), {closure#2}()

### Community 21 - "WellNotification"
Cohesion: 0.12
Nodes (3): WellNotification, WellNotificationRepository, WellNotificationService

### Community 22 - "ReadingRepository"
Cohesion: 0.14
Nodes (3): WellReading, ReadingRepository, {closure#1}()

### Community 23 - "ApplicationRegistry"
Cohesion: 0.13
Nodes (5): ApplicationRegistry, {closure#3}(), {closure#5}(), HandleInertiaRequests, {closure#1}()

### Community 24 - "SystemPermissionRepository.php"
Cohesion: 0.11
Nodes (11): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}() (+3 more)

### Community 25 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.11
Nodes (5): TripPassenger, TripPoint, TripStop, TripTrackingEvent, ValveMaintenanceReport

### Community 27 - "NotificationType"
Cohesion: 0.17
Nodes (5): {closure#5}(), NotificationType, NotificationTypeRepository, {closure#1}(), NotificationTypeService

### Community 32 - "ValveInspector"
Cohesion: 0.20
Nodes (3): ValveInspector, ValveInspectorRepository, ValveInspectorService

### Community 35 - "ValveTeam"
Cohesion: 0.19
Nodes (3): ValveTeam, ValveTeamRepository, ValveTeamService

### Community 38 - "WellsService"
Cohesion: 0.16
Nodes (8): WellsController, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), WellsService

### Community 39 - "DieselInventory"
Cohesion: 0.20
Nodes (10): DieselInventory, {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+2 more)

### Community 40 - "DieselInventoryService"
Cohesion: 0.17
Nodes (5): DieselFuelCalculator, {closure#12}(), {closure#13}(), {closure#14}(), DieselInventoryService

### Community 41 - "MonthlyDieselReportService.php"
Cohesion: 0.16
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 42 - "FieldManager.vue"
Cohesion: 0.13
Nodes (16): close(), dialog, doomed, editing, editInspector(), editTeam(), filter, idsOf() (+8 more)

### Community 43 - "AccessService"
Cohesion: 0.20
Nodes (7): AccessService, EnsureApplicationAccess, EnsurePlatformAdmin, EnsurePlatformUser, RejectWhenSuspended, OperationalStateService, ExecutiveOverviewService

### Community 44 - "ManualTrips.vue"
Cohesion: 0.11
Nodes (12): dialog, emit, { t }, dialog, form, props, { t, num, date }, target (+4 more)

### Community 45 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 46 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.18
Nodes (6): MissionLocation, MissionType, {closure#1}(), AuditLog, MobileAccessToken, SystemOperationalState

### Community 48 - "UppercaseKeys"
Cohesion: 0.15
Nodes (3): ReadingGeneratorLog, WellGenerator, UppercaseKeys

### Community 49 - "FleetReportService"
Cohesion: 0.22
Nodes (3): ReportRequest, FleetReportService, ReportExporter

### Community 52 - "Diesel.vue"
Cohesion: 0.13
Nodes (12): dialog, form, props, { t }, url, deleter, highCount, props (+4 more)

### Community 53 - "Access.vue"
Cohesion: 0.13
Nodes (10): editing, filter, form, listed, props, query, results, search (+2 more)

### Community 56 - "ReportTable.vue"
Cohesion: 0.14
Nodes (10): emit, page, pages, picker, props, selected, slice, { t, num, date } (+2 more)

### Community 57 - "StatusBadge.vue"
Cohesion: 0.14
Nodes (11): label, props, { t }, tone, tones, props, { t, num, relative, date }, props (+3 more)

### Community 59 - "StoreManualTripRequest"
Cohesion: 0.19
Nodes (4): {closure#1}(), StoreManualTripRequest, {closure#1}(), UpdateAccessRequest

### Community 61 - "Illuminate\Database\Eloquent\Relations\Pivot"
Cohesion: 0.21
Nodes (4): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell

### Community 63 - "Icon.vue"
Cohesion: 0.15
Nodes (9): props, attention, page, props, rank, { t, num, relative }, urgent, props (+1 more)

### Community 65 - "ReadingRepository.php"
Cohesion: 0.26
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 68 - "ValvesRepository.php"
Cohesion: 0.18
Nodes (8): {closure#10}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 70 - "composer.json"
Cohesion: 0.18
Nodes (10): description, keywords, license, minimum-stability, name, prefer-stable, $schema, suggest (+2 more)

### Community 71 - "Valves/Map.vue"
Cohesion: 0.20
Nodes (9): el, esc(), mode, onClick(), overlays, props, { t }, tileOptions (+1 more)

### Community 77 - "config"
Cohesion: 0.20
Nodes (10): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+2 more)

### Community 78 - "CommandPalette.vue"
Cohesion: 0.27
Nodes (9): active, emit, go(), input, onKey(), props, query, results (+1 more)

### Community 82 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 83 - "SignalTypes.vue"
Cohesion: 0.25
Nodes (6): edit(), editing, form, icons, { t, num }, truthy()

### Community 85 - "WellsRepository.php"
Cohesion: 0.25
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 86 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 91 - ".__invoke"
Cohesion: 0.38
Nodes (3): AuditLogRepository, {closure#1}(), {closure#2}()

### Community 92 - "require"
Cohesion: 0.29
Nodes (7): require, inertiajs/inertia-laravel, laravel/framework, laravel/tinker, mpdf/mpdf, php, yajra/laravel-oci8

### Community 101 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Fixture\\, Database\\Seeders\\

### Community 105 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 106 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **59 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Trip` connect `Trip` to `fleet.php`, `SystemPermissionRepository`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `Inertia\Response`, `TripReviewService`, `FleetAccessService`, `VirtualFuelService.php`, `TripDetailService.php`, `ManualTripService`, `TripRepository`, `VirtualFuelService`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **Should `VehicleClaimService` be split into smaller, more focused modules?**
  _Cohesion score 0.05698778833107191 - nodes in this community are weakly interconnected._
- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `fleet.php`, `ReportsController`, `AccessService`, `FleetReportService`, `MunicipalityVehicleRepository`, `FleetAccessService`, `.app`, `ApplicationRegistry`, `SystemPermissionRepository.php`, `Illuminate\Http\Request`?**
  _High betweenness centrality (0.040) - this node is a cross-community bridge._
- **Should `@inertiajs/vue3` be split into smaller, more focused modules?**
  _Cohesion score 0.06271186440677966 - nodes in this community are weakly interconnected._
- **Why does `TripRepository` connect `TripRepository` to `VehicleClaimService`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Support\Collection`, `Trip`, `Illuminate\Database\Eloquent\Builder`, `TripReviewService`, `FleetReportService`, `FleetAccessService`, `TripDetailService.php`, `ManualTripService`, `StoreManualTripRequest`, `VirtualFuelService`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Should `ReportsController` be split into smaller, more focused modules?**
  _Cohesion score 0.06108597285067873 - nodes in this community are weakly interconnected._
- **Should `Wells/Map.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.053544494720965306 - nodes in this community are weakly interconnected._