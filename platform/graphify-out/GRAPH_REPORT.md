# Graph Report - platform  (2026-11-03)

## Corpus Check
- 284 files · ~73,527 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 6 file(s) not represented in the graph (top: (none) 4, .xml 1, .css 1)

## Summary
- 2331 nodes · 5465 edges · 130 communities (75 shown, 55 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 486 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `0268c03e`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- WellActivityMedia
- @inertiajs/vue3
- Wells/Map.vue
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- Illuminate\Foundation\Http\FormRequest
- Well
- i18n.js
- InspectorService
- vue
- Illuminate\Support\Collection
- Trip
- SystemPermissionRepository
- Illuminate\Database\Eloquent\Builder
- AppShell.vue
- Inertia\Response
- DriverApiService
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
- .app
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
- Beneficiary
- MobileSyncService
- Access.vue
- DieselInventoryService
- ReportTable.vue
- StatusBadge.vue
- StoreManualTripRequest
- api.php
- Illuminate\Database\Eloquent\Relations\Pivot
- ValveLog
- Illuminate\Http\Response
- VehicleClaimService
- ReadingRepository.php
- WellMedia
- TripSyncService.php
- UpdateValveInspectorRequest
- Illuminate\Support\Carbon
- composer.json
- ReportsController
- ManualTripController
- ValveRepository
- SystemOperationalStateRepository
- Diesel.vue
- config
- CommandPalette.vue
- WellsListRequest
- FleetAccessService
- .update
- scripts
- SignalTypes.vue
- Icon.vue
- WellsMobileRepository.php
- require-dev
- AppServiceProvider.php
- MonthlyDieselReportService
- StoreWellRequest
- UpdateWellRequest
- require
- TripSyncService
- MasterDataController
- Wells/Http/Controllers/MasterDataController.php
- Illuminate\Validation\Rule
- MunicipalityDriverRepository
- Valves/Map.vue
- .confirm
- UpdateTeamRequest
- psr-4
- autoload-dev
- extra
- ValvesService.php
- DriverAppInstallation
- CatalogRepository
- MonthlyDieselReportRequest
- .sendCode
- SyncDieselInventoriesRequest
- SyncController

## God Nodes (most connected - your core abstractions)
1. `Trip` - 80 edges
2. `TripRepository` - 63 edges
3. `Controller` - 55 edges
4. `MunicipalityVehicleRepository` - 54 edges
5. `useI18n()` - 48 edges
6. `@inertiajs/vue3` - 44 edges
7. `Valve` - 43 edges
8. `SystemPermissionRepository` - 43 edges
9. `Beneficiary` - 42 edges
10. `AccessService` - 41 edges

## Surprising Connections (you probably didn't know these)
- `{closure#7}()` --references--> `Valve`  [EXTRACTED]
  app/Modules/Valves/Repositories/ValveRepository.php → app/Modules/Valves/Models/Valve.php
- `{closure#7}()` --references--> `WellActivityMedia`  [EXTRACTED]
  app/Modules/Wells/Repositories/WellsRepository.php → app/Modules/Wells/Models/WellActivityMedia.php
- `DriverController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/Api/DriverController.php → app/Http/Controllers/Controller.php
- `MapTileController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/Api/MapTileController.php → app/Http/Controllers/Controller.php
- `TripSyncController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/Api/TripSyncController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (130 total, 55 thin omitted)

### Community 0 - "WellActivityMedia"
Cohesion: 0.24
Nodes (3): WellActivityMedia, ActivityMediaRepository, ActivityMediaService

### Community 1 - "@inertiajs/vue3"
Cohesion: 0.06
Nodes (38): @inertiajs/vue3, { t, num }, apply, props, term, debounce(), visit(), bars (+30 more)

### Community 3 - "Wells/Map.vue"
Cohesion: 0.05
Nodes (45): dependencies, @fontsource/ibm-plex-sans-arabic@^5.2.6, @fontsource/inter@^5.2.6, @inertiajs/vue3@^3.7.1, leaflet@^1.9.4, leaflet-polylinedecorator@^1.6.0, lucide-vue-next@^0.544.0, vue@^3.5.42 (+37 more)

### Community 4 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.06
Nodes (10): {closure#5}(), NotificationType, Team, NotificationTypeRepository, TeamRepository, {closure#1}(), NotificationTypeService, {closure#1}() (+2 more)

### Community 5 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.08
Nodes (10): ApproveManualTripRequest, AppUsageRequest, CatalogItemRequest, DecisionRequest, ReviewQueueRequest, StoreTripRequest, VerifySmsCodeRequest, UpdateMaintenanceReportRequest (+2 more)

### Community 6 - "Well"
Cohesion: 0.09
Nodes (9): Well, {closure#1}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}() (+1 more)

### Community 7 - "i18n.js"
Cohesion: 0.07
Nodes (24): emit, onKey(), { t }, emit, { t }, ar, dictionaries, en (+16 more)

### Community 8 - "InspectorService"
Cohesion: 0.14
Nodes (6): {closure#4}(), Inspector, InspectorRepository, {closure#1}(), {closure#2}(), InspectorService

### Community 9 - "vue"
Cohesion: 0.07
Nodes (26): vue, useCrumb(), [, endTime], form, fromOdometer, props, seconds(), [startDate, startTime] (+18 more)

### Community 10 - "Illuminate\Support\Collection"
Cohesion: 0.12
Nodes (6): {closure#10}(), {closure#3}(), {closure#6}(), {closure#8}(), {closure#9}(), DriverAppUsageService

### Community 11 - "Trip"
Cohesion: 0.08
Nodes (12): Trip, {closure#14}(), {closure#23}(), {closure#24}(), {closure#31}(), {closure#32}(), DriverTripRoutePreviewService, {closure#1}() (+4 more)

### Community 12 - "SystemPermissionRepository"
Cohesion: 0.13
Nodes (3): SystemPermissionRepository, EnsureFleetAction, OracleReadiness

### Community 13 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.05
Nodes (38): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}() (+30 more)

### Community 14 - "AppShell.vue"
Cohesion: 0.06
Nodes (22): standalone, items, page, activeItem, apps, collapsed, currentApp, lastVisited (+14 more)

### Community 15 - "Inertia\Response"
Cohesion: 0.09
Nodes (8): Controller, AuthController, ValvesController, AccessController, ApplicationsController, AuditController, LocaleController, OverviewController

### Community 16 - "DriverApiService"
Cohesion: 0.09
Nodes (8): DriverController, TripSyncController, RespondClarificationRequest, StoreDriverAppPresenceRequest, DriverAppPresenceResource, CurrentBeneficiaryService, DriverApiService, DriverTripSummaryService

### Community 17 - "TripPanel.vue"
Cohesion: 0.07
Nodes (22): emit, form, pending, props, submit(), { t }, emit, fuelLabel (+14 more)

### Community 18 - "ReadingService"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), {closure#4}(), ReadingService

### Community 19 - "FleetAccessService"
Cohesion: 0.16
Nodes (4): FleetModule, DriverAppInstallationRepository, {closure#1}(), FleetLiveService

### Community 20 - "Valve"
Cohesion: 0.09
Nodes (3): Valve, {closure#1}(), {closure#2}()

### Community 21 - "WellNotification"
Cohesion: 0.12
Nodes (3): WellNotification, WellNotificationRepository, WellNotificationService

### Community 22 - "ReadingRepository"
Cohesion: 0.15
Nodes (3): WellReading, ReadingRepository, {closure#1}()

### Community 23 - "ApplicationRegistry"
Cohesion: 0.10
Nodes (12): AccessService, ApplicationRegistry, {closure#1}(), EnsureApplicationAccess, EnsurePlatformAdmin, EnsurePlatformUser, {closure#3}(), {closure#4}() (+4 more)

### Community 24 - "SystemPermissionRepository.php"
Cohesion: 0.10
Nodes (11): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}() (+3 more)

### Community 25 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.10
Nodes (4): TripPassenger, TripStop, TripTrackingEvent, ValveMaintenanceReport

### Community 26 - "Illuminate\Http\Request"
Cohesion: 0.20
Nodes (3): EnsureMobilePrincipal, SetLocale, SetMobileLocale

### Community 28 - "GeneratorRepository"
Cohesion: 0.17
Nodes (3): WellGenerator, GeneratorRepository, GeneratorService

### Community 30 - "WellsRepository"
Cohesion: 0.06
Nodes (7): ValvesRepository, ValvesModule, WellsController, WellsRepository, WellsService, WellsModule, Module

### Community 31 - "VirtualFuelService"
Cohesion: 0.11
Nodes (4): {closure#2}(), {closure#4}(), {closure#8}(), VirtualFuelService

### Community 32 - "ValveInspector"
Cohesion: 0.12
Nodes (5): AuthController, LoginValveInspectorRequest, ValveInspector, ValveInspectorRepository, ValveInspectorService

### Community 35 - "ValveTeam"
Cohesion: 0.16
Nodes (3): ValveTeam, ValveTeamRepository, ValveTeamService

### Community 38 - "Illuminate\Http\JsonResponse"
Cohesion: 0.15
Nodes (5): DashboardController, MaintenanceController, ValveController, StoreValveActionRequest, ValveMobileService

### Community 39 - "DieselInventory"
Cohesion: 0.18
Nodes (13): DieselInventory, {closure#1}(), {closure#10}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#2}(), {closure#3}() (+5 more)

### Community 41 - "MonthlyDieselReportService.php"
Cohesion: 0.15
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 42 - "FieldManager.vue"
Cohesion: 0.13
Nodes (16): close(), dialog, doomed, editing, editInspector(), editTeam(), filter, idsOf() (+8 more)

### Community 43 - "OperationalStateService"
Cohesion: 0.24
Nodes (4): RejectMobileWhenSuspended, RejectWhenSuspended, OperationalStateService, OperationsController

### Community 44 - "ManualTrips.vue"
Cohesion: 0.11
Nodes (12): dialog, emit, { t }, dialog, form, props, { t, num, date }, target (+4 more)

### Community 45 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 46 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.12
Nodes (12): MissionLocation, MissionType, SmsLoginCode, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+4 more)

### Community 48 - "UppercaseKeys"
Cohesion: 0.10
Nodes (3): {closure#1}(), ReadingGeneratorLog, UppercaseKeys

### Community 49 - "FleetReportService"
Cohesion: 0.22
Nodes (4): OperationsController, ReportRequest, FleetReportService, ReportExporter

### Community 51 - "Beneficiary"
Cohesion: 0.18
Nodes (4): Beneficiary, {closure#6}(), {closure#9}(), {closure#1}()

### Community 52 - "MobileSyncService"
Cohesion: 0.15
Nodes (8): {closure#1}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), MobileSyncService

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

### Community 60 - "api.php"
Cohesion: 0.12
Nodes (3): ActivityMediaController, AuthController, WellsLoginRequest

### Community 61 - "Illuminate\Database\Eloquent\Relations\Pivot"
Cohesion: 0.21
Nodes (4): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell

### Community 64 - "VehicleClaimService"
Cohesion: 0.15
Nodes (5): VehicleClaimController, VehicleClaimIndexRequest, {closure#5}(), {closure#6}(), VehicleClaimService

### Community 65 - "ReadingRepository.php"
Cohesion: 0.29
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 66 - "WellMedia"
Cohesion: 0.16
Nodes (3): WellMedia, WellMediaRepository, WellMediaService

### Community 70 - "composer.json"
Cohesion: 0.18
Nodes (10): description, keywords, license, minimum-stability, name, prefer-stable, $schema, suggest (+2 more)

### Community 73 - "ValveRepository"
Cohesion: 0.12
Nodes (5): ValveLog, ValveRepository, {closure#1}(), ValveActionService, ValveService

### Community 75 - "SystemOperationalStateRepository"
Cohesion: 0.09
Nodes (7): MapTileController, MapClientRepository, GeoServerTileService, MapClientAuthService, AuthenticateMapClient, {closure#1}(), SystemOperationalStateRepository

### Community 76 - "Diesel.vue"
Cohesion: 0.13
Nodes (12): dialog, form, props, { t }, url, deleter, highCount, props (+4 more)

### Community 77 - "config"
Cohesion: 0.20
Nodes (10): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+2 more)

### Community 78 - "CommandPalette.vue"
Cohesion: 0.27
Nodes (9): active, emit, go(), input, onKey(), props, query, results (+1 more)

### Community 80 - "FleetAccessService"
Cohesion: 0.11
Nodes (5): ReviewController, FleetAccessService, {closure#4}(), TripDetailService, TripReviewService

### Community 82 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 83 - "SignalTypes.vue"
Cohesion: 0.25
Nodes (6): edit(), editing, form, icons, { t, num }, truthy()

### Community 84 - "Icon.vue"
Cohesion: 0.15
Nodes (9): props, attention, page, props, rank, { t, num, relative }, urgent, props (+1 more)

### Community 85 - "WellsMobileRepository.php"
Cohesion: 0.13
Nodes (3): {closure#1}(), WellsMobileRepository, WellsMobileService

### Community 86 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 87 - "AppServiceProvider.php"
Cohesion: 0.15
Nodes (3): MobilePrincipals, AppServiceProvider, {closure#1}()

### Community 92 - "require"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/sanctum, laravel/tinker, mpdf/mpdf, php, yajra/laravel-oci8

### Community 94 - "MasterDataController"
Cohesion: 0.12
Nodes (3): MasterDataController, StoreValveInspectorRequest, ValvesMapService

### Community 95 - "Wells/Http/Controllers/MasterDataController.php"
Cohesion: 0.08
Nodes (7): {closure#2}(), {closure#3}(), StoreInspectorRequest, StoreNotificationTypeRequest, StoreTeamRequest, UpdateInspectorRequest, UpdateNotificationTypeRequest

### Community 96 - "Illuminate\Validation\Rule"
Cohesion: 0.09
Nodes (4): StoreValveTeamRequest, UpdateValveTeamRequest, ValvesListRequest, DieselInventoryReportRequest

### Community 98 - "Valves/Map.vue"
Cohesion: 0.20
Nodes (9): el, esc(), mode, onClick(), overlays, props, { t }, tileOptions (+1 more)

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
Cohesion: 0.38
Nodes (5): {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), ValvesService

### Community 131 - "SyncController"
Cohesion: 0.11
Nodes (3): SyncController, SyncNotificationsRequest, SyncReadingsRequest

## Knowledge Gaps
- **55 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `fleet.php`, `ReportsController`, `Illuminate\Support\Collection`, `Inertia\Response`, `FleetAccessService`, `FleetReportService`, `MunicipalityVehicleRepository`, `ApplicationRegistry`, `SystemPermissionRepository.php`, `Illuminate\Http\Request`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **Should `@inertiajs/vue3` be split into smaller, more focused modules?**
  _Cohesion score 0.06271186440677966 - nodes in this community are weakly interconnected._
- **Why does `Trip` connect `Trip` to `.confirm`, `TripSyncService.php`, `SystemPermissionRepository`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `Inertia\Response`, `DriverApiService`, `FleetAccessService`, `Beneficiary`, `FleetAccessService`, `TripSyncService`, `MunicipalityDriverRepository.php`, `Illuminate\Database\Eloquent\Relations\HasMany`, `TripRepository`, `VirtualFuelService`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Should `fleet.php` be split into smaller, more focused modules?**
  _Cohesion score 0.11764705882352941 - nodes in this community are weakly interconnected._
- **Why does `Controller` connect `Inertia\Response` to `VehicleClaimService`, `ValveInspector`, `Illuminate\Http\RedirectResponse`, `SyncController`, `Illuminate\Http\JsonResponse`, `ReportsController`, `ManualTripController`, `SystemOperationalStateRepository`, `OperationalStateService`, `DriverApiService`, `FleetAccessService`, `FleetReportService`, `WellsRepository`, `.app`, `api.php`, `MasterDataController`, `Wells/Http/Controllers/MasterDataController.php`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Should `Wells/Map.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.053544494720965306 - nodes in this community are weakly interconnected._
- **Should `Illuminate\Contracts\Pagination\LengthAwarePaginator` be split into smaller, more focused modules?**
  _Cohesion score 0.05712050078247261 - nodes in this community are weakly interconnected._