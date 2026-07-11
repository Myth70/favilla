# AGENTS.md — Agent instructions for this repository

This file gives AI coding agents the minimal, high-value guidance needed to be productive in this codebase. Follow the "link, don't embed" principle: pointers to existing docs are preferred.

Quick pointers

- Install dependencies: `composer install`. See [composer.json](composer.json).
- Run tests: `vendor/bin/phpunit` (Windows: `C:\xampp\php\php.exe vendor/bin/phpunit`). See [phpunit.xml](phpunit.xml) and [tests/](tests/).
- Local quickstart: `quickstart.sh` / `quickstart.ps1` or Docker: `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build`. See [quickstart.sh](quickstart.sh).

Important conventions (read before editing)

- Layering: Controller → Service → Repository → View. Do not call Repositories from Controllers. See [CLAUDE.md](CLAUDE.md) and [docs/contracts/architecture.md](docs/contracts/architecture.md).
- Do not modify framework surfaces (`app/Core/`, `app/Middleware/`, `app/Security/`, shared `app/Services/`, layouts/partials, `bootstrap/app.php`); extend inside your module instead. Full list in [CLAUDE.md](CLAUDE.md).
- i18n: Always use `t()` for user-facing copy; Italian (`it`) is the canonical source. See [docs/contracts/i18n.md](docs/contracts/i18n.md) and [resources/lang/](resources/lang/).
- Security: Use `csrf_field()` on mutating forms, escape output with `e()`, and use prepared statements. See [docs/contracts/security.md](docs/contracts/security.md).

Operational commands to run after common changes

- After route/permission/schema changes: `php favilla context:generate`. See [project_context.json](project_context.json).
- After adding migrations: `php database/migrate.php`.
- After translation changes: `php favilla lang:check`.

Key files and dirs to inspect first

- Entry points: [public/index.php](public/index.php), [bootstrap/app.php](bootstrap/app.php)
- App layer: [app/](app/)
- Context & metadata: [project_context.json](project_context.json) and [context/](context/)
- Tests: [tests/](tests/)
- Docs: [CLAUDE.md](CLAUDE.md) and [docs/contracts/](docs/contracts/)
