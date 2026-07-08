# CLAUDE.md

This file guides Claude Code (claude.ai/code) in this repository. It is the **always-load entry point**: identity, non-negotiable rules, and a map to the on-demand contract docs. Open the rest only when relevant.

## Non-negotiables

- **Layering:** `Controller → Service → Repository → View`. Never call a Repository from a Controller. Resolve app dependencies via `app(ClassName::class)`.
- **Don't modify framework surfaces** — extend inside your module instead: `app/Core/`, `app/Middleware/`, `app/Security/`, `app/Contracts/`, shared `app/Services/`, `app/Support/`, `app/Traits/ControllerHelpers.php`, `app/Helpers/functions.php`, `app/Repositories/BaseRepository.php`, `app/Views/layouts/`, `app/Views/partials/`, `bootstrap/app.php`, `public/index.php`. `app/Config/` only for core/system modules.
- **Security:** `csrf_field()` in every mutating form; `e()` on all untrusted output; prepared statements only; whitelist any user-driven `ORDER BY`.
- **Routing:** static routes (`/create`, `/search`, `/bulk-delete`) before parametric (`/{id}`); separate permission per action.
- **Navigation:** new modules belong in `sidebar` as their standard entry point. Do not add application modules to `user_menu` (header dropdown) or `radial`: both are reserved as personal user zones / core shortcuts.
- **UI:** light + dark, Bootstrap-first, shared CSS variables and hero partials.
- **i18n:** user-facing copy goes through `t()` — **never hardcode strings**. Keys are English symbolic dot-notation; **Italian (`it`) is the canonical source**, then `en`/`fr`/`de`/`es`. `module.json` stays Italian (overlays translate at render). Keep `e()` around `t()` output. See [i18n.md](docs/contracts/i18n.md).
- **After** route/permission/schema changes → `php favilla context:generate`; after permission changes → logout/login; after adding/removing lang keys → `php favilla lang:check`.

## Documentation map

- **[project_context.json](project_context.json)** — slim always-load inventory index (modules summary, permission catalog, table names, counts + pointers). Per-module detail (routes, table schemas, permission labels, FQCN) lives in `context/<Module>.json`; `context/_core.json` for shared tables/services. Regenerate with `php favilla context:generate`.
- **`docs/contracts/`** — platform contracts by topic, loaded on demand:
  - [architecture.md](docs/contracts/architecture.md) — lifecycle, layering, module anatomy, routing, auto-discovery, framework surfaces.
  - [security.md](docs/contracts/security.md) — security invariants, CSRF, sanitization, SQL safety, soft-delete, flash/session.
  - [data.md](docs/contracts/data.md) — schema source of truth, migrations, SQL conventions, Repository contract.
  - [ui.md](docs/contracts/ui.md) — design system, tokens, hero/layout, HTMX list/form, theme runtime.
  - [i18n.md](docs/contracts/i18n.md) — translation engine, `t()`/overlays, lang-file layout, locale resolution + switcher, `lang:check`.
  - [integrations.md](docs/contracts/integrations.md) — reusable providers (notifications, dashboard, search, export, contacts, calendar, scheduler).
  - [editions.md](docs/contracts/editions.md) — Developer/Personal/Team profiles, `edition()` resolution, setup wizard + Admin settings, release zip packaging (`tools/build-editions.php`).
  - [building-a-module.md](docs/contracts/building-a-module.md) — step-by-step workflow + one minimal reference example per layer.
  - [gotchas.md](docs/contracts/gotchas.md) — documented blocking errors + testing portability quirks.
- **New module?** Start from [building-a-module.md](docs/contracts/building-a-module.md); copy patterns from real modules (`Contacts`, `Tasks`) and the scaffold stubs in `app/Modules/_Template/stubs/` (or run `php favilla make:module`).

## Commands

```bash
# Dependencies
composer install

# Tests (Windows)
C:\xampp\php\php.exe vendor/bin/phpunit
C:\xampp\php\php.exe vendor/bin/phpunit --testsuite Core
C:\xampp\php\php.exe vendor/bin/phpunit --testsuite Modules
C:\xampp\php\php.exe vendor/bin/phpunit --filter ClassName

# Coverage (needs Xdebug or PCOV; scope is defined by <source> in phpunit.xml)
C:\xampp\php\php.exe -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text
C:\xampp\php\php.exe -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html build/coverage

# Real-MariaDB integration suite (opt-in; default run stays on in-memory SQLite)
# Set RUN_DB_INTEGRATION=1 plus DB_HOST/DB_PORT/DB_USER/DB_PASS (see tests/Integration)
RUN_DB_INTEGRATION=1 C:\xampp\php\php.exe vendor/bin/phpunit --testsuite Integration

# Database migrations
php database/migrate.php --status
php database/migrate.php
php database/migrate.php --module=ModuleName

# Project CLI (root `favilla` script)
php favilla context:generate      # regenerate project_context.json + context/<Module>.json
php favilla lang:check            # verify translation completeness vs the `it` baseline
php favilla module:status
php favilla make:module ModuleName
php favilla make:migration ModuleName migrationName
php favilla help:export                # dumpa la KB Help Online in database/help/<modulo>.json
php favilla help:import [--module=X] [--force]   # importa la KB Help Online da database/help/*.json
php favilla demo:seed [--force] [--enable-modules]   # carica i dati demo "Aurora Studio" (seeds in database/seeds/demo/)

# Static analysis (PHPStan level 6 + baseline)
php vendor/bin/phpstan analyse -c phpstan.neon    # or: composer stan

# Docker (Apache+PHP 8.2 image, MariaDB, scheduler loop)
bash quickstart.sh                # generates .env secrets, pulls ghcr.io/myth70/favilla, starts stack at http://localhost:8080
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build   # build image from local source instead
```

After changing routes, permissions, module metadata, or schema → `php favilla context:generate`.
After adding migrations → run `database/migrate.php`. After changing permissions → logout/login to refresh session.

## Tech Stack

- **Backend:** PHP 8.2+, custom MVC framework (no Laravel/Symfony), MariaDB 10.4
- **Frontend:** Bootstrap 5.3, HTMX 2.0 (partial page updates), Font Awesome 6
- **Key libraries:** dompdf (PDF), HTMLPurifier (report-template sanitization), PhpSpreadsheet (Excel), PHPMailer (email), Monolog (logging), phpdotenv (env config)
- **Dev environment:** XAMPP (Apache + MariaDB) for local dev, or Docker (`docker/` + `docker-compose.yml`); `.env` from `.env.example` (Docker reads config from the process environment, no `.env` file required)
