# Deploying Favilla on Railway

Favilla needs the PHP extensions `gd`, `intl` and `zip` (dompdf, PhpSpreadsheet,
`intl`). Railway's default **Nixpacks** PHP builder does **not** include them, so
`composer install` fails with:

```
phpoffice/phpspreadsheet … requires ext-gd … it is missing from your system.
```

> Do **not** work around this with `--ignore-platform-req=ext-gd`: the build
> would pass but the app would crash at runtime the first time it renders a PDF
> or an Excel file. `gd` must actually be present in the image.

The fix is to build from the project's **`docker/Dockerfile`** (which compiles
`gd`/`intl`/`zip`/`pdo_mysql`/`opcache` and serves the app with Apache), not from
Nixpacks. The committed [`railway.json`](../railway.json) already tells Railway to
do this.

---

## 1. Create the service

Deploy this repository as a Railway service. With `railway.json` present, Railway
picks the **Dockerfile** builder automatically. (If you deploy without the file,
set it manually: **Settings → Build → Builder = Dockerfile**, *Dockerfile Path* =
`docker/Dockerfile`.)

## 2. Add a MySQL database

Add a **MySQL** service (or MariaDB) to the project. Then, on the Favilla service,
set the DB variables the container's entrypoint reads — reference the database
service so they stay in sync:

| Variable  | Value (from the MySQL service)   |
|-----------|----------------------------------|
| `DB_HOST` | `${{MySQL.MYSQLHOST}}`           |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}`           |
| `DB_NAME` | `${{MySQL.MYSQLDATABASE}}`       |
| `DB_USER` | `${{MySQL.MYSQLUSER}}`           |
| `DB_PASS` | `${{MySQL.MYSQLPASSWORD}}`       |

(Adjust the reference names to match your database plugin's exposed variables.)

## 3. Set the application variables

| Variable         | Value                                                        |
|------------------|-------------------------------------------------------------|
| `APP_ENV`        | `production`                                                 |
| `APP_DEBUG`      | `false`                                                      |
| `APP_URL`        | your public URL, e.g. `https://favilla-production.up.railway.app` |
| `APP_KEY`        | a random 32+ character secret (see below)                   |
| `APP_BASE_PATH`  | empty (the app is served at the domain root)                |
| `APP_TIMEZONE`   | e.g. `Europe/Rome`                                           |
| `APP_EDITION`    | `personal`, `team` or `developer` (optional; overrides the default) |
| `TRUSTED_PROXIES`| Railway terminates TLS at its proxy — set this so client IP and HTTPS detection are correct (see note) |
| `AUTO_MIGRATE`   | `true` — on an **empty** DB, load the schema + seeds on first boot; on a populated DB, apply only pending migrations (never destructive). Omit it to use the web installer instead. |
| `DEMO_DATA`      | `true` (optional) — load the "Aurora Studio" demo dataset on first boot of an empty DB |

Generate an `APP_KEY` locally:

```bash
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
```

> **`TRUSTED_PROXIES`**: the app is behind Railway's reverse proxy, so `X-Forwarded-*`
> headers drive HTTPS detection, HSTS and the real client IP used by rate limiting
> and the security log. Set it to the proxy you trust. If you understand the
> trade-off (only Railway can reach the container), `*` works; otherwise pin it to
> Railway's proxy address.

## 4. Port

The image runs Apache on port **80** (`docker/apache-vhost.conf`) and the app does
**not** read `$PORT`. Railway normally detects the `EXPOSE 80` and routes to it; if
the public URL doesn't connect, set the service's **target port** to `80` in
**Settings → Networking**.

## 5. First boot

With `AUTO_MIGRATE=true`, the container waits for MySQL, loads the schema + seeds,
then starts Apache. Open the public URL and complete the setup wizard (or sign in
with the seeded admin — change its password immediately).

---

## Alternative: deploy the prebuilt image (no build)

The project publishes the same image to GHCR. On Railway choose **Deploy from a
Docker image** and use:

```
ghcr.io/myth70/favilla:2.2.0
```

It already contains every extension, so there is no build step — set the same
variables from steps 2–3 and you're done.

## Scheduler (optional)

Recurring jobs (webhook dispatch, reminders, cleanup, backups) run via
`php favilla scheduler:run`. Add a second Railway service from the same image with
the start command `php favilla scheduler:run` (a loop), or a Railway **cron** that
invokes it periodically. Point it at the same database variables.
