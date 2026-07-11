# Roadmap — structural debt

Post-audit roadmap for the **structural** work that remains after the P0/P1/P2
remediation landed (security hardening, MariaDB test coverage, ops robustness —
merged in #17).

**Nothing here is urgent or blocking.** A fresh-eyes audit found no
critical/high vulnerabilities; the codebase is disciplined and well-secured.
What follows is *maintainability* debt. It should be paid down opportunistically
— not as a stop-the-world project — with the one exception called out as the
keystone below.

Effort: **S** ≈ half a day · **M** ≈ a few days · **L** ≈ 1–2 weeks.

| # | Phase | Effort | Risk | Value | Depends on |
|---|-------|:------:|:----:|:-----:|------------|
| 0 | Cleanup tail | S | low | low–med | — |
| 1 | Router static-route index | M | low | med | — |
| 2 | **Request / Response abstraction** ★ | L | high | high | — (unblocks 3 + tests) |
| 3 | Layering discipline | M (ongoing) | med | med | eased by 2 |
| 4 | Split the Progetti god-module | L | med | med | — |
| 5 | PHPStan generics backfill | L (diffuse) | low | low | — |

**Recommended sequence:** 0 and 1 first (fast, isolated, near-zero risk) →
**2 is the keystone** → 3 incrementally in parallel → 4 whenever convenient →
5 always in the background.

---

## Phase 0 — Cleanup tail

Small, independent quick wins deferred from the P1/P2 cycle.

- **Timestamp convention** (P1.4): Documenti and Calendar use `datetime` for
  `created_at/updated_at/deleted_at` while the core uses `timestamp` (TZ + 2038
  differences). Align to `timestamp` via a dedicated `ALTER` migration, updating
  `database/schema.sql` in parallel. Test on MariaDB against existing data.
- **i18n exception messages** (P2.5): hardcoded Italian in service-layer
  exception messages (e.g. `NotificationDispatcherService`) bypasses `t()`.
  Route through `t()` with dedicated keys; `lang:check` guards completeness.
- **Dead code**: remove the 8 `deadCode.unreachable` statements flagged in the
  PHPStan baseline (`HelpOnlineService`, `Setup/SetupController`).
- *Optional:* add `down`/rollback support and transactional-DDL guards to
  `database/migrate.php` (forward-only today).

## Phase 1 — Router static-route index

`app/Core/Router.php` does an O(n) linear `preg_match` scan of every route until
first match, is registration-order-dependent (each module's `routes.php`
hand-orders static-before-parametric), and scans the whole table a second time
to build the `Allow` header on a 405.

- Add a **static-route hash** fast-path and **per-method bucketing**.
- Removes the order-dependence and the double-scan.
- Self-contained to `app/Core`; guarded by the existing `RouteAuthzAuditTest` /
  `RoutePermissionParityTest`. A good warm-up for touching the core.

## Phase 2 — Request / Response abstraction ★ keystone

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

Unblocks clean controller testing and makes Phase 3 substantially easier.

## Phase 3 — Layering discipline

The `Controller → Service → Repository` rule holds at the controller boundary but
frays underneath. Best done incrementally: whenever you touch a module, pull its
data access back behind a repository.

- Raw SQL in the base `Controller` (`getUserPreferences`, a per-render hot path).
- ~103 raw PDO calls across ~30 service files → move behind repositories.
- View → repository violations (e.g. `Teams/Views/partials/chat_panel.php`).

## Phase 4 — Split the Progetti god-module

`ProgettiService` carries ~8 responsibilities in one class (projects, milestones,
tasks, dependencies, members, timesheets, files, reporting). Current sizes:
`ProgettiService` ~1630 lines, `ProgettiRepository` ~1453, `ProgettiController`
~1118.

- Split the service into per-aggregate services (Project / Task / Milestone /
  Member / Timesheet / ProjectReport); mirror for repository/controller.
- Fully isolated to the module — parallelizable at any time.

## Phase 5 — PHPStan generics backfill

`phpstan.neon` runs level 6 but blanket-ignores `missingType.iterableValue`
(~1300 missing `array<…>` generics). Param/return/property typing is otherwise
clean.

- Backfill generics opportunistically; drop the `ignoreErrors` entry per
  package/directory as it reaches zero.
- **Never a blocking milestone** — low-value grind, background chore only.

---

## Deliberately excluded

Informational audit observations not worth a phase — they work, they are
consistent, and rewriting them would add near-zero value:

- **Events system** carries only 2 listeners — fine as user-lifecycle plumbing.
- **Controllers use a service-locator** (`app(Service::class)`) rather than
  constructor DI — consistent and intentional.
- **No pluggable global-middleware slot** — locale/security-headers are bolted on
  in two places, but the bolt-ons work.
