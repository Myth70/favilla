# Contributing to Favilla

Thanks for your interest in improving Favilla! This guide covers how to set up a
dev environment, the conventions the codebase enforces, and how to get a change
merged.

> The code and these docs are in **English**. The end-user interface ships in
> five languages with **Italian as the canonical source**: new user-facing
> strings go through `t()` in Italian first, then get their `en`/`fr`/`de`/`es`
> translations (`php favilla lang:check` must stay green).

## Getting started

```bash
git clone https://github.com/Myth70/favilla.git favilla
cd favilla
composer install
cp .env.example .env          # set APP_KEY, BACKUP_ENCRYPTION_KEY, DB_*
```

Generate the secrets:

```bash
php -r "echo bin2hex(random_bytes(32));"   # APP_KEY
php -r "echo bin2hex(random_bytes(32));"   # BACKUP_ENCRYPTION_KEY
```

Point your web server at `public/`; the first visit runs the setup wizard.

## Development commands

```bash
composer test            # PHPUnit (in-memory SQLite, no external DB)
composer test:core       # only tests/Unit
composer test:modules    # only app/Modules/**/Tests
composer stan            # PHPStan (level 6 + baseline)
composer cs              # php-cs-fixer dry-run (PSR-12)
composer cs:fix          # apply style fixes
composer security        # composer audit

# Real-MariaDB integration suite (opt-in)
RUN_DB_INTEGRATION=1 DB_HOST=127.0.0.1 DB_USER=root DB_PASS= \
  vendor/bin/phpunit --testsuite Integration
```

After changing routes, permissions, module metadata or schema:
`php favilla context:generate`. After permission changes, log out/in.

## Architecture & conventions (non-negotiable)

These are enforced in review — see [`CLAUDE.md`](CLAUDE.md) and
[`docs/contracts/`](docs/contracts/) for the full contracts.

- **Layering:** `Controller → Service → Repository → View`. Never call a
  Repository from a Controller. Resolve dependencies with `app(ClassName::class)`.
- **Security:** `csrf_field()` in every mutating form; `e()` on all untrusted
  output; prepared statements only; whitelist any user-driven `ORDER BY`. See
  [`SECURITY.md`](SECURITY.md) and [`docs/contracts/security.md`](docs/contracts/security.md).
- **Routing:** static routes before parametric; a separate permission per action.
- **New modules** go in the `sidebar` navigation; start from
  [`docs/contracts/building-a-module.md`](docs/contracts/building-a-module.md) or
  `php favilla make:module ModuleName`.
- **Code style:** PSR-12, `declare(strict_types=1)` on new PHP files.

## Submitting a change

1. Branch off `main`.
2. Keep the change focused; add/adjust tests for new behavior.
3. Before opening a PR, make sure these pass — CI runs all of them and they
   are blocking: `composer test`, `composer stan`, `composer cs` and
   `php favilla lang:check`.
4. Open a PR using the template; describe the change and how you tested it.

## Reporting security issues

**Do not** open a public issue for vulnerabilities — see
[`SECURITY.md`](SECURITY.md) for private reporting.
