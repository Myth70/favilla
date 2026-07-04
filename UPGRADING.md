# Upgrading Favilla

How to move an existing installation to a newer release. Favilla is pre-1.0:
**minor versions may include breaking changes**, so always read the
[CHANGELOG](CHANGELOG.md) entry for the target version before you start.

Database migrations are tracked in a `migrations` table and are **idempotent**:
running `php database/migrate.php` on an existing database applies only what is
pending and never touches your data. It is always safe to re-run.

## Before every upgrade

1. **Back up the database** — from the app (Backup module: encrypted, restorable
   in-app) or with `mysqldump`.
2. **Copy your state** somewhere safe. Everything user-specific lives in three
   places, none of which ship inside release packages:
   - `.env` — credentials and encryption keys. Losing `APP_KEY` /
     `BACKUP_ENCRYPTION_KEY` means losing encrypted fields and backups.
   - `storage/` — sessions, logs, backups, reports, and the
     `.setup_complete` marker.
   - `public/uploads/` — user files.

## Zip install (Apache / XAMPP)

Applies to the Personal and Team zips, which bundle `vendor/` — no Composer
needed on the server. (The Developer zip does not: run `composer install`
after extracting it.)

1. Download the new zip from the
   [Releases page](https://github.com/Myth70/favilla/releases).
2. Extract it **over the installation folder**, letting it overwrite code
   files. Your `.env`, `storage/` and `public/uploads/` are not part of the
   zip, so they survive — but you did step "Before every upgrade" anyway.

   *Cleaner alternative:* extract to a fresh folder, copy `.env`, `storage/`
   and `public/uploads/` in from the old install, then swap the folders. This
   avoids stale files left behind by files removed between releases.
3. Run the pending migrations (PHP CLI):

   ```bash
   php database/migrate.php          # Windows/XAMPP: C:\xampp\php\php.exe database/migrate.php
   ```

4. Log out and back in — sessions cache permissions, and new releases may add
   some.

## Git checkout

```bash
git pull
composer install
php database/migrate.php
```

## Docker Compose

```bash
docker compose pull
docker compose up -d
docker compose exec app php database/migrate.php
```

To pin a specific version instead of `latest`, set `FAVILLA_TAG` in your `.env`
(e.g. `FAVILLA_TAG=2.0.3`) before pulling.

Alternatively, set `AUTO_MIGRATE=true` permanently in `.env`: on every boot the
entrypoint applies pending migrations to a populated database (and performs the
full schema install only on an empty one), so the `exec` step above becomes
unnecessary. The scheduler container needs no attention — its loop picks up the
new code on the next run.

## After the upgrade

- Visiting `setup.php` returns **403 "Setup già completato"** — that is
  expected; the wizard only runs on fresh installs.
- `php database/migrate.php --status` shows what has been applied;
  `--dry-run` previews what a run would do.
- Log out/log in once so the session picks up any new permissions.

## Downgrading

Not supported. To go back, restore the database backup taken before the
upgrade and redeploy the previous release's code.
