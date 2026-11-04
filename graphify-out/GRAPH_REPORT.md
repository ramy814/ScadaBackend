# Graph Report - ScadaBackend  (2026-11-04)

## Corpus Check
- 284 files · ~72,185 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 6 file(s) not represented in the graph (top: (none) 4, .xml 1, .css 1)

## Summary
- 2338 nodes · 5478 edges · 147 communities (82 shown, 65 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 486 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `c5d4711e`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- WellActivityMedia
- @inertiajs/vue3
- TripMap.vue
- Team
- Illuminate\Foundation\Http\FormRequest
- WellService
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
- ValvesRepository
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
- .__invoke
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
- useI18n
- Illuminate\Validation\Rule
- WellRepository
- Illuminate\Database\Eloquent\Relations\Pivot
- ValveRepository.php
- Illuminate\Http\Response
- VehicleClaimService
- Carbon\CarbonImmutable
- WellMedia
- api.php
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
- TripDetailService.php
- .update
- scripts
- SignalTypes.vue
- Icon.vue
- WellsMobileService
- require-dev
- AppServiceProvider.php
- Icon.vue
- StoreWellRequest
- UpdateWellRequest
- Illuminate\Database\Eloquent\Relations\HasMany
- require
- TripSyncService
- .features
- Wells/Http/Controllers/MasterDataController.php
- UpdateValveInspectorRequest
- MunicipalityDriverRepository
- Valves/Map.vue
- .confirm
- psr-4
- autoload-dev
- extra
- package.json
- ManualTripForm.vue
- ValvesService
- SyncReadingsRequest
- DriverApiService
- LoginValveInspectorRequest
- StoreValveTeamRequest
- ValveReportService.php
- Wells/Map.vue
- ReadingRepository.php
- MobileSyncService
- SyncNotificationsRequest
- {closure#2}
- WellNotificationService
- dependencies
- StoreNotificationTypeRequest
- app.js
- .routePreview
- ReviewController.php
- WellsController
- .verify
- DriverAppInstallation
- scripts

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
- `{closure#11}()` --references--> `Well`  [EXTRACTED]
  app/Modules/Wells/Services/WellService.php → app/Modules/Wells/Models/Well.php
- `{closure#8}()` --references--> `Well`  [EXTRACTED]
  app/Modules/Wells/Services/WellService.php → app/Modules/Wells/Models/Well.php

## Import Cycles
- None detected.

## Communities (147 total, 65 thin omitted)

### Community 0 - "WellActivityMedia"
Cohesion: 0.21
Nodes (3): WellActivityMedia, ActivityMediaRepository, ActivityMediaService

### Community 1 - "@inertiajs/vue3"
Cohesion: 0.07
Nodes (26): apply, props, term, debounce(), visit(), dateFrom, dateTo, expanded (+18 more)

### Community 3 - "TripMap.vue"
Cohesion: 0.22
Nodes (10): leaflet@^1.9.4, leaflet, draw(), el, emit, fit(), props, splitDirections() (+2 more)

### Community 4 - "Team"
Cohesion: 0.06
Nodes (10): {closure#5}(), NotificationType, Team, NotificationTypeRepository, TeamRepository, {closure#1}(), NotificationTypeService, {closure#1}() (+2 more)

### Community 5 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.08
Nodes (10): ApproveManualTripRequest, AppUsageRequest, CatalogItemRequest, DecisionRequest, ReviewQueueRequest, SendSmsCodeRequest, StoreTripRequest, UpdateMaintenanceReportRequest (+2 more)

### Community 6 - "WellService"
Cohesion: 0.08
Nodes (7): {closure#2}(), {closure#1}(), {closure#11}(), {closure#4}(), {closure#7}(), {closure#8}(), WellService

### Community 7 - "i18n.js"
Cohesion: 0.15
Nodes (9): ar, dictionaries, en, find(), lookup(), merge(), translate(), page (+1 more)

### Community 9 - "vue"
Cohesion: 0.08
Nodes (19): vue, items, page, crumb, useCrumb(), base, props, { t } (+11 more)

### Community 10 - "Illuminate\Support\Collection"
Cohesion: 0.13
Nodes (6): {closure#10}(), {closure#3}(), {closure#6}(), {closure#8}(), {closure#9}(), DriverAppUsageService

### Community 11 - "Trip"
Cohesion: 0.09
Nodes (9): Trip, {closure#14}(), {closure#23}(), {closure#24}(), {closure#31}(), {closure#32}(), {closure#1}(), {closure#2}() (+1 more)

### Community 12 - "SystemPermissionRepository"
Cohesion: 0.14
Nodes (4): AccessController, SystemPermissionRepository, EnsureFleetAction, OracleReadiness

### Community 13 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.06
Nodes (37): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}() (+29 more)

### Community 14 - "AppShell.vue"
Cohesion: 0.09
Nodes (18): activeItem, apps, collapsed, currentApp, lastVisited, navigation, open, page (+10 more)

### Community 15 - "Controller"
Cohesion: 0.12
Nodes (6): Controller, ApplicationsController, AuditController, LocaleController, OperationsController, OverviewController

### Community 16 - "DriverController"
Cohesion: 0.12
Nodes (4): DriverController, StoreDriverAppPresenceRequest, DriverAppPresenceResource, CurrentBeneficiaryService

### Community 17 - "TripPanel.vue"
Cohesion: 0.07
Nodes (22): emit, form, pending, props, submit(), { t }, emit, fuelLabel (+14 more)

### Community 18 - "ReadingRepository"
Cohesion: 0.08
Nodes (7): {closure#1}(), WellReading, ReadingRepository, {closure#1}(), {closure#2}(), {closure#4}(), ReadingService

### Community 19 - "FleetAccessService"
Cohesion: 0.14
Nodes (3): FleetModule, DriverAppInstallationRepository, FleetAccessService

### Community 20 - "Valve"
Cohesion: 0.09
Nodes (3): Valve, {closure#1}(), {closure#2}()

### Community 22 - "ValvesRepository"
Cohesion: 0.13
Nodes (5): ValvesController, ValvesListRequest, ValvesRepository, ValvesService, ValvesModule

### Community 23 - "AccessService"
Cohesion: 0.11
Nodes (10): AccessService, ApplicationRegistry, {closure#1}(), EnsurePlatformUser, {closure#3}(), {closure#4}(), {closure#5}(), HandleInertiaRequests (+2 more)

### Community 24 - "SystemPermissionRepository.php"
Cohesion: 0.13
Nodes (11): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}(), {closure#5}() (+3 more)

### Community 25 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.08
Nodes (7): TripPassenger, TripPoint, TripStop, TripTrackingEvent, {closure#6}(), ValveLog, ValveMaintenanceReport

### Community 26 - "Illuminate\Http\Request"
Cohesion: 0.14
Nodes (7): AuthController, EnsureApplicationAccess, EnsureMobilePrincipal, EnsurePlatformAdmin, SetLocale, SetMobileLocale, AuthController

### Community 28 - "GeneratorRepository"
Cohesion: 0.17
Nodes (3): WellGenerator, GeneratorRepository, GeneratorService

### Community 29 - "TripRepository"
Cohesion: 0.12
Nodes (3): TripRepository, {closure#1}(), FleetLiveService

### Community 30 - "WellsRepository"
Cohesion: 0.11
Nodes (4): WellsRepository, WellsService, WellsModule, Module

### Community 31 - "VirtualFuelService"
Cohesion: 0.13
Nodes (5): {closure#2}(), {closure#4}(), TripDetailService, {closure#8}(), VirtualFuelService

### Community 32 - "ValveInspector"
Cohesion: 0.16
Nodes (4): AuthController, ValveInspector, ValveInspectorRepository, ValveInspectorService

### Community 35 - "ValveTeam"
Cohesion: 0.12
Nodes (3): ValveTeam, ValveTeamRepository, ValveTeamService

### Community 38 - "Illuminate\Http\JsonResponse"
Cohesion: 0.18
Nodes (5): DashboardController, MaintenanceController, ValveController, StoreValveActionRequest, ValveMobileService

### Community 39 - "DieselInventory"
Cohesion: 0.24
Nodes (10): DieselInventory, {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+2 more)

### Community 40 - "Inspector"
Cohesion: 0.21
Nodes (3): Inspector, {closure#1}(), {closure#2}()

### Community 41 - "MonthlyDieselReportService.php"
Cohesion: 0.12
Nodes (9): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}() (+1 more)

### Community 42 - "FieldManager.vue"
Cohesion: 0.13
Nodes (16): close(), dialog, doomed, editing, editInspector(), editTeam(), filter, idsOf() (+8 more)

### Community 43 - "OperationalStateService"
Cohesion: 0.26
Nodes (3): RejectMobileWhenSuspended, RejectWhenSuspended, OperationalStateService

### Community 44 - "ManualTrips.vue"
Cohesion: 0.11
Nodes (12): dialog, emit, { t }, dialog, form, props, { t, num, date }, target (+4 more)

### Community 45 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 46 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.13
Nodes (12): MissionLocation, MissionType, SmsLoginCode, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+4 more)

### Community 48 - "UppercaseKeys"
Cohesion: 0.11
Nodes (3): {closure#1}(), ReadingGeneratorLog, UppercaseKeys

### Community 49 - "Symfony\Component\HttpFoundation\StreamedResponse"
Cohesion: 0.23
Nodes (4): OperationsController, ReportRequest, FleetReportService, ReportExporter

### Community 51 - "Beneficiary"
Cohesion: 0.18
Nodes (3): Beneficiary, {closure#6}(), {closure#9}()

### Community 52 - "MobileSyncService"
Cohesion: 0.16
Nodes (7): {closure#1}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}()

### Community 53 - "Access.vue"
Cohesion: 0.13
Nodes (10): editing, filter, form, listed, props, query, results, search (+2 more)

### Community 55 - "DieselInventoryService"
Cohesion: 0.14
Nodes (5): DieselFuelCalculator, {closure#12}(), {closure#13}(), {closure#14}(), DieselInventoryService

### Community 56 - "ReportTable.vue"
Cohesion: 0.14
Nodes (10): emit, page, pages, picker, props, selected, slice, { t, num, date } (+2 more)

### Community 57 - "useI18n"
Cohesion: 0.07
Nodes (30): @inertiajs/vue3, { t, num }, label, props, { t }, tone, tones, intlLocale() (+22 more)

### Community 59 - "Illuminate\Validation\Rule"
Cohesion: 0.09
Nodes (6): {closure#1}(), StoreManualTripRequest, DieselInventoryReportRequest, UpdateInspectorRequest, {closure#1}(), UpdateAccessRequest

### Community 61 - "Illuminate\Database\Eloquent\Relations\Pivot"
Cohesion: 0.19
Nodes (4): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell

### Community 64 - "VehicleClaimService"
Cohesion: 0.15
Nodes (5): VehicleClaimController, VehicleClaimIndexRequest, {closure#5}(), {closure#6}(), VehicleClaimService

### Community 65 - "Carbon\CarbonImmutable"
Cohesion: 0.13
Nodes (4): {closure#1}(), TripReviewService, {closure#2}(), {closure#4}()

### Community 66 - "WellMedia"
Cohesion: 0.21
Nodes (3): WellMedia, WellMediaRepository, WellMediaService

### Community 68 - "MasterDataController"
Cohesion: 0.12
Nodes (3): MasterDataController, StoreValveInspectorRequest, ValveService

### Community 70 - "composer.json"
Cohesion: 0.20
Nodes (9): description, keywords, license, minimum-stability, name, prefer-stable, repositories, $schema (+1 more)

### Community 73 - "ValveRepository"
Cohesion: 0.19
Nodes (3): ValveRepository, {closure#1}(), ValveActionService

### Community 75 - "SystemOperationalStateRepository"
Cohesion: 0.09
Nodes (7): MapTileController, MapClientRepository, GeoServerTileService, MapClientAuthService, AuthenticateMapClient, {closure#1}(), SystemOperationalStateRepository

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
Cohesion: 0.18
Nodes (8): emit, { t }, edit(), editing, form, icons, { t, num }, truthy()

### Community 84 - "Icon.vue"
Cohesion: 0.29
Nodes (6): attention, page, props, rank, { t, num, relative }, urgent

### Community 85 - "WellsMobileService"
Cohesion: 0.13
Nodes (4): ActivityMediaController, {closure#1}(), WellsMobileRepository, WellsMobileService

### Community 86 - "require-dev"
Cohesion: 0.29
Nodes (7): require-dev, fakerphp/faker, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 87 - "AppServiceProvider.php"
Cohesion: 0.12
Nodes (4): MobilePrincipals, RuntimeConfig, AppServiceProvider, {closure#1}()

### Community 88 - "Icon.vue"
Cohesion: 0.15
Nodes (9): emit, onKey(), { t }, props, { t, date }, editing, form, props (+1 more)

### Community 92 - "require"
Cohesion: 0.22
Nodes (9): require, inertiajs/inertia-laravel, laravel/framework, laravel/sanctum, laravel/tinker, mog/project-helper, mpdf/mpdf, php (+1 more)

### Community 95 - "Wells/Http/Controllers/MasterDataController.php"
Cohesion: 0.09
Nodes (6): {closure#3}(), {closure#4}(), StoreInspectorRequest, StoreTeamRequest, UpdateTeamRequest, WellsListRequest

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

### Community 107 - "package.json"
Cohesion: 0.24
Nodes (10): devDependencies, laravel-vite-plugin@^3.2.0, vite@^8.3.0, @vitejs/plugin-vue@^6.0.8, private, $schema, type, laravel-vite-plugin (+2 more)

### Community 109 - "ManualTripForm.vue"
Cohesion: 0.18
Nodes (10): [, endTime], form, fromOdometer, props, seconds(), [startDate, startTime], submit(), { t } (+2 more)

### Community 121 - "ValvesService"
Cohesion: 0.36
Nodes (4): {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 124 - "DriverApiService"
Cohesion: 0.14
Nodes (5): TripSyncController, RespondClarificationRequest, CatalogRepository, DriverApiService, DriverTripSummaryService

### Community 128 - "Wells/Map.vue"
Cohesion: 0.21
Nodes (11): active(), colors, damage, draw(), el, esc(), operational, props (+3 more)

### Community 129 - "ReadingRepository.php"
Cohesion: 0.29
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 130 - "MobileSyncService"
Cohesion: 0.16
Nodes (3): SyncController, SyncDieselInventoriesRequest, MobileSyncService

### Community 134 - "dependencies"
Cohesion: 0.25
Nodes (7): dependencies, @inertiajs/vue3@^3.7.1, leaflet-polylinedecorator@^1.6.0, lucide-vue-next@^0.544.0, vue@^3.5.42, leaflet-polylinedecorator, lucide-vue-next

### Community 138 - "app.js"
Cohesion: 0.29
Nodes (5): @fontsource/ibm-plex-sans-arabic@^5.2.6, @fontsource/inter@^5.2.6, @fontsource/ibm-plex-sans-arabic, @fontsource/inter, standalone

### Community 145 - "scripts"
Cohesion: 0.50
Nodes (4): scripts, build, build:release, dev

## Knowledge Gaps
- **65 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `ReviewController.php`, `ReportsController`, `Inertia\Response`, `MonthlyDieselReportService.php`, `ReportsController.php`, `Controller`, `MunicipalityVehicleRepository`, `FleetAccessService`, `AccessService`, `SystemPermissionRepository.php`?**
  _High betweenness centrality (0.058) - this node is a cross-community bridge._
- **Should `@inertiajs/vue3` be split into smaller, more focused modules?**
  _Cohesion score 0.0746031746031746 - nodes in this community are weakly interconnected._
- **Why does `Trip` connect `Trip` to `Carbon\CarbonImmutable`, `.confirm`, `VirtualFuelService.php`, `.routePreview`, `ReviewController.php`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `TripDetailService.php`, `DriverController`, `TripRepository`, `Beneficiary`, `FleetAccessService`, `MunicipalityDriverRepository.php`, `DriverApiService`, `TripSyncService`, `VirtualFuelService`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Should `ReviewController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.11764705882352941 - nodes in this community are weakly interconnected._
- **Why does `TripRepository` connect `TripRepository` to `VehicleClaimService`, `Carbon\CarbonImmutable`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Support\Collection`, `Trip`, `.routePreview`, `Illuminate\Database\Eloquent\Builder`, `TripDetailService.php`, `Symfony\Component\HttpFoundation\StreamedResponse`, `FleetAccessService`, `MunicipalityDriverRepository.php`, `Illuminate\Validation\Rule`, `DriverApiService`, `VirtualFuelService`?**
  _High betweenness centrality (0.040) - this node is a cross-community bridge._
- **Should `Team` be split into smaller, more focused modules?**
  _Cohesion score 0.05712050078247261 - nodes in this community are weakly interconnected._
- **Should `Illuminate\Foundation\Http\FormRequest` be split into smaller, more focused modules?**
  _Cohesion score 0.0766488413547237 - nodes in this community are weakly interconnected._