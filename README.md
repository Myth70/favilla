# Favilla

[![CI](https://github.com/Myth70/favilla/actions/workflows/ci.yml/badge.svg)](https://github.com/Myth70/favilla/actions/workflows/ci.yml)
[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)

**Favilla** is a complete, self-hosted workspace and company intranet: projects,
tasks, calendar, contacts, documents, files, reports, notifications and a full
security & compliance suite — in five languages, from one install, on your own
server. Your data stays yours, there is no per-seat pricing, and nothing phones
home.

It runs on a custom PHP 8.2 micro-framework (no Laravel, no Symfony) with
MariaDB, Bootstrap and HTMX: a classic server-rendered application you can
read, audit and extend end-to-end — no build step, no SPA, no magic.

> Code and docs are in **English**; the end-user interface ships in **five
> languages** — Italian (the canonical source), English, French, German and
> Spanish — with a per-user language switcher.

![Favilla dashboard](docs/screenshots/dashboard.png)

## Highlights

- **A dashboard that's actually yours** — every module contributes live
  widgets (17 providers: today's agenda, open tasks, project status, backup
  health, latest posts… even local weather); each user picks, hides and
  reorders their own.
- **Drag-and-drop report designer** — build print-ready PDF and Excel templates
  in the browser (GrapesJS) with smart data components, reusable styles and
  server-side sanitization. Reports are first-class citizens, not an
  afterthought.
- **Contextual help everywhere** — a help panel available on every page, backed
  by a searchable multilingual Q&A knowledge base with synonyms and admin
  analytics on what users search for and don't find.
- **A real security suite** — TOTP two-factor auth, security dashboard with
  incident detection (brute force, CSRF), full audit log, data-retention
  policies, AES-256-GCM encrypted backups with in-app restore, session
  hardening, login rate limiting, password policy.
- **Notifications, template-driven** — one dispatcher, three channels (in-app,
  email, Telegram), per-user preferences, queued delivery with retry/backoff.
  Modules publish events; admins control the wording and look from the UI.
- **Fast to move around in** — global search across all modules, a right-click
  radial quick menu, HTMX partial updates everywhere, light & dark themes.
- **Operations built in** — a cron-equivalent scheduler with admin UI, health
  checks with history and export, log rotation, and a project CLI
  (`php favilla`) for automation.
- **Engineered, not just written** — 1,600+ automated tests, PHPStan level 6,
  PSR-12 enforced in CI, a 100-table schema installed by a guided setup wizard.

## Screenshots

| | |
|---|---|
| ![Configurable dashboard](docs/screenshots/dashboard-configure.png) <br>*Every widget is yours: drag to reorder, toggle to hide* | ![Contextual help](docs/screenshots/help-online.png) <br>*Contextual help with a searchable knowledge base, on every page* |
| ![Kanban board](docs/screenshots/tasks-kanban.png) <br>*Tasks as list, calendar or kanban board* | ![Appearance settings](docs/screenshots/appearance.png) <br>*Per-user themes, colors, fonts and layout styles* |

## Modules

| Module | What you get |
|---|---|
| **Home** | Personal widget dashboard with per-user layout |
| **Tasks** (*Attività*) | Personal & operational tasks, kanban board, due-date reminders |
| **Calendar** | Personal and shared events, reminders, ICS export |
| **Contacts** (*Contatti*) | Address book with map view, CSV import, role-based sharing, follow-up reminders |
| **Files** | Uploads with sharing, previews and SHA-256 checksums |
| **Progetti** | Projects with milestones, kanban & Gantt views, task dependencies, timesheet and budget control |
| **Teams** | Team messaging: 1:1 and group chats, mentions, reactions, presence |
| **Documenti** | Managed documents: versioning, approval workflow, protocol numbers, expiry and integrity checks |
| **Blog** | Internal news with scheduled publishing, role-based visibility and moderated comments |
| **Reports** | GrapesJS template designer, PDF/Excel generation, document models |
| **Notifications** | Multi-channel template-driven notification center |
| **HelpOnline** | Contextual help + knowledge base with search analytics |
| **Admin** | Users, roles & fine-grained permissions, impersonation, security area, app settings |
| **Auth** | Login, registration with admin approval, 2FA, password recovery |
| **Backup** | Encrypted database backups, download and in-app restore |
| **HealthCheck** | System diagnostics with history and export |
| **Scheduler** | Recurring jobs with UI: cron expressions, timeouts, run history |
| **Feedback** | In-app issue reporting with triage workflow |

The complete capability list, module by module, is in
[**FEATURES.md**](FEATURES.md).

## Editions

Favilla ships from a single codebase in three editions, chosen during the setup
wizard (or later from Admin → Configurazione):

| | **Developer** | **Personal** | **Team** |
|---|---|---|---|
| Intended for | Contributing to Favilla itself | Single-user personal workspace | Multi-user company intranet |
| Multi-user / RBAC UI | Visible | Hidden (sharing controls, admin area tucked under a "Settings" corner) | Visible |
| Registration page | Open | Disabled (single account) | Open |
| Progetti, Teams, Documenti, Blog | Installable from Admin → Moduli | Installable from Admin → Moduli | **Enabled by default** |
| Dev/LLM aids (`CLAUDE.md`, `docs/contracts/`, `context/`) | Included | Not included | Not included |

The scheduler and all core modules run in every edition — Personal only hides
multi-user surfaces from the UI, it never disables functionality that other
features (like reminders) depend on.

**Upgrading Personal or Developer to Team**: enable the four optional modules
from **Admin → Moduli** (they ship with every edition's codebase, just
disabled by default outside Team) and switch the edition in **Admin →
Configurazione**. No reinstall or migration is required — hidden ≠ disabled.

Release zips for all three editions are published on the
[Releases](../../releases) page, built by [`tools/build-editions.php`](tools/build-editions.php)
from a git tag. The Developer zip is the full repository (including the
contributor-only docs above); Personal and Team are a cleaned `git archive`
with `app/Config/editions.php`'s default pre-set to the matching edition.

## Tech stack

- **Backend:** PHP 8.2+, custom MVC micro-framework, MariaDB 10.4+ / MySQL
- **Frontend:** Bootstrap 5.3, HTMX 2.0, Font Awesome 6
- **Libraries:** dompdf, HTMLPurifier, PhpSpreadsheet, PHPMailer, Monolog, phpdotenv

## Requirements

Docker installs need nothing but Docker — the image ships everything. Native
installs need:

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`
- MariaDB 10.4+ (or MySQL 8)
- [Composer](https://getcomposer.org/) — only for git checkouts and the
  Developer zip; the **Personal and Team release zips bundle `vendor/`**
- Apache (the upload/storage `.htaccess` hardening assumes Apache; on Nginx you
  must reproduce the "deny script execution in upload dirs" rules at the
  web-server level — see [`SECURITY.md`](SECURITY.md))

## Quick start

### Option A — Docker (recommended)

One command, no clone required — the script generates the `.env` secrets and
starts the stack (app + MariaDB + scheduler):

```bash
mkdir favilla && cd favilla
curl -LO https://raw.githubusercontent.com/Myth70/favilla/main/quickstart.sh
bash quickstart.sh        # add --auto for a hands-off first boot
# app on http://localhost:8080 — the setup wizard finishes the install
```

On Windows, use `quickstart.ps1` the same way. The stack pulls the prebuilt
multi-arch image [`ghcr.io/myth70/favilla`](https://github.com/Myth70/favilla/pkgs/container/favilla)
(amd64 + arm64 — Raspberry Pi works). Prefer doing it manually? Create a `.env`
with `APP_KEY`, `BACKUP_ENCRYPTION_KEY`, `DB_PASS` and `DB_ROOT_PASS` (see
[`.env.example`](.env.example)) and run `docker compose up -d`. To build the
image from source instead:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

### Option B — local (XAMPP / native PHP)

```bash
git clone https://github.com/Myth70/favilla.git favilla
cd favilla
composer install
cp .env.example .env          # then set APP_KEY, BACKUP_ENCRYPTION_KEY, DB_PASS
```

Generate the secrets:

```bash
php -r "echo bin2hex(random_bytes(32));"   # use for APP_KEY
php -r "echo bin2hex(random_bytes(32));"   # use for BACKUP_ENCRYPTION_KEY
```

Create the database, then point your web server's document root at `public/`
and open the app. On first visit the **setup wizard** (`setup.php`) walks you
through the database connection, the initial schema and the edition choice.
Alternatively, load the schema and required seed manually:

```bash
php database/migrate.php
```

## First login

The required seed creates a default administrator:

- **Username:** `admin`
- **Password:** `Admin123!`

The account is flagged `must_change_password`, so you are forced to set a new
password on first login. **Change it immediately** and do not expose the default
credentials to the public internet.

> `database/seeds/test_users.sql` is an **optional development-only** seed with
> deliberately weak, predictable credentials. Never load it in production.

## Upgrading

See [`UPGRADING.md`](UPGRADING.md). TL;DR: back up, replace the code, run
`php database/migrate.php` — migrations are tracked and idempotent. Docker:
`docker compose pull && docker compose up -d`.

## Development

```bash
composer test               # PHPUnit — in-memory SQLite, no external DB needed
composer stan               # static analysis (PHPStan level 6)
composer cs                 # code style check (PSR-12)
composer security           # dependency CVE audit
php favilla lang:check      # translation completeness (en/fr/de/es vs it)

# Project CLI
php favilla make:module ModuleName
php favilla context:generate        # regenerate project_context.json
```

Architecture, security, data and UI contracts live in
[`docs/contracts/`](docs/contracts/); start a new module from
[`docs/contracts/building-a-module.md`](docs/contracts/building-a-module.md).
The repository is **AI-assistant-ready**: [`CLAUDE.md`](CLAUDE.md) plus
machine-readable module inventories (`project_context.json`, `context/`) let
coding agents navigate the codebase with full context.

## Contributing

Contributions are welcome — see [`CONTRIBUTING.md`](CONTRIBUTING.md) for the
dev-environment setup, the non-negotiable conventions and the PR workflow.

## Security

Found a vulnerability? Please **do not** open a public issue — see
[`SECURITY.md`](SECURITY.md) for private reporting.

## License

Favilla is licensed under the **GNU Affero General Public License v3.0 or later
(AGPL-3.0-or-later)**. See [`LICENSE`](LICENSE) for the full text. In short: if
you run a modified version of Favilla as a network service, you must make your
modified source available to its users.
