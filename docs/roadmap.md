# Roadmap

Project roadmap after the **2.2.0** release (Reach & Integrations — the 2.x line
is stable). Two tracks:

- **Track A — Product & adoption**: user-visible gaps that matter most for
  self-hosted adoption, ranked by expected impact.
- **Track B — Structural debt**: post-audit maintainability work. Nothing there
  is urgent or blocking; pay it down opportunistically.

Effort: **S** ≈ half a day · **M** ≈ a few days · **L** ≈ 1–2 weeks.

---

# Track A — Product & adoption

| # | Item | Effort | Value | Notes |
|---|------|:------:|:-----:|-------|
| A1 | ~~Backup completeness~~ ✅ done | S–M | high | merged in #20 — ships with 2.3.0 |
| A2 | **API v1 breadth** ★ next up | M–L | high | synergy with B2 |
| A3 | SSO validation + public demo | M | high | unblocks Show HN |
| A4 | Full-text search | M | med–high | FEATURES.md wording fix is S |
| A5 | Import/export round-out | S each | med | — |
| A6 | Launch calendar | — | med | external dates |

## A1 — Backup completeness ✅ done

> **Done** — merged 2026-07-12 (#20), ships with the next release. Backup sets
> now bundle `public/uploads/` and the Documenti storage next to the SQL dumps
> (manifest v2), the in-app restore brings files back without deleting later
> uploads, and the admin UI shows the archive contents. Opt-out:
> `BACKUP_INCLUDE_FILES=false`.

Original rationale: the Backup module produced a **database-only** backup, so a
restore silently lost every uploaded file — versioned, integrity-checked
documents included. For a groupware this was a data-loss trap and a trust
problem, and it was cheap to fix. Off-site targets (S3, rclone, …) remain
**out of scope** — see the exploratory list.

## A2 — API v1 breadth ★ next up

API v1 today is a pilot: Tasks (CRUD), Contacts (read-only), `/me`. Calendar,
Files, Progetti, Documenti, Teams, Blog and Notifications have no API surface,
which caps integrations and automation — the top reason technical self-hosters
pick a platform.

- Next endpoints by value: Calendar (read), Progetti (read), Contacts (write),
  Documenti (read); iterate from there.
- Per-module scopes and the same envelope/middleware as the pilot;
  `docs/api/openapi.json` stays the single source of truth for the catalog.
- Soft synergy with **B2** (Request/Response abstraction): once a module's
  controllers are migrated, its API endpoints get cheaper to add and test.

## A3 — SSO validation + public demo instance

SSO OIDC v1 shipped in 2.1.0 but has never been smoke-tested against a real
IdP. The checklist lives in [security.md §6](contracts/security.md).

- Run the Keycloak/Authentik smoke test: happy path, SSO-only mode +
  break-glass `/login?local=1`, JIT on/off, disabled user, realm key rotation,
  interstitial behavior.
- Stand up the **public demo VPS**: `quickstart.sh --demo` plus a scheduled
  hourly DB reset. It is the missing asset for the Show HN launch and the
  natural place to run the smoke test.
- Afterwards, on the same `ExternalIdentityService` seam: LDAP (v2), role
  mapping from claims, RP-initiated logout, unlink UI.

## A4 — Full-text search

Global search fans out to 12 `*SearchProvider` classes that all use
leading-wildcard `LIKE '%term%'`: no index use, no relevance ranking, degrades
on large datasets. FEATURES.md currently oversells this as "full-text".

- Migrate providers to MariaDB `FULLTEXT` indexes + `MATCH … AGAINST` ranking.
  Bounded, provider-by-provider work; mind the SQLite test dialect rules in
  [gotchas.md](contracts/gotchas.md).
- Until then, tone down the FEATURES.md wording (S, can land immediately).

## A5 — Import/export round-out

Contacts imports CSV + vCard; Calendar imports/exports ICS; ~10 modules have
export providers. Small missing pieces:

- Tasks CSV import (S).
- Contacts vCard **export** (S) — import exists, the reverse doesn't.

## A6 — Launch calendar (external dates)

- **From 2026-11-04**: submit the awesome-selfhosted entry (their 4-month
  first-release age rule; the entry is already prepared in the local launch
  kit).
- **After A3's demo instance**: Show HN.
- Keep visible commit activity on the public repo in the meantime.

## Exploratory — long horizon, no commitment

- **Inbound email** (email-to-task / shared mailbox): a defining groupware
  capability, but L+ — there is no IMAP story today, it starts from zero.
- **Pluggable file storage** (S3/object storage adapter) and a **shared cache
  layer** (PSR-16 → Redis/APCu): only matter for multi-node deployments;
  today storage is local-disk and caches are file-based and ad hoc.
- **CalDAV/CardDAV sync**: high demand, heavy protocol surface.

## Already solid — do not reinvest

The gap analysis confirmed these as strengths; they need maintenance, not
roadmap slots: 2FA/TOTP (backup codes, admin-forced MFA), password policy,
active-session list + revoke, login rate limiting + incident tracking, the
scheduler (full admin UI, run history), PWA/Web Push.

---

# Track B — Structural debt

Post-audit roadmap for the **structural** work that remains after the P0/P1/P2
remediation landed (security hardening, MariaDB test coverage, ops robustness —
merged in #17).

**Nothing here is urgent or blocking.** A fresh-eyes audit found no
critical/high vulnerabilities; the codebase is disciplined and well-secured.
What follows is *maintainability* debt. It should be paid down opportunistically
— not as a stop-the-world project — with the one exception called out as the
keystone below.

| # | Phase | Effort | Risk | Value | Depends on |
|---|-------|:------:|:----:|:-----:|------------|
| B0 | Cleanup tail | S | low | low–med | — |
| B1 | Router static-route index | M | low | med | — |
| B2 | **Request / Response abstraction** ★ | L | high | high | — (unblocks B3 + tests) |
| B3 | Layering discipline | M (ongoing) | med | med | eased by B2 |
| B4 | Split the Progetti god-module | L | med | med | — |
| B5 | PHPStan generics backfill | L (diffuse) | low | low | — |

**Recommended sequence:** B0 and B1 first (fast, isolated, near-zero risk) →
**B2 is the keystone** → B3 incrementally in parallel → B4 whenever convenient →
B5 always in the background.

## B0 — Cleanup tail

Small, independent quick wins deferred from the P1/P2 cycle.

- **Timestamp convention** (P1.4): Documenti uses `datetime` for
  `created_at/updated_at/deleted_at` while the core uses `timestamp` (TZ + 2038
  differences). Align to `timestamp` via a dedicated `ALTER` migration, updating
  `database/schema.sql` in parallel. Test on MariaDB against existing data.
  *(Calendar, originally listed here too, is already on `timestamp` for its
  audit columns — only the semantic event columns are `datetime`, correctly.)*
- **i18n exception messages** (P2.5): hardcoded Italian in service-layer
  exception messages (e.g. `NotificationDispatcherService`) bypasses `t()`.
  Route through `t()` with dedicated keys; `lang:check` guards completeness.
- **Dead code**: remove the 8 `deadCode.unreachable` statements flagged in the
  PHPStan baseline (`HelpOnlineService`, `Setup/SetupController`).
- *Optional:* add `down`/rollback support and transactional-DDL guards to
  `database/migrate.php` (forward-only today).

## B1 — Router static-route index

`app/Core/Router.php` does an O(n) linear `preg_match` scan of every route until
first match, is registration-order-dependent (each module's `routes.php`
hand-orders static-before-parametric), and scans the whole table a second time
to build the `Allow` header on a 405.

- Add a **static-route hash** fast-path and **per-method bucketing**.
- Removes the order-dependence and the double-scan.
- Self-contained to `app/Core`; guarded by the existing `RouteAuthzAuditTest` /
  `RoutePermissionParityTest`. A good warm-up for touching the core.

## B2 — Request / Response abstraction ★ keystone

The single highest-leverage refactor. There is **no `Request`/`Response` class**;
controllers read `$_POST/$_GET/$_SERVER/$_SESSION/$_FILES` directly (~760
superglobal reads across 63 controllers) and emit output via bare
`echo`/`header()`/`exit`. This is the root cause of the `FAVILLA_TESTING` /
`Testing\HaltResponse` test hack and of scattered, un-centralizable input
handling.

**De-risking approach** (large blast radius — do it incrementally):

1. Introduce Request/Response objects **as an adapter over the superglobals**, so
   old and new styles coexist during the migration.
2. Migrate controllers **module by module**, behind the existing controller test
   harness (`ControllerTestCase`).
3. Delete the `FAVILLA_TESTING`/`HaltResponse` hack **last**, once the final
   controller is migrated.

Unblocks clean controller testing, makes B3 substantially easier, and lowers the
cost of every new API endpoint (A2).

## B3 — Layering discipline

The `Controller → Service → Repository` rule holds at the controller boundary but
frays underneath. Best done incrementally: whenever you touch a module, pull its
data access back behind a repository.

- Raw SQL in the base `Controller` (`getUserPreferences`, a per-render hot path).
- ~197 raw PDO calls across ~45 service files (93 across 29 files under
  `app/Modules/*/Services`, 104 across 16 under `app/Services`) → move behind
  repositories.
- View → repository violations (e.g. `Teams/Views/partials/chat_panel.php`).

## B4 — Split the Progetti god-module

`ProgettiService` carries ~8 responsibilities in one class (projects, milestones,
tasks, dependencies, members, timesheets, files, reporting). Current sizes:
`ProgettiService` ~1630 lines, `ProgettiRepository` ~1453, `ProgettiController`
~1118.

- Split the service into per-aggregate services (Project / Task / Milestone /
  Member / Timesheet / ProjectReport); mirror for repository/controller.
- Fully isolated to the module — parallelizable at any time.

## B5 — PHPStan generics backfill

`phpstan.neon` runs level 6 but blanket-ignores `missingType.iterableValue`
(~1300 missing `array<…>` generics). Param/return/property typing is otherwise
clean.

- Backfill generics opportunistically; drop the `ignoreErrors` entry per
  package/directory as it reaches zero.
- **Never a blocking milestone** — low-value grind, background chore only.

## Deliberately excluded

Informational audit observations not worth a phase — they work, they are
consistent, and rewriting them would add near-zero value:

- **Events system** carries only 2 listeners — fine as user-lifecycle plumbing.
- **Controllers use a service-locator** (`app(Service::class)`) rather than
  constructor DI — consistent and intentional.
- **No pluggable global-middleware slot** — locale/security-headers are bolted on
  in two places, but the bolt-ons work.
