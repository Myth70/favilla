# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html):
within the current 2.x line, minor and patch releases are backward-compatible —
any breaking change would bump the major version.

## [Unreleased]

### Added
- **Demo instance tooling** (roadmap A3): a guarded `php favilla demo:reset`
  command (wipes uploads, rebuilds the DB and reseeds the demo dataset —
  refuses to run unless `DEMO_MODE=true`), a `docker-compose.demo.yml` overlay
  with an hourly reset loop, a demo notice with the sample credentials on the
  login page, and a deploy guide in `docs/demo-instance.md`.
- **SSO smoke-test lab**: a throwaway Keycloak with a pre-provisioned realm
  (`tools/sso-lab/`, client + test users covering verified/unverified e-mail
  and IdP-disabled accounts) and a step-by-step runbook mapping the
  security-contract checklist (`docs/sso-smoke-test.md`).
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
