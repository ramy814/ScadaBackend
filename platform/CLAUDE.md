# MOG Operations Platform — Agent Rules

Independent Laravel 13 + Inertia 3 + Vue 3 admin platform for the Gaza Municipality Flutter apps.
It runs **beside** the legacy backend in the repository root and reads the **same Oracle schema**.

Read before changing anything: `docs/01-audit.md`, `docs/02-architecture.md`, `docs/03-design-system.md`, `docs/04-roadmap.md`.
The legacy business context lives in `../.ai/` (read the module you are porting).

## Hard rules
- Never modify files outside `platform/`. The legacy backend is in production.
- Never add migrations for shared Oracle tables. Test schema lives in `database/fixture/FixtureSchema.php` (SQLite only).
- Port business rules verbatim with their tests; do not invent rules. Fix only documented gaps and say so in `docs/04-roadmap.md`.
- Mobile APIs stay in the legacy backend; do not reimplement them here.
- Permissions: `SYSTEM_PERMISSIONS` semantics (system entry fails open without Oracle, actions fail closed) and Oracle station scope (`VEHICLE_STAGES.ACCESS_TYPE`) exactly as legacy.

## Structure
- `app/Platform/*` shared core (identity, access, applications registry, operations, overview, audit).
- `app/Modules/{Fleet,Wells,Valves}` one module per Flutter app: `Models`, `Repositories`, `Services`, `Http`, `<Name>Module.php` (navigation, attention, pulse).
- Controllers thin (FormRequest → Service → Repository). No queries in controllers.
- Oracle PDO returns lowercase attributes: read via lowercase keys and shape explicit payloads.

## Frontend
- Custom CSS tokens only (`resources/css/tokens.css`); logical properties; no Tailwind; no hard-coded routes beyond module prefixes.
- All strings in `resources/js/lang/{ar,en}.js`; Latin digits; plates/phones/versions inside `<bdi>`/`dir="ltr"`.
- Directional icons: use the LTR icon plus `class="flip"`.

## Checks
`php artisan test`, `vendor/bin/pint --test`, `npm run build`.
