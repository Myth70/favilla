# Editions — one codebase, three profiles

> On-demand contract. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Tags: `MUST` non-negotiable · `SHOULD` default unless justified · `NOTE` practical reference.

## 1. Concept

- `MUST`: Favilla ships as **Developer / Personal / Team** from a single codebase. An edition changes what the UI shows — **never** what the code can do. Hidden ≠ disabled: core modules and the Scheduler run in every edition regardless of the chosen profile.
- `NOTE`: **Personal** — single-user workspace: registration off, multi-user surfaces (roles, sharing, admin area) tucked away. **Team** — multi-user company intranet: RBAC visible, open registration with admin approval, Progetti/Teams/Documenti/Blog enabled by default. **Developer** — full repository for contributing to Favilla itself: no restrictions, includes the dev/AI-assistant docs (`CLAUDE.md`, `docs/contracts/`, `context/`).

## 2. Config source of truth

`app/Config/editions.php` — off-limits framework surface (part of `app/Config/`, touch only for core/system config work):

```php
return [
    'default' => 'developer',
    'profiles' => [
        'developer' => ['label' => 'Developer', 'single_user' => false, 'sidebar_hidden_modules' => []],
        'personal'  => ['label' => 'Personal', 'single_user' => true, 'sidebar_hidden_modules' => ['Admin', 'Scheduler', 'Feedback']],
        'team'      => ['label' => 'Team', 'single_user' => false, 'sidebar_hidden_modules' => [], 'default_enabled_modules' => ['Progetti', 'Teams', 'Documenti', 'Blog']],
    ],
];
```

- `MUST`: never rename the `default` key — `tools/build-editions.php` locates and rewrites its value with a regex match on the literal `'default' => 'developer'`.
- `NOTE`: `single_user` and `sidebar_hidden_modules` / `default_enabled_modules` are read through the shared helpers below, not re-checked ad hoc per module.

## 3. Runtime resolution

Helpers in `app/Helpers/functions.php` (off-limits — consume them, don't add new ones there):

| Helper | Resolves |
|---|---|
| `edition()` | `APP_EDITION` env → `setting('app_edition')` (DB, `app_settings`) → `config('editions.default')`; falls back to `developer` if the resolved value isn't a known profile key |
| `edition_profile()` | full profile array (`label`/`single_user`/`sidebar_hidden_modules`/…) for the current edition |
| `is_single_user()` | shortcut for `edition_profile()['single_user'] ?? false` |

## 4. Where it's consumed

- **Sidebar** — `NavigationRegistry` hides entries listed in `sidebar_hidden_modules`; the module itself keeps running (routes, permissions, scheduled jobs stay active), it's simply not linked from the sidebar.
- **Registration** — `Auth\RegistrazioneController` redirects to login whenever `is_single_user()` is true (Personal ships as a single account).
- **Views** — several views (Admin → Impostazioni, login, the shared header partial, a handful of module views) branch on `edition()` / `is_single_user()` to show or hide multi-user chrome.
- `SHOULD`: gate new edition-specific UI/behavior through `edition()` / `edition_profile()` / `is_single_user()`, not ad hoc `env('APP_EDITION')` reads.

## 5. Choosing / changing the edition

- **Setup wizard** — step 4 (`app/Setup/steps/04_edizione.php`) lets the operator pick a profile; the options shown there are a **hardcoded list**, not generated from `config('editions.profiles')` (see §7 if adding a profile). `SetupController` (case 4) validates the POSTed value against `config('editions.profiles')` keys, falling back to the configured default. `SetupController::runSetupComplete()` then writes the choice into the `app_settings.app_edition` row (pre-seeded by `database/seeds/required.sql`, updated here with the real choice) and, if the profile declares `default_enabled_modules` (Team), auto-enables those `module_states` rows. Personal/Developer leave those modules disabled — installable later from Admin → Moduli (the upgrade path to Team-like functionality without reinstalling).
- **After install** — changing edition is just editing the `app_edition` setting from Admin → Impostazioni (rendered as a dedicated selector for that one key in `Admin/Views/settings/index.php`) — there is no separate controller action.

## 6. Release packaging (`tools/build-editions.php`)

`php tools/build-editions.php <version> [--out=dist]` builds the 3 release zips from whatever is currently checked out — HEAD for a local dry run, the tagged ref in CI. It never switches branches/tags itself.

| Zip | Built via | Contents |
|---|---|---|
| `favilla-<version>-developer.zip` | `git ls-files` (does **not** apply `.gitattributes` `export-ignore`) | full tracked tree, including dev-only paths (`CLAUDE.md`, `docs/contracts/`, `context/`, `app/Modules/_Template`, `tools/`, `tests/`); **no** `vendor/` — contributors run `composer install` themselves |
| `favilla-<version>-personal.zip` | `git archive` (`export-ignore` applied → dev-only paths above are absent) | `app/Config/editions.php`'s `'default' => 'developer'` rewritten to `'personal'` **inside the zip only** (the checked-out file on disk is never touched) + the locally-installed `vendor/` bundled in, so the zip is unzip-and-go — no Composer needed on the target server |
| `favilla-<version>-team.zip` | same mechanism as Personal | rewritten to `'team'` |

- `MUST`: before building the Personal/Team zips, `vendor/` must be the result of `composer install --no-dev --optimize-autoloader` — the script aborts if `vendor/phpunit` is present, to avoid shipping dev tooling to end users (`--allow-dev-vendor` overrides this for local dry runs only).
- `NOTE`: wired into [`.github/workflows/release.yml`](../../.github/workflows/release.yml) — pushing a `v*` tag installs production dependencies, runs the build script, and uploads the 3 zips as GitHub release assets with auto-generated release notes. `ci.yml` already gates every commit (tests/PHPStan/code style/`lang:check`) before it can be tagged; this job only packages, it does not re-test.

## 7. Adding a new profile

1. Add the profile to `app/Config/editions.php` → `profiles` (`label`, `single_user`, `sidebar_hidden_modules`, optionally `default_enabled_modules`).
2. Add a matching entry to the hardcoded `$options` array in `app/Setup/steps/04_edizione.php` — the wizard step does not derive its choices from the config file.
3. If the new profile should also ship as a release zip, extend the `['personal', 'team']` loop in `tools/build-editions.php` (the Developer zip logic is generic and needs no change).
