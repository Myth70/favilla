# Favilla — Full Feature List

Every capability listed here is also documented in the **in-app contextual
help** (the `?` panel), in all five UI languages. This page is the complete
reference; the [README](README.md) has the short version.

> **Naming.** Two module directories on disk use Italian identifiers —
> `Progetti` (Projects) and `Documenti` (Documents); the others match their
> English names. This page and the README use the English display names.

**Modules:**
[Home](#home) · [Tasks](#tasks) · [Calendar](#calendar) ·
[Contacts](#contacts) · [Files](#files) · [Projects](#projects) ·
[Teams](#teams) · [Documents](#documents) · [Blog](#blog) ·
[Reports](#reports) · [Notifications](#notifications) ·
[Help Online](#help-online) · [Feedback](#feedback) ·
[Account & Sign-in](#account--sign-in) · [Administration](#administration) ·
[Backup](#backup) · [Health Check](#health-check) · [Scheduler](#scheduler) ·
[Cross-module](#cross-module) · [Platform](#platform)

---

## Home

Personal dashboard and entry point.

- Widget dashboard: every module contributes live widgets (agenda, open tasks,
  project status, notifications, files, backups, system status, weather…).
- Per-user configuration: drag to reorder, toggle to show/hide, reset to default.
- Stat, list, chart and HTML widget types (e.g. weekly completions chart).
- Quick-access launcher with shortcuts to every module.
- **Today page**: a focused daily view of what needs attention now.
- Global search from the header (Ctrl+K), scoped by permissions.
- Application changelog: in-app "what's new" per version.

## Tasks

Personal and operational to-dos.

- Three views: list, kanban board, in-calendar.
- Board columns: backlog, to do, in progress, in review, done — drag cards to
  change status, quick-complete from the board.
- Checklists inside tasks; tags for classification.
- Due dates with alerts and reminder notifications (scheduler-driven).
- Two-way integration with the Calendar.
- Filters and full-text search.

## Calendar

Personal and shared scheduling.

- Month/week/day views with a command bar and quick filters.
- Events with reminders (notification lead time per event).
- Recurring events.
- Shared events, visible across users.
- ICS import and export.
- Integration with Tasks and Contacts (deadlines and anniversaries appear
  automatically).

## Contacts

Address book with light-CRM traits.

- Contact records with categories, tags and favorites.
- Social profiles and contact channels per record.
- Map view (OpenStreetMap) of geolocated contacts.
- Recurrences and follow-up reminders (birthdays, anniversaries, check-ins).
- CSV import plus import from other modules' data.
- Role-based sharing of your own contacts.
- Filters and full-text search.

## Files

Central file management.

- Uploads with magic-byte MIME validation and SHA-256 checksums.
- Folder organization; grid and list views; filters.
- File versions.
- Sharing with other users.
- Preview and download; metadata editing.
- Multiple selection and bulk actions; trash with restore.
- Reusable file picker embedded by other modules.

## Projects

Structured project management.

- Projects with statuses, members and per-project permissions.
- Milestones.
- Project tasks with their own status workflow and **dependencies**.
- **Kanban and Gantt** views.
- Checklists and reusable project templates.
- **Timesheet**: log worked hours per task/project.
- **Budget and cost control** per project.
- Project closure report; per-project reporting and export.
- "My assigned tasks" across all projects.
- Admin trash with restore.

## Teams

Internal team messaging.

- 1:1 direct chats and named groups with member management.
- Mentions, attachments and emoji reactions.
- Edit/delete your own messages; pin important ones.
- Online presence and typing indicators.
- Search within one conversation or across all of them.
- Mute, hide, archive or leave conversations.
- Group info panel; admin console for conversations.
- Automatic cleanup of old messages (retention).

## Documents

Managed documents with a formal lifecycle.

- Document records with categories and **protocol numbering**.
- **Versioning** with history.
- **Approval workflow** (draft → review → approved → published → archived)
  with an approval inbox and per-step actions.
- Expiry dates with automatic reminders and auto-expiry of published documents.
- Links between documents; links to records in other modules.
- SHA-256 **integrity verification** of stored files (tamper detection).
- Audit trail of every document action.
- Dedicated health check and scheduled maintenance jobs (orphan cleanup,
  expiry processing, integrity scans).
- Search, filters, preview and download.

## Blog

Internal news and announcements.

- Articles with draft/scheduled/published states and **scheduled publishing**.
- Categories, tags and pinned articles.
- **Role-based visibility** per article.
- Comments with moderation queue and word blacklist.
- Likes, bookmarks, reading time, view counters.
- SEO fields and social-sharing metadata.
- PDF export of articles; batch actions; trash with permanent deletion.

## Reports

Self-service reporting and document generation.

- **Drag-and-drop template designer** (GrapesJS) with smart data components.
- Data sources and field catalogs exposed by modules.
- Reusable styles (fonts, colors, chart styles) managed centrally.
- PDF (dompdf) and Excel (PhpSpreadsheet) generation; quick export.
- **Document binding**: generate a formatted document from any supported
  record (e.g. a contact card, a project summary).
- Export history with re-download.
- Server-side HTML sanitization of user-authored templates (HTMLPurifier).

## Notifications

One notification center, many channels.

- Template-driven dispatcher: modules publish events; wording, icon and color
  are configured centrally by admins.
- Channels: in-app, email (SMTP), **Telegram** (per-user account linking).
- Header bell with live unread count and dropdown.
- Per-user preferences per event type and channel.
- Full notifications page with bulk actions (read all, delete).
- Queued email/Telegram delivery with retry and backoff.

## Help Online

Contextual help and knowledge base.

- Floating `?` panel on every page, aware of the current module and route.
- Searchable Q&A knowledge base — 340+ curated answers in **five languages**.
- Quick questions per context; recommended sections; confidence indicator.
- Results filtered by the user's permissions.
- Feedback on answers (helpful / not helpful).
- Synonyms and aliases for natural phrasing; full guide at `/help`.
- Admin console: entries, aliases, module coverage, reindexing, and analytics
  of what users searched and didn't find.

## Feedback

In-app issue reporting.

- Report a problem from any page; context (route, user, environment) is
  attached automatically.
- Admin triage console with statuses.
- Technical export, including a **"Copy for LLM"** format for pasting a
  complete bug report into an AI assistant.

## Account & Sign-in

- Login with rate limiting and lockout on repeated failures.
- **Single Sign-On (OIDC)**: authorization code + PKCE against any standard
  Identity Provider (Keycloak, Authentik, Entra ID…); account linking by
  verified e-mail, optional JIT user provisioning with configurable default
  role, "SSO only" mode with admin break-glass, encrypted client secret,
  admin "test connection" — Team/Developer editions.
- Self-registration with **admin approval** workflow (open/closed per edition).
- **Two-factor authentication** (TOTP) with anti-replay; SSO logins delegate
  MFA to the Identity Provider.
- Password recovery by email; password policy enforcement.
- Personal profile with avatar upload and cropping.
- Active sessions list with remote revocation; sign-in history.
- Per-user appearance: light/dark theme, primary color, sidebar style, menu
  color, page style, font, header background.
- Per-user language switcher (it/en/fr/de/es).

## Administration

The back office. Highlights of its ~120 documented capabilities:

- **Users**: onboarding/offboarding workflows, activate/deactivate, password
  reset, role assignment, session revocation, **impersonation**.
- **Roles & permissions**: fine-grained RBAC (~100 permission slugs, one per
  action), role editor, permission import from modules, separation-of-duties
  guidance.
- **Security area**: dashboard, incident tracking (brute force, CSRF), login
  attempts, active sessions, security policies, **asset inventory**,
  security logs, key rotation, hardening checklist.
- **Audit log** of application actions, with retention policies and export.
- **Data retention**: configurable per-data-class purge policies.
- **Modules**: enable/disable, import/export module packages, edition defaults.
- **Settings**: application settings with typed toggles; mail (SMTP) settings
  with test send; email templates editor.
- **Notifications admin**: dispatcher/event configuration, channel bindings,
  Telegram bot setup, delivery queue monitoring, manual send.
- **Files admin**: inventory with statistics, bulk delete, administrative
  trash with final purge, export.
- **Logs**: error log viewer with statistics, rotation, cleanup, export;
  mail log.
- **Changelog**: author and publish in-app version notes (Markdown).
- Admin panels for Backup, Scheduler, Health Check, Help Online and Reports.

## Backup

- Encrypted database backups (**AES-256-GCM**) with key from `.env`.
- Manual backup creation; scheduled backups via Scheduler.
- Download, delete, rotation policy.
- **In-app restore** with safety confirmation.
- Separate permissions for manage / download / restore.

## Health Check

- System diagnostics across areas: database, filesystem, mail, scheduler,
  queue, module integrity.
- Quick scan and deep scan modes.
- OK / warning / failed statuses with remediation hints.
- Run history and CSV export.
- Automatic runs via Scheduler with failure notifications to admins.

## Scheduler

Cron-equivalent with a UI.

- Recurring jobs on cron expressions with per-job timeout.
- Whitelisted command catalog (module-provided CLI commands).
- Run now, enable/disable, reset; edit schedules from the UI.
- Run history with output, errors and retry information; log cleanup.
- Powers reminders, scheduled publishing, backups, retention, queue
  processing and maintenance jobs out of the box.

## Cross-module

- Global search across all modules (12 search providers), permission-scoped.
- Dashboard widgets (17 providers) and export providers (10 modules) via
  shared contracts.
- Guided flows: task deadline → notification; contact → calendar anniversary;
  file → report attachment; record → generated document.
- Right-click **radial quick menu** for personal shortcuts.
- Role-based sharing surfaces (files, contacts, calendars, documents).

## Platform

- **Five UI languages** — Italian (canonical), English, French, German,
  Spanish — switchable per user; completeness enforced in CI.
- **Three editions** from one codebase (Developer / Personal / Team), chosen
  in the setup wizard and switchable later; optional modules installable from
  the admin area.
- Guided browser **setup wizard**: DB connection, schema, edition, admin
  account.
- Security invariants: CSRF on every mutation, output encoding, prepared
  statements only, whitelisted ORDER BY, magic-byte upload validation, CSP
  with nonces, session hardening ([SECURITY.md](SECURITY.md)).
- Bootstrap 5.3 + HTMX 2.0 server-rendered UI — no build step; light & dark.
- Project CLI (`php favilla`) with 25+ commands (migrations, module
  scaffolding, i18n checks, help KB import/export, queue processing,
  maintenance).
- Docker deployment (app + MariaDB + scheduler loop) or classic
  Apache/XAMPP; 100-table schema installed by migrations.
<!-- Keep counts in sync with README. Recompute: tests → `phpunit --list-tests | grep -c '^ - '`; Q&As → `grep -rho '"question"' database/help/*.json | wc -l` ÷ 5; tables → `grep -c 'CREATE TABLE' database/schema.sql`. -->
- 1,700+ automated tests, PHPStan level 6, PSR-12 — all enforced in CI.
