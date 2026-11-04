# MOG Operations Platform — Agent Rules

Laravel 13 + Inertia 3 + Vue 3 platform for the Gaza Municipality Flutter apps: the admin dashboard
**and** the mobile APIs. It replaced the legacy backend in place (same repository, domain, paths and
Oracle schema; see `docs/05-cutover-plan.md` §8). The legacy code is in git history and `docs/legacy/`.

Read before changing anything: `docs/01-audit.md`, `docs/02-architecture.md`, `docs/03-design-system.md`, `docs/04-roadmap.md`.
The legacy business context lives in `.ai/` (read the module you are changing).

## Hard rules
- The only remote is Gitea (`origin`, http://172.17.80.51:3000/mog_admin/mog-scada); there is no GitHub.
  Deployment is `git push origin main` (never force), which the production server pulls. It runs no
  migrations and (assume) no `composer install`/`npm`: commit `public/build-platform` (`npm run build:release`)
  and never require a PHP package the server's vendor/ lacks.
- Never track the server-local files `config/{app,database,filesystems,session}.php`, `composer.lock`,
  `package-lock.json` (ignored since legacy); defaults they need go in `App\Platform\Support\RuntimeConfig`.
- Never add or run migrations for shared Oracle tables. `database/migrations/` is the legacy history (read-only).
  Test schema lives in `database/fixture/FixtureSchema.php` (SQLite only).
- `config/mog-helpers.php` and `config/geoserver.php` are legacy files read by the SSO package and the map proxy: keep them.
- Port business rules verbatim with their tests; do not invent rules. Fix only documented gaps and say so in `docs/04-roadmap.md`.
- Mobile APIs live here (owner decision, `docs/05-cutover-plan.md`): same paths, middleware names, route names, JSON (UPPERCASE model keys) and Sanctum tokens as legacy. Legacy `TOKENABLE_TYPE` values resolve through `App\Platform\Identity\MobilePrincipals`. Port legacy tests unchanged; document every deliberate difference in `docs/04-roadmap.md`.
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
`php artisan test`, `vendor/bin/pint --test`, `npm run build:release` (commit `public/build-platform`).

## Code map (graphify)

The code knowledge graph lives in `graphify-out/` (`graph.json`, `GRAPH_REPORT.md`, `graph.html`), built with
[graphify](https://github.com/Graphify-Labs/graphify) over the scope in `.graphifyignore` (app, routes, config, Vue frontend).
Install once with `pip install graphifyy`. The project skill is in `.claude/skills/graphify/`.

- Before exploring code, query the graph: `graphify query "<question>"`, `graphify explain "<node>"`,
  `graphify path "<A>" "<B>"`, `graphify affected "<node>"` (what a change can break). Read raw files only for lines the graph located.
- Check freshness: `GRAPH_REPORT.md` names the commit it was built from; compare with `git rev-parse HEAD`.
- After any code change, run `graphify update .` at the repository root (AST only, no API cost) and commit the regenerated
  `graphify-out/graph.json`, `GRAPH_REPORT.md` and `graph.html` with the change. A large diff on these files is expected.
- The graph describes structure, not business rules: rules still come from `docs/` and `.ai/`.
- The legacy baseline used for the audit is `docs/graph/legacy-GRAPH_REPORT.md`.
