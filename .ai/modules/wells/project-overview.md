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
4. Inspector records well readings offline (flow rate, chlorine, salinity, quality)
5. Inspector records diesel inventory reports offline (generator hours, diesel height, available fuel balance)
6. Readings and diesel inventories auto-sync to server when internet is available
7. Admin monitors readings, diesel inventory, quality distribution, and inspector activity

## Production Reports
- The readings monitoring page supports `date_from` / `date_to` filters and exports a production report as PDF or Excel.
- Report rows are calculated from well readings: previous reading, current reading, operating-hours difference, and `production = difference * WELLS.CAPACITY`.
- The report format follows the daily wells Excel sample (`تقرير_الابار_اليومي19-06-2026.xlsx`) with well name, official number/name, coordinates, capacity, previous/current readings, operating hours, production, and notes.
- The diesel inventory page exports PDF or Excel using its current well, inspector, generator, and date filters.
- The diesel report follows `تقرير الابار اليومي28-06-2026.xlsx`: it includes generator capacity/nominal consumption, consecutive meter and fuel-height readings, approved deliveries, actual consumption rate, tank capacity, water production, and calculated diesel consumption.
- Diesel report PDF output uses one A3 landscape table. Excel output uses the same Arabic table and calculations.

## Database
- **Production**: Oracle DB via `yajra/laravel-oci8`
- **Development**: SQLite
- **Primary key**: `OID` column (not `id`)
- **Column naming**: UPPERCASE (Oracle convention)

## Important Notes
- The wells Flutter app source of truth is `/Volumes/SecondDrive/Wells/wells_app`; do not assume a mobile app lives inside this backend repository.
- The wells Flutter app must call this unified backend's wells API routes, not the old standalone `wells-backend` service.
- Backend API changes for the wells Flutter app should be made in this project and kept compatible with the models in `/Volumes/SecondDrive/Wells/wells_app`.
- No authentication on dashboard (will be integrated into existing auth system later)
- Flutter app works fully offline using SQLite
- Generators data comes from existing Oracle tables (`G_STORE.GENERATOR`, `G_STORE.FUEL_LOG_DETAILS`) and dashboard well assignment lists must only show active generator assets (`IS_ACTIVE = 1`, `TYPE_OID = 128`).
- Diesel inventory is stored locally in `DIESEL_INVENTORIES` and is entered from the field inspector app through `/api/diesel-inventories/sync`.
- The dashboard derives the actual fuel balance from `DIESEL_HEIGHT_CM` and the well tank dimensions. It also shows the preceding chronological inventory reading for the same well; this lookup must include history outside the current dashboard filters.
- Diesel reports derive operating hours and consumption from consecutive inventories. Approved supply is summed from `G_STORE.FUEL_LOG_DETAILS.APPROVED_GALLONS` between the preceding and current inventory dates; never infer or manually invent missing supply.
- If `DIESEL_INVENTORIES.GENERATOR_OID` is null, the report may use `WELL_GENERATORS` only when the well has exactly one assigned generator. Multiple assignments must remain unresolved and appear as a report note.
- Dashboard users monitor diesel inventory reports at `/diesel-inventories`, with a summary also shown on the main dashboard.
- Well equipment records include pump horsepower, `submersible`/`vertical` pump types, generator power, fuel/chlorine/cooling tank specifications, chlorination operating status, cooling system, and cooling-line type.
- Well images are managed through the unified `WELL_MEDIA` table. Supported categories are `pump`, `fuel_tank`, `cooling`, `valves_parts`, and `well_room`; every image can have a user-provided label and can be removed independently.
- Well images are stored on Laravel's private `local` disk and streamed only through the authenticated `wells.media.show` route. Do not expose their storage paths directly.
- Notification type management lives at `/notification-types`. It stores visual icon codes in `ICON` (e.g. `danger`, `fuel`, `water`) instead of raw emoji because the current Oracle charset may persist emoji as `??`. The Vue UI maps those codes to display symbols.
- Notification type active state is stored in Oracle as `IS_ACTIVE` = `1` or `0`; Vue must normalize `"1"`/`"0"` strings to booleans before display or form editing.
- Vue components strictly expect Oracle UPPERCASE column keys (e.g. `team.NAME`, `team.OID`). The backend uses an `UppercaseKeys` trait on Eloquent Models to ensure JSON outputs are upper-cased automatically before being sent to Inertia, without breaking Laravel's internal pivot structures.
- Internally, Eloquent models MUST define `$primaryKey = 'oid'` and use lowercase foreign/local keys in relations to match the lowercase array returned by PDO.
- **Business Rule (Data Integrity):** If a Well has any associated readings or diesel inventories, core identifying fields (`WELL_NUM`, `NAME`, `QUARTER_OID`) MUST be locked and uneditable to prevent historical data corruption. Deletion of the well is also strictly prohibited under these conditions. The frontend MUST disable these fields dynamically using a `hasTransactions` flag.
