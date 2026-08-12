# Changelog & Recent Actions

## [2026-08-12] Permissioned Wells Reading And Inventory Soft Deletion

- Added `wells.readings.delete` and `wells.diesel-inventories.delete` to the existing central permissions screen; both require `wells` access and fail closed when permission storage is unavailable.
- Added reason-required delete actions to the Wells readings register and external diesel-inventory/results tables. `Daily Tank` fuel-program rows are not deletable from this workflow.
- Successful reading/inventory deletion now displays the Wells success alert and closes the reason modal only after the server confirms the operation; failed validation or authorization leaves the modal open.
- Added audited soft deletion to `WELL_READINGS` and `DIESEL_INVENTORIES` through `DELETED_AT`, `DELETED_BY_USER_ID`, `DELETED_BY_NAME`, and `DELETE_REASON`; no operational row is physically removed.
- Normal model queries, statistics, reports, exports, mobile history, previous-reading lookup, and previous-inventory analytics exclude deleted rows. Mobile UUID retry lookup includes deleted rows only to prevent a deleted record from being recreated.
- Master-data deletion blockers continue counting soft-deleted rows because the physical operational history and foreign keys remain present.
- Applied migration `2026_08_12_000022_add_soft_deletion_audit_to_wells_activities` and added regression coverage for audit persistence, non-physical deletion, hidden chronology, sync idempotency, and fail-closed action permissions.

## [2026-08-12] External Diesel Inventory Date Visibility

- Renamed the external inventory business-date column to **تاريخ الجرد** and made it visible by default in both the inventory and calculated-results tabs.
- Advanced the saved column-selection version so existing browser preferences receive the newly required default date column.

## [2026-08-11] Zero-Supply External Rate

- For an external inventory interval with supplied quantity `0`, calculate consumed fuel as `previous balance - current balance` and divide by the absolute non-zero generator-meter difference.
- Do not classify that zero-supply interval as invalid merely because the meter moved backward or exceeds elapsed calendar time; assess the calculated rate against the approved limit.
- Keep zero meter differences, non-increasing inventory times, incomplete inputs, and negative fuel-consumption results invalid or unavailable as appropriate.

## [2026-08-11] External Inventory Zero-Supply Consumption

- Continued external-tank consumption calculation when a valid inventory interval contains no approved delivery: supplied quantity is `0`, consumed liters are `previous balance - current balance`, and the actual rate uses the absolute non-zero generator-meter difference.
- Made previous balance, supplied quantity, current balance, consumed liters, and actual rate visible by default in the external calculated-results tab and its PDF/Excel column selection.
- Preserved signed negative formula results for diagnosis while classifying them as invalid instead of hiding the calculated values.
- Kept genuinely unavailable inputs distinct: first inventory, unresolved generator, incomplete tank geometry, zero meter difference, or non-increasing inventory time are not invented as zero.

## [2026-08-11] External Diesel Supply Meter-Range Allocation

- Fixed external-tank supply allocation when `FUEL_LOG.FILL_UP_DATE` stores only midnight and incorrectly places a same-day delivery before the previous inventory.
- External supply now uses the approved delivery `CURRENT_READING` against `(previous inventory meter, current inventory meter]`; missing delivery meters fall back to same-day `ENTRY_DATE`, then `FILL_UP_DATE`.
- Widened the repository's lower fetch bound to the start of the previous inventory day so date-only rows reach the service's authoritative meter comparison even under a single-day report filter.
- Verified against Saraya: request 78134 (`650.6`, 950 liters) now belongs to the `645 → 713` interval and the 2026-08-01 inventory reports 950 liters, including when filtering only that date.

## [2026-08-11] Diesel Supply Final-Status Requirement

- Required `G_STORE.FUEL_LOG.STATUS_OID = 416` in both external-inventory approved-supply lookup and internal `Daily Tank` fill sequencing.
- Retained the existing generator asset discriminator, non-null approved quantity, and non-canceled request conditions.
- Verified the updated Oracle queries against Saraya generator `1840`; requests 76960, 78134, 78726, and 79403 remain accepted because each has final status 416.
- Added a repository regression test covering both report query paths.

## [2026-08-11] Diesel Inventory Chronology And Missing-Supply Clarity

- Diagnosed the displayed Saraya row as a backfilled inventory timestamped before every other inventory for that well; its approved supply interval was therefore undefined rather than zero.
- Added date-and-time selection plus a confirmable chronological warning to the offline-first Flutter diesel form. Historical saves remain available and backend sync continues to accept out-of-order records.
- Replaced unexplained null previous-reading/supply dashes with explicit Arabic reasons on the dashboard and shared PDF/Excel exports, while preserving zero for valid intervals with no approved delivery.
- Added regression coverage for the external-report reason and Flutter chronology warning.

## [2026-08-11] Wells Reading Abnormal Threshold

- Changed the production-report indicator so any negative current/previous meter reading, reversed meter, or operating-hours difference above 20 is **غير طبيعي**.
- Kept a difference of exactly 20 hours normal, retained **قراءة أولى** for missing history, and retained **تحتاج مراجعة** for missing current data.
- Included abnormal rows in the review-required summary and added regression coverage for negative, 21-hour, and exact-20-hour cases.

## [2026-08-11] Wells Reading Report Tab And Print Layout

- Replaced the readings production-report modal with a lazy-loaded same-page tab alongside the readings register; the existing PDF/Excel preview actions now switch to that tab instead of opening a popup or browser window.
- Added a server-owned reading report column registry and a locally persisted chooser. The on-screen report, PDF, and Excel now use exactly the same allowlisted columns in the selected order.
- Changed the reading PDF to A2 landscape and increased report typography, cell padding, header hierarchy, and Excel font sizing for clearer printing.
- Added regression coverage for column allowlisting/order and the dynamic shared export template, visually verified the generated A2 PDF, and passed the focused Wells report tests plus the Vite production build.

## [2026-08-10] Lightweight Driver Route Preview

- Added authenticated `GET /api/driver/trips/{trip}/route-preview` for confirmed trips owned by the current driver.
- Kept `GET /api/driver/trips` summary-only; route coordinates are loaded only after opening one server trip.
- Added repository/service downsampling to at most 100 ordered accurate coordinates, excluding invalid and stationary-heartbeat points while preserving endpoints.
- Updated the configured driver release default to `1.1.11+34`.

## [2026-08-10] Fleet Dashboard Experimental Trip Filter

- Classified completed dashboard trips with fewer than 50 stored GPS points as experimental display data; active trips remain visible for live supervision until they end.
- Hid experimental trips from the Fleet dashboard list and trip statistics by default while preserving them in Oracle, reports, and direct trip details.
- Added a prominent Arabic toggle in the trip-table header that shows or hides experimental trips, labels them clearly, and preserves the selection across search, filters, pagination, live refresh, and review actions.
- Verified the repository rule against live Oracle data and passed all 69 Laravel tests plus the Vite production build.

## [2026-08-10] Trip 1591 GPS Gap Recovery

- Verified trip `1591` uploaded all 96 locally recorded points with a matching checksum, but contained four local capture gaps totaling about 24 minutes; internet/server loss and point-quality rejection were not the cause.
- Fixed the driver watchdog so a restarted stream receives a fresh 45-second acquisition window instead of being cancelled every 15 seconds against the pre-restart last point.
- Driver `1.1.6+29` records one `tracking_gap` per provider-silence period, warns the driver, falls back to Android LocationManager/GNSS, and atomically stores airplane mode already enabled at trip creation.
- Fixed `VirtualFuelService` so a gap is split before `stationary_heartbeat` is collapsed. All raw gaps now remain visible as excluded connectors and cannot be joined into distance/fuel.
- Added dashboard counts for explicit GPS-gap events beside GPS-disable and airplane-mode counts, while preserving the rule that these are technical review signals and not proof of intent.
- Advanced backend app-presence defaults to version `1.1.6`, build `29`.

## [2026-08-09] Offline GPS And Airplane-Mode Driver Warning

- Released driver app `1.1.5+28`; satellite GPS, quality filtering, waiting, duration, and SQLite persistence remain operational without internet. Connectivity is needed only for best-effort sync and optional map tiles.
- Replaced the ineffective manifest-only airplane-mode listener with a process-wide context-registered protected broadcast receiver, which remains available while the active foreground trip keeps the process alive.
- If airplane mode is already enabled at trip start, the app shows a non-blocking Arabic dialog. Enabling it during a trip records `airplane_mode_on` against the active driver/trip and displays a high-priority notification explaining that local GPS continues while upload waits.
- Refactored the existing native open-trip notification helper for boot, battery, and airplane alerts, and made native telemetry writes return whether an active-trip event was inserted so alerts are not shown outside a trip.
- Advanced backend app-presence defaults to version `1.1.5`, build `28`.

## [2026-08-09] Driver First-Fix Recovery

- Released driver app `1.1.4+27` after production trip 1586 ended in 6:39 with zero points and zero events, proving its background isolate never acknowledged startup or reached live sync.
- Made startup require an actual background health acknowledgement. The main isolate now sends an unconditional Android start plus `startTracking` and retries once after eight seconds without acknowledgement instead of trusting nominal running-service state.
- Kept the 10-second continuous stream and added a bounded immediate first-position request at tracking startup.
- Activated the existing 15-second watchdog constants: after 45 seconds without any provider callback it recreates the silent stream and retries the immediate request, while SQLite remains authoritative.
- Updated active-trip status and map copy to distinguish a running service waiting for any GPS callback from retained samples that are too weak to paint as route movement; the duration display now refreshes every 10 seconds.
- Advanced backend app-presence defaults to version `1.1.4`, build `27`.

## [2026-08-09] Ten-Second GPS Sampling And Stationary Drift Filtering

- Released driver app `1.1.3+26` with a 10-second GPS request interval instead of 2 seconds; locally persisted movement and 20-second stationary heartbeats remain offline-first.
- Inspected production trips 1582 and 1583. Their 19 and 10 stored rows were mostly `stationary_heartbeat` coverage samples; trip 1582 correctly calculated zero distance, while low-speed startup drift in trip 1583 produced about 50 false meters.
- Classified finite provider speeds from 0 through 1 m/s as stationary for persistence, so coordinate drift is compressed to heartbeats instead of being labeled movement.
- Excluded `stationary_heartbeat` coordinates from filtered distance and fuel in both Flutter and `VirtualFuelService`, while retaining them for GPS coverage/audit continuity.
- Advanced backend app-presence defaults to version `1.1.3`, build `26`.

## [2026-08-09] Immediate GPS Disable Capture

- Released driver app `1.1.2+25` to close the timing gap where a driver could disable location and finish/upload the trip before the periodic device-state watchdog ran.
- Subscribed the active Android foreground tracker to location-service status changes, persisted `gps_disabled`/`gps_enabled` immediately in SQLite, and requested the existing best-effort live sync after insertion.
- Added a final non-blocking GPS-disabled audit before local trip closure, without allowing a platform-status failure to block the offline-first end transaction.
- Advanced backend app-presence defaults to version `1.1.2`, build `25`. Dashboard events remain technical evidence for supervisor review, not proof of deliberate intent.

## [2026-08-09] Non-Blocking Driver Start And Per-Trip Tracking Flags

- Released driver app `1.1.1+24`. Trip creation still requires disclosure, location/notification permissions, precise-location mode, and an enabled location service, but it no longer waits for two `<= 50m` fixes or fails after 25 seconds. The tracker starts immediately and the established quality guardrails reject weak points until reception improves.
- Initialized `flutter_screenutil` globally and adapted the shared shell plus trip start/active controls for narrow and older Android screens while retaining practical minimum touch sizes.
- Kept GPS/airplane evidence attached to the authenticated trip/driver and uploaded through the existing offline-first `tracking_events` contract.
- Added dashboard counts for `gps_disabled` and `airplane_mode_on` on every active or completed trip. The trip list now shows the counts as review evidence instead of hiding them behind the generic completed status; the event timeline remains in trip details.
- Advanced backend app-presence defaults to version `1.1.1`, build `24`. Technical events remain evidence requiring human review and do not prove intentional driver action.

## [2026-08-03] Active-Well Reading Report And Export Preview

- Changed the Wells production report to start from all active wells instead of only wells that already have a matching reading. An explicit `well_oid` filter may narrow the active-well set; inspector/date/quality filters determine which reading is attached and leave unmatched active wells visible as **غير مدخلة**.
- Kept at most one matching reading per well through `READING_DATE DESC, OID DESC`, while the immediately preceding reading still comes from full history for meter and production calculations.
- Added supervisor statuses for normal, first, missing, and review-required rows. These statuses use only reading availability and meter consistency; they do not invent unsupported chlorine, salinity, or water-quality thresholds.
- Added report indicators for active wells, submitted wells, missing wells, completion percentage, and readings requiring review.
- PDF and Excel buttons now load an Inertia preview with the exact current filters before download. The preview shows all report rows and indicators, and the shared export template includes the same summaries and missing-well highlighting.
- Moved reading report validation to `ReadingReportRequest`, changed the PDF to a visually verified A3 landscape layout, passed all 35 Wells tests (90 assertions), and completed the Vite production build.

## [2026-08-02] Wells Diesel Inventory Immediate Dashboard Sync

- Diagnosed new diesel inventories remaining only in Flutter SQLite: reading saves attempted an immediate online upload, while diesel saves waited for a manual sync or a later connectivity-restored event.
- Added a diesel-only immediate upload through `SyncManager` after the local insert. Successful API acknowledgements update the local sync state before the latest-three inventory panel reloads.
- Kept offline-first safety: unavailable or timed-out uploads remain pending and the inspector receives an explicit local-save message.
- Verified the live Oracle table had received no newer inventory after 2026-07-27 before the fix, then passed all 7 Flutter tests and Flutter analysis with only pre-existing info-level findings.

## [2026-07-29] Driver Live Route, Automatic Waiting, And Tracking-State Evidence

- Released the compatible driver contract `1.1.0+23`: the active screen now plots the original locally recorded GPS trace and current/stop markers with lightweight OpenStreetMap tiles, while all controls remain offline-first and independent of tile availability.
- Added automatic waiting detection after two stationary minutes at no more than 1 m/s within a 20-meter radius. SQLite stores candidate/confirmed intervals, anchor coordinates, and separate manual wait so the backend can prevent double counting.
- Reused the existing idempotent trip and point endpoints for best-effort active uploads about every 30 seconds. Active trips are never checksum-confirmed or locally deleted; the established final confirmation protocol remains unchanged.
- Added retry-safe `trip_tracking_events` for GPS/permission state, airplane mode, graceful shutdown/boot, low battery, tracking errors, and gaps. The dashboard presents these as technical evidence requiring review, never proof of intentional driver behavior.
- Added `trip_stops` coordinates/source/client UUID and a new `trip_tracking_events` table through migration `2026_07_29_000021_add_live_tracking_telemetry.php`.
- Added active-trip freshness to the Fleet dashboard, 15-second active Inertia refresh, stop markers/durations, and the full tracking-event audit list on trip details.
- Preserved rolling compatibility: old apps may omit all new fields, new apps can still upload the original contract to an older backend, and only the upgraded backend exposes stop/event monitoring.
- Verified 64 Laravel tests (174 assertions), the Vite production build, Flutter analysis, 12 Flutter tests, and signed Android release/debug APK builds.

## [2026-07-26] Wells Master Data Deletion Protection

- Diagnosed a production Wells inspector deletion that removed its readings, diesel inventories, notifications, and team assignment because every Oracle foreign key used `ON DELETE CASCADE` and `InspectorService` performed an unconditional delete.
- Added repository-owned relation counts and service-level deletion guards for inspectors, teams, and wells. Any linked business record, assignment, media row, generator assignment, or inspector access token blocks physical deletion with a detailed Arabic reason.
- Added `CAN_DELETE` and `DELETE_BLOCKERS` to the three dashboard payloads. Their delete actions now render as disabled locks and explain exactly which linked records prevent deletion.
- Added and applied Oracle migration `2026_07_26_000020`; all foreign keys from Wells master data now use enabled, validated `NO ACTION` constraints instead of cascading deletes.
- Kept physical deletion available only for completely unlinked records. Operational entities with history should be disabled through `IS_ACTIVE` rather than deleted.
- The deleted experimental inspector data was intentionally not restored, and no existing orphan media/token rows were purged.
- Verified all 12 live Oracle master-data constraints, passed 34 Wells tests (82 assertions), and completed the Vite production build.

## [2026-07-26] Signal Image Gallery In Dashboard

- Verified that the latest uploaded signal image is stored in `WELL_ACTIVITY_MEDIA`, linked to its notification, and available through the authenticated relative media route.
- Replaced the subtle signal thumbnails with an explicit **الصور المرفقة** section showing the attachment count and larger previews.
- Added an in-dashboard lightbox with close, previous/next, image counter, and open-original controls while retaining the compact thumbnail variant for reading tables.
- Kept notification media eager-loaded and private; the UI never exposes the storage file path.
- Verified the Vite production build and the Wells activity-media/dashboard unit tests.

## [2026-07-23] Diesel Inventory Tank Classification And Consumption Checks

- Split the Wells diesel inventory page into **مولدات خزان داخلي** (`Daily Tank`) and **مولدات خزان خارجي**. The external section contains separate inventory and calculated-results tabs.
- Internal-tank consumption now uses consecutive approved fuel-program fills: `APPROVED_GALLONS ÷ generator meter-hours difference`.
- External-tank results retain the geometric balance formula: `(previous balance + approved supply - current balance) ÷ operating hours`.
- Added per-well generator settings for the approved consumption rate and allowed rate difference in `WELL_GENERATORS`, including editable fields in the Wells create/edit generator cards.
- Added normal, high, unavailable, and invalid consumption states. Reverse/zero/impossible meter intervals and negative balance results are never mislabeled as high consumption.
- Restricted fuel-program joins to `FUEL_LOG_DETAILS.ASSET_TYPE = 401`, excluded canceled fills, and kept missing intermediate readings in the chronological sequence so they cannot be skipped to manufacture a rate.
- Excluded wells with no `TANK_SHAPE` from both classified groups and added a dashboard warning linking administrators to complete the 71 currently unclassified well records.
- Added an allowlisted column chooser shared by the on-screen table, Excel, and PDF exports. PDF and Excel now use one dynamic RTL report template.
- Applied Oracle migration `2026_07_23_000019`, verified live results (299 approved internal fills and 14 classified external inventories), visually verified the generated PDF, and passed the Wells test suite and Vite production build.

## [2026-07-23] Editable Notification Types

- Enabled administrators to edit notification types even after they have been used by field notifications.
- Kept deletion protection for types that are still referenced, separating `CAN_DELETE` from edit availability in the Vue contract.
- Editing supports the type name, safe icon code, theme color, priority, and active state with Arabic validation errors and a visible saving state.
- Moved notification-type validation into dedicated Form Requests and retained ASCII icon codes for Oracle compatibility.

## [2026-07-23] Reading Reports Latest Row Per Well

- Fixed duplicate wells in both PDF and Excel production reports by selecting only the latest matching reading per `WELL_OID`.
- The selection applies all report filters first, then orders each well's matching readings by `READING_DATE DESC, OID DESC`.
- Kept the immediately preceding historical reading available outside the selected report period for operating-hours and production calculations.
- Verified the live Oracle report changed from 221 reading rows across 26 wells to exactly 26 unique well rows with zero duplicates.
- Added SQLite regression coverage for multiple readings on the same date, deterministic OID tie-breaking, and a newer reading outside the selected period.

## [2026-07-23] Wells Photo Sync Recovery And Parent Idempotency

- Diagnosed production image upload failure: `/api/wells/activity-media/sync` returned `404` while reading sync remained available, proving the APK feature was ahead of the deployed production backend.
- Recovered the two queued inspector images, attached them to reading `266`, verified both private files, and confirmed the main dashboard payload now contains their relative URLs.
- Fixed activity media URLs to use same-origin relative paths instead of the local `APP_URL`.
- Made mobile compression enforce the 1.75 MB target, including recompression of already queued oversized files, while preserving pixel dimensions.
- Changed media synchronization to upload and confirm each photo independently so one oversized/failed image cannot reject the full group.
- Added unique `CLIENT_LOCAL_ID` columns to readings, signals, and diesel inventories; retries now return the existing Oracle row/OID instead of duplicating parent records.
- Migrated the new Oracle columns, restored and cleared the current device queue through the updated local API, rebuilt the APK, and reinstalled the production-target build.

## [2026-07-23] Wells Map Operational Layers And Popup Redesign

- Changed the Wells administrative map source from active-only wells to all wells, preserving the independent ability to show or hide inactive records.
- Added intersecting operational-status and damage-status layer controls with per-layer counts and quick **العاملة فقط** / **عرض الكل** actions.
- Replaced Leaflet's default pin with a custom water-well marker: the primary color indicates operating state, a small dot indicates damage state, and selecting a marker changes the well-name label to the Wells accent color.
- Rebuilt the well popup as a structured, theme-aware RTL card with activation, operation, damage, area, pump type, capacity, and assigned generator number.
- Removed the misleading **سولار** power-source row from the map and resolved generator numbers through `WellService`/`GeneratorRepository` as `MAP_GENERATORS`.
- Switched the page from runtime CDN assets to the locally installed Leaflet package and added regression coverage for inactive-map records and generator resolution.

## [2026-07-23] Required Diesel Meter And Height Readings

- Made generator operating-hours reading and diesel height mandatory in the Flutter diesel inventory form.
- Replaced the optional hints/validators with required non-negative numeric validation while preserving zero as a valid value.
- Parsed both values only after successful form validation and always stored them in the new local inventory and synchronization payload.
- Added matching nested API validation for every inventory item so older or alternate clients cannot submit either value as missing, null, non-numeric, or negative.
- Added Flutter and Laravel regression tests and updated the Wells AI contracts for mobile entry, API validation, and legacy nullable database rows.

## [2026-07-23] Wells Generator Resolution And Editable Well Number

- Fixed the Wells list generator lookup to read the Oracle-hydrated lowercase `generator_oid` pivot attribute before resolving `G_STORE.GENERATOR` metadata.
- The generator column now receives the real generator number and Arabic name for assigned wells instead of showing `غير متوفر`.
- Temporarily enabled editing and persistence of `WELL_NUM` alongside `NAME` and `QUARTER_OID` until the Wells workflow is approved.
- Added regression coverage for generator-ID resolution and editable well identity fields.

## [2026-07-23] Wells Reading And Signal Photo Attachments

- Added camera/gallery attachments to the Flutter reading and signal forms, with up to four images per record and previews/removal before save.
- Added local high-quality compression that preserves pixel dimensions, targets 1.75 MB, avoids replacing a small original with a larger compressed copy, and queues private files offline.
- Extended reading and notification sync responses with local-ID-to-Oracle-OID mappings so images upload only after their parent record exists.
- Added idempotent multipart image synchronization using a unique client photo UUID; partial failures retain only the unconfirmed photos for automatic retry.
- Created and migrated Oracle `WELL_ACTIVITY_MEDIA`, storing files on Laravel's private local disk and verifying the authenticated inspector owns the parent activity.
- Added authorized image thumbnails/full-image links to the main Wells dashboard, readings index, and notifications index.
- Included pending images in Flutter synchronization counts and cleaned queued files when an unsynced parent/draft is removed.
- Verified Flutter tests and Android debug build, Laravel unit tests, Oracle migration, Pint, and the Vite production build.

## [2026-07-23] Wells List, Editing, And Generator Labels

- Added a dedicated generator column to the Wells management list, using the established diesel-inventory presentation: LTR generator number with the Arabic generator name below it.
- Replaced the quarter filter with a damage-status filter while retaining the quarter as a visible table column.
- Visually separated submersible (**غاطس**) and vertical (**رأسي**) pump types with distinct blue-family badges and kept legacy centrifugal values mapped to the vertical label.
- Temporarily allowed administrators to edit the well name and quarter even when readings or diesel inventories exist. The well number remains locked, and wells with transactions still cannot be deleted.
- Removed installation date and last-maintenance date from the general create/edit section without clearing historical database values.
- Renamed generator-card values to **آخر قراءة لعداد المولد (ساعة)** and **آخر كمية تعبئة سولار (لتر)**, removing the incorrect gallon display unit.

## [2026-07-23] Wells Reading Date And Shared Recent-Records Design

- Added an inspector-selectable well-reading date in Flutter, defaulting to today and limited to today through 7 calendar days earlier.
- Kept the selected value in `READING_DATE` through local SQLite storage and synchronization. Reading chronology, dashboard today counts, filters, recent rows, and reports use `READING_DATE`, not record creation or synchronization timestamps.
- Redesigned the Flutter latest-three reading and diesel sections as one legible theme-aware activity panel with relative day labels, exact dates, sync state, and compact primary metrics. Route well selection resolves once in the reading controller so the block appears reliably.
- Simplified Flutter well cards by removing pump-type, power-source, and capacity tags. Cards now show the latest reading as today/yesterday/elapsed days and use a restrained teal completion border/glow when today's reading exists.
- Reading saves now attempt immediate pending-data synchronization when online, refresh the app's home data, and close the reading screen after success. Offline or delayed uploads remain safely queued in SQLite for automatic retry.
- Diesel inventory saves keep the established behavior: remain on the selected well, clear the entry fields, and reload the latest three local inventories.
- Added explicit **تاريخ القراءة** (`READING_DATE`) and **تاريخ إدخال السجل** (`CREATED_AT`) columns to the readings page and the main dashboard's recent-readings table.
- Added a 10-second partial auto-refresh to the Wells main dashboard and readings index so newly synchronized rows appear without manually reloading the page.
- Changed the main dashboard's latest-submitted readings card to order by `CREATED_AT` and then `OID`, ensuring newly received backdated readings appear immediately while all reading-date metrics, filters, and reports continue to use `READING_DATE`.
- Added visible pagination to the readings index, retained active filters across page links, continued row numbering across pages, and ordered rows by `CREATED_AT DESC, OID DESC`.
- Updated Wells AI documentation to reflect the unified `/api/wells/*` routes, the active backend location, the reading-date contract, daily well-card status, and the shared latest-activity design.

## [2026-07-21] Presence Reporting From The Authenticated Main Screen

- Driver release `1.0.20+21` now calls `AppPresenceService` whenever an authenticated `DriverAppShell` opens and whenever the app resumes, covering drivers whose login session is retained.
- Kept the sync coordinator attempt as a fallback; the service-level six-hour throttle and pending flag prevent duplicate traffic while still retrying failed reports.
- Advanced the default approved release to version `1.0.20`, build `21`.
- Production diagnosis rule: unauthenticated `POST /api/driver/app-presence` should reach authentication; HTTP 404 means the backend route has not been deployed and no client-side trigger can register the installation.

## [2026-07-21] Driver App Adoption And Version Monitoring

- Added `DRIVER_APP_INSTALLATIONS`, keyed by the authenticated Oracle driver and a random per-installation UUID, to record app version/build, Android installer store, install/update times, and first/last presence.
- Added authenticated `POST /api/driver/app-presence`; it never trusts a client-supplied driver identity and contains no GPS location.
- Added `/admin/driver-apps` with search and filters for current/outdated/unreported versions, installation source, and recent activity. It combines telemetry with historical trip drivers so older app users remain visible before they upgrade.
- Current vehicle assignments come from `MunicipalityVehicleRepository`; the latest trip vehicle is display-only fallback context when a live assignment is unavailable.
- The approved release is configurable through `DRIVER_APP_LATEST_VERSION`, `DRIVER_APP_LATEST_BUILD`, and `DRIVER_APP_RECENT_DAYS`; defaults are `1.0.19`, build `20`, and 14 days.
- Driver release `1.0.19+20` reports best-effort presence after pending trip upload on startup/connectivity recovery and every six hours, persists retry state locally, and throttles unchanged-build reports. Presence failure never delays trip synchronization.

## [2026-07-21] Driver GPS Readiness And Trip Summaries

- Driver app release `1.0.18+19` now requires precise Android location and two fresh fixes at `<= 50m` before creating a trip.
- The driver provider stream remains roughly two seconds, but duplicate timestamps are skipped, movement is persisted after 8 meters, stationary heartbeats every 20 seconds, and repeated rejected diagnostics every 30 seconds.
- New confirmed trips persist `TRIPS.DISTANCE_KM` and `TRIPS.DURATION_MINUTES`; virtual fuel continues to use the filtered-distance policy.
- Driver confirm/index/reconcile payloads expose distance, duration, fuel, filtered/provisional flags, and quality flags. The Flutter app caches these values for offline trip history.
- The driver active-trip and history screens show distance, estimated diesel, and duration without introducing a map or network dependency during a trip.

## [2026-07-21] Honest GPS Point Classification And Gap Display

- Split genuinely rejected trip points from harmless sampling compression: duplicate timestamps and stationary GPS noise are now counted as collapsed samples rather than invalid points.
- Stopped labeling equal timestamps as out-of-order; only a timestamp that actually moves backward receives that warning.
- Added per-reason rejection counts to trip details and clarified that no raw sample is deleted.
- Kept distance and fuel conservative, while rendering gaps between trusted segments as labeled amber dashed connectors that are explicitly excluded from both calculations.
- Anchored map start/end markers to the first and last recorded valid coordinates rather than the first and last surviving calculation vertices.

## [2026-07-21] Provisional Fuel For Trips With Missing Values

- Added a read-only fallback fuel estimate to trip details when the stored `virtual_fuel_liters` value is missing and a filtered GPS distance is available.
- The fallback uses the existing `0.12 liters/km` policy and remains visible even when coverage warnings prevent the value from being formally stored or approved.
- Labeled fallback values as provisional for review and displayed the calculation formula; no trip row, GPS point, report total, or stored fuel value is modified.

## [2026-07-21] Recorded And Filtered Trip Distances

- Made every trip detail page show the original recorded GPS distance and, when supported by the data, a separate filtered distance.
- Enabled quality filtering for legacy trips when at least 80% of their GPS samples contain usable accuracy metadata; older trips below that threshold continue to show only the recorded route and distance.
- Switched the trip map to accepted GPS segments whenever filtering is available, including eligible legacy trips, without deleting or updating any stored trip point, distance, or fuel value.
- Kept legacy fuel values on their original policy and limited the change to read-time distance analysis and presentation.

## [2026-07-21] Legacy Trip Map Copy Cleanup

- Removed the technical “previous version / new rules” notice from legacy trip maps; older trips now show their recorded GPS route without an implementation-version warning.
- Made the route legend and empty state accurately distinguish between recorded legacy points and accepted quality-v2 points.
- Preserved all original trip coordinates and legacy calculation behavior; this is a presentation-only clarification.

## [2026-07-19] GeoServer Map Click Data Restored

- Passed `mapFeaturesUrl` to the dedicated valves map and registered click lookup only while the mutually exclusive GeoServer mode is active.
- Queried only the currently visible WMS layers through authenticated same-origin `/valves/map-features`; the browser still never sends direct WFS or `GetFeatureInfo` requests to GeoServer.
- Added attractive RTL popups that identify each source layer, translate common valve/pipe/street fields, safely escape values, support overlapping features, and show localized loading, empty, and failure states.
- Added the Leaflet zoom to feature lookup so `ValvesMapService` narrows its WFS bounding box as the user zooms in. Returned features now include internal layer/id metadata used only to label popup sections.
- Preserved complete OpenStreetMap independence: returning to OSM aborts any feature request, closes the GeoServer popup, removes WMS layers, and marker clicks continue using the already-loaded Oracle payload.
- Verified live against GeoServer at valve 6: zoom-level lookup returned the matching `WATER_VALVES.6` data plus nearby centerline features. PHP syntax, all 30 automated tests, `git diff --check`, and the Vite production build passed.

## [2026-07-19] Fleet Dashboard Trip Number Search

- Extended the vehicle-tracking dashboard search to accept an exact numeric trip ID while preserving the existing vehicle-plate and driver-name matching.
- Displayed the trip number as `#id` in its own table column so administrators can identify and search the same value.
- Kept period, clarification-status, pagination, and post-review navigation filters intact.
- Moved the dashboard trip-list query into `TripRepository` and covered trip-number plus legacy vehicle/driver matching with repository feature tests.

## [2026-07-19] Valves Web Map Modes And Gaza Bounds

> **Current valves-map state:** This entry and `.ai/modules/valves/project-overview.md` supersede the older web-map behavior documented below.

- Made OpenStreetMap the default web-map mode and rendered interactive valve markers directly from the `mapValves` Inertia payload sourced through `ValveRepository` and `ValveService` from `EME.WATER_VALVES`.
- Restored a mutually exclusive OpenStreetMap/GeoServer base-map chooser. OpenStreetMap contains only OSM tiles plus Oracle markers; GeoServer has a separate layer selector for centerlines, landmarks, pipes, valves, and optional buildings.
- Ensured switching to OpenStreetMap removes every WMS layer and hides the GeoServer layer selector; switching to GeoServer removes the OSM tile and Oracle marker group before enabling WMS layers.
- Constrained Leaflet to the Gaza bounding box (`31.15..31.65` latitude, `34.15..34.65` longitude) with `minZoom = 10`, preventing the world view.
- Filtered missing and outlier transformed coordinates in `ValveService::getMapValves()`. Live verification kept 1,836 valid Gaza markers and excluded 11 corrupt outliers whose transformed coordinates reached unrelated global locations.
- Kept OpenStreetMap marker popups local to the already-loaded Oracle payload and styled them with the Valves orange identity. GeoServer mode later regained server-side click lookup through `/valves/map-features` as documented by the newer entry above.
- Verified the Gaza filter with `ValveServiceTest`, live Oracle bounds, `git diff --check`, PHP syntax checks, and a successful Vite production build.

## [2026-07-16] Active Wells First

- Ordered the paginated Wells management table by `WELLS.IS_ACTIVE` descending, then by well name, so active wells appear before inactive wells while preserving filters and pagination.

## [2026-07-16] Wells Recent Records And Optional Pump Reading

- Restored the latest three per-well reading and diesel inventory records in the Flutter input forms using local SQLite data, including pending offline records.
- Made `PUMP_HOURS_READING` optional end-to-end: omitted mobile values remain null and the Laravel sync endpoint no longer rejects them.
- Kept `GENERATOR_HOURS_READING` required and added Flutter/PHP regression coverage for the optional pump value.

## [2026-07-16] Wells Quarter Names In List And Map

- Joined `GAZADATA.QUARTERS` once in the shared Wells repository display query and exposed its name as `QUARTER_NAME`.
- Updated the Wells table and map popup to consume the uppercase Oracle/Inertia key, so wells with a stored `QUARTER_OID` display their actual area while wells without one continue to show `-`.

## [2026-07-16] Unified Themes Across All Systems

- Added the authoritative shared semantic theme tokens to `resources/css/app.css` for Fleet, Wells, Valves, the unified portal, and the permissions screen.
- Standardized light mode on the Fleet dashboard palette: `#f0f4f8` page background, translucent white surfaces, slate text, subtle borders, and low-contrast shadows.
- Standardized dark mode on the Valves dashboard palette: `#09090b` page background, translucent zinc surfaces, slate text, white translucent borders, and deeper shadows.
- Kept each module's identity accent independent from the shared surfaces: Fleet/Wells remain blue and Valves remains orange; semantic green, amber, and red continue to indicate status.
- All pages now initialize `html[data-theme]` before rendering from the shared `localStorage.theme` preference or the operating-system preference, preventing portal/theme flashes and keeping navigation between systems consistent.
- Replaced fixed light/dark backgrounds in Fleet forms, Wells surfaces, Valves forms/tables, the portal, and the permissions screen with shared theme variables. Solid map controls and popups remain intentionally light for legibility over map imagery.
- Reworked Wells pagination to use the same shared surfaces in both themes and aligned the Wells map popup action with the module's blue identity accent.
- Removed the Blade-level `bg-gray-50` body class so it cannot override a module's selected theme.
- Audited the remaining module pages and replaced the undefined `--v-*` tokens in Valves reports, the fixed dark Valves theme-toggle surface, and residual white-only table/progress/hover styling with the authoritative shared aliases.

## [2026-07-16] Wells Unified Colors And Diesel Generator Display

- Unified the Wells dashboard light and dark themes around a restrained blue accent; green, amber, and red are now reserved for semantic statuses instead of competing primary navigation colors.
- Replaced the mixed teal/blue navigation, buttons, cards, highlights, and generator panels with one blue color family while retaining neutral white/slate light surfaces.
- Made the diesel inventory table display the generator number and name instead of only the raw `GENERATOR_OID`.
- Split the generator display into a non-wrapping LTR number and a smaller Arabic name below it, preventing generator codes from breaking vertically and inflating table rows.
- For historical inventories with a null `GENERATOR_OID`, the dashboard now uses the same safe resolution rule as the diesel report: use the well assignment only when exactly one generator is assigned; ambiguous assignments remain unresolved.
- Verified the resolved generator against live Oracle data and covered the paginated inventory contract with a unit test.

## [2026-07-16] Wells Sidebar And Light Theme Repair

- Fixed sidebar route matching so `/wells/diesel-inventories` activates only `جرد السولار` instead of also activating `لوحة التحكم`; child well routes still activate `الآبار` correctly.
- Removed the duplicated Wells content wrapper from the shared layout, eliminating excessive top spacing on every Wells page.
- Prevented wide diesel tables from expanding the flex layout or pressing against the fixed sidebar by constraining `.app-content` and the table scroll container.
- Improved light-theme contrast for accents, secondary/muted text, borders, cards, inputs, table headers, hover states, and secondary buttons while preserving separate dark-theme colors. The later unified-colors entry supersedes the original green accent choice.
- Restored the responsive sidebar toggle on narrow screens and kept flash messages in a fixed layout-safe container.
- Verified active-route edge cases, WCAG contrast ratios (accent 4.96:1; secondary text 8.56:1), and the production Vite build.

## [2026-07-16] Inspector App OpenStreetMap-Only Map

- Removed all GeoServer/WMS code, layer settings, feature queries, and feature-info UI from `/Volumes/SecondDrive/Scada/scada_flutter`.
- Changed the full inspector map and dashboard preview to use OpenStreetMap only and render markers from authenticated `GET /api/valves`.
- Kept mobile map visibility team-scoped by using the existing inspector/team assignment query, including the explicit `ASSIGN_ALL_VALVES` rule.
- Added Oracle Spatial coordinate extraction in `ValveRepository`: `EME.WATER_VALVES.GEOMETRY` is transformed from SRID 2039 to WGS84/EPSG:4326 and returned as `latitude`/`longitude`.
- Removed the unused mobile endpoints `/api/dashboard/map-data`, `/api/dashboard/map-settings`, and `/api/dashboard/map-features`, plus their mobile-only controller/service methods.
- Preserved the separate web map contract, `ValvesMapService::features()`, authenticated `/valves/map-features`, and `public/wms.php` without modification.
- Verified against live Oracle data: inspector 1 received 11 assigned valves with 11 coordinates; the all-valves path returned 1,847 valves with 1,847 coordinates.

## [2026-07-16] Valves Map Server-First Tile Loading

> **Historical state:** Superseded by the 2026-07-19 valves-map entry and the “Current Authoritative Web Map Contract” in `.ai/modules/valves/project-overview.md`. Historical GeoServer credential/header and cURL instructions must not be restored.

- Reordered the diesel inventory dashboard so current and previous generator-hour readings are adjacent and explicitly measured in hours.
- Made dashboard fuel balances and report calculations use only the well tank geometry plus diesel height; stored/client `AVAILABLE_FUEL_LITERS` is no longer used as a fallback when tank data is incomplete.

- Changed the valves map service worker from cache-first to network-first WMS tile loading.
- Successful server tiles refresh the browser cache; cached tiles are used for up to 30 days only when the server is unreachable or returns a proxy fallback.
- Kept `public/wms.php` unchanged and aligned web WMS callers with its required `g_page=/cite/wms` parameter.
- Normalized verified PNG responses in the service worker because the fixed proxy emits its default `text/html` content type even when the response body is a valid PNG tile.
- Restored direct GeoServer tile loading for local HTTP development, where the fixed proxy intentionally rejects non-production referrers; HTTPS production continues through the same proxy.
- Disabled the direct local `GetCapabilities` browser probe because GeoServer rejects cross-origin `fetch` requests; this removes the CORS/404 console errors without changing tile loading or `public/wms.php`.
- Replaced browser `GetFeatureInfo` calls with authenticated `/valves/map-features` requests backed by `ValvesMapService`, fixing the popup that displayed the literal text `error` locally.
- Made `ValvesMapService` feature lookup compatible with production by trying the internal `appdev-01` host before the local FQDN and using unauthenticated PHP streams instead of the optional cURL extension; upstream failures no longer cause `/valves/map-features` to return HTTP 500.
- Preserved the GeoServer valve feature ID as `W4_GENERAL_ID` when absent from properties so valve-team map selection continues to resolve Oracle valve records.

## [2026-07-15] Assigned Team Valves First

- Updated valve-team create/edit selectors to keep assigned valves at the beginning of the filtered list for quick revocation.

## [2026-07-15] Valve Team Assignment From Map

- Added an “اختيار من الخريطة” action to valve-team create and edit forms while preserving the existing searchable checkbox list.
- Added `ValveMapSelector.vue`, which uses WMS `GetFeatureInfo` on `cite:WATER_VALVES` and maps the GeoServer feature ID to `EME.WATER_VALVES.W4_GENERAL_ID`.
- Superseded on 2026-07-16: the selector now uses authenticated `/valves/map-features` backed by `ValvesMapService`; it no longer calls browser `GetFeatureInfo`.
- Map selections update the existing `valve_ids` payload, so no new assignment endpoint or database path was introduced.
- Added an explicit chooser for multiple valves occupying the same or nearby map location.

## [2026-07-15] Valves Tile Cache Retention Extended

- Extended the browser-local WMS tile cache lifetime from 24 hours to 30 days while retaining the 800-tile limit and fallback-response exclusions.

## [2026-07-15] Valves Landmarks Layer And Tile Cache Response Fix

- Added `cite:V_LANDMARKS_V2` to the WMS allowlist, web valves map, feature-identification priority, and mobile map settings.
- Fixed the browser tile cache returning a consumed response by caching a cloned response body, resolving `FetchEvent.respondWith: Response is disturbed or locked`.
- Kept the buildings layer disabled by default.

## [2026-07-15] Valves Browser Tile Cache And Building Default

- Added a browser-local Cache Storage service worker for successful valves WMS `GetMap` tiles, with a 24-hour lifetime and an 800-tile limit.
- Excluded transparent WMS fallback responses from the local cache and retained stale cached tiles as an offline fallback.
- Disabled `cite:V_BUILDING_GEOSRV` by default in both the web map and mobile map settings while keeping it available in the layer selector.
- Left `public/wms.php` unchanged.

## [2026-07-15] Valves Map Operational Layers Enabled

- Enabled `cite:WATER_VALVES`, `cite:WATER_PIPE`, `cite:V_BUILDING_GEOSRV`, and `cite:V_CENTERLINE_SEGMENTS` by default on the web valves map.
- Aligned `GET /api/dashboard/map-settings` so the same four layers are visible by default in the valve inspector mobile map.
- Preserved the existing WMS proxy implementation in `public/wms.php` without modification.

## [2026-07-15] Inspector Wells Mobile Sync Regression Fix

- Fixed `GET /api/wells/sync/wells` returning HTTP 500 when an assigned well had a previous diesel inventory. Oracle returned `INVENTORY_DATE` as a string, so the endpoint now normalizes it through Carbon before emitting ISO 8601 JSON.
- Moved inspector-well filtering and latest-inventory queries out of `SyncController` into `WellRepository`, `DieselInventoryRepository`, and `WellService`.
- Replaced the per-well latest-inventory query with one Oracle `ROW_NUMBER()` query for all assigned wells.
- Verified live Oracle responses for every active inspector: Rami Ghayada receives 12 active assigned wells, and all inspector responses return HTTP 200.
- Added `WellSyncServiceTest` to guard Oracle string-date formatting in the Flutter sync contract.

## [2026-07-14] Wells Diesel Inventory Report

- Added PDF and Excel exports to the Wells diesel inventory screen using the active well, inspector, generator, and date filters.
- Matched the supplied `تقرير الابار اليومي28-06-2026.xlsx` structure with well/generator data, previous/current meter readings, operating hours, previous/current diesel heights, approved fuel deliveries, actual consumption rate, tank capacity, water production, and calculated diesel consumption.
- Generator capacity and nominal fuel consumption come from `G_STORE.GENERATOR.ENGINE_CAPACITY` and `FUEL_CONSUMPTION`; approved deliveries come from `G_STORE.FUEL_LOG` and `FUEL_LOG_DETAILS.APPROVED_GALLONS` between consecutive inventories.
- Inventory rows without `GENERATOR_OID` use the well's assigned generator only when exactly one generator is assigned; ambiguous assignments remain explicit in report notes.
- Added unit coverage for the report calculations and visually verified the Oracle-backed PDF as a single A3 landscape page.

## [2026-07-14] Wells Equipment Fields, Media, And Project Name

- Renamed the application UI to `نظام ادارة آبار بلدية غزة`.
- Updated pump types to `submersible` and `vertical`, added pump horsepower, and added `generator` as a power source.
- Updated fuel tank choices with `HDPE` material and `Daily Tank`, and removed concrete/reinforced-plastic choices from new edits.
- Changed chlorination availability to `يوجد`/`لا يوجد`, added `يعمل`/`لا يعمل` status, constrained chlorine tank types/materials, and removed the chlorine pump model from the form.
- Added cooling tank capacity/type, cooling-system status, and cooling-line type choices.
- Added named, removable private images for pumps, fuel tanks, cooling systems, valves/parts, and well rooms through `WELL_MEDIA`.
- Normalized safe legacy values (`centrifugal` to `vertical`, reinforced plastic to `HDPE`, and legacy chlorination types to binary availability) without deleting historical concrete-tank data.
- Ran migrations `2026_07_14_000011_extend_wells_equipment_data` and `2026_07_14_000012_normalize_well_equipment_values`, the PHP test suite, and the Vite production build successfully.

## [2026-07-14] Wells Diesel Inventory Calculated Balance

- Calculated the dashboard's actual diesel balance and latest-balance summary from the tank shape, tank dimensions, and measured diesel height instead of trusting the submitted liters value alone.
- Supported vertical cylinders, horizontal cylinders (circular-segment volume), and cube/square-base tanks.
- Added the previous chronological fuel reading for each well to the diesel inventory table using one Oracle `LAG` query, independent of the active dashboard filters.
- Added unit coverage for all three tank shapes and verified the result against current Oracle inventory data.

## [2026-07-14] Active SSO Users Only In Permission Search

- Added `MOG_USERS.USERS_LIST.IS_ACTIVE = 1` to the central permission-screen user search.
- Grouped the name, SSO ID, and employee ID alternatives so numeric `OR` conditions cannot bypass the active-user filter.
- Existing permission assignments remain visible even if an SSO user later becomes inactive, allowing administrators to revoke stale access.

## [2026-07-14] Unified Local And Production SSO User

- Set local development login through `MOG_LOCAL_ADMIN_USER_ID=140`, matching the SSO user ID used in production.
- Removed the stale hard-coded fallback user ID from `config/mog-helpers.php`; local environments must configure the ID explicitly.
- Moved `fleet`, `wells`, and `valves` permissions from the duplicate legacy SSO row `1061` to the canonical user `140`.
- Removed the `user_id` request override from `/admin/dev-login`, preventing old development URLs from logging back into the inactive duplicate SSO row.
- Normalized stale authenticated local sessions in `EnsureAdminIsAllowlisted`, so existing browser cookies are switched to the configured development SSO user before system permissions are checked.

## [2026-07-08] Wells Teams/Inspectors — Oracle PDO Lowercase Pluck Fix

- **Problem**: `TeamRepository::syncWells()` and `InspectorRepository::syncTeams()` used `pluck('WELL_OID')` / `pluck('TEAM_OID')` with uppercase column names on raw `DB::table(...)` queries. Oracle's PDO driver always returns column names in **lowercase**, so the pluck returned empty collections — making every sync think it was starting from scratch and causing duplicate INSERT errors.
- **Fix**: Changed `pluck('WELL_OID')` → `pluck('well_oid')` and `pluck('TEAM_OID')` → `pluck('team_oid')`.
- **Files**: `app/Repositories/Wells/TeamRepository.php`, `app/Repositories/Wells/InspectorRepository.php`
- **Rule added**: When using `DB::table()` raw queries on Oracle, always use **lowercase** column names in `pluck()`, `value()`, and `where()` calls — PDO normalizes Oracle column names to lowercase on fetch.

---

## [2026-07-08] Valves Map Building WMS 500 Noise Suppressed

- Hid the `cite:V_BUILDING_GEOSRV` buildings layer by default on the Valves map and mobile map settings because the upstream WMS currently returns HTTP 500 for building `GetMap` tiles.
- Kept the buildings layer available in the map layer control for manual testing after the upstream WMS is fixed.
- Hardened `public/wms.php` so missing PHP cURL support returns a controlled fallback instead of a fatal 500.
- Added a shutdown fallback for `GetMap` requests so fatal proxy errors return a transparent PNG where possible instead of filling the browser console with tile 500 errors.
- Removed custom `console.warn` logging for WMS tile failures in `Map.vue`; the UI health banner remains the user-facing outage signal.

## [2026-07-08] Fleet Dashboard Search, Pagination, and Report Filtering

- Added vehicle/driver search to the Fleet dashboard using trip snapshot fields (`vehicle_plate`, `driver_name`) so admins can find trips without querying live Oracle asset tables.
- Changed the Fleet dashboard trip list from a fixed latest-30 view to paginated results (`30` per page) while preserving period, clarification-response, and search filters across page navigation and review actions.
- Updated the dashboard table title to clarify that rows belong to the selected period.
- Reworked Fleet reports filters from driver/vehicle dropdowns to direct text inputs:
  - `driver_search` filters by `driver_name`, `driver_phone`, `driver_employee_number`, or `oracle_driver_id`.
  - `vehicle_search` filters by `vehicle_plate`, `vehicle_name`, or `oracle_vehicle_id`.
- Reports now filter reactively after typing, preserve filters for pagination and CSV/PDF exports, and include a row-level "عرض" link to open the trip detail page.
- Rebuilt Vite assets under `public/build` because production deploys may use committed frontend assets.
- Verified with `php -l app/Http/Controllers/AdminAuthController.php`, `npm run build`, and `php artisan test`.

## [2026-07-05] Wells Module — Oracle Pivot & Auth Fixes

### 1. Auth: Multi-System Gate Fix (`EnsureAdminIsAllowlisted`)
- **Problem**: `admin.allowlisted` middleware checked the old `FLEET_ADMIN_ALLOWLIST` table, blocking Wells/Valves-only users even when they had permissions in `SYSTEM_PERMISSIONS`.
- **Fix**: Rewrote `EnsureAdminIsAllowlisted` to check `SYSTEM_PERMISSIONS` — any user with at least one system permission passes the gate; per-page `EnsureSystemAccess` handles fine-grained access.
- **File**: `app/Http/Middleware/EnsureAdminIsAllowlisted.php`

### 2. Wells Teams: 500 on Create (`$team->OID = null`)
- **Problem**: After `create()` on Oracle, Eloquent stores the primary key in lowercase (`oid`), so `$team->OID` returns `null`. Passing `null` to `syncWells()` triggered a `findOrFail(null)` exception.
- **Fix**: Use `$team->getKey()` everywhere after `create()`.
- **Files**: `app/Services/Wells/TeamService.php`

### 3. Wells Teams: 500 on sync (`Eloquent sync()` vs Oracle)
- **Problem**: `BelongsToMany::sync()` conflicts with Oracle uppercase column names in pivot tables (`CREATED_AT` vs `created_at`), causing Eloquent errors.
- **Fix**: Replaced `->sync()` with raw SQL (`DB::connection('oracle')->statement(...)`) for manual INSERT/DELETE on `team_wells`.
- **Files**: `app/Repositories/Wells/TeamRepository.php`

### 4. Wells Inspectors: Same two issues (#2 & #3)
- Applied identical fix: `getKey()` in service, raw SQL in repository for `inspector_teams`.
- **Files**: `app/Services/Wells/InspectorService.php`, `app/Repositories/Wells/InspectorRepository.php`

### 5. Wells: 500 on `withPivot('created_at')` when loading relations
- **Problem**: `->withPivot('created_at')` in `BelongsToMany` definitions caused a 500 when Eloquent tried to hydrate pivot rows — OCI8 returns `CREATED_AT` (uppercase) but Eloquent expects `created_at` (lowercase).
- **Fix**: Removed `->withPivot('created_at')` from all four relations (`Team::wells`, `Team::inspectors`, `Inspector::teams`, `Well::teams`). The pivot timestamp is not used in the UI.
- **Files**: `app/Models/Wells/Team.php`, `app/Models/Wells/Inspector.php`, `app/Models/Wells/Well.php`

### Key Lesson Documented
Two permanent rules added to `.ai/coding-rules.md` and `.ai/modules/wells/coding-rules.md`:
- Always use `$model->getKey()` after Oracle `create()`, never `$model->OID`.
- Never use `->withPivot()`, `->withTimestamps()`, or `->sync()` on Oracle-backed pivot tables; use raw SQL instead.

---

## [2026-07-02] Wells Generator Assignment Filter


- Filtered the Wells add/edit generator dropdown to active generator assets only from `G_STORE.GENERATOR` using `IS_ACTIVE = 1` and `TYPE_OID = 128`.
- Kept the generator card data contract unchanged: `OID`, `NAME`, `GENERATOR_NUM`, latest `CURRENT_READING`, and `GALLONS`.
- Changed the latest fuel-log join to `LEFT JOIN` so valid active generators still appear even when they do not yet have a fuel-log reading.
- Verified the Oracle-backed repository returns 37 filtered generators with card fields populated where available.

## [2026-07-02] Wells Readings Production Report Export

- Added `date_from` / `date_to` filters to the Wells readings monitoring page.
- Added PDF and Excel export actions for the filtered Wells readings report.
- Implemented production report calculations from readings: previous reading, current reading, operating-hours difference, and `production = difference * well.CAPACITY`.
- Added `wells/readings-report` Blade template matching the daily wells report structure from the provided Excel sample.
- Added backend report routes under `/wells/readings/report/pdf` and `/wells/readings/report/excel`.
- Hardened Wells reading date filtering so `date_to` includes the full selected day.

## [2026-07-02] Wells Dashboard Broken Panels Removed

- Removed the Wells dashboard `توزيع الجودة` panel because the quality fields are no longer reliable for dashboard reporting.
- Removed the Wells dashboard `آخر جرد السولار` and `آخر القراءات المدخلة` tables because their latest-record summaries were not working correctly.
- Simplified `App\Services\Wells\DashboardService` so it no longer queries recent readings, recent diesel inventories, quality distribution, or poor-quality alerts for the dashboard.
- Removed unused quality-distribution/poor-quality helper code from the Wells reading repository/service path while keeping the standalone readings and diesel inventory pages intact.

## [2026-07-02] Production Permission Data Correction

- Verified production SSO login for `رامي عيد سليم غياضة` resolves to `mog_users.users_list.id = 140` and `emp_id = 2541`.
- Found old permissions on a different SSO row (`id = 1061`, display name `رامي غياضة`), which made production show all systems as locked despite development access working.
- Granted `fleet`, `wells`, and `valves` permissions to the actual production SSO user row `SYSTEM_PERMISSIONS.USER_ID = 140`.
- Keep future portal permissions tied to the exact `mog_users.users_list.id` returned by SSO login, not similar names and not `emp_id`.

## [2026-07-02] Production Portal Permission ID Fix

- Fixed production portal cards showing locked for an authorized SSO user after login.
- `PortalController` and `EnsureSystemAccess` now resolve per-system permissions using `mog_users.users_list.id` via `getAuthIdentifier()`, not `emp_id`.
- Added `tests/Feature/SystemPermissionUserIdTest.php` to guard the `id` vs `emp_id` distinction for portal card rendering and system access middleware.
- Keep `emp_id` scoped to the global admin allowlist flow; `CONTROL_UNIT.SYSTEM_PERMISSIONS.USER_ID` stores the SSO internal `id`.

## [2026-07-02] Production Blank Page Deployment Fix

- Diagnosed the production blank page after MOG SSO login where the returned HTML body rendered without the Inertia root element.
- Confirmed the local Inertia root Blade view renders `id="app"`, `data-page`, and Vite `/build/assets/app-*` links.
- Added `tests/Feature/InertiaRootViewTest.php` to catch regressions where the Inertia root view renders empty.
- Removed `/public/build` from `.gitignore` so git-based production deploys can include Vite build assets.
- Rebuilt frontend assets locally; production deploys must either commit the complete `public/build` directory or run `npm install && npm run build` on the server, followed by Laravel cache clearing.

## [2026-07-01] Valves Map Performance, CORS Fixes & Theme Variables Cleanup

- Fixed valves map freezing by introducing `AbortController` in `Map.vue` to cancel pending `GetFeatureInfo` requests when clicking or unmounting.
- Resolved single-threaded Laravel server blocking by loading WMS tiles directly from `GEOSERVER_WMS_URL` (direct browser network access).
- Routed WMS `GetFeatureInfo` requests through `/wms.php` proxy to bypass browser CORS restrictions.
- Tuned `public/wms.php` proxy: added `set_time_limit(0)` / `ignore_user_abort(true)`, and set `MAX_GETMAP_DIMENSION = 256` for faster slow-network responses.
- Cleaned up Light/Dark theme variables across `valves.css`, `wells.css`, and `AppLayout.vue` by removing hardcoded dark overlays (`rgba(17, 24, 39, ...)` etc.) to ensure a clean, bright, and legible light mode.
- Removed the raw tracking points log table ("السجل الكامل لنقاط التتبع") from the trip detail view (`TripShow.vue`).
- Removed the `'fltr_citizens'` layer from the allowed WMS layers list in `public/wms.php` and cleaned up its references in the AI overview docs.
- Configured all GeoServer layers (Buildings, Streets, Pipes, Valves) to load and be visible by default on the valves map.
- Set the default base map to "بدون OpenStreetMap" (noBaseLayer) on startup, with OSM remaining as an optional selector.
- Replaced the gear (cog) icon for the map link in the valves system Sidebar.vue with a dedicated map SVG icon, and changed its label to "خريطة المحابس".

## [2026-07-01] SCADA Flutter Map Source Alignment

- Added backend map endpoints for the mobile SCADA app:
  - `GET /api/dashboard/map-settings` returns the canonical backend `/wms.php` URL and valves map layers.
  - `GET /api/dashboard/map-features` proxies feature lookup through the backend instead of exposing direct GeoServer calls to Flutter.
- Implemented those endpoints through `App\Http\Controllers\Api\Valves\MapController` and `App\Services\Valves\ValvesMapService` so route files stay thin.
- Updated `/Volumes/SecondDrive/Scada/scada_flutter` so the main map, dashboard map preview, and fallback settings all read WMS tiles from this backend's `/wms.php` proxy.
- Kept the SCADA Flutter default backend port at `8000`; use `--dart-define=API_PORT=8002` only when the local Laravel server is actually running on `8002`.
- Reduced `public/wms.php` GetMap behavior to one upstream attempt with a 25 second timeout so slow GeoServer tiles can still render while staying below the local PHP execution limit.
- Reduced initial map pressure by disabling `cite:V_CENTERLINE_SEGMENTS` by default and setting Leaflet WMS `keepBuffer` to `0`; the layer remains available in the control for manual enabling.
- Removed mobile direct dependency on old external map hosts from Flutter `lib/`.
- Kept `cite:WATER_PIPE` available but hidden by default in mobile, matching the stable web valves map behavior.
- Verified `GET /api/dashboard/map-settings` on the running local backend and confirmed Laravel registers both dashboard map routes.

## [2026-07-01] Valves Map Stabilized

- Confirmed the valves map is stable after the WMS proxy and Leaflet adjustments.
- Keep the outage banner suppressed when GeoServer WMS tiles are loading successfully, even if the health probe is stale or intermittently fails.
- Keep `GetFeatureInfo` popups on a solid white readable background independent of dark/light page theme.
- Keep `cite:WATER_PIPE` disabled by default until the GeoServer/Oracle Spatial issue `ETYPE 4 inconsistent with expected LINE` is fixed upstream.

## [2026-07-01] Valves Map Open Fix

- Fixed the broken valves sidebar link that pointed to `/valves/valves`; it now opens the existing valves map route.
- Pointed the maintenance reports sidebar entry back to the valves dashboard instead of the missing `/valves/maintenance` route.
- Added backend redirects for the old `/valves/valves` and `/valves/maintenance` paths so stale browser assets no longer produce 404s.
- Updated `public/wms.php?health=1` to always return a JSON response with HTTP 200 and `ok: false` when GeoServer is unreachable, so the UI can show the Arabic outage message without a browser-level failed-resource error.

## [2026-07-01] GeoServer Oversized WMS Protection

- Reviewed GeoServer logs showing repeated full-viewport WMS `GetMap` requests such as `WIDTH=1060` and `HEIGHT=597` with `Tiled=false`.
- Added a `public/wms.php` guard that blocks oversized `GetMap` requests above `512x512` and returns a transparent PNG before they reach GeoServer.
- Updated the valves Leaflet WMS overlays to explicitly request tiled `256x256` images, disable zoom-time tile refreshes, and keep a smaller tile buffer.
- Restored the valves map default base layer to `بدون OpenStreetMap`; OSM remains available as an optional base layer.
- Fixed normal-sized WMS tile failures so `GetMap` now returns a transparent PNG instead of HTTP `502` when GeoServer returns an error or exception body.
- Disabled `cite:WATER_PIPE` by default because GeoServer currently returns `ETYPE 4 inconsistent with expected LINE`; the layer remains available in the layer control for manual testing after GeoServer data/style repair.
- Suppressed the GeoServer outage banner when WMS tiles are successfully loading, and made `GetFeatureInfo` popups use a solid white readable background independent of the page theme.

## [2026-07-01] Valves Source Table Correction Follow-up

- Corrected the valves authoritative source from `EME.WATER_PIPE` to `EME.WATER_VALVES`.
- Updated `App\Models\Valves\Valve` to use `W4_GENERAL_ID` and `W4_*` valve columns.
- Updated valve team validation to check `oracle.EME.WATER_VALVES.W4_GENERAL_ID`.
- Verified `EME.WATER_VALVES` reads through the model with `1847` records.

## [2026-07-01] Valves Source Table Correction

- Removed the incorrectly-created local `VALVES` migration file and added `2026_06_30_000009_drop_wrong_valves_table.php` to drop the wrong table.
- Ran `php artisan migrate`; confirmed local `VALVES` table count is `0`.
- Updated `App\Models\Valves\Valve` to read from the external EME source table instead of local `VALVES`.
- Avoided selecting Oracle Spatial `GEOMETRY` by default because it breaks normal PDO fetches.
- Removed `php artisan valves:sync-geoserver` because it populated the wrong local table.
- Removed API routes for creating/updating valves directly; valves are read from the external EME source table, while field actions are recorded in `VALVE_LOGS` and maintenance requests in `VALVE_MAINTENANCE_REPORTS`.
- Verified no pending migrations.

## [2026-06-30] Unused Temporary File Cleanup

- Removed one-off root CSS repair scripts that were not referenced anywhere:
  - `fix_css.py`
  - `fix_wells_css.py`
  - `fix_applayout_css.py`
  - `fix_themes.py`
- Removed macOS `.DS_Store` files from project directories.
- Added `/.venv` to `.gitignore` so local Python tooling does not appear in project cleanup scans.
- Did not delete Oracle/database rows because no table, retention rule, or deletion criteria were specified; data deletion must be explicit and auditable.
- Verified cleanup with `php artisan test`, `npm run build`, `php artisan route:list`, and `php artisan oracle:check`.

## [2026-06-30] Ponytail Skills Installed And Applied

- Verified local Codex skills from `DietrichGebert/ponytail`: `ponytail`, `ponytail-review`, `ponytail-audit`, `ponytail-debt`, `ponytail-gain`, and `ponytail-help`.
- Added Ponytail usage rules to `.ai/coding-rules.md`.
- Ran a Ponytail debt scan; no `ponytail:` deferral markers currently exist in the project.
- Applied the Ponytail review to the portal permissions flow and moved SSO user search out of `SystemPermissionController` into `SystemPermissionRepository`.
- Removed the stale fake SSO callback assertion from `tests/Feature/ExampleTest.php`; the route exists, but dummy callback tokens correctly return package-level failure instead of redirecting.
- Ran a broad Ponytail/system check over the backend workspace:
  - Counted 250 non-vendor/non-build files and 192 PHP files in scope.
  - Ran PHP syntax checks across scoped PHP files successfully.
  - Verified 116 Laravel routes with `php artisan route:list`.
  - Verified Oracle connectivity with `php artisan oracle:check`.
  - Verified migration state with `php artisan migrate:status`.
  - Ran `php artisan test` successfully after removing the stale callback assertion.
  - Ran `npm run build` successfully.
- Ponytail audit notes left for future cleanup:
  - Several legacy controllers still contain direct Eloquent/DB queries and should be refactored gradually behind repositories/services when those flows are next touched.
  - Temporary root scripts `fix_css.py`, `fix_wells_css.py`, `fix_applayout_css.py`, and `fix_themes.py` appear removable if no longer needed.
  - `app/Services/SmsAuthService.php` still contains the expected TODO for integrating the real municipality SMS provider.

## [2026-06-30] WMS Proxy Local Cache Removal

- Removed local disk caching from `public/wms.php` per project owner direction; the proxy no longer reads or writes `storage/app/wms-cache/`.
- Historical only, superseded on 2026-07-16: the old cURL proxy header is not required and must not be restored.
- `GetMap` requests now retry once, then return a transparent PNG on upstream GeoServer failures so Leaflet does not show broken tile icons.
- `GetFeatureInfo` failures return `204 No Content` to keep map click inspection quiet when a layer cannot identify a feature.
- Added diagnostic headers (`X-WMS-Proxy-Fallback`, `X-WMS-Proxy-Reason`) to fallback responses so missing tiles can be traced from browser Network tools.
- Added `/wms.php?health=1` and a valves map outage banner so users see a clear Arabic warning when GeoServer is unreachable.

## [2026-06-29] GeoServer Valve Import for Team Assignment

- Fixed the empty valve selector in the valve teams modal by importing WMS/WFS valves into the managed `VALVES` table.
- Added `php artisan valves:sync-geoserver` in `routes/console.php`.
  - Source: GeoServer WFS `cite:WATER_VALVES`.
  - Historical only, superseded on 2026-07-16: no GeoServer credential header is used by the current map.
  - Stable local code format: `WV-{GeoServer feature id}`.
  - Maps geometry coordinates into `LATITUDE` and `LONGITUDE`.
- Ran the importer successfully:
  - Synced 1805 valves.
  - Skipped 42 GeoServer features without valid coordinates.
- Added search to the valve selector in `resources/js/Pages/Valves/Teams/Index.vue`, with “تحديد المعروض” so admins can filter and assign groups efficiently.
- Verified `VALVES=1805`, `php -l routes/console.php`, and `npm run build`.

## [2026-06-29] Valve Team All-Valves Assignment & Flutter Binding

- Added migration `2026_06_29_000008_add_assign_all_valves_to_valve_teams_table.php` with `VALVE_TEAMS.ASSIGN_ALL_VALVES`.
- Updated valve team create/update flows so dashboard admins can either select a specific group of valves or enable `إسناد كل المحابس لهذا الفريق`.
- When `ASSIGN_ALL_VALVES` is enabled, individual `VALVE_TEAM_VALVES` rows are cleared/ignored and assigned inspectors can access all current and future valves.
- Updated valve inspector API scoping:
  - `GET /api/valves` returns all valves if any of the inspector's teams has `ASSIGN_ALL_VALVES`.
  - Valve actions/show/logs allow access under the same rule.
- Login now returns each inspector team with `assign_all_valves`.
- Updated `/Volumes/SecondDrive/Scada/scada_flutter/lib/services/api_service.dart` so the Flutter app logs in through this backend's `POST /api/valves/auth/login` using `phone` and `password`, then keeps consuming `GET /api/valves` with the Sanctum token.
- Ran `php artisan migrate`, PHP syntax checks, `npm run build`, `dart format`, and `flutter analyze` for the Flutter app. Flutter analyze reports pre-existing warnings/infos only.

## [2026-06-29] Valves Map Default Base Layer

- Changed the valves map default base layer to `بدون OpenStreetMap`.
- OpenStreetMap remains available in the Leaflet base-layer control but is no longer added on initial map load.
- Verified with `npm run build`.

## [2026-06-29] Valves Map GetFeatureInfo Error Fix

- Removed the `propertyName` parameter from valves WMS `GetFeatureInfo` requests because `cite:WATER_VALVES` returned `PropertyDescriptor is null` for non-matching requested fields.
- Updated the HTML fallback parser to ignore GeoServer exception/error text instead of displaying it inside the popup.
- Added simple table-row parsing for HTML `GetFeatureInfo` responses so real feature fields render as label/value rows when JSON is unavailable.
- Verified with `npm run build`.

## [2026-06-29] Valves Map Background & Popup Readability

- Restored municipal/background layers on the valves map to near-natural opacity so buildings, centerlines, landmarks, and the municipal base layer remain readable behind pipes and valves.
- Kept `cite:WATER_PIPE` and `cite:WATER_VALVES` above all context layers using Leaflet panes, while preserving the visual clarity of lower layers.
- Raised the Leaflet popup pane above map overlays and layer controls.
- Updated `.v-map-popup` to use clear RTL alignment, readable light styling, and better field/value layout.
- Improved WMS feature inspection by requesting JSON first and falling back to `text/html` parsing when a GeoServer layer does not return JSON.
- Verified with `npm run build`.

## [2026-06-29] Valves Map Dedicated Page

- Removed the Leaflet/GeoServer map from `resources/js/Pages/Valves/Dashboard.vue`; the dashboard now focuses on KPIs, recent actions, and maintenance reports.
- Added `resources/js/Pages/Valves/Map.vue` as the dedicated valves map page.
- Added `GET /valves/map` named `valves.map` in `routes/valves.php`.
- Added `ValvesDashboardController::map()` to render the map page with `mapValves`.
- Updated the valves sidebar so `خريطة المحابس` links to `/valves/map`.
- Removed inactive sidebar items that pointed to `#`, including `المحابس` and `تقارير الصيانة`.
- Verified with `php -l` for the controller/routes and `npm run build`.

## [2026-06-29] Valves Dashboard Map Layer UX

- Reworked `resources/js/Pages/Valves/Dashboard.vue` so water network layers are visually prioritized:
  - `cite:WATER_VALVES` renders in the highest WMS pane.
  - `cite:WATER_PIPE` renders above municipal context layers.
  - Buildings, centerlines, and landmarks stay as lower-opacity context layers.
  - Database valve markers render above all GeoServer WMS layers.
- Improved the Leaflet layer control Arabic labels and kept the `بدون OpenStreetMap` base-layer option.
- Added WMS `GetFeatureInfo` click handling through `public/wms.php`; map clicks query visible layers in the order valves → pipes → landmarks → centerlines → buildings.
- Added a shared, RTL-friendly `.v-map-popup` design in `resources/css/valves.css` for readable feature details and local valve marker details.
- Verified the frontend build with `npm run build`.

## [2026-06-29] Valves Dashboard WMTS Proxy Fix

- `public/wms.php` was updated so the valves dashboard map tiles load successfully again.
- Historical only, superseded on 2026-07-16: the old WMTS credential header and cURL requirement are not part of the current map contract.
- `TileOutOfRange` responses now return a transparent PNG instead of a visible tile error.
- Added WMS proxy support and Leaflet overlays for `cite:WATER_VALVES`, `cite:WATER_PIPE`, `cite:V_BUILDING_GEOSRV`, `cite:V_CENTERLINE_SEGMENTS`, and `cite:V_LANDMARKS_V3`.
- Added a `بدون OpenStreetMap` base-layer option so OpenStreetMap can be turned off.
- Removed the persistent GeoServer warning banner that was triggered by individual tile errors even when layers were visible.
- Keep the valves dashboard map using the `public/wms.php` proxy for GeoServer tiles; do not revert to the older documented header because it prevents tiles from loading.

## [2026-06-29] Valves Teams & Inspectors Separation

- Added valve-only field team management, fully separated from the wells module.
- Created and migrated the dedicated valves tables:
  - `VALVE_TEAMS`
  - `VALVE_TEAM_VALVES`
  - `VALVE_INSPECTORS`
  - `VALVE_INSPECTOR_TEAMS`
- Added dashboard pages under the valves system:
  - `/valves/teams` for creating valve field teams and assigning valves to each team.
  - `/valves/inspectors` for creating valve inspector app accounts, using `PHONE` as username, hashed `PASSWORD`, `IS_ACTIVE`, and multiple team assignment.
- Added backend layers for the valves module:
  - Models: `ValveTeam`, `ValveInspector`, `ValveTeamValve`, `ValveInspectorTeam`
  - Repositories: `ValveRepository`, `ValveTeamRepository`, `ValveInspectorRepository`
  - Services: `ValveService`, `ValveTeamService`, `ValveInspectorService`
  - FormRequests under `App\Http\Requests\Valves`
  - Dashboard controllers: `ValveTeamController`, `ValveInspectorController`
- Added `POST /api/valves/auth/login` for valve inspector app login.
- Scoped authenticated valve inspectors to valves assigned to their teams in the valves API.
- Important rule: never reuse wells tables (`TEAMS`, `INSPECTORS`, `TEAM_WELLS`, `INSPECTOR_TEAMS`) for valves workflows.

## [2026-06-25] Wells App Mobile API Sync Integration

- Added new backend API controllers (`AuthController` and `SyncController` in `App\Http\Controllers\Api\Wells`) to support the Wells mobile application.
- Registered mobile API routes under the `wells` prefix in `routes/api.php` (`/api/wells/auth/login`, `/api/wells/sync/wells`, `/api/wells/readings/sync`).
- Created and ran a migration to make `CHLORINE`, `SALINITY`, and `QUALITY` fields nullable in the `well_readings` table, as the mobile app no longer records them.
- Updated the Flutter app `ApiConstants` to point to the new `/api/wells/...` routes.
- Identified and resolved a 500 server error caused by the `App\Models\Inspector` namespace change. Authenticated inspectors are now properly mapped to `App\Models\Wells\Inspector`.
- Fixed `ORA-01400` constraint violation on `INSPECTOR_OID` during sync by ensuring lowercase model properties (`$inspector->oid`) are used when accessing primary keys mapped by the `UppercaseKeys` trait.
- Fixed `ORA-00904` invalid identifier errors by stripping `notes` and `local_id` from the sync payload since these columns don't exist in the `well_readings` Oracle table.
- Fixed `ORA-01858` date formatting errors by wrapping the mobile app's ISO 8601 `READING_DATE` string in `Carbon::parse()` to properly strip the 'T' character and format it for Oracle DB.
- Implemented **Diesel Inventory** feature end-to-end:
  - Created Mobile UI for data entry (DieselScreen) and Controller (DieselController).
  - Designed local SQLite storage for pending/historical records (DieselModel, DieselService) and upgraded database schema version.
  - Implemented offline-first synchronization logic within `SyncService` to upload pending records in batch (`POST /api/wells/diesel-inventories/sync`).
  - Corrected analyzer errors in `DieselScreen` (`super.key`, `initialValue`, `InputDecoration`).
- Fixed data visibility issue in `SyncController@wells`: The API now correctly filters active wells by matching the authenticated Inspector's assigned team(s) using the `teams` relationship, rather than returning all wells in the system.
- **UI Redesign (Mobile App)**: Migrated the `HomeView` layout from a vertical card list into a modern `DefaultTabController` with three top tabs: "Readings", "Diesel Inventory", and "Signals/Notifications". This greatly cleans up the inspector's workflow in the field.
- **Implemented Notifications/Signals Sync Feature**:
  - Added new backend sync routes (`GET /api/wells/sync/notification-types`, `POST /api/wells/notifications/sync`) to support offline-first notifications in the field.
  - Upgraded SQLite schema to v5 and created `NotificationModel` & `NotificationTypeModel`.
  - Built out `NotificationScreen` to allow inspectors to write messages, pick a well, and pick a predefined notification type offline. 
  - Overhauled `SyncService` to upload offline notifications to the backend.
## [2026-06-30] Central Portal System Permissions

- Removed the hard-coded personal identity reference from the local dev-login helper; local login now uses `MOG_LOCAL_ADMIN_USER_ID`.
- Moved system access management out of the Fleet dashboard and into the unified portal at `/permissions`.
- Added a central per-system permission UI for SSO users:
  - `fleet` => تتبع المركبات
  - `wells` => إدارة الآبار
  - `valves` => متابعة المحابس
- The permission-management screen itself is limited by `MOG_PERMISSIONS_ADMIN_USER_IDS` outside local development.
- `PortalController` and `EnsureSystemAccess` now resolve system access through `SystemPermissionRepository` in every environment.
- `FLEET_ADMIN_ALLOWLIST` remains only a global admin gate, not the per-system permission source.
- **SSO ID resolution and permissions search update:**
  - Diagnosed a critical issue where MOG SSO logs users in using their internal database `id` from `mog_users.users_list` (e.g. 1061), while admins were manually typing the `emp_id` (e.g. 2541) into the permissions input field.
  - Reverted the backend `usersById` query to strictly use `id` as the primary key instead of `emp_id` to ensure permissions are checked against the exact SSO session ID.
  - Built an AJAX user search auto-complete inside `Permissions.vue` (`/permissions/search-users`), allowing administrators to find SSO users by `full_name` or numeric `id` and visually mapping the correct `id` to the permission record without guessing.

- **Premium UI/UX Redesign & Typography**:
  - Implemented a "Glassmorphism" design system using a new custom `GlassCard` widget to give the app a modern, translucent, premium feel.
  - Replaced native `TabBar` and `DefaultTabController` in all forms (`HomeView`, `DieselScreen`, `NotificationScreen`) with a custom `AnimatedSegmentedTab` micro-animation component.
  - Form fields were restyled to be borderless with subtle filled backgrounds.
  - Applied the **Cairo** font globally across the entire application by integrating the `google_fonts` package and updating all text/app bar/navigation themes in `app_theme.dart`.

## [2026-06-25] Local Dev Login Portal Shortcuts

- `/admin/dev-login` is the local development entry point for the unified portal card selector.
- Optional direct shortcuts are supported:
  - `/admin/dev-login?system=wells`
  - `/admin/dev-login?system=fleet`
  - `/admin/dev-login?system=valves`
- Superseded on 2026-06-30: system cards are now governed by `CONTROL_UNIT.SYSTEM_PERMISSIONS`; repository fail-open behavior only applies when Oracle or the permission table is unavailable.

## [2026-06-24] CONTROL_UNIT Production Schema Switch

- Production Oracle connection moved to the `CONTROL_UNIT` schema on `gisdb`.
- Laravel migrations were verified on `CONTROL_UNIT`; the pending `FLEET_ADMIN_ALLOWLIST` migration was run successfully.
- Direct hard-coded old-schema references were removed from active Models and Repositories.
- Oracle source table/column names are now configured through `config/fleet.php` and `.env` keys.
- Driver and assigned vehicle lookup now use `FLEET_USERS` in `CONTROL_UNIT`:
  - Driver: `ID`, `USER_MOBILE`, `USER_NAME`, `USER_ID`.
  - Assigned vehicle: `VEHICLE_ID`, `VEHICLE_NO`, `VEHICLE_MODEL`, `VEHICLE_TYPE`.
- `MOG_VEHICLES` exists as a general vehicle reference table, but driver-scoped vehicle lookup must continue to use `FLEET_USERS` unless a verified assignment relationship is added.
- Added `php artisan oracle:copy-schema-data {source_schema}` for Oracle-to-Oracle managed-data migration.
  - Default mode is dry-run and shows source readability, source counts, target counts, and shared columns.
  - Use `--execute` to copy rows and `--replace` to clear `CONTROL_UNIT` target rows before copying.
  - The command requires `CONTROL_UNIT` to have `SELECT` grants on the old source schema tables.

## [2026-06-22] Original GPS Route Readability

- Final deployment decision: production runs on Windows and must not require Docker or additional map-matching software.
- The admin trip map displays only the authoritative original `trip_points`; no road-matching service or external routing API is required.
- Removed the original/corrected route toggle and all OSRM configuration and server calls.
- Added clear green start and red end markers with permanent Arabic labels.
- Added a white casing around the GPS trace so it remains readable over streets, buildings, and map labels.
- Repeating direction arrows show chronological movement.
- Return legs are detected conservatively and colored orange when the trip comes materially back toward its start; the outbound leg remains blue.
- Checksums, distance, virtual fuel calculations, and audit data remain unchanged.
- Future work must not reintroduce route snapping or transmit municipal vehicle traces to a third party without explicit product-owner approval.

## [2026-05-24] SSO Logout Fix & Error Logs Button Integration

### 1. Unified SSO Logout Route Usage
- **Context**: The driver app and admin dashboard use MOG SSO for authentication.
- **Requirement**: Use the library's routes directly without any external overrides in the main application's `routes/web.php` or `AdminAuthController.php`.
- **Status**: Reverted the custom logout override route and method to ensure the application delegates directly to the package's `Mog\ProjectHelper\AuthHelpers@logout` controller/action via the registered library routes.

### 2. Guard Static Analysis & IDE Error Resolution
- **Context**: Calls like `Auth::guard('admin')->logout()` and `Auth::guard('admin')->loginUsingId($id)` triggered static analysis warnings/errors in PHPStan (`Call to undefined method Illuminate\Contracts\Auth\Guard::...()`) and IDE squiggly lines because the base `Guard` contract does not define these stateful methods.
- **Solution**:
  - Implemented safe type checking against `\Illuminate\Contracts\Auth\StatefulGuard` before invoking `logout()` and `loginUsingId()` in `routes/web.php` and `AdminAuthController.php`. This eliminated IDE warnings and resolved static analysis compiler errors.

### 3. Server Logs Dashboard Integration
- **Context**: Integrated a server log inspection dashboard available on production/local development via `/mog/logs` routes from the helper package.
- **Actions**:
  - Updated `app/Http/Middleware/HandleInertiaRequests.php` to safely resolve the `mog.logs.index` route within a `try-catch` block, preventing crash loops during route caching/boot-time commands if the helper package's routes are not loaded.
  - Dynamically shared `auth.can_view_logs` status to only allow authorized user IDs (configured in `mog-helpers.logs_allowed_user_ids`).
  - Added a responsive "Error Log (سجل الأخطاء)" button to the dashboard layout (`Dashboard.vue`) to allow authorized administrators to inspect production log traces directly.

## [2026-06-29] GeoServer WMS Tile Caching & Oracle Spatial Coordinate Fixes

### 1. `php artisan serve` Blocking Fix via WMS Disk Cache
- **Context**: The `Valves/Map.vue` loaded 5 heavy WMS tiles (256x256) per GeoServer layer. `php artisan serve` is single-threaded, and layers like `fltr_citizens` could take 22 seconds to render, causing a pileup of 100+ requests. The browser would time out the requests, returning blank/transparent tiles.
- **Solution**: Completely rewrote `public/wms.php` to include an MD5-based disk cache.
  - Tiles are fetched from GeoServer once and saved to `storage/app/wms-cache/`.
  - Subsequent requests are served instantly (under 0.05 seconds), preventing server thread exhaustion.
- **Superseded on 2026-06-30**: Local disk cache was removed from `public/wms.php`; upstream/server-side GeoServer caching is now expected.

### 2. Oracle Column Case-Sensitivity Bug (`LATITUDE` vs `latitude`)
- **Context**: Map markers (all 1,805 valves) were defaulting to the Atlantic Ocean (`[0, 0]`) instead of Gaza.
- **Cause**: Laravel Models accessed Oracle DB columns strictly with `$v->LATITUDE`. Since Oracle columns were mapped by Laravel to lowercase `latitude`, `$v->LATITUDE` returned `null`, which was float-casted to `0.0`.
- **Solution**: Updated `ValvesDashboardService.php` to map `$v->latitude` correctly, resolving the Null Island rendering bug.

### 3. WGS84 Datum Offset & Toggleable Interactive Markers
- **Context**: Because the interactive Leaflet Database markers and GeoServer WMS pipes originate from differently transformed Spatial Reference Systems (WGS84 vs EPSG:28191 Palestine 1923), the interactive Leaflet markers suffered a 50m datum offset visually compared to the WMS tile streets. Furthermore, the markers were unconditionally overlaid via `addTo(map)`, leading users to believe the WMS layer control was broken because turning off "Water Valves" in Leaflet did not hide the 1,805 interactive dots.
- **Solution**:
  - Grouped the interactive marker dots into a distinct `L.layerGroup` and exposed it in the Leaflet controls as **"علامات المحابس (تفاعلية)"**.
  - Added a **"بدون OpenStreetMap"** empty base layer group so administrators can shut off the OSM background map completely to solely inspect the WMS pipe layers.
