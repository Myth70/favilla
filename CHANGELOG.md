# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html):
within the current 2.x line, minor and patch releases are backward-compatible —
any breaking change would bump the major version.

## [Unreleased]

## [2.4.0] - 2026-07-25

**Favilla is now feature-complete and frozen.** See [`STABILITY.md`](STABILITY.md)
for the full policy: what is frozen (schema, modules, routes, permissions, API v1),
what stays open (security, bug fixes, translations) and what is declined.

### Changed
- **Stability declaration.** Added [`STABILITY.md`](STABILITY.md) and marked all
  twenty modules `"stability": "stable"` in their `module.json`. The database
  schema (105 tables) is frozen and locked by `tests/Unit/SchemaFreezeTest.php`,
  which fails on any change to `database/schema.sql` — a deliberate update to the
  fingerprint is now required, so schema changes cannot happen by accident.
- The `Container` no longer injects `null` when a nullable constructor dependency
  cannot be resolved. An unresolvable type is a wiring error, and it now fails
  where it happens instead of surfacing as a null far from the cause. A dependency
  with a declared default still receives that default — that is an explicit choice
  by whoever wrote the constructor. This branch was previously untested; it is now
  covered.
- `e()` accepts `mixed` instead of `?string`. Views routinely pass ints, floats
  and bools, which worked only because no view declares `strict_types` — the first
  one to do so would have thrown a `TypeError` inside the function that guards
  every output. Behaviour for strings and `null` is unchanged.

### Fixed
- **Timestamps written by PHP and by SQL `NOW()` no longer disagree.** The PDO
  session is now pinned to `APP_TIMEZONE`, so `BaseRepository::applyTimestamps()`
  and statements like `SET deleted_at = NOW()` share one clock. Without this, a
  Docker install — where the official MariaDB image runs UTC while the app defaults
  to `Europe/Rome` — wrote rows hours apart into the same column. Independent
  module databases inherit the same session options. Guarded by
  `tests/Integration/TimestampClockAlignmentIntegrationTest`.
- The MariaDB integration harness now aligns PHP's clock with `APP_TIMEZONE`, the
  same thing `Application::boot()` does in production. It never did, which went
  unnoticed while the session clock was left at the server default: pinning the
  session (above) without pinning PHP left the suite running on two clocks and
  failed the scheduler tests on any host whose `php.ini` is not on
  `APP_TIMEZONE` — a CI runner, typically. The harness now reproduces the
  production setup rather than the host's.
- Teams no longer resolves repositories from inside its views: the reactions and
  "read by N" batches moved into `TeamsService`/`TeamsMessageService`, restoring
  the `Controller → Service → Repository → View` layering. No view in the project
  calls a repository any more.
- PHPStan's `tmpDir` is now resolved from the project root rather than the current
  working directory, which had been creating a nested
  `storage/tmp/storage/tmp/phpstan-cache` whenever the analysis ran from elsewhere.

### Documentation
- `CONTRIBUTING.md` opens with the freeze policy, so contributors learn what is
  in scope before writing code rather than after a PR is declined. The feature
  request issue template does the same, and points at the bug template (still
  fixed) and at forking (fully supported) as the two real paths forward.
- `docs/contracts/architecture.md` states the layering rule for views explicitly:
  a view resolving a repository is now named as a violation, with the note that
  batching a query to avoid an N+1 belongs in the service — that is exactly how
  the rule got broken in Teams.
- `AGENTS.md` and `CLAUDE.md` carry the freeze as a leading non-negotiable.

### Removed
- `docs/roadmap.md`. Every item in it is now resolved: A1/A2 shipped in 2.3.0,
  the layering item (B3) is fixed in this release, and the rest — full-text
  search, import/export, the router index (B1) and the Progetti split (B4) — is
  declined by the freeze. The two that were structural debt rather than features
  are recorded as accepted debt in `STABILITY.md`, with reasons. Keeping a
  forward-looking roadmap in a frozen project would only mislead.
- **The planned migration from MariaDB to SQLite (v3.0.0) has been abandoned.**
  MariaDB remains the only supported engine. The work in progress was reverted;
  only the timezone fix above, which was never SQLite-specific, was kept. This is
  recorded here so the decision is not rediscovered and reopened later.

## [2.3.1] - 2026-07-24

### Security
- Updated third-party dependencies to clear newly published advisories:
  **`dompdf/dompdf`** 2.0.8 → **3.1.6** (major bump; file-existence oracles, DoS
  via oversized/BMP images, local file read via SVG data-URI, chroot bypass),
  **`phpoffice/phpspreadsheet`** 5.8.0 → **5.9.0** (memory exhaustion on
  crafted XLS/Gnumeric, WEBSERVICE SSRF whitelist bypass) and
  **`guzzlehttp/guzzle`** 7.13.3 → **7.15.1** (referer/cookie leakage, response
  cookie DoS, proxy-auth header). The app's PDF engine uses only dompdf's stable
  API, so the major bump is transparent.

### Removed
- Dropped the unused bundled `plotly.min.js` — it was never wired up; ApexCharts
  covers all charting.

## [2.3.0] - 2026-07-24

### Added
- **Demo dataset overhaul — international "Aurora Studio"**: the demo cast is now
  10 users, two per locale (it/en/fr/de/es), each with their own UI language,
  avatar (`avatar_path`), personalised dashboard (`user_preferences` +
  `user_widget_preferences`) and personal tasks/calendar/contacts/files. Content
  spreads across a **rolling current year** (never looks stale) with recurring
  calendar events; projects gain a 4th (staggered timelines); Teams gains
  English work groups + native-language DMs and image/PDF attachments; the blog
  is ~10 English articles (one scheduled, one pinned, cover images). New demo
  coverage for previously-unseeded modules: **Reports** (templates, style
  presets, history), **Feedback**, per-user **notification preferences**,
  **HealthCheck/Backup/Scheduler** minimal data and inert **Webhooks/API** demo
  entries. Demo-only permission grants let Managers/Users actually use
  Projects/Documents. New **`php favilla demo:reset`** wipes the demo data
  (rows, users, grants, assets) for a clean reload, and **`php favilla
  demo:refresh`** restores a whole demo instance to a pristine state
  (`migrate --fresh` + reseed, uploads included) — for public demos that reset
  on a schedule via an external cron.
- **API v1 breadth** (roadmap A2): the REST API grows from the two-module pilot
  to five modules. New endpoints: **Contacts write** (`POST/PUT/DELETE
  /contacts`, partial updates, owned contacts only), **Calendar read**
  (`/calendar/events` with a `from`/`to` range and recurrences expanded into
  occurrences, plus single-event detail), **Projects read** (`/projects`, same
  owner/member scoping as the UI, `progetti.view_all` honored from token
  scopes) and **Documents read** (`/documents`, metadata only, UI visibility
  rules driven by token scopes). OpenAPI spec bumped to 1.1.0 — still the
  single source of truth at `/api/v1/openapi.json`.
- **Full backups — files included** (roadmap A1): backup archives now bundle
  uploaded files (`public/uploads/` and the Documents storage) next to the SQL
  dumps, with a per-root summary in the manifest (v2), the admin UI and the
  history log. The in-app restore brings files back to the backup's state
  (files uploaded after the backup are never deleted). Opt out with
  `BACKUP_INCLUDE_FILES=false`.

### Changed
- Backup downloads and zip restores now stream unencrypted archives from disk
  instead of loading them into memory (relevant with multi-GB file backups).
- `backup_history.size_bytes` widened to `BIGINT` (archives can exceed 4 GB
  once files are included); new `files_json` column with the per-root summary.

### Fixed
- A failed backup encryption (e.g. archive over the in-memory cap) no longer
  leaves the unencrypted archive on disk — exactly what the policy forbids.
- Plain (unencrypted) `.zip` backup sets are now downloadable and restorable
  when `BACKUP_ENCRYPTION_KEY` is set: they were mistaken for the legacy CBC
  format and failed to decrypt.

## [2.2.0] — 2026-07-11

### Added
- **Web Push + installable PWA**: a fourth notification channel delivering push
  to browsers and desktops (VAPID, per-device opt-in), a `push_subscriptions`
  store, a service worker with an offline fallback and an installable
  `manifest.webmanifest`. New dependency: `minishlink/web-push`.
- **Public REST API v1**: a token-authenticated JSON API under `api/v1`. Personal
  Access Tokens are managed from the profile (hashed at rest, shown once, with
  **mandatory scopes** that are a subset of the user's permissions). Consistent
  `{data,meta}` / `{error}` envelope, per-token rate limiting (`X-RateLimit-*`),
  and an OpenAPI 3.1 spec at `/api/v1/openapi.json`. Pilot endpoints: `/me`,
  Tasks (CRUD) and Contacts (read). Developer reference in
  [`docs/api/README.md`](docs/api/README.md).
- **Outgoing webhooks**: subscribe an HTTPS endpoint to any notification event.
  Deliveries are signed with a timestamped `HMAC-SHA256` signature
  (`X-Favilla-Signature: t=…,v1=…`, anti-replay), retried with exponential
  backoff by the scheduler (`webhooks:dispatch`), and recorded in a delivery log.

### Security
- Webhook destinations are guarded against SSRF: reserved-range blocking
  (including the IPv4-mapped IPv6 `::ffff:` forms, CGNAT, NAT64 and cloud
  metadata), resolved-IP pinning that closes the DNS-rebinding window, and no
  redirect following. Signing secrets are excluded from audit logs.
- API tokens require at least one scope (an empty selection no longer inherits
  full permissions). Rotating the Web Push VAPID keys invalidates existing
  subscriptions so clients re-subscribe cleanly.

## [2.1.0] — 2026-07-05

### Added
- **Single Sign-On (OIDC)** for the Team and Developer editions: authorization
  code + PKCE against any standards-compliant Identity Provider, configured
  from Admin → Impostazioni (new SSO tab with computed redirect URI and a
  "test connection" button; client secret encrypted at rest). Existing users
  are matched by verified e-mail on first login and permanently linked by
  provider subject (`oidc_identities` table); optional JIT provisioning
  creates new users with a configurable default role (never admin). "SSO only"
  mode hides the password form, with an admin break-glass at `/login?local=1`.
  SSO logins delegate MFA to the IdP. New dependency: `firebase/php-jwt`
  (JWT/JWKS signature verification only).

## [2.0.4] — 2026-07-05

### Added
- **Loadable demo dataset** ("Aurora Studio"): sample tasks, calendar events,
  contacts, files, notifications and — when the modules are enabled — projects
  with Gantt/timesheet, documents mid-approval-workflow with real protocol
  numbers, team chats and blog posts, with real downloadable files (checksums
  verified). Loaded from the setup wizard (checkbox at the edition step), via
  `php favilla demo:seed [--force] [--enable-modules]`, or on Docker first
  boot with `DEMO_DATA=true` / `quickstart --demo`. Seeds are idempotent
  (fixed high IDs + INSERT IGNORE) and dates are relative, so the demo never
  goes stale.

### Changed
- `database/seeds/test_users.sql` now uses explicit user IDs (3-12) and
  `INSERT IGNORE`: reloadable, and the hardcoded role mappings can no longer
  drift from AUTO_INCREMENT.

## [2.0.3] — 2026-07-05

Install-friction release: prebuilt Docker image, one-command quickstart,
unzip-and-go release zips and an upgrade guide.

### Added
- Prebuilt **multi-arch Docker image** (amd64/arm64) published to
  `ghcr.io/myth70/favilla` by the new Docker workflow (release tags +
  manual dispatch).
- **One-command quickstart**: `quickstart.sh` / `quickstart.ps1` generate the
  `.env` secrets (APP_KEY, BACKUP_ENCRYPTION_KEY, DB passwords) and start the
  Docker stack — no clone required, `--auto` for a hands-off first boot.
- `docker-compose.dev.yml` override to build the image from local source.
- **`UPGRADING.md`** — upgrade guide for zip, git and Docker installs.
- The setup wizard pre-fills `APP_KEY` from the process environment when set
  (Docker), instead of generating a decoy value.

### Changed
- `docker-compose.yml` now pulls the published ghcr.io image by default
  (pin with `FAVILLA_TAG`) instead of building from source.
- The Personal and Team release zips **bundle `vendor/`** — unzip-and-go, no
  Composer needed on the target server (Developer zip unchanged);
  `tools/build-editions.php` aborts if vendor/ is missing or contains dev
  dependencies.

## [2.0.2] — 2026-07-04

First public release under the GNU AGPL-3.0-or-later.

### Added
- **Editions** — one codebase, three editions (Developer / Personal / Team)
  chosen in the setup wizard or from Admin → Configurazione; release zips are
  built from a git tag by `tools/build-editions.php` (Release workflow).
- Four optional modules: **Progetti** (projects), **Teams**, **Documenti**
  (managed documents with expiry and integrity checks) and **Blog** — enabled
  by default in the Team edition, installable from Admin → Moduli in the others.
- **Multilingual UI** — English, French, German and Spanish translations on top
  of the canonical Italian, covering the PHP, database-overlay and JS layers;
  completeness is enforced by `php favilla lang:check --strict` (blocking in CI).
- `declare(strict_types=1)` across the application code layer.
- Unified application logging via the `app_log()` helper (Monolog with a safe
  `error_log` fallback).
- HTTP-level testability seam: middleware and the router raise `HttpException` /
  `HttpRedirectException` instead of `exit`, handled centrally in `Application`.
- Security-invariant regression tests (CSRF rejection, route authorization audit
  over all routes, dompdf SSRF config, ORDER BY whitelist, upload magic bytes).
- Opt-in real-MariaDB integration suite (`RUN_DB_INTEGRATION=1`) + CI job.
- Controller tests for the Feedback, Tasks, HealthCheck and HelpOnline modules.
- CI: `composer audit`, code coverage (pcov/clover), a non-blocking code-style
  check, and PHPStan result caching.
- Tooling: `.editorconfig`, php-cs-fixer config, and composer `scripts`.
- `CONTRIBUTING.md`, `CHANGELOG.md`, `CODE_OF_CONDUCT.md`, Dependabot config and
  issue/PR templates.

### Changed
- `.env.example` now documents all configuration keys read by the application.
- Static analysis raised to **PHPStan level 6** (parameter/return/property types
  enforced; array-generics check deferred). The whole code tree is now PSR-12
  conformant and the CI code-style check is blocking.
- `flash()` / `flash_success()` / `flash_error()` helpers replace direct
  `$_SESSION['_flash_*']` writes across the app.
- Backup at-rest encryption extracted from `BackupService` into a focused
  `BackupEncryptionService` (with an added encrypt→decrypt round-trip test).
- `ResetPasswordController` no longer issues raw SQL: rate-limit checks go through
  `RateLimiter`. `CalendarService` resolves its ICS helper via the container.
- `SessionSecurityMiddleware` audits 403 denials synchronously (catching the
  thrown `HttpException`) instead of via a shutdown function.

### Security
- Post-login and MFA redirects now share the hardened `isSafeRedirectTarget()`
  guard (raw + decoded checks for `//`, `\`, `..`) instead of ad-hoc regexes.
- Avatar cropping rejects oversized source image dimensions before GD decoding
  (decompression-bomb guard) in `FileUploadService::saveCroppedAvatar()`.
- Public Telegram webhook applies per-IP rate limiting on repeated wrong-secret
  attempts (defence in depth on top of the timing-safe secret comparison).
- Docker: `MARIADB_ROOT_PASSWORD` now requires an explicit `DB_ROOT_PASS` and no
  longer silently falls back to the application DB password.
- `WidgetDataCache` reads cache files with `allowed_classes: false` and sanitises
  the cache key against path traversal.
- TOTP anti-replay degradation is now logged loudly and actionably.
- Defensive column whitelist in `NotificationEventRepository::hasEventTypeColumn()`.

[Unreleased]: https://github.com/Myth70/favilla/compare/v2.2.0...HEAD
[2.2.0]: https://github.com/Myth70/favilla/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/Myth70/favilla/compare/v2.0.4...v2.1.0
[2.0.4]: https://github.com/Myth70/favilla/compare/v2.0.3...v2.0.4
[2.0.3]: https://github.com/Myth70/favilla/compare/v2.0.2...v2.0.3
[2.0.2]: https://github.com/Myth70/favilla/releases/tag/v2.0.2
