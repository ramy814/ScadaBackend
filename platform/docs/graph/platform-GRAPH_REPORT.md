# Graph Report - platform-graph  (2026-11-01)

## Corpus Check
- 154 files · ~36,001 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 943 nodes · 2046 edges · 58 communities (28 shown, 30 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 134 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Trip
- Illuminate\Database\Eloquent\Builder
- ValvesRepository
- AppShell.vue
- Illuminate\Database\Eloquent\Relations\BelongsTo
- ApplicationRegistry
- Illuminate\Database\Eloquent\Model
- EmptyState.vue
- WellsRepository
- SystemPermissionRepository
- Controller
- Illuminate\Database\Eloquent\Relations\HasMany
- i18n.js
- SystemPermissionRepository.php
- Illuminate\Http\Request
- DriverAppUsageService
- Valve
- Illuminate\Database\Eloquent\Relations\BelongsToMany
- ref_vue
- UppercaseKeys
- AccessService
- Reviews.vue
- Illuminate\Foundation\Http\FormRequest
- Illuminate\Database\Eloquent\Relations\Pivot
- SystemOperationalStateRepository
- ref_inertiajs_vue3
- Illuminate\Support\Collection
- TripPanel.vue
- Access.vue
- MunicipalityVehicleRepository.php
- MunicipalityVehicleRepository
- WellReading
- FleetReportService
- ReviewController.php
- Inertia\Response
- WellsController
- Catalogs.vue
- Operations.vue
- OperationsController
- AppServiceProvider.php
- Controllers/OperationsController.php
- Trip.vue
- Overview.vue
- NotificationType
- UpdateAccessRequest.php
- TripMap.vue

## God Nodes (most connected - your core abstractions)
1. `Trip` - 37 edges
2. `UppercaseKeys` - 35 edges
3. `useI18n()` - 35 edges
4. `TripRepository` - 33 edges
5. `AccessService` - 30 edges
6. `SystemPermissionRepository` - 29 edges
7. `Controller` - 23 edges
8. `FleetAccessService` - 23 edges
9. `MunicipalityVehicleRepository` - 22 edges
10. `Valve` - 22 edges

## Surprising Connections (you probably didn't know these)
- `OperationsController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/OperationsController.php → app/Http/Controllers/Controller.php
- `ReviewController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Fleet/Http/Controllers/ReviewController.php → app/Http/Controllers/Controller.php
- `ValvesController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Valves/Http/Controllers/ValvesController.php → app/Http/Controllers/Controller.php
- `WellsController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Wells/Http/Controllers/WellsController.php → app/Http/Controllers/Controller.php
- `AccessController` --inherits--> `Controller`  [EXTRACTED]
  app/Platform/Access/AccessController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (58 total, 30 thin omitted)

### Community 0 - "Trip"
Cohesion: 0.05
Nodes (11): FleetModule, Trip, {closure#12}(), TripRepository, FleetAccessService, {closure#1}(), FleetLiveService, TripDetailService (+3 more)

### Community 1 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.05
Nodes (33): DemoCommand, {closure#10}(), {closure#11}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}() (+25 more)

### Community 2 - "ValvesRepository"
Cohesion: 0.10
Nodes (11): ValvesRepository, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+3 more)

### Community 3 - "AppShell.vue"
Cohesion: 0.06
Nodes (28): standalone, active, emit, go(), input, onKey(), props, query (+20 more)

### Community 4 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.08
Nodes (7): TripPassenger, TripPoint, TripStop, TripTrackingEvent, ValveLog, ValveMaintenanceReport, WellNotification

### Community 5 - "ApplicationRegistry"
Cohesion: 0.11
Nodes (6): ApplicationRegistry, {closure#3}(), {closure#4}(), {closure#5}(), HandleInertiaRequests, {closure#1}()

### Community 6 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.13
Nodes (16): MissionLocation, MissionType, {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+8 more)

### Community 7 - "EmptyState.vue"
Cohesion: 0.13
Nodes (17): { t, num }, apply, props, term, debounce(), visit(), bars, props (+9 more)

### Community 10 - "Controller"
Cohesion: 0.14
Nodes (7): Controller, ApplicationsController, {closure#1}(), AuditController, LocaleController, OperationsController, OverviewController

### Community 12 - "i18n.js"
Cohesion: 0.15
Nodes (12): { t, num }, tab, dictionaries, find(), intlLocale(), lookup(), translate(), useI18n() (+4 more)

### Community 13 - "SystemPermissionRepository.php"
Cohesion: 0.13
Nodes (9): SystemPermission, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#4}(), {closure#6}(), {closure#9}() (+1 more)

### Community 15 - "DriverAppUsageService"
Cohesion: 0.13
Nodes (4): DriverAppInstallation, DriverAppInstallationRepository, {closure#3}(), DriverAppUsageService

### Community 17 - "Illuminate\Database\Eloquent\Relations\BelongsToMany"
Cohesion: 0.13
Nodes (3): ValveInspector, ValveTeam, Team

### Community 18 - "ref_vue"
Cohesion: 0.12
Nodes (13): label, props, { t }, tone, tones, items, page, props (+5 more)

### Community 19 - "UppercaseKeys"
Cohesion: 0.14
Nodes (5): ReadingGeneratorLog, WellActivityMedia, WellMedia, {closure#7}(), UppercaseKeys

### Community 20 - "AccessService"
Cohesion: 0.19
Nodes (7): AccessService, EnsureApplicationAccess, EnsurePlatformAdmin, EnsurePlatformUser, RejectWhenSuspended, OperationalStateService, ExecutiveOverviewService

### Community 21 - "Reviews.vue"
Cohesion: 0.13
Nodes (16): afterDecision(), close(), counts, detail, failed, loading, mobile, onKey() (+8 more)

### Community 22 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.16
Nodes (4): DecisionRequest, ReviewQueueRequest, AuditFilterRequest, UpdateOperationalStateRequest

### Community 23 - "Illuminate\Database\Eloquent\Relations\Pivot"
Cohesion: 0.17
Nodes (5): ValveInspectorTeam, ValveTeamValve, InspectorTeam, TeamWell, WellGenerator

### Community 25 - "ref_inertiajs_vue3"
Cohesion: 0.15
Nodes (7): props, { t }, page, { t }, { t, date }, props, { t, num, date, relative }

### Community 26 - "Illuminate\Support\Collection"
Cohesion: 0.17
Nodes (5): {closure#10}(), {closure#6}(), {closure#8}(), {closure#9}(), {closure#5}()

### Community 27 - "TripPanel.vue"
Cohesion: 0.14
Nodes (13): emit, form, pending, props, submit(), { t }, emit, m (+5 more)

### Community 28 - "Access.vue"
Cohesion: 0.13
Nodes (10): editing, filter, form, listed, props, query, results, search (+2 more)

### Community 36 - "Catalogs.vue"
Cohesion: 0.20
Nodes (7): emit, onKey(), { t }, editing, form, props, { t, num }

### Community 37 - "Operations.vue"
Cohesion: 0.20
Nodes (7): dialog, emit, { t }, dialog, form, props, { t, date }

### Community 41 - "Trip.vue"
Cohesion: 0.33
Nodes (5): crumb, useCrumb(), base, props, { t }

### Community 42 - "Overview.vue"
Cohesion: 0.29
Nodes (6): attention, page, props, rank, { t, num, relative }, urgent

### Community 45 - "TripMap.vue"
Cohesion: 0.40
Nodes (3): draw(), el, props

## Knowledge Gaps
- **30 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Trip` connect `Trip` to `ReviewController.php`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Model`, `SystemPermissionRepository`, `Illuminate\Database\Eloquent\Relations\HasMany`?**
  _High betweenness centrality (0.115) - this node is a cross-community bridge._
- **Should `Trip` be split into smaller, more focused modules?**
  _Cohesion score 0.0515406162464986 - nodes in this community are weakly interconnected._
- **Why does `SystemPermissionRepository` connect `SystemPermissionRepository` to `Trip`, `ReviewController.php`, `ApplicationRegistry`, `SystemPermissionRepository.php`, `AccessService`, `Illuminate\Support\Collection`, `MunicipalityVehicleRepository`?**
  _High betweenness centrality (0.047) - this node is a cross-community bridge._
- **Should `Illuminate\Database\Eloquent\Builder` be split into smaller, more focused modules?**
  _Cohesion score 0.0546448087431694 - nodes in this community are weakly interconnected._
- **Why does `TripRepository` connect `Trip` to `FleetReportService`, `Illuminate\Database\Eloquent\Builder`, `DriverAppUsageService`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Support\Collection`?**
  _High betweenness centrality (0.038) - this node is a cross-community bridge._
- **Should `ValvesRepository` be split into smaller, more focused modules?**
  _Cohesion score 0.09581646423751687 - nodes in this community are weakly interconnected._
- **Should `AppShell.vue` be split into smaller, more focused modules?**
  _Cohesion score 0.05689900426742532 - nodes in this community are weakly interconnected._