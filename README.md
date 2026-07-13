<div align="center">

<img width="500" alt="Favilla" src="docs/logo/favilla_logo.jpg" />


**The self-hosted workspace that runs your company — and stays yours.**

Projects · Documents · Team chat · Tasks · Calendar · Contacts · Files · Reports

[![CI](https://github.com/Myth70/favilla/actions/workflows/ci.yml/badge.svg)](https://github.com/Myth70/favilla/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/Myth70/favilla)](../../releases)
[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)

🌐 **English** · [Italiano](README.it.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Español](README.es.md)

**[What's different](#what-makes-it-different) · [Modules](#modules) · [Editions](#editions) · [Quick start](#quick-start) · [Status](#project-status) · [Development](#development) · [Contributing](#contributing)**

</div>

**Favilla** — Italian for *spark* — is a complete workspace and company
intranet you host yourself: projects with Gantt, timesheets and budgets,
documents with approval workflows, team messaging, kanban tasks, shared
calendars, contacts, files, print-ready reports, multi-channel notifications
and a full security & compliance suite. Twenty modules, five languages, one
install, on your own server — no per-seat pricing, no telemetry, nothing
phones home, and the AGPL keeps it that way.

On the map of tools you already know, Favilla sits where a project tracker, a
document-management system and a team messenger overlap — an operational
intranet in the Basecamp tradition, not an office suite. It complements
Nextcloud rather than replacing it: Favilla doesn't sync files, it runs your
projects, documents and processes.

**What Favilla is not:** a file-sync or office suite (that's Nextcloud /
OnlyOffice), a public-facing CMS, a customer-facing helpdesk, or a multi-tenant
SaaS. It's an internal operational workspace for a single organization, run by
that organization on its own server.

![Favilla dashboard](docs/screenshots/dashboard.png)

## What makes it different

- **Build reports like you build slides.** A drag-and-drop designer
  (GrapesJS) for print-ready PDF and Excel templates, right in the browser:
  smart data components, reusable styles, server-side sanitization. Reports
  are first-class citizens, not an afterthought.
- **Help that ships with the product.** Every page has a contextual help
  panel backed by a built-in knowledge base — 340+ Q&As, each in all five
  languages, with synonym-aware search and admin analytics on what users look
  for and don't find. Fewer "how do I…?" tickets from day one.
- **A security suite you'd expect from paid software.** SSO (OIDC) with PKCE,
  account linking and optional JIT provisioning; TOTP two-factor auth; a
  security dashboard with incident detection (brute force, CSRF); full audit
  log; data-retention policies; AES-256-GCM encrypted backups with in-app
  restore; session hardening, login rate limiting, password policy.
- **Five languages out of the box.** Italian (the canonical source), English,
  French, German and Spanish, with a per-user switcher — and not just the UI:
  notifications and the help knowledge base are translated too. (Code and
  docs are in English.)
- **One codebase, three editions.** Personal, Team and Developer are the same
  product wearing different clothes: start alone, grow into a company
  intranet without reinstalling anything. See [Editions](#editions).
- **AI-assistant-ready.** The repo ships [`CLAUDE.md`](CLAUDE.md),
  machine-readable module inventories (`project_context.json`, `context/`)
  and written architecture contracts (`docs/contracts/`), so coding agents
  and new contributors navigate it the same way. Much of Favilla was built
  pairing with AI agents — the workflow is first-class, not incidental.

And the fundamentals are all there:

- **A dashboard that's actually yours** — 17 live widget providers (today's
  agenda, open tasks, project status, backup health… even local weather);
  each user picks, hides and reorders their own.
- **Template-driven notifications** — one dispatcher, three channels (in-app,
  email, Telegram), per-user preferences, queued delivery with retry/backoff;
  admins control the wording and look from the UI.
- **Fast to move around in** — global search across all modules, a
  right-click radial quick menu, HTMX partial updates everywhere, light &
  dark themes.
- **Operations built in** — a cron-equivalent scheduler with admin UI, health
  checks with history and export, log rotation, and a project CLI
  (`php favilla`) for automation.

## Boring tech, built to last

Favilla makes two deliberately unfashionable choices:

1. **Server-rendered PHP 8.2 + HTMX.** No SPA, no build step, no
   `node_modules`. It deploys on anything from XAMPP to Docker Compose and
   runs happily on a Raspberry Pi.
2. **A custom micro-framework — no Laravel, no Symfony.** A classic MVC
   application you can read, audit and extend end-to-end: controllers,
   services, repositories, views, no magic.

<!-- Keep counts in sync with FEATURES.md. Recompute: tests → `phpunit --list-tests | grep -c '^ - '`; tables → `grep -c 'CREATE TABLE' database/schema.sql`; help Q&As → `grep -rho '"question"' database/help/*.json | wc -l` ÷ 5. -->
Choices like these only hold up with discipline behind them: **1,800+
automated tests**, **PHPStan level 6** and **PSR-12** enforced in CI, and a
**100+ table schema** installed by a guided setup wizard.

## Screenshots

| | |
|---|---|
| ![Configurable dashboard](docs/screenshots/dashboard-configure.png) <br>*Every widget is yours: drag to reorder, toggle to hide* | ![Contextual help](docs/screenshots/help-online.png) <br>*Contextual help with a searchable knowledge base, on every page* |
| ![Kanban board](docs/screenshots/tasks-kanban.png) <br>*Tasks as list, calendar or kanban board* | ![Appearance settings](docs/screenshots/appearance.png) <br>*Per-user themes, colors, fonts and layout styles* |

## Modules

| Module | What you get |
|---|---|
| **Home** | Personal widget dashboard with per-user layout |
| **Tasks** | Personal & operational tasks, kanban board, due-date reminders |
| **Calendar** | Personal and shared events, reminders, ICS export |
| **Contacts** | Address book with map view, CSV import, role-based sharing, follow-up reminders |
| **Files** | Uploads with sharing, previews and SHA-256 checksums |
| **Projects** | Milestones, kanban & Gantt views, task dependencies, timesheets and budget control |
| **Teams** | Team messaging: 1:1 and group chats, mentions, reactions, presence |
| **Documents** | Managed documents: versioning, approval workflow, protocol numbers, expiry and integrity checks |
| **Blog** | Internal news with scheduled publishing, role-based visibility and moderated comments |
| **Reports** | GrapesJS template designer, PDF/Excel generation, document models |
| **Notifications** | Multi-channel template-driven notification center (in-app, email, Telegram, **Web Push**) + installable PWA |
| **Webhooks** | Outgoing webhooks to external systems (Zapier, n8n, custom) with HMAC signing, retries and anti-SSRF |
| **API** | Public REST API v1 with Personal Access Tokens, scoped permissions and per-token rate limiting |
| **HelpOnline** | Contextual help + knowledge base with search analytics |
| **Admin** | Users, roles & fine-grained permissions, impersonation, security area, app settings |
| **Auth** | Login, SSO (OIDC), registration with admin approval, 2FA, password recovery |
| **Backup** | Encrypted database backups, download and in-app restore |
| **HealthCheck** | System diagnostics with history and export |
| **Scheduler** | Recurring jobs with UI: cron expressions, timeouts, run history |
| **Feedback** | In-app issue reporting with triage workflow |

The complete capability list, module by module, is in
[**FEATURES.md**](FEATURES.md).

## Editions

One product that grows with you. Favilla ships from a single codebase in
three editions, chosen during the setup wizard (or changed later from Admin →
Configurazione):

- **Personal** — a single-user workspace. Registration is off and every
  multi-user surface (roles, sharing, the admin area) is tucked away under a
  discreet Settings corner. It feels like a personal app; it's still all of
  Favilla underneath.
- **Team** — the multi-user company intranet: role-based permissions, open
  registration with admin approval, and Projects, Teams, Documents and Blog
  enabled by default.
- **Developer** — for working on Favilla itself: the full repository,
  including the contributor and AI-assistant docs (`CLAUDE.md`,
  `docs/contracts/`, `context/`).

| | **Personal** | **Team** | **Developer** |
|---|---|---|---|
| Intended for | Single-user personal workspace | Multi-user company intranet | Contributing to Favilla itself |
| Multi-user / RBAC UI | Hidden | Visible | Visible |
| Registration page | Disabled (single account) | Open | Open |
| Projects, Teams, Documents, Blog | Installable from Admin → Moduli | **Enabled by default** | Installable from Admin → Moduli |
| Dev & AI-assistant docs | Not included | Not included | Included |

An edition changes what the UI shows — never what the code can do. **Hidden ≠
disabled**: the scheduler and all core modules run in every edition, so
nothing that other features (like reminders) depend on ever goes away. When a
Personal install stops being just you, enable the four team modules from
**Admin → Moduli** and switch the edition in **Admin → Configurazione** — no
reinstall, no migration, no export/import.

Release zips for all three editions are published on the
[Releases](../../releases) page, built from a git tag by
[`tools/build-editions.php`](tools/build-editions.php): Developer is the full
repository; Personal and Team are a cleaned archive with the default edition
pre-set and `vendor/` bundled, so they don't even need Composer. Full
technical contract — config, runtime resolution, setup wizard and release
packaging — in [`docs/contracts/editions.md`](docs/contracts/editions.md).

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
bash quickstart.sh        # --auto: hands-off first boot · --demo: also load sample data
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

### Option C — Railway / other PaaS

Deploying to Railway (or a similar Docker-based PaaS)? The committed
[`railway.json`](railway.json) makes Railway build from
[`docker/Dockerfile`](docker/Dockerfile) — which compiles the required PHP
extensions (`gd`, `intl`, `zip`) that the default Nixpacks image lacks. The
step-by-step recipe (database, environment variables, first-boot migration and
the port note) is in [`docs/deploy-railway.md`](docs/deploy-railway.md).

## First login

The required seed creates a default administrator:

- **Username:** `admin`
- **Password:** `Admin123!`

The account is flagged `must_change_password`, so you are forced to set a new
password on first login. **Change it immediately** and do not expose the default
credentials to the public internet.

> `database/seeds/test_users.sql` is an **optional development-only** seed with
> deliberately weak, predictable credentials. Never load it in production.

## Demo data

Want to evaluate Favilla with content in it instead of an empty install? Load
the demo dataset — a fictional creative agency (*Aurora Studio*) with projects
(Gantt, timesheets), documents mid-approval-workflow, team chats, tasks,
calendar, contacts and blog posts, plus real downloadable files:

- **Setup wizard**: tick *"Carica dati dimostrativi"* at the edition step;
- **CLI**: `php favilla demo:seed` on an installed instance;
- **Docker**: `bash quickstart.sh --demo` (or `DEMO_DATA=true` with
  `AUTO_MIGRATE=true`) on first boot.

The dataset includes the 10 weak-password test users above — evaluation
environments only.

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
Integrating with the public API, webhooks or Web Push? See the
[developer reference](docs/api/README.md) and the
[OpenAPI spec](docs/api/openapi.json).
The repository is **AI-assistant-ready**: [`CLAUDE.md`](CLAUDE.md) plus
machine-readable module inventories (`project_context.json`, `context/`) let
coding agents navigate the codebase with full context.

## Project status

Favilla is **stable and actively developed**, and runs in production. It follows
[Semantic Versioning](https://semver.org/): within the current **2.x** line,
minor and patch releases are backward-compatible — a breaking change would bump
the major version. Still, skim the [changelog](CHANGELOG.md) before upgrading
(and see [`UPGRADING.md`](UPGRADING.md)). Security fixes land on `main` and the
latest release ([`SECURITY.md`](SECURITY.md)).

Recently shipped (**2.2.0**): Web Push notifications with an installable PWA, a
token-authenticated public **REST API v1**, and outgoing **webhooks** (HMAC-signed,
anti-SSRF) — see the [developer reference](docs/api/README.md). Earlier: SSO
(OIDC), a loadable demo dataset, prebuilt multi-arch Docker images and a
one-command quickstart. On the near horizon: a native-speaker review pass on the
`fr`/`de` translations, and LDAP as a second sign-in backend alongside OIDC.

## Contributing

Contributions are welcome — see [`CONTRIBUTING.md`](CONTRIBUTING.md) for the
dev-environment setup, the non-negotiable conventions and the PR workflow. By
participating you agree to the [Code of Conduct](CODE_OF_CONDUCT.md); notable
changes are tracked in the [changelog](CHANGELOG.md).

## Security

Found a vulnerability? Please **do not** open a public issue — see
[`SECURITY.md`](SECURITY.md) for private reporting.

## License

Favilla is licensed under the **GNU Affero General Public License v3.0 or later
(AGPL-3.0-or-later)**. See [`LICENSE`](LICENSE) for the full text. In short: if
you run a modified version of Favilla as a network service, you must make your
modified source available to its users.

<div align="center">
    <img width="300" height="100" alt="mobile-title" src="https://github.com/user-attachments/assets/ceeff067-98e1-4f7c-bb19-9585e501c275" />

<sub>Made in Italy 🇮🇹</sub>
</div>
