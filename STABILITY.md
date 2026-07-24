# Stability Policy

**As of v2.4.0, Favilla is feature-complete and frozen.**

This is not an abandonment notice. It is the opposite: a commitment that the
software you install today will behave the same way in a year, and that the
maintenance burden stays small enough for one person to carry indefinitely.

Most self-hosted projects fail not because the code is bad but because the
surface keeps growing until nobody can maintain it. Favilla stops here, on
purpose, while it is still fully working and fully tested.

---

## What is frozen

| Surface | Size | Meaning |
| --- | --- | --- |
| Database schema | 105 tables (`database/schema.sql`) | No new tables. Enforced by `tests/Unit/SchemaFreezeTest.php`. |
| Modules | 20 | No new modules. The list below is final. |
| Routes | 561 | No new endpoints. |
| Permissions | 97 | The catalog is final. |
| Public API | v1, 12 endpoints (`docs/api/openapi.json`) | The contract will not change shape. |

The frozen module list: Admin, Api, Auth, Backup, Blog, Calendar, Contacts,
Documenti, Feedback, Files, HealthCheck, HelpOnline, Home, Notifications,
Progetti, Reports, Scheduler, Tasks, Teams, Webhooks.

## What stays open

The project is maintained, not archived. These continue:

- **Security updates and dependency bumps.** Advisories and CVEs are fixed
  promptly, as in the 2.3.1 release. This is the reason the project stays alive.
- **Bug fixes.** Behaviour that does not do what it says it does gets corrected.
  A bug fix does not add a feature, a route, or a column.
- **Translations and copy.** The five locales (it/en/fr/de/es) can be completed
  and corrected via `php favilla lang:check`. This touches no code and no schema.

## What is declined

- New modules, tables, routes, permissions, or API endpoints.
- New features in existing modules.
- Refactors of working code for aesthetic or architectural reasons.
- Performance work on paths that are not measurably slow.

If you need one of these, fork the project. The module system exists precisely so
that a fork can add its own module without touching the framework, and
[`docs/contracts/building-a-module.md`](docs/contracts/building-a-module.md)
documents how.

---

## Accepted debt

Two known imperfections are **closed decisions**, not open to-do items. They are
recorded here so nobody rediscovers them in six months and reopens the question.

**`Progetti` is a large module.** Its Service is ~1600 lines, its Repository
exposes 67 public methods, its Controller has 31 actions. By the layering rules
in [`CLAUDE.md`](CLAUDE.md) it is coarse. It is also correct, covered by tests,
and has no known defects. Splitting it would risk regressions in working code to
satisfy a metric no user experiences. **It stays as it is.**

**The router dispatches by linear scan.** Matching walks 561 compiled patterns
per request instead of using a compiled lookup table, costing single-digit
milliseconds. That cost was worth optimising only if the route count kept
growing — and it no longer does. Freezing the surface turned this from a
trajectory into a constant. **No route cache will be added.**

## What "stable" means for upgrades

The 2.x line follows [Semantic Versioning](https://semver.org/). Within it,
minor and patch releases are backward-compatible: your database, configuration,
and any integration built against API v1 keep working. See
[`UPGRADING.md`](UPGRADING.md) for the upgrade procedure.

Because the schema is frozen, most future releases will require no migration at
all.

## Verifying the freeze

The declaration is enforced, not merely asserted. These run in CI on every push:

- `tests/Unit/SchemaFreezeTest.php` — fails if `database/schema.sql` changes.
- `tests/Unit/RouteAuthzAuditTest.php` — every mutating route carries CSRF and
  authentication.
- `tests/Unit/RoutePermissionParityTest.php` — every permission slug guarding a
  route is declared in some module's `permissions.php`.
- `tests/Unit/OrderByWhitelistTest.php` — user-driven sorting never reaches SQL raw.

```bash
C:\xampp\php\php.exe vendor/bin/phpunit
```
