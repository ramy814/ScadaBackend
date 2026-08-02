# Coding Rules

## PHP / Laravel

### Always
- `declare(strict_types=1)` in every PHP file
- Repository pattern for all DB queries
- Service layer for all business logic
- Type hints on all method parameters and return types
- Form Request validation (never validate in controllers)
- Use `OID` as primary key (not `id`)
- UPPERCASE column names (Oracle convention)

### Never
- Direct SQL in controllers
- Business logic in controllers
- Bypass the service layer
- Use TailwindCSS or any utility CSS framework
- Use PHP 8.3+ features (readonly classes, typed class constants, #[\Override])
- Use `id` as primary key column name

### Naming Conventions
- Models: PascalCase singular (Well, Team, Inspector)
- Controllers: PascalCase + Controller suffix
- Services: PascalCase + Service suffix
- Repositories: PascalCase + Repository suffix
- Migrations: snake_case with timestamps
- Vue Pages: PascalCase in directories (Wells/Index.vue)
- Vue Components: PascalCase (StatsCard.vue)
- CSS classes: kebab-case (stat-card, nav-item)
- Routes: kebab-case

### Response Pattern
- Dashboard: `Inertia::render('Page/Name', ['data' => $data])`
- API: `response()->json(['data' => $data, 'message' => 'msg'])`

## Vue.js / Frontend

### Always
- Composition API with `<script setup>`
- Props validation with types and defaults
- Use CSS classes from app.css (not inline styles)
- Arabic text with RTL direction
- Import `router` from `@inertiajs/vue3` for navigation

### CSS Design System
- Dark theme with CSS variables
- Glassmorphism cards
- Teal (#00d4aa) as primary accent
- Cairo Arabic font
- RTL direction everywhere
- Smooth transitions on all interactive elements

## Flutter / Mobile

Source path: `/Volumes/SecondDrive/Wells/wells_app`.

### Always
- MVC with GetX
- Offline-first: save to SQLite first, sync later
- All models must have `fromJson`, `toJson`, `fromDb`, `toDb`
- Use `Obx()` for reactive UI
- Arabic RTL layout
- ScreenUtil for responsive sizing
- Keep API URLs, JSON keys, and auth behavior compatible with this unified backend project.
- Keep the selected reading date in `READING_DATE`; do not substitute the local insert time or server sync time.
- Well-reading date selection is inclusive from today through 7 calendar days earlier. Future dates and older dates are invalid in the mobile form.
- Diesel inventory generator hours and diesel height are required. Validate trimmed input as a non-negative number, parse with `double.parse` only after form validation, and always include both values in the local row and sync payload.
- After the local diesel insert, use `SyncManager.uploadPendingDiesel()` for an immediate online upload. Keep the form open and retain offline/failed rows as pending; the current API acknowledges saved rows through `synced_ids`, and Flutter marks only those returned local IDs when that field is present.
- Use the shared `RecentActivityPanel` for the latest three readings and diesel inventories, retaining the module accent, relative day, exact date, sync status, and compact metrics.
- Activity images must be picked/compressed before the parent form save, persisted outside temporary picker storage, and represented by `activity_photos` SQLite rows. Keep original pixel dimensions and target 1.75 MB without replacing a smaller supported original with a larger compressed copy.
- Upload a reading/signal image only after the parent sync response maps its local UUID to an Oracle OID. A media failure must not discard the parent record or other unconfirmed images.
- Re-check queued image bytes before upload, recompress any file above the 1.75 MB mobile target without changing pixel dimensions, and upload photos independently so one rejection does not block its siblings.

### Never
- Make network calls without checking connectivity
- Block UI during network operations
- Store sensitive data in plain SharedPreferences (use for non-sensitive only)
- Do not assume the old standalone `wells-backend` is still the production API target.

## Reading Date Semantics
- Use `READING_DATE` for chronological reading queries, dashboard "today" counts, filters, previous/current reading calculations, and reports.
- Use `CREATED_AT` only for row-creation auditing and `SYNCED_AT` only for synchronization auditing.
- Never reorder a reading feed by `CREATED_AT` when the requested meaning is the field reading date.
- Dashboard reading surfaces must label and display both values separately: `READING_DATE` as **تاريخ القراءة** and `CREATED_AT` as **تاريخ إدخال السجل**.
- Production-report queries must return only the latest filtered reading per `WELL_OID`, ordered by `READING_DATE DESC, OID DESC` within each well.
- Apply all report filters inside the newer-reading exclusion query as well as the outer query. Otherwise a newer row outside the selected period, inspector, or quality filter can incorrectly remove the valid report row.
- PDF and Excel must continue to share the same service result; never deduplicate independently in Blade or in one export controller method.

## Diesel Inventory Validation
- Validate every `inventories.*.GENERATOR_HOURS_READING` and `inventories.*.DIESEL_HEIGHT_CM` as `required`, `numeric`, and `min:0` at the API boundary.
- Do not silently coerce a missing generator reading or diesel height to null. Historical null rows remain readable, but new Flutter/API submissions must contain both values.
- Treat numeric zero as valid; do not use truthiness checks for these fields.

## Diesel Inventory Reporting
- Keep tank classification, calculations, status evaluation, and report-column allowlists in `DieselInventoryService`; controllers only pass validated filters and render.
- `Daily Tank` means `UPPER(TRIM(TANK_SHAPE)) = 'DAILY TANK'`. External reports require a non-null classified shape other than `Daily Tank`; never silently place an unclassified well in the external group.
- Always constrain `G_STORE.FUEL_LOG_DETAILS` generator joins with `ASSET_TYPE = 401` and exclude canceled fuel logs.
- Apply the requested date range outside the analytic `LAG` query. Keep missing/malformed intermediate meter readings in sequence and return unavailable; never jump over them.
- Mark reverse/zero/impossible meter differences, non-increasing timestamps, and negative calculated consumption as invalid. Do not let them trigger a high-consumption alert.
- A high alert requires `actual > approved rate + allowed difference`; equality remains normal.
- Store the approved rate and allowed difference on `WELL_GENERATORS`, because a well can have multiple generators. Preserve these settings when synchronizing assignments.
- Accept report columns only through the service's fixed metadata registry. Never interpolate request-provided database column names into SQL or a report.

## Wells Management UI Rules
- The Wells list filter contract is `search`, `damage_status`, and `is_active`; do not restore the quarter filter unless explicitly requested.
- Render assigned generators in their own column using the generator number as non-wrapping LTR text and the Arabic name beneath it.
- Distinguish `submersible` (**غاطس**) and `vertical` (**رأسي**) with separate blue-family badges; normalize legacy `centrifugal` to the vertical presentation.
- `WELL_NUM`, `NAME`, and `QUARTER_OID` are temporarily editable even when transactions exist, while deletion protection remains enforced.
- Read the internal Oracle Eloquent pivot attribute as lowercase `generator_oid`; use uppercase `GENERATOR_OID` only after `UppercaseKeys` serializes data for Vue.
- Do not expose `INSTALLATION_DATE` or `LAST_MAINTENANCE_DATE` in the general Wells form.
- The external generator `GALLONS` value is displayed operationally as **آخر كمية تعبئة سولار (لتر)**; never append a gallon unit in the Wells UI.
- The administrative Wells map must include inactive records and expose explicit operational-status, damage-status, and inactive-record controls.
- Use the custom water-well SVG marker. Its main color communicates operation, its small dot communicates damage, and its well-name label changes to the Wells accent when selected.
- Map popups must use theme variables and structured RTL fields. Show `MAP_GENERATORS.*.NUMBER`; do not show the power-source value as **سولار**.
- Import the installed `leaflet` package and stylesheet dynamically; do not add a runtime CDN dependency for the Wells map.

## Master Data Deletion Safety

- Never configure `ON DELETE CASCADE` from `INSPECTORS`, `TEAMS`, or `WELLS`. Use Oracle `NO ACTION` foreign keys so history cannot be removed by deleting a master row.
- Physical deletion is allowed only when every known relation count is zero. Keep the preflight logic in the service and the counts/queries in the repository.
- Inspector blockers: team assignments, readings, diesel inventories, notifications, and Sanctum tokens.
- Team blockers: assigned wells and inspectors.
- Well blockers: readings, diesel inventories, notifications, team assignments, generator assignments, and media.
- Dashboard payloads expose `CAN_DELETE` and `DELETE_BLOCKERS`; Vue renders a disabled lock and reason but is never the authoritative guard.
- Catch Oracle `ORA-02292` only to provide a friendly domain message. Do not swallow unrelated database errors.
- Prefer deactivation through `IS_ACTIVE` when a master record has operational history.

## Oracle SQL Rules
- Always use UPPERCASE for table and column names
- Use `ROW_NUMBER() OVER (PARTITION BY ... ORDER BY ...)` for latest records
- Use NVL instead of COALESCE for Oracle compatibility
- Always alias subqueries
- Prefix external schemas: `G_STORE.`, `GAZADATA.`
- **Case Sensitivity and Eloquent Pivot Tables**: Do NOT use `PDO::ATTR_CASE => PDO::CASE_UPPER` globally in `config/database.php` as it breaks Eloquent's `BelongsToMany` pivot relation mapping (Laravel expects lowercase `pivot_` aliases). Instead, use a trait (`App\Traits\UppercaseKeys`) on models to explicitly upper-case array keys when serialized for Vue components.
- **Eloquent Keys (CRITICAL)**: Always define `$primaryKey = 'oid'` (lowercase) and use LOWERCASE foreign/local keys in Eloquent relations (`belongsTo`, `hasMany`, `belongsToMany`, etc.). E.g. `belongsToMany(Team::class, 'inspector_teams', 'inspector_oid', 'team_oid')`. This is required because PDO returns lowercase attributes by default, and Eloquent uses these definitions to match the retrieved array keys internally. Using uppercase here will break `sync()` and `fresh()`.
- **Property Access in Logic**: Because Oracle PDO returns column keys in lowercase but the model defines casts in UPPERCASE, `$model->IS_ACTIVE` might return `null`. In `Services` and `Controllers`, always fallback to lowercase access: `empty($model->is_active ?? $model->IS_ACTIVE)`.
- **JSON API Responses**: The Flutter app (`wells_app`) expects strictly lowercase keys in JSON responses (`oid`, `name`, `phone`, `team_oid`). Never send uppercase keys or nested object arrays if the Flutter model expects flat lowercase properties. Explicitly format the `response()->json()` in the controllers to match the client's models.
- **Notification Type Icons**: Do not store raw emoji in Oracle for notification type icons; the current Oracle charset may persist them as `??`. Store safe ASCII codes in `ICON` (and mirror to `EMOJI` only for compatibility), then map codes to symbols in Vue.
- **Notification Type Editing**: Referenced notification types remain editable. Only deletion is protected, and the UI must use `CAN_DELETE` exclusively for the delete action rather than locking the edit action.
- **Oracle Boolean Values in Vue**: Oracle may return booleans as `"1"` / `"0"`. Vue must normalize these with explicit helpers, e.g. `value === true || value === 1 || value === '1'`; never rely on JavaScript truthiness because `"0"` is truthy.
- **Oracle-only Dashboard Data**: Dashboard repositories that must read/write production data should use the `oracle` connection explicitly when there is any risk of default connection drift, especially for notification types and notifications.
- **Primary Key After Oracle INSERT (CRITICAL)**: After calling `Model::create()` on an Oracle table, **never** access the primary key via `$model->OID` (uppercase). Oracle PDO returns the key in lowercase, so `$model->OID` returns `null`. Always use `$model->getKey()` to retrieve the newly created record's ID, regardless of how the primary key column is named in Oracle.
  ```php
  // WRONG ❌
  $team = $this->teamRepository->create($data);
  $this->teamRepository->syncWells($team->OID, $wellIds); // OID is null!

  // CORRECT ✅
  $team = $this->teamRepository->create($data);
  $this->teamRepository->syncWells((int) $team->getKey(), $wellIds);
  ```
- **BelongsToMany Pivot Timestamps on Oracle (CRITICAL)**: Do NOT use `->withPivot('created_at')` or `->withTimestamps()` on Eloquent `BelongsToMany` relations pointing to Oracle pivot tables. OCI8 returns column names in uppercase (`CREATED_AT`), but Eloquent's pivot hydration expects lowercase (`created_at`), which causes a 500 error when loading the relation. Instead, use raw SQL with `DB::connection('oracle')->statement(...)` to manually INSERT/DELETE pivot rows — this also avoids Eloquent's automatic `sync()` behavior which conflicts with Oracle uppercase columns:
  ```php
  // WRONG ❌
  public function teams(): BelongsToMany {
      return $this->belongsToMany(Team::class, 'inspector_teams', 'inspector_oid', 'team_oid')
          ->withPivot('created_at'); // Crashes on Oracle!
  }

  // CORRECT ✅
  public function teams(): BelongsToMany {
      return $this->belongsToMany(Team::class, 'inspector_teams', 'inspector_oid', 'team_oid');
  }
  ```

## Well Images
- Store well images only on Laravel's private `local` disk through `WellMediaService`.
- Database access belongs in `WellMediaRepository`; controllers only delegate.
- Serve images through the authenticated `wells.media.show` route. Never serialize or expose `WELL_MEDIA.FILE_PATH` as a public URL.
- Validate uploads as JPG, PNG, or WebP with a maximum size of 2 MB per image.

## Reading And Signal Images
- Store activity images on the private `local` disk through `ActivityMediaService`; database access belongs in `ActivityMediaRepository`.
- `CLIENT_LOCAL_ID` is a unique mobile-generated UUID. Always check it before writing a file so network retries cannot duplicate media.
- Persist the parent mobile UUID in each activity table and return the existing parent OID on retry. Do not create a second reading/notification/inventory after a lost response.
- Accept only `reading` and `notification` activity types, 1–4 matching files/UUIDs per request, and JPG/PNG/WebP files up to 2 MB each.
- Resolve inspector identity only from Sanctum authentication and verify the authenticated inspector owns the parent activity before storage.
- Serve media through authenticated `wells.activity-media.show`; never expose `WELL_ACTIVITY_MEDIA.FILE_PATH`.
- Generate activity media URLs as relative same-origin routes; do not serialize an absolute localhost/`APP_URL` address.
- Keep `media` eager-loaded for reading and notification dashboard queries. Signals must show a visible labeled attachment gallery and open images inside the dashboard lightbox; use the compact gallery variant only in dense reading tables.
  And in the Repository, replace `->sync($ids)` with manual raw SQL:
  ```php
  // WRONG ❌ — sync() fails on Oracle pivot tables
  $inspector->teams()->sync($teamIds);

  // CORRECT ✅ — manual raw SQL
  $existing = DB::connection('oracle')->table('inspector_teams')
      ->where('INSPECTOR_OID', $inspectorId)->pluck('TEAM_OID')
      ->map(fn ($id) => (int) $id)->all();
  $toDetach = array_diff($existing, $newIds);
  $toAttach  = array_diff($newIds, $existing);
  foreach ($toAttach as $teamId) {
      DB::connection('oracle')->statement(
          'INSERT INTO inspector_teams (INSPECTOR_OID, TEAM_OID, CREATED_AT) VALUES (?, ?, ?)',
          [$inspectorId, $teamId, now()->toDateTimeString()]
      );
  }
  ```
