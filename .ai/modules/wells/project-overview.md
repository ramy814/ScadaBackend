# نظام ادارة آبار بلدية غزة — Gaza Municipality Well Management System

## System Overview
A comprehensive well management system consisting of two main applications:
1. **Unified Laravel Backend/Dashboard** — Integrated inside this fleet backend project at `/Volumes/SecondDrive/Fleet Mangment/backend`
2. **Flutter Mobile App** — Field inspector app at `/Volumes/SecondDrive/Wells/wells_app`

## Architecture
- **Backend**: Laravel 11 + Inertia.js + Vue 3 (Composition API)
- **Mobile**: Flutter + GetX + SQLite (offline-first)
- **Database**: Oracle DB (production) / SQLite (development)
- **Styling**: Vanilla CSS (no TailwindCSS)
- **Language**: Arabic RTL interface

## Key Business Flows
1. Admin creates wells, teams, and inspectors via dashboard
2. Admin assigns wells to teams, inspectors to teams
3. Inspector logs in via mobile app (phone + password)
4. Inspector records well readings offline (generator hours required; pump hours optional) and selects a reading date from today through 7 calendar days earlier
5. Inspector may attach up to four camera/gallery images to a reading or field signal; the app compresses and queues them locally without changing pixel dimensions
6. Inspector records diesel inventory reports offline; generator operating hours and diesel height are mandatory non-negative values
7. Parent records auto-sync first; queued reading/signal images upload after the server returns the parent Oracle OID
8. Admin monitors readings, signals, attached images, diesel inventory, quality distribution, and inspector activity

## Production Reports
- The readings monitoring page supports `date_from` / `date_to` filters and exports a production report as PDF or Excel.
- The PDF and Excel reading reports contain one row per well: only the latest reading inside the selected filters is exported, using `READING_DATE DESC, OID DESC` as the deterministic order.
- A later reading outside the selected period does not hide the well's latest in-period reading. The prior reading used to calculate operating hours may come from before the selected period.
- Report rows are calculated from well readings: previous reading, current reading, operating-hours difference, and `production = difference * WELLS.CAPACITY`.
- The report format follows the daily wells Excel sample (`تقرير_الابار_اليومي19-06-2026.xlsx`) with well name, official number/name, coordinates, capacity, previous/current readings, operating hours, production, and notes.
- The diesel inventory page has separate internal (`Daily Tank`) and external-tank sections. External tanks have separate inventory and calculated-results tabs.
- Internal-tank rates come from consecutive approved fuel-program fills. External-tank rates use consecutive field inventories, geometric tank balances, and approved deliveries.
- The page exposes normal/high/unavailable/invalid consumption states and never treats inconsistent readings as high consumption.
- Administrators choose an allowlisted set of columns for each report type; the same selection drives the screen, Excel, and PDF.
- Diesel report PDF output uses a dynamic A3 landscape RTL table. Excel output uses the same Arabic rows, columns, and calculations.

## Database
- **Production**: Oracle DB via `yajra/laravel-oci8`
- **Development**: SQLite
- **Primary key**: `OID` column (not `id`)
- **Column naming**: UPPERCASE (Oracle convention)

## Important Notes
- The wells Flutter app source of truth is `/Volumes/SecondDrive/Wells/wells_app`; do not assume a mobile app lives inside this backend repository.
- The wells Flutter app must call this unified backend's wells API routes, not the old standalone `wells-backend` service.
- Backend API changes for the wells Flutter app should be made in this project and kept compatible with the models in `/Volumes/SecondDrive/Wells/wells_app`.
- Mobile reading sync accepts a null/missing `PUMP_HOURS_READING`; `GENERATOR_HOURS_READING` remains required.
- `READING_DATE` is the inspector-selected field date and is preserved through offline sync. Dashboard reading lists, recent records, "today" metrics, filters, and reports must use it instead of `CREATED_AT` or `SYNCED_AT`.
- Dashboard reading tables show `READING_DATE` and `CREATED_AT` in separate, explicitly labeled columns so the field date and insertion date cannot be confused.
- The main dashboard's latest-submitted readings card orders by `CREATED_AT` (then `OID`) so backdated readings appear immediately. Reading metrics, chronology, filters, reports, and the readings index continue to use `READING_DATE`.
- The readings index is paginated at 15 rows per page and ordered by `CREATED_AT DESC, OID DESC`. Its date-range filters and production calculations continue to use `READING_DATE`.
- Flutter's latest-3 reading and diesel sections use one theme-aware activity panel: relative day plus calendar date, sync state, and compact primary metrics. Reading accents are teal and diesel accents are orange.
- Flutter well cards omit pump-type, power-source, and capacity tags. They show only well identity plus the relative latest-reading status, and use a restrained teal completion border/glow when a reading exists for today.
- After saving a reading locally, Flutter attempts an immediate upload when online, refreshes home data, and closes the reading form. If the short immediate-sync wait is unavailable, the pending SQLite row remains queued for automatic retry and the form still closes with a local-save message.
- After saving a diesel inventory locally, keep the selected well and form open, clear only its entry fields, and reload its latest 3 inventories immediately.
- The diesel form and sync API require both `GENERATOR_HOURS_READING` and `DIESEL_HEIGHT_CM`. Empty, non-numeric, and negative values are rejected; zero remains valid.
- Reading and signal images are optional, limited to four per mobile form, compressed locally toward 1.75 MB while retaining their pixel dimensions, and stored in the offline queue until confirmed by the server.
- Image upload uses `/api/wells/activity-media/sync` after parent sync. `CLIENT_LOCAL_ID` makes retries idempotent; an uploaded image is displayed through an authenticated dashboard URL rather than a public storage path.
- Reading, signal, and diesel parent records also persist their mobile UUID in `CLIENT_LOCAL_ID`; a lost response or retry returns the original row/OID and must never create a duplicate.
- Dashboard media URLs are same-origin relative paths. A production `404` from unauthenticated `POST /api/wells/activity-media/sync` means the backend feature has not been deployed even if the mobile APK already exposes image selection.
- The main Wells dashboard and readings list show reading thumbnails; the notifications list shows signal thumbnails. Each thumbnail opens the authorized full image.
- The Wells main dashboard and readings index reload their data every 10 seconds so newly synchronized readings appear without a manual full-page refresh.
- No authentication on dashboard (will be integrated into existing auth system later)
- Flutter app works fully offline using SQLite
- Generators data comes from existing Oracle tables (`G_STORE.GENERATOR`, `G_STORE.FUEL_LOG_DETAILS`) and dashboard well assignment lists must only show active generator assets (`IS_ACTIVE = 1`, `TYPE_OID = 128`).
- Diesel inventory is stored locally in `DIESEL_INVENTORIES` and is entered from the field inspector app through `/api/wells/diesel-inventories/sync`.
- The dashboard derives the actual fuel balance exclusively from `DIESEL_HEIGHT_CM` plus the well's `TANK_SHAPE`, `TANK_DIAMETER`, and `TANK_HEIGHT`. Dashboard totals, rows, and reports must not fall back to the stored/client-provided `AVAILABLE_FUEL_LITERS` when the geometric calculation is unavailable; show the balance as unavailable until the tank data is completed.
- In the diesel inventory dashboard table, the current and preceding `GENERATOR_HOURS_READING` values are adjacent and displayed in hours. Never populate either meter-reading column from `AVAILABLE_FUEL_LITERS` or a calculated fuel balance.
- The diesel inventory generator column shows the generator number on one non-wrapping LTR line and its Arabic name below it; do not concatenate both into one wrapping text value.
- Previous/current generator meter readings determine operating hours and consumption rates; they do not change the geometric current fuel balance.
- The preceding chronological inventory lookup must include history outside the current dashboard filters.
- Diesel reports derive operating hours and consumption from consecutive inventories. Approved supply is summed from `G_STORE.FUEL_LOG_DETAILS.APPROVED_GALLONS` between the preceding and current inventory dates; never infer or manually invent missing supply.
- Every generator fuel-log query must include `FUEL_LOG_DETAILS.ASSET_TYPE = 401` and exclude canceled fills. `ASSET_OID` alone can collide with vehicle records.
- `Daily Tank` calculations use the immediately preceding approved fill even when its meter value is missing. Never skip an intermediate fill to connect two numeric readings.
- A meter delta is invalid when it is non-positive, when fill times are not strictly increasing, or when operating hours exceed elapsed calendar hours plus one hour.
- The consumption warning limit belongs to the well-generator assignment: configured liters/hour + allowed difference. When no assignment rate is stored, use the generator's G_STORE nominal value; the allowed difference defaults to zero.
- Wells with no `TANK_SHAPE` are unclassified and excluded from both report groups until corrected. The diesel page shows their count and links to Wells management.
- If `DIESEL_INVENTORIES.GENERATOR_OID` is null, the report and dashboard table may use `WELL_GENERATORS` only when the well has exactly one assigned generator. Multiple assignments must remain unresolved; reports include an explicit note and the dashboard shows no invented generator.
- Dashboard users monitor diesel inventory reports at `/diesel-inventories`, with a summary also shown on the main dashboard.
- Wells dashboard pages use one page-level `.content-wrapper`. Wide inventory tables must remain inside their horizontal scroll container and must not resize or overlap the fixed sidebar.
- Sidebar active-state matching treats `/wells` as an exact dashboard route; `/wells/diesel-inventories` activates only `جرد السولار`.
- Wells surfaces, text, borders, inputs, cards, tables, and shadows alias the global `--theme-*` contract in `resources/css/app.css`: light mode follows Fleet and dark mode follows Valves. Wells keeps a restrained blue identity accent for navigation, buttons, highlights, and decorative panels; green, amber, and red are reserved for semantic statuses. Do not introduce a separate Wells base palette in `wells.css`.
- Wells list/map display data resolves `WELLS.QUARTER_OID` through `GAZADATA.QUARTERS` in `WellRepository` and exposes the result as uppercase `QUARTER_NAME`; Vue must not read a lowercase `quarter_name` key from the Inertia well payload.
- The Wells management list orders active wells first (`IS_ACTIVE DESC`) and then orders wells by name; filtering and pagination apply to this same repository query.
- The Wells management list filters by `DAMAGE_STATUS` instead of quarter, while the quarter name remains a visible table column. Damage choices are `سليمة`, `متضررة`, `أعيد تأهيلها`, and `مدمرة`.
- The Wells management list shows assigned generators in a dedicated **المولد** column. Each generator uses the diesel-inventory display convention: non-wrapping LTR generator number with the Arabic name below it.
- Pump type is visually explicit in the Wells list: `submersible` is labeled **غاطس** and `vertical` (including the normalized legacy `centrifugal`) is labeled **رأسي**, with distinct blue-family badges.
- Well equipment records include pump horsepower, `submersible`/`vertical` pump types, generator power, fuel/chlorine/cooling tank specifications, chlorination operating status, cooling system, and cooling-line type.
- Well images are managed through the unified `WELL_MEDIA` table. Supported categories are `pump`, `fuel_tank`, `cooling`, `valves_parts`, and `well_room`; every image can have a user-provided label and can be removed independently.
- Well images are stored on Laravel's private `local` disk and streamed only through the authenticated `wells.media.show` route. Do not expose their storage paths directly.
- Notification type management lives at `/notification-types`. Administrators may edit the name, icon, color, priority, and active state even when a type is already referenced; only deletion remains blocked for referenced types. The list exposes this deletion rule as `CAN_DELETE`.
- Notification types store visual icon codes in `ICON` (e.g. `danger`, `fuel`, `water`) instead of raw emoji because the current Oracle charset may persist emoji as `??`. The Vue UI maps those codes to display symbols.
- Notification type active state is stored in Oracle as `IS_ACTIVE` = `1` or `0`; Vue must normalize `"1"`/`"0"` strings to booleans before display or form editing.
- Vue components strictly expect Oracle UPPERCASE column keys (e.g. `team.NAME`, `team.OID`). The backend uses an `UppercaseKeys` trait on Eloquent Models to ensure JSON outputs are upper-cased automatically before being sent to Inertia, without breaking Laravel's internal pivot structures.
- Internally, Eloquent models MUST define `$primaryKey = 'oid'` and use lowercase foreign/local keys in relations to match the lowercase array returned by PDO.
- The Wells create/edit **البيانات العامة** section no longer exposes `INSTALLATION_DATE` or `LAST_MAINTENANCE_DATE`. Existing database values remain historical compatibility data and are not cleared by normal edits.
- Generator cards label `CURRENT_READING` as **آخر قراءة لعداد المولد (ساعة)** and the legacy `GALLONS` source as **آخر كمية تعبئة سولار (لتر)**. The UI must never describe that operational source value as gallons.
- **Temporary Editing Rule:** Until the Wells workflow is formally approved, `WELL_NUM`, `NAME`, and `QUARTER_OID` remain editable even when readings or diesel inventories exist. Deletion remains prohibited whenever the well has readings or diesel inventories.
- Generator assignment IDs are hydrated by Oracle Eloquent as lowercase `generator_oid` in PHP and serialized as uppercase `GENERATOR_OID` for Vue. Reading the uppercase key before serialization returns null and causes the list to show unavailable generator metadata.
- The administrative Wells map currently loads all 91 well records with coordinates, including 55 inactive records. The UI can filter intersecting operational and damage layers and independently include or exclude inactive records.
- Map markers use a water-drop/well symbol, operational-status color, a damage-status dot, and a selected name label in the Wells accent color.
- The map popup is an RTL theme-aware information card with well identity, activation/operation/damage state, area, pump type, capacity, and assigned generator number from `MAP_GENERATORS`. It does not display the misleading **سولار** power-source label.
