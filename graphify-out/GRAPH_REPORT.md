# Graph Report - ScadaBackend  (2026-11-03)

## Corpus Check
- 284 files · ~72,097 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 6 file(s) not represented in the graph (top: (none) 4, .xml 1, .css 1)

## Summary
- 2336 nodes · 5473 edges · 137 communities (76 shown, 61 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 486 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `9b64c127`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- WellActivityMedia
- @inertiajs/vue3
- ReviewController.php
- Wells/Map.vue
- Team
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
- Controller
- DriverController
- TripPanel.vue
- ReadingRepository
- FleetAccessService
- Valve
- WellNotification
- NotificationType
- AccessService
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
- Illuminate\Contracts\Pagination\LengthAwarePaginator
- Illuminate\Http\JsonResponse
- DieselInventory
- Inspector
- MonthlyDieselReportService.php
- FieldManager.vue
- OperationalStateService
- ManualTrips.vue
- Reviews.vue
- Illuminate\Database\Eloquent\Model
- MunicipalityVehicleRepository.php
- UppercaseKeys
- Symfony\Component\HttpFoundation\StreamedResponse
- MunicipalityVehicleRepository
- Beneficiary
- MobileSyncService
- Access.vue
- DieselInventoryService
- ReportTable.vue
- StatusBadge.vue
- StoreManualTripRequest
- VehicleClaimController.php
- Illuminate\Database\Eloquent\Relations\Pivot
- ValveRepository.php
- Illuminate\Http\Response
- VehicleClaimService
- VehicleClaimService.php
- WellMedia
- TripSyncService.php
- MasterDataController
- Illuminate\Support\Carbon
- composer.json
- ReportsController
- Inertia\Response
- ValveRepository
- SystemOperationalStateRepository
- Diesel.vue
- config
- CommandPalette.vue
- ReportsController.php
- TripDetailService
- .update
- scripts
- SignalTypes.vue
- Icon.vue
- WellsMobileService
- require-dev
- AppServiceProvider.php
- MonthlyDieselReportService
- StoreWellRequest
- UpdateWellRequest
- require
- TripSyncService
- .features
- Wells/Http/Controllers/MasterDataController.php
- Illuminate\Validation\Rule
- MunicipalityDriverRepository
- Valves/Map.vue
- .confirm
- psr-4
- autoload-dev
- extra
- StoreValveInspectorRequest
- UpdateValveTeamRequest
- ValvesService
- SyncReadingsRequest
- DriverApiService
- LoginValveInspectorRequest
- StoreValveTeamRequest
- ValveReportService.php
- StoreTeamRequest
- UpdateInspectorRequest
- SyncDieselInventoriesRequest
- SyncNotificationsRequest
- DriverAppPresenceResource

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
- `{closure#2}()` --references--> `Trip`  [EXTRACTED]
  app/Modules/Fleet/Services/VehicleClaimService.php → app/Modules/Fleet/Models/Trip.php
- `{closure#4}()` --references--> `Trip`  [EXTRACTED]
  app/Modules/Fleet/Services/VehicleClaimService.php → app/Modules/Fleet/Models/Trip.php
- `{closure#7}()` --references--> `Valve`  [EXTRACTED]
  app/Modules/Valves/Repositories/ValveRepository.php → app/Modules/Valves/Models/Valve.php
- `{closure#7}()` --references--> `WellActivityMedia`  [EXTRACTED]
  app/Modules/Wells/Repositories/WellsRepository.php → app/Modules/Wells/Models/WellActivityMedia.php
- `AuthController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/Api/AuthController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (137 total, 61 thin omitted)

### Community 0 - "WellActivityMedia"
Cohesion: 0.20
Nodes (3): WellActivityMedia, ActivityMediaRepository, ActivityMediaService

### Community 1 - "@inertiajs/vue3"
Cohesion: 0.06
Nodes (38): @inertiajs/vue3, { t, num }, apply, props, term, debounce(), visit(), bars (+30 more)

### Community 3 - "Wells/Map.vue"
Cohesion: 0.05
Nodes (46): dependencies, @fontsource/ibm-plex-sans-arabic@^5.2.6, @fontsource/inter@^5.2.6, @inertiajs/vue3@^3.7.1, leaflet@^1.9.4, leaflet-polylinedecorator@^1.6.0, lucide-vue-next@^0.544.0, vue@^3.5.42 (+38 more)

### Community 4 - "Team"
Cohesion: 0.12
Nodes (5): Team, TeamRepository, {closure#1}(), {closure#2}(), TeamService

### Community 5 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.07
Nodes (11): ApproveManualTripRequest, AppUsageRequest, CatalogItemRequest, DecisionRequest, SendSmsCodeRequest, StoreTripRequest, VerifySmsCodeRequest, StoreValveActionRequest (+3 more)

### Community 6 - "Well"
Cohesion: 0.07
Nodes (11): {closure#2}(), Well, WellRepository, {closure#1}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}() (+3 more)

### Community 7 - "i18n.js"
Cohesion: 0.07
Nodes (24): emit, onKey(), { t }, emit, { t }, ar, dictionaries, en (+16 more)

### Community 8 - "InspectorService"
Cohesion: 0.15
Nodes (3): {closure#4}(), InspectorRepository, InspectorService

### Community 9 - "vue"
Cohesion: 0.07
Nodes (26): vue, useCrumb(), [, endTime], form, fromOdometer, props, seconds(), [startDate, startTime] (+18 more)

### Community 10 - "Illuminate\Support\Collection"
Cohesion: 0.12
Nodes (6): {closure#10}(), {closure#3}(), {closure#6}(), {closure#8}(), {closure#9}(), DriverAppUsageService

### Community 11 - "Trip"
Cohesion: 0.07
Nodes (11): Trip, {closure#14}(), {closure#23}(), {closure#24}(), {closure#31}(), {closure#32}(), {closure#1}(), {closure#1}() (+3 more)

### Community 12 - "SystemPermissionRepository"
Cohesion: 0.16
Nodes (4): AccessController, SystemPermissionRepository, EnsureFleetAction, OracleReadiness

### Community 13 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.06
Nodes (35): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}() (+27 more)

### Community 14 - "AppShell.vue"
Cohesion: 0.06
Nodes (22): standalone, items, page, activeItem, apps, collapsed, currentApp, lastVisited (+14 more)

### Community 15 - "Controller"
Cohesion: 0.12
Nodes (7): Controller, ActivityMediaController, ApplicationsController, AuditController, LocaleController, OperationsController, OverviewController

### Community 16 - "DriverController"
Cohesion: 0.15
Nodes (3): DriverController, StoreDriverAppPresenceRequest, CurrentBeneficiaryService

### Community 17 - "TripPanel.vue"
Cohesion: 0.07
Nodes (22): emit, form, pending, props, submit(), { t }, emit, fuelLabel (+14 more)

### Community 18 - "ReadingRepository"
Cohesion: 0.07
Nodes (7): {closure#1}(), WellReading, ReadingRepository, {closure#1}(), {closure#2}(), {closure#4}(), ReadingService

### Community 19 - "FleetAccessService"
Cohesion: 0.10
Nodes (5): FleetModule, DriverAppInstallation, DriverAppInstallationRepository, FleetAccessService, FleetLiveService

### Community 20 - "Valve"
Cohesion: 0.10
Nodes (3): Valve, {closure#1}(), {closure#2}()

### Community 21 - "WellNotification"
Cohesion: 0.13
Nodes (3): WellNotification, WellNotificationRepository, WellNotificationService

### Community 22 - "NotificationType"
Cohesion: 0.13
Nodes (5): {closure#5}(), NotificationType, NotificationTypeRepository, {closure#1}(), NotificationTypeService

### Community 23 - "AccessService"
Cohesion: 0.10
Nodes (12): AccessService, ApplicationRegistry, {closure#1}(), EnsureApplicationAccess, EnsurePlatformAdmin, EnsurePlatformUser, {closure#3}(), {closure#4}() (+4 more)

### Community 24 - "SystemPermissionRepository.php"
Cohesion: 0.10
Nodes (11): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}() (+3 more)

### Community 25 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.09
Nodes (5): TripPassenger, TripStop, TripTrackingEvent, ValveLog, ValveMaintenanceReport

### Community 26 - "Illuminate\Http\Request"
Cohesion: 0.15
Nodes (4): EnsureMobilePrincipal, SetLocale, SetMobileLocale, AuthController

### Community 28 - "GeneratorRepository"
Cohesion: 0.20
Nodes (3): WellGenerator, GeneratorRepository, GeneratorService

### Community 30 - "WellsRepository"
Cohesion: 0.06
Nodes (7): ValvesRepository, ValvesModule, WellsController, WellsRepository, WellsService, WellsModule, Module

### Community 31 - "VirtualFuelService"
Cohesion: 0.16
Nodes (4): {closure#2}(), {closure#4}(), {closure#8}(), VirtualFuelService

### Community 32 - "ValveInspector"
Cohesion: 0.15
Nodes (4): AuthController, ValveInspector, ValveInspectorRepository, ValveInspectorService

### Community 35 - "ValveTeam"
Cohesion: 0.16
Nodes (3): ValveTeam, ValveTeamRepository, ValveTeamService

### Community 37 - "Illuminate\Contracts\Pagination\LengthAwarePaginator"
Cohesion: 0.20
Nodes (3): AuditLogRepository, {closure#1}(), {closure#2}()

### Community 38 - "Illuminate\Http\JsonResponse"
Cohesion: 0.07
Nodes (8): AuthController, DashboardController, MaintenanceController, ValveController, ValveMobileService, AuthController, SyncController, WellsLoginRequest

### Community 39 - "DieselInventory"
Cohesion: 0.24
Nodes (10): DieselInventory, {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+2 more)

### Community 40 - "Inspector"
Cohesion: 0.29
Nodes (3): Inspector, {closure#1}(), {closure#2}()

### Community 41 - "MonthlyDieselReportService.php"
Cohesion: 0.16
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 42 - "FieldManager.vue"
Cohesion: 0.13
Nodes (16): close(), dialog, doomed, editing, editInspector(), editTeam(), filter, idsOf() (+8 more)

### Community 43 - "OperationalStateService"
Cohesion: 0.31
Nodes (3): RejectMobileWhenSuspended, RejectWhenSuspended, OperationalStateService

### Community 44 - "ManualTrips.vue"
Cohesion: 0.11
Nodes (12): dialog, emit, { t }, dialog, form, props, { t, num, date }, target (+4 more)

### Community 45 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 46 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.14
Nodes (11): MissionLocation, MissionType, SmsLoginCode, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+3 more)

### Community 47 - "MunicipalityVehicleRepository.php"
Cohesion: 0.06
Nodes (9): {closure#12}(), {closure#15}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+1 more)

### Community 49 - "Symfony\Component\HttpFoundation\StreamedResponse"
Cohesion: 0.18
Nodes (3): ReportRequest, FleetReportService, ReportExporter

### Community 51 - "Beneficiary"
Cohesion: 0.18
Nodes (3): Beneficiary, {closure#6}(), {closure#9}()

### Community 52 - "MobileSyncService"
Cohesion: 0.15
Nodes (8): {closure#1}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), MobileSyncService

### Community 53 - "Access.vue"
Cohesion: 0.13
Nodes (10): editing, filter, form, listed, props, query, results, search (+2 more)

### Community 55 - "DieselInventoryService"
Cohesion: 0.12
Nodes (5): DieselFuelCalculator, {closure#12}(), {closure#13}(), {closure#14}(), DieselInventoryService

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
Cohesion: 0.19
Nodes (4): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell

### Community 65 - "VehicleClaimService.php"
Cohesion: 0.22
Nodes (4): {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 66 - "WellMedia"
Cohesion: 0.30
Nodes (3): WellMedia, WellMediaRepository, WellMediaService

### Community 70 - "composer.json"
Cohesion: 0.20
Nodes (9): description, keywords, license, minimum-stability, name, prefer-stable, repositories, $schema (+1 more)

### Community 72 - "Inertia\Response"
Cohesion: 0.16
Nodes (4): ManualTripController, OperationsController, ManualTripIndexRequest, ValvesController

### Community 73 - "ValveRepository"
Cohesion: 0.14
Nodes (3): ValveRepository, {closure#1}(), ValveActionService

### Community 75 - "SystemOperationalStateRepository"
Cohesion: 0.08
Nodes (8): MapTileController, MapClientRepository, GeoServerTileService, MapClientAuthService, AuthenticateMapClient, SystemOperationalState, {closure#1}(), SystemOperationalStateRepository

### Community 76 - "Diesel.vue"
Cohesion: 0.13
Nodes (12): dialog, form, props, { t }, url, deleter, highCount, props (+4 more)

### Community 77 - "config"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 78 - "CommandPalette.vue"
Cohesion: 0.27
Nodes (9): active, emit, go(), input, onKey(), props, query, results (+1 more)

### Community 82 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 83 - "SignalTypes.vue"
Cohesion: 0.25
Nodes (6): edit(), editing, form, icons, { t, num }, truthy()

### Community 84 - "Icon.vue"
Cohesion: 0.15
Nodes (9): props, attention, page, props, rank, { t, num, relative }, urgent, props (+1 more)

### Community 85 - "WellsMobileService"
Cohesion: 0.18
Nodes (3): {closure#1}(), WellsMobileRepository, WellsMobileService

### Community 86 - "require-dev"
Cohesion: 0.29
Nodes (7): require-dev, fakerphp/faker, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 87 - "AppServiceProvider.php"
Cohesion: 0.12
Nodes (4): MobilePrincipals, RuntimeConfig, AppServiceProvider, {closure#1}()

### Community 92 - "require"
Cohesion: 0.22
Nodes (9): require, inertiajs/inertia-laravel, laravel/framework, laravel/sanctum, laravel/tinker, mog/project-helper, mpdf/mpdf, php (+1 more)

### Community 95 - "Wells/Http/Controllers/MasterDataController.php"
Cohesion: 0.09
Nodes (6): {closure#3}(), StoreInspectorRequest, StoreNotificationTypeRequest, UpdateNotificationTypeRequest, UpdateTeamRequest, WellsListRequest

### Community 96 - "Illuminate\Validation\Rule"
Cohesion: 0.08
Nodes (5): ReviewQueueRequest, UpdateValveInspectorRequest, ValvesListRequest, DieselInventoryReportRequest, MonthlyDieselReportRequest

### Community 98 - "Valves/Map.vue"
Cohesion: 0.20
Nodes (9): el, esc(), mode, onClick(), overlays, props, { t }, tileOptions (+1 more)

### Community 99 - ".confirm"
Cohesion: 0.17
Nodes (3): ConfirmTripUploadRequest, UploadTripPointsRequest, DriverTripRoutePreviewService

### Community 101 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Fixture\\, Database\\Seeders\\

### Community 105 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 106 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 121 - "ValvesService"
Cohesion: 0.38
Nodes (5): {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), ValvesService

### Community 124 - "DriverApiService"
Cohesion: 0.15
Nodes (5): TripSyncController, RespondClarificationRequest, CatalogRepository, DriverApiService, DriverTripSummaryService

## Knowledge Gaps
- **61 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `ReviewController.php`, `ReportsController`, `Illuminate\Support\Collection`, `ReportsController.php`, `Symfony\Component\HttpFoundation\StreamedResponse`, `MunicipalityVehicleRepository`, `FleetAccessService`, `AccessService`, `SystemPermissionRepository.php`, `Illuminate\Http\Request`?**
  _High betweenness centrality (0.056) - this node is a cross-community bridge._
- **Should `@inertiajs/vue3` be split into smaller, more focused modules?**
  _Cohesion score 0.06271186440677966 - nodes in this community are weakly interconnected._
- **Why does `Trip` connect `Trip` to `VehicleClaimService.php`, `ReviewController.php`, `.confirm`, `TripSyncService.php`, `VirtualFuelService.php`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `TripDetailService`, `TripRepository`, `Beneficiary`, `FleetAccessService`, `MunicipalityDriverRepository.php`, `DriverApiService`, `TripSyncService`, `VirtualFuelService`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Should `ReviewController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.09881422924901186 - nodes in this community are weakly interconnected._
- **Why does `Controller` connect `Controller` to `ValveInspector`, `Illuminate\Http\RedirectResponse`, `ReviewController.php`, `MasterDataController`, `Illuminate\Http\JsonResponse`, `ReportsController`, `Inertia\Response`, `SystemOperationalStateRepository`, `SystemPermissionRepository`, `VehicleClaimController.php`, `ReportsController.php`, `DriverController`, `Illuminate\Http\Request`, `DriverApiService`, `WellsRepository`, `Wells/Http/Controllers/MasterDataController.php`?**
  _High betweenness centrality (0.040) - this node is a cross-community bridge._
- **Should `Wells/Map.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.05224963715529753 - nodes in this community are weakly interconnected._
- **Should `Team` be split into smaller, more focused modules?**
  _Cohesion score 0.11861861861861862 - nodes in this community are weakly interconnected._