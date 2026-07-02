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

### Never
- Make network calls without checking connectivity
- Block UI during network operations
- Store sensitive data in plain SharedPreferences (use for non-sensitive only)
- Do not assume the old standalone `wells-backend` is still the production API target.

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
- **Oracle Boolean Values in Vue**: Oracle may return booleans as `"1"` / `"0"`. Vue must normalize these with explicit helpers, e.g. `value === true || value === 1 || value === '1'`; never rely on JavaScript truthiness because `"0"` is truthy.
- **Oracle-only Dashboard Data**: Dashboard repositories that must read/write production data should use the `oracle` connection explicitly when there is any risk of default connection drift, especially for notification types and notifications.
