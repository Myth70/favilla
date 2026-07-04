# Architecture — platform contract

> On-demand contract. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Tags: `MUST` non-negotiable · `SHOULD` default unless justified · `NOTE` practical reference.

## 1. Identity and stack
- `MUST`: Favilla is a **custom modular monolith** — not Laravel, Symfony, Blade/Twig, no CDN. For new code, do not introduce external-framework conventions.
- `MUST`: stack PHP ≥ 8.2, MariaDB 10.4, Bootstrap 5.3.3, HTMX 2.0.4, Font Awesome 6.7.2.
- `NOTE`: stack dependencies — Monolog, PHPMailer, PhpSpreadsheet, dompdf, HTMLPurifier, Dotenv. JS charting in `public/assets/js/vendor/`: ApexCharts (widgets/dashboard) and Plotly.js (advanced analytics) → [`ui.md`](ui.md).

## 2. Request lifecycle
```
public/index.php → bootstrap/app.php → Application::boot() → ModuleLoader
  → [global] SecurityHeadersMiddleware → Router
    → [per route/group] Auth · Csrf · Role → Controller → Service → Repository → View
```
- `MUST`: the only auto-prepended middleware is `SecurityHeadersMiddleware`. Do not re-add it on routes.
- `MUST`: `SessionSecurity` and `RateLimit` are **not** implicit globals — apply them only where explicitly configured.
- `MUST`: if `storage/.setup_complete` is missing, the app redirects to `setup.php`.

## 3. Three-layer pattern
`Controller → Service → Repository → View`

| Layer | Does | Must not |
|---|---|---|
| Controller | HTTP, input, sanitization, form validation, flash, redirect, render | call a Repository, hold complex business logic |
| Service | business logic, domain rules, side-effects, orchestration, typed exceptions | read `$_GET`/`$_POST`, render/redirect |
| Repository | SQL, data access, lifecycle hooks, whitelisting | business logic, HTTP knowledge |
| View | rendering, escaping, layout, partials, HTMX fragments | build queries, mutate, print unescaped output |

- `MUST`: never `Controller → Repository` directly. Resolve app dependencies via `app(ClassName::class)`.

## 4. Off-limits framework surfaces
Module work **extends** the framework, it does not modify it. Do not open these for writing during feature work:
`app/Core/` · `app/Middleware/` · `app/Security/` · `app/Contracts/` · `app/Services/` (shared) · `app/Support/` · `app/Traits/ControllerHelpers.php` · `app/Helpers/functions.php` · `app/Repositories/BaseRepository.php` · `app/Views/layouts/main.php` · `app/Views/partials/` · `bootstrap/app.php` · `public/index.php`. Touch `app/Config/` only for core/system modules (e.g. `modules.php`).
- `SHOULD`: new shared module logic belongs in the module, not in global surfaces.

## 5. Module auto-discovery
- `MUST`: modules live in `app/Modules/<ModuleName>/`. `module.json` is the metadata file; `ModuleLoader` combines it with the `app/Config/modules.php` registry.
- `MUST`: `Admin` stays **last** in `app/Config/modules.php`.
- `MUST`: `core => true` means "always active, hidden from the Admin module manager" — **not** "admin-only". User-facing vs system depends on navigation surfaces, not the flag.
- `NOTE`: `_Template` is scaffolding, not a runtime module. Derive the runtime inventory from `project_context.json` + `app/Config/modules.php` + real `module.json` files, not from historical samples.
- `MUST`: after adding/removing a module or changing routes/permissions/schema → `php favilla context:generate`; after permission/role changes → logout/login.

## 6. Module anatomy
```
app/Modules/ModuleName/
├── Controllers/  Services/  Repositories/
├── Views/ (index.php, form.php, show.php, partials/{table,search-results}.php)
├── Providers/ (opt.: Dashboard/Search/Export/ContactSource)
├── routes.php  permissions.php  module.json
├── migrations/ (NNN_*.sql)
├── report_templates/ (opt.)  Tests/Unit/ (recommended)
public/assets/{css,js}/modulename.* (opt.)
```
- `MUST`: minimum CRUD module = `routes.php`, `permissions.php`, `module.json`, ≥1 module migration, Controller, Service, Repository, base views.
- Naming `MUST`: folder in PascalCase; namespace `App\Modules\ModuleName\...`; tables in snake_case plural; named routes `module.action` / `module.entity.action`; permission slugs `modulename_snake.action`; short, unique CSS prefix.

## 7. `module.json` contract
Main fields: `name`, `core`, `icon`, `version`, `description`, `database` (usually `shared`), `tables`, `dependencies` / `optional_dependencies`, `dashboard_provider` / `search_provider` / `export_provider` / `contact_source_provider`, `services`, `assets`, `notification_events`, `navigation` (+ legacy `menu`), `admin_panel`.
- `MUST`: `tables` lists **all** module tables (including those added by later migrations).
- `MUST`: `navigation[].route` / `.permission` point to real routes and permissions; `surfaces` governs exposure on `sidebar` / `user_menu` / `radial` / `quick_access`.
- `MUST`: the standard navigation surface for a new application module is `sidebar`.
- `MUST`: do **not** include `"user_menu"` or `"radial"` for application modules; header dropdown and radial menu are personal user zones reserved for core shortcuts (for example Home, Oggi, profile/actions).
- `NOTE`: `quick_access` is a Home shortcut surface, not the default module entry point.
- `SHOULD`: declare providers explicitly and, for module admin panels, use `admin_panel` instead of hardcoding entries in shared admin surfaces.
- → Full `admin_panel` schema and provider contracts: [`integrations.md`](integrations.md).

## 8. `permissions.php` contract
```php
<?php
return [
    ['slug' => 'clienti.view',   'name' => 'Visualizza Clienti'],
    ['slug' => 'clienti.create', 'name' => 'Crea Clienti'],
    ['slug' => 'clienti.edit',   'name' => 'Modifica Clienti'],
    ['slug' => 'clienti.delete', 'name' => 'Elimina Clienti'],
];
```
- `MUST`: 4 base CRUD permissions (`view`/`create`/`edit`/`delete`); lowercase slugs with optional underscore (no dashes, no CamelCase); **never rename** slugs after import (roles reference them by slug). User-facing `name` stays in Italian.

## 9. HTTP routing
Group with the module `prefix` + `AuthMiddleware`/`CsrfMiddleware`; per-action sub-groups via `RoleMiddleware::withPermission()`.

| Permission | Typical routes |
|---|---|
| `module.view` | `GET /`, `GET /{id}` |
| `module.create` | `GET /create`, `POST /` |
| `module.edit` | `GET /{id}/edit`, `PUT /{id}` |
| `module.delete` | `DELETE /{id}` |

- `MUST`: static routes (`/create`, `/search`, `/bulk-delete`) registered **before** parametric ones (`/{id}`).
- `MUST`: separate permission per action (no single CRUD permission); PUT/DELETE from HTML forms go through POST + hidden `_method`.
- `MUST`: do not re-add `SecurityHeadersMiddleware`; do not assume `SessionSecurity`/`RateLimit` as globals.
- `NOTE`: extra HTMX patterns (live search → dedicated partial; bulk delete → static before `/{id}`; toggle → PUT returning `204`; module admin route → `module.admin.index` with a separate permission).

## 10. Controller contract
- `MUST`: handles input, sanitization, validation, flash, redirect, render; depends on the Service (not the Repository).
- **Sanitization**: `cleanPost()` / `cleanGet()` — never on passwords, free HTML, files/binaries. → details in [`security.md`](security.md).
- **Flash/session**: `_flash_success` / `_flash_error` (text or structured payload), `_errors` (shape `['field' => ['msg']]`), `_old`. `MUST`: flash is **text-first**, no HTML/pre-escaped markup in session.
- **HTMX**: for HTMX requests use `renderPartial()` and stop the full-page flow; give explicit feedback via toast/banner. → full HTMX and form contract in [`ui.md`](ui.md).
- **Breadcrumbs**: use `'route' => 'route.name'` (not `'url'`); pass `pageTitle` + `breadcrumbs` on main pages; last breadcrumb has no route.
- **Action patterns**: `index / show / create / store / edit / update / destroy / search` — table and skeleton in [`building-a-module.md`](building-a-module.md).

## 11. Service contract
- `MUST`: all business logic lives in the Service; it receives **already-sanitized** data; it does not read HTTP superglobals; it does not `render()`/`renderPartial()`/`redirect()`; it depends on the Repository via the constructor.
- `SHOULD`: typed exceptions for domain failures; `create()` adds `created_by`; multi-step orchestration (create+upload+notify+audit) lives in one Service; cross-module integration goes through existing shared Services, not parallel runtimes.

## 12. Cross-module navigation
- `MUST`: `NavigationRegistry` is the single source for the 4 UI surfaces (`sidebar`, `user_menu`, `radial`, `quick_access`); declared via `navigation` in `module.json` (`id`, `label`, `icon`, `route`, `permission`, `order`, `surfaces`). No manual sync between sidebar/header/radial.
- `MUST`: new modules enter the product through `sidebar`; `user_menu` and `radial` stay reserved for the personal user zone rather than acting as a module catalog.
- `NOTE`: `menu` is a legacy fallback mapped to `sidebar` only. Dashboard widgets: `DashboardWidgetProvider` (`getWidgets` metadata + `getWidgetData` on-demand) or auto-discovery `Providers/*DashboardProvider.php`; widgets lazy-load in parallel per `id` and permission checks are centralized in `DashboardService`.

## 13. Reference: helpers and base methods
- **Global helpers**: `e()` `env()` `route()` `asset()` `csrf_field()`/`csrf_token()` `auth()` `has_permission()` `app()` `config()`/`config_flush()` `setting()` `format_date_it()` `isModuleEnabled()` `navigation()` `sort_context()`/`sort_link()` `app_version()` `csp_nonce()` `cover_url()`.
- **Base Controller / `ControllerHelpers`**: `render()` `renderPartial()` `redirect()` `json()` `isHtmxRequest()` `isAjaxRequest()` `isPartialRequest()` `htmxOrRender()` `hxTrigger()` `hxToast()` `flashErrors()` `notifyCurrentUser()` `hxSyncNotificationBadge()` `cleanPost()`/`cleanGet()`.

## 14. Source hierarchy
1. [`CLAUDE.md`](../../CLAUDE.md) — entry, non-negotiables, map.
2. `project_context.json` + `context/<Module>.json` — operational inventory.
3. `docs/contracts/*` — these contracts, by topic.
4. Real modules (`Contacts`, `Tasks`, `Notifications`, `Files`, `Admin`) — pattern reference.

- `MUST`: on the same topic the **most restrictive** rule wins; examples never loosen contracts; legacy practices apply to existing code only — new code uses the most recent recommended pattern.
