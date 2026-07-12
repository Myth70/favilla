# Gotchas — blocking errors & testing

> On-demand reference. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> These errors are documented and recurring — check here first when something breaks.

## 1. Blocking errors
| Symptom | Cause | Fix |
|---|---|---|
| Empty sidebar / missing permissions after creating a module | session lacks the new permissions | logout + login |
| 404 on `/create` | `/{id}` registered before `/create` | static routes before parametric |
| CSRF mismatch (403) | missing `csrf_field()` in the form | add `<?= csrf_field() ?>` to every mutating `<form>` |
| HTMX filters lose their state | missing `hx-include` | every filter includes the others |
| SQL injection via sort | non-whitelisted column | whitelist `$allowedSorts` in the repository |
| Blank page / no layout | missing `$view->layout('main')` | make it the view's first line |
| Partial renders the full layout | used `render()` instead of `renderPartial()` | dedicated HTMX branch |
| View not found (500) | wrong render path (case-sensitive) | path = `ModuleName/Views/name` |
| Namespace not found | folder/file mismatch | `App\Modules\ModuleName\Controllers\NameController` |
| Broken breadcrumb link | used `'url' => route(...)` | use `'route' => 'module.index'` (lazy resolution) |
| `pushStyle()` ignored | called from a layout partial | use a direct `<link>` with `asset()` in layout partials |
| `$router` undefined in a group callback | used `$router` inside the callback | use `$r` (the callback parameter) |
| Confirm missing / XSS on confirm | `onsubmit="return confirm(...)"` | use `data-app-confirm="..."` on the button |
| Framework corrupted | edited a file in `app/Core`, `app/Middleware`, … | never touch framework surfaces |
| Ad-hoc CSV export | `/export-csv` route in the module | implement `ExportProvider` (entry point: Admin → Export) |
| Custom events table | new `mymodule_events` table | use `CalendarService::createEvent()` |
| Custom avatar endpoint | new crop/upload endpoint | use `POST /api/avatar/crop` + `window.AvatarCropper` |
| `app('db')` in a CLI command | string alias not registered in the container | add a Repository method and call it |
| `isModuleEnabled()` in a CLI command | `ModuleLoader` not resolvable outside the web context | use `class_exists(NameService::class)` as guard |
| Admin panel missing from the index | `admin_panel` absent in `module.json`, or route not registered | add `admin_panel` to `module.json`; route must exist and module be active |
| Hardcoded admin panel for a **module** | a module's own console links written directly in `AdminIndexService::catalog()` | give the module an `admin_panel` in its `module.json` (auto-discovered — this also makes its row clickable in Admin → Moduli via `ModuleManagementService::resolveAdminLink()`). Only the core **cross-cutting** links that don't belong to any single module (Backup, Scheduler, HealthCheck, Reports, Notifications) stay hardcoded in `catalog()` |
| Non-core module unreachable after leaving the sidebar | a non-core module (e.g. Webhooks) removed from `sidebar` but without an `admin_panel` | add an `admin_panel` to `module.json`: it is the canonical admin entry point read by Admin → Moduli. Core system tooling instead gets its Moduli link from the `$coreModuleRoutes` map in `Admin/Views/modules/index.php` |
| JOIN to `users` from an independent-DB module | `users` does not exist in the module DB (e.g. `favilla_documenti`) | enrich via `app(\PDO::class)` on the main DB; never cross-DB JOIN |

## 2. Post-change workflow (`MUST`)
- `php -l` on every touched or created PHP file.
- Run the relevant PHPUnit suite (prefer `Modules` for module work).
- `php database/migrate.php` if migrations were added.
- `php favilla context:generate` after route/permission/schema changes.
- logout/login after permission changes.

## 3. Testing portability (SQLite vs MariaDB)
- `SHOULD`: tests live in the module under `Tests/Unit/`; use `Tests\ModuleTestCase` for tests that need a DB (SQLite in-memory), and plain `PHPUnit\Framework\TestCase` for pure logic.
- Type mapping for the SQLite schema in tests: `VARCHAR(n)` → `TEXT`, `ENUM(...)` → `TEXT DEFAULT '...'`, `TINYINT(1)` / `UNSIGNED INT` → `INTEGER`; `NOW()` is predefined in `ModuleTestCase`; do not use `INSERT IGNORE` in tests (test other methods instead).
- `NOTE`: `FOR UPDATE` and other MariaDB-only clauses do not run on SQLite — cover those paths with pure-function tests.
- `NOTE` (XAMPP): under `open_basedir`, avoid `sys_get_temp_dir()` in tests — use a path under `__DIR__`.
- Run: `vendor/bin/phpunit` · `--testsuite Modules` · `app/Modules/ModuleName`.
