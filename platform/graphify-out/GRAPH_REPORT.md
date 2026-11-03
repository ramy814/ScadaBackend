# Graph Report - platform  (2026-11-03)

## Corpus Check
- 259 files · ~66,543 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 6 file(s) not represented in the graph (top: (none) 4, .xml 1, .css 1)

## Summary
- 2105 nodes · 4863 edges · 138 communities (67 shown, 71 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 404 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `26f57b4c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- WellActivityMedia
- @inertiajs/vue3
- ReportsController
- Wells/Map.vue
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- Illuminate\Foundation\Http\FormRequest
- WellService.php
- i18n.js
- InspectorService
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
- Illuminate\Support\Facades\DB
- GeneratorRepository
- TripRepository
- WellsRepository
- VirtualFuelService
- ValveInspector
- Illuminate\Http\RedirectResponse
- DieselInventoryRepository
- ValveTeam
- WellRepository
- Illuminate\Http\JsonResponse
- DieselInventory
- DieselFuelCalculator
- MonthlyDieselReportService.php
- FieldManager.vue
- OperationalStateService
- ManualTrips.vue
- Reviews.vue
- Illuminate\Database\Eloquent\Model
- MunicipalityVehicleRepository.php
- UppercaseKeys
- FleetReportService
- MunicipalityVehicleRepository
- ValveMobileService
- MobileSyncService
- Access.vue
- DieselInventoryService
- ReportTable.vue
- Icon.vue
- Well
- StoreManualTripRequest
- ValveInspectorService
- Illuminate\Database\Eloquent\Relations\Pivot
- ValveLog
- Illuminate\Http\Response
- VehicleClaimService
- ReadingRepository.php
- WellMedia
- ManualTripService
- UpdateValveInspectorRequest
- composer.json
- MasterDataController
- ManualTripController
- ValveRepository
- SystemOperationalStateRepository
- config
- CommandPalette.vue
- Wells/Http/Controllers/MasterDataController.php
- FleetAccessService
- .update
- scripts
- SignalTypes.vue
- StoreValveTeamRequest
- WellsMobileRepository.php
- require-dev
- AppServiceProvider.php
- Wells/Map.vue
- StoreWellRequest
- UpdateWellRequest
- Inspector
- require
- ValvesMapService
- StoreNotificationTypeRequest
- UpdateValveTeamRequest
- StoreValveInspectorRequest
- StoreInspectorRequest
- VehicleClaimController.php
- UpdateTeamRequest
- psr-4
- autoload-dev
- extra
- ValvesService.php
- DriverAppInstallation
- TripDetailService.php
- {closure#2}
- WellService
- ReadingReportRequest
- StoreTeamRequest
- SyncDieselInventoriesRequest
- SyncNotificationsRequest
- SyncReadingsRequest
- CatalogItemRequest
- UpdateInspectorRequest

## God Nodes (most connected - your core abstractions)
1. `Trip` - 58 edges
2. `TripRepository` - 52 edges
3. `useI18n()` - 48 edges
4. `Controller` - 47 edges
5. `@inertiajs/vue3` - 44 edges
6. `Valve` - 43 edges
7. `SystemPermissionRepository` - 43 edges
8. `AccessService` - 41 edges
9. `Well` - 40 edges
10. `MunicipalityVehicleRepository` - 39 edges

## Surprising Connections (you probably didn't know these)
- `{closure#24}()` --references--> `Trip`  [EXTRACTED]
  app/Modules/Fleet/Repositories/TripRepository.php → app/Modules/Fleet/Models/Trip.php
- `{closure#31}()` --references--> `Trip`  [EXTRACTED]
  app/Modules/Fleet/Repositories/TripRepository.php → app/Modules/Fleet/Models/Trip.php
- `{closure#2}()` --references--> `Trip`  [EXTRACTED]
  app/Modules/Fleet/Services/VehicleClaimService.php → app/Modules/Fleet/Models/Trip.php
- `{closure#4}()` --references--> `Trip`  [EXTRACTED]
  app/Modules/Fleet/Services/VehicleClaimService.php → app/Modules/Fleet/Models/Trip.php
- `{closure#7}()` --references--> `Valve`  [EXTRACTED]
  app/Modules/Valves/Repositories/ValveRepository.php → app/Modules/Valves/Models/Valve.php

## Import Cycles
- None detected.

## Communities (138 total, 71 thin omitted)

### Community 0 - "WellActivityMedia"
Cohesion: 0.19
Nodes (3): WellActivityMedia, ActivityMediaRepository, ActivityMediaService

### Community 1 - "@inertiajs/vue3"
Cohesion: 0.09
Nodes (23): apply, props, term, debounce(), visit(), dateFrom, dateTo, expanded (+15 more)

### Community 2 - "ReportsController"
Cohesion: 0.07
Nodes (3): ReportsController, DeleteOperationalRecordRequest, MonthlyDieselReportRequest

### Community 3 - "Wells/Map.vue"
Cohesion: 0.05
Nodes (44): dependencies, @fontsource/ibm-plex-sans-arabic@^5.2.6, @fontsource/inter@^5.2.6, @inertiajs/vue3@^3.7.1, leaflet@^1.9.4, leaflet-polylinedecorator@^1.6.0, lucide-vue-next@^0.544.0, vue@^3.5.42 (+36 more)

### Community 4 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.06
Nodes (9): NotificationType, Team, NotificationTypeRepository, TeamRepository, {closure#1}(), NotificationTypeService, {closure#1}(), {closure#2}() (+1 more)

### Community 5 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.07
Nodes (10): ApproveManualTripRequest, AppUsageRequest, DecisionRequest, ReviewQueueRequest, StoreValveActionRequest, UpdateMaintenanceReportRequest, DieselInventoryReportRequest, StoreActivityMediaRequest (+2 more)

### Community 6 - "WellService.php"
Cohesion: 0.17
Nodes (5): {closure#1}(), {closure#11}(), {closure#4}(), {closure#7}(), {closure#8}()

### Community 7 - "i18n.js"
Cohesion: 0.07
Nodes (29): @inertiajs/vue3, ar, dictionaries, en, find(), intlLocale(), lookup(), merge() (+21 more)

### Community 8 - "InspectorService"
Cohesion: 0.18
Nodes (3): {closure#4}(), InspectorRepository, InspectorService

### Community 9 - "vue"
Cohesion: 0.06
Nodes (26): crumb, useCrumb(), [, endTime], form, fromOdometer, props, seconds(), [startDate, startTime] (+18 more)

### Community 10 - "Illuminate\Support\Collection"
Cohesion: 0.15
Nodes (6): {closure#10}(), {closure#3}(), {closure#6}(), {closure#8}(), {closure#9}(), DriverAppUsageService

### Community 11 - "Trip"
Cohesion: 0.10
Nodes (9): Trip, {closure#14}(), {closure#23}(), {closure#32}(), {closure#1}(), FleetLiveService, {closure#1}(), {closure#2}() (+1 more)

### Community 12 - "SystemPermissionRepository"
Cohesion: 0.15
Nodes (4): AccessController, SystemPermissionRepository, EnsureFleetAction, OracleReadiness

### Community 13 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.06
Nodes (40): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}() (+32 more)

### Community 14 - "AppShell.vue"
Cohesion: 0.09
Nodes (18): activeItem, apps, collapsed, currentApp, lastVisited, navigation, open, page (+10 more)

### Community 15 - "Inertia\Response"
Cohesion: 0.10
Nodes (7): Controller, ValvesController, ValvesListRequest, ApplicationsController, AuditController, LocaleController, OverviewController

### Community 16 - "ValvesRepository"
Cohesion: 0.18
Nodes (3): ValvesRepository, ValvesService, ValvesModule

### Community 17 - "TripPanel.vue"
Cohesion: 0.07
Nodes (22): emit, form, pending, props, submit(), { t }, emit, fuelLabel (+14 more)

### Community 18 - "ReadingService"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), {closure#4}(), ReadingService

### Community 19 - "FleetAccessService"
Cohesion: 0.15
Nodes (3): FleetModule, DriverAppInstallationRepository, Module

### Community 20 - "Valve"
Cohesion: 0.11
Nodes (3): Valve, {closure#1}(), {closure#2}()

### Community 21 - "WellNotification"
Cohesion: 0.12
Nodes (3): WellNotification, WellNotificationRepository, WellNotificationService

### Community 22 - "ReadingRepository"
Cohesion: 0.14
Nodes (3): WellReading, ReadingRepository, {closure#1}()

### Community 23 - "ApplicationRegistry"
Cohesion: 0.10
Nodes (12): AccessService, ApplicationRegistry, {closure#1}(), EnsureApplicationAccess, EnsurePlatformAdmin, EnsurePlatformUser, {closure#3}(), {closure#4}() (+4 more)

### Community 24 - "SystemPermissionRepository.php"
Cohesion: 0.11
Nodes (11): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}() (+3 more)

### Community 25 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.09
Nodes (5): TripPassenger, TripPoint, TripStop, TripTrackingEvent, ValveMaintenanceReport

### Community 26 - "Illuminate\Http\Request"
Cohesion: 0.16
Nodes (4): EnsureMobilePrincipal, SetLocale, SetMobileLocale, AuthController

### Community 28 - "GeneratorRepository"
Cohesion: 0.20
Nodes (3): WellGenerator, GeneratorRepository, GeneratorService

### Community 30 - "WellsRepository"
Cohesion: 0.12
Nodes (4): WellsController, WellsRepository, WellsService, WellsModule

### Community 35 - "ValveTeam"
Cohesion: 0.18
Nodes (3): ValveTeam, ValveTeamRepository, ValveTeamService

### Community 38 - "Illuminate\Http\JsonResponse"
Cohesion: 0.08
Nodes (7): DashboardController, MaintenanceController, ValveController, ActivityMediaController, AuthController, SyncController, WellsMobileService

### Community 39 - "DieselInventory"
Cohesion: 0.15
Nodes (15): DieselInventory, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#2}() (+7 more)

### Community 41 - "MonthlyDieselReportService.php"
Cohesion: 0.13
Nodes (9): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}() (+1 more)

### Community 42 - "FieldManager.vue"
Cohesion: 0.13
Nodes (16): close(), dialog, doomed, editing, editInspector(), editTeam(), filter, idsOf() (+8 more)

### Community 43 - "OperationalStateService"
Cohesion: 0.21
Nodes (4): RejectMobileWhenSuspended, RejectWhenSuspended, OperationalStateService, OperationsController

### Community 44 - "ManualTrips.vue"
Cohesion: 0.07
Nodes (20): vue, dialog, emit, { t }, dialog, form, props, { t } (+12 more)

### Community 45 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 46 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.14
Nodes (11): MissionLocation, MissionType, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+3 more)

### Community 48 - "UppercaseKeys"
Cohesion: 0.08
Nodes (3): {closure#1}(), ReadingGeneratorLog, UppercaseKeys

### Community 49 - "FleetReportService"
Cohesion: 0.17
Nodes (5): OperationsController, ReportRequest, CatalogRepository, FleetReportService, ReportExporter

### Community 52 - "MobileSyncService"
Cohesion: 0.15
Nodes (8): {closure#1}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), MobileSyncService

### Community 53 - "Access.vue"
Cohesion: 0.13
Nodes (10): editing, filter, form, listed, props, query, results, search (+2 more)

### Community 56 - "ReportTable.vue"
Cohesion: 0.09
Nodes (17): emit, page, pages, picker, props, selected, slice, { t, num, date } (+9 more)

### Community 57 - "Icon.vue"
Cohesion: 0.08
Nodes (21): props, { t, num }, label, props, { t }, tone, tones, bars (+13 more)

### Community 59 - "StoreManualTripRequest"
Cohesion: 0.19
Nodes (4): {closure#1}(), StoreManualTripRequest, {closure#1}(), UpdateAccessRequest

### Community 60 - "ValveInspectorService"
Cohesion: 0.12
Nodes (3): AuthController, LoginValveInspectorRequest, ValveInspectorService

### Community 61 - "Illuminate\Database\Eloquent\Relations\Pivot"
Cohesion: 0.21
Nodes (4): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell

### Community 62 - "ValveLog"
Cohesion: 0.10
Nodes (4): ValveLog, {closure#7}(), {closure#1}(), ValveReportService

### Community 63 - "Illuminate\Http\Response"
Cohesion: 0.17
Nodes (5): {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), GeoTileProxyService

### Community 65 - "ReadingRepository.php"
Cohesion: 0.29
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 66 - "WellMedia"
Cohesion: 0.28
Nodes (3): WellMedia, WellMediaRepository, WellMediaService

### Community 70 - "composer.json"
Cohesion: 0.18
Nodes (10): description, keywords, license, minimum-stability, name, prefer-stable, $schema, suggest (+2 more)

### Community 77 - "config"
Cohesion: 0.20
Nodes (10): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+2 more)

### Community 78 - "CommandPalette.vue"
Cohesion: 0.27
Nodes (9): active, emit, go(), input, onKey(), props, query, results (+1 more)

### Community 79 - "Wells/Http/Controllers/MasterDataController.php"
Cohesion: 0.17
Nodes (4): {closure#2}(), {closure#3}(), {closure#5}(), WellsListRequest

### Community 80 - "FleetAccessService"
Cohesion: 0.19
Nodes (3): ReviewController, FleetAccessService, TripReviewService

### Community 82 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 83 - "SignalTypes.vue"
Cohesion: 0.09
Nodes (15): emit, onKey(), { t }, emit, { t }, editing, form, props (+7 more)

### Community 86 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 87 - "AppServiceProvider.php"
Cohesion: 0.18
Nodes (3): MobilePrincipals, AppServiceProvider, {closure#1}()

### Community 88 - "Wells/Map.vue"
Cohesion: 0.21
Nodes (11): active(), colors, damage, draw(), el, esc(), operational, props (+3 more)

### Community 91 - "Inspector"
Cohesion: 0.27
Nodes (3): Inspector, {closure#1}(), {closure#2}()

### Community 92 - "require"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/sanctum, laravel/tinker, mpdf/mpdf, php, yajra/laravel-oci8

### Community 101 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Fixture\\, Database\\Seeders\\

### Community 105 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 106 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 121 - "ValvesService.php"
Cohesion: 0.36
Nodes (4): {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

## Knowledge Gaps
- **71 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Trip` connect `Trip` to `ManualTripService`, `Illuminate\Http\JsonResponse`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `Inertia\Response`, `FleetAccessService`, `UppercaseKeys`, `VirtualFuelService.php`, `TripDetailService.php`, `Illuminate\Http\Response`, `VirtualFuelService`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Should `@inertiajs/vue3` be split into smaller, more focused modules?**
  _Cohesion score 0.09462365591397849 - nodes in this community are weakly interconnected._
- **Why does `Controller` connect `Inertia\Response` to `Illuminate\Http\RedirectResponse`, `ReportsController`, `VehicleClaimController.php`, `Illuminate\Http\JsonResponse`, `MasterDataController`, `ManualTripController`, `OperationalStateService`, `SystemPermissionRepository`, `Wells/Http/Controllers/MasterDataController.php`, `FleetAccessService`, `FleetReportService`, `Illuminate\Http\Request`, `ValveInspectorService`, `WellsRepository`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Should `ReportsController` be split into smaller, more focused modules?**
  _Cohesion score 0.07017543859649122 - nodes in this community are weakly interconnected._
- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `ReportsController`, `Inertia\Response`, `FleetAccessService`, `FleetReportService`, `MunicipalityVehicleRepository`, `FleetAccessService`, `ApplicationRegistry`, `DieselInventoryService`, `SystemPermissionRepository.php`, `Illuminate\Http\Request`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Should `Wells/Map.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.050314465408805034 - nodes in this community are weakly interconnected._
- **Should `Illuminate\Contracts\Pagination\LengthAwarePaginator` be split into smaller, more focused modules?**
  _Cohesion score 0.060528559249786874 - nodes in this community are weakly interconnected._