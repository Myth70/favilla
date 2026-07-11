# Integrations — reusable providers & shared services

> On-demand contract. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Tags: `MUST` non-negotiable · `SHOULD` default unless justified · `NOTE` practical reference.
> Use existing providers/services instead of parallel infrastructure. The live event catalog, routes and module list are in `project_context.json`, not here.

## 1. Notifications (template-first)
```php
NotificationService::dispatchEventToUser(
    eventSlug:    'module.event',
    sourceModule: 'Module',
    toUserId:     $userId,
    context:      ['order_id' => 123],
    link:         route('module.show', ['id' => $id]),
    fromUserId:   auth()['id'] ?? null
);
// also: dispatchEventToRole(eventSlug, sourceModule, roleSlug, context, link)
```
- `MUST`: for new code use `dispatchEventToUser()` / `dispatchEventToRole()`; the module publishes event + context, never the final hardcoded text (subject/body/visuals come from the dispatcher + template catalog).
- `NOTE`: channels are `in_app`, `email`, `telegram`. Legacy `send()`/`sendToRole()` remain for ad-hoc cases — avoid in new modules. Declare `notification_events` in `module.json` if the module publishes events.

## 2. Dashboard widgets
- `SHOULD`: implement `DashboardWidgetProvider`; declare the provider in `module.json`. The contract is split for parallel loading:
  - `MUST` expose `getWidgets(int $userId): array` — cheap metadata catalog (no heavy queries / HTTP): `id`, `type`, `label`, `icon`, `size`, `permission`, optional `cache_ttl`.
  - `MUST` expose `getWidgetData(int $userId, string $widgetId): ?array` — computes one widget's payload on demand; returns a fragment merged over the metadata (at least `data`, optionally `label`/`icon`), or `null` to hide an empty widget.
- `NOTE`: widget types are `stat`, `chart`, `list`, `html`. The dashboard renders a skeleton from `getWidgets()`, then each widget lazy-loads its body in parallel via `GET /home/widget/{id}` (`hx-trigger="load"`); `DashboardService::renderWidget()` caches the payload (`WidgetDataCache`, TTL via `cache_ttl`). Permission checks are centralized in `DashboardService` (not in providers); `user_widget_preferences` stores order/visibility.

## 3. Global search
- `SHOULD`: implement `SearchableModule`. `MUST` expose `search(string $query, int $userId, int $limit = 5): array`, `getSearchLabel(): string`, `getSearchIcon(): string`.
- `NOTE`: each normalized record uses keys `title`, `subtitle`, `url`, `icon`, `badge`.

## 4. Export (Admin → Export)
- `SHOULD`: implement `ExportableModule`. `MUST` expose `getDataSources()`, `getExportData()`, `getExportModuleName()`, `getExportModuleIcon()`, `getSingleRecord()`.
- `MUST`: do not build ad-hoc CSV export as the primary path if the domain fits central export. Each data source declares `key`, `label`, `icon`, `permission`, `fields[]`.

## 5. Contact source provider
- `SHOULD`: implement `ContactSourceProvider` if the module holds contact-like entities (suppliers, clients, partners) with name + (email | phone | company).
- `MUST`: expose `getContactSources()`, `listContacts(sourceKey, filters, page, perPage)`, `getContact(sourceKey, sourceId)`, `getContactModuleName()`, `getContactModuleIcon()`.
- `NOTE`: discovery via `module.json["contact_source_provider"]` (FQCN) or `Providers/*ContactSourceProvider.php`. Map records onto canonical Contacts fields (`source_id`, `nome` required; others optional). Dispatch runs through `App\Modules\Contacts\Services\ContactSourceService`; the final save goes through `ContactsService::create()`.

## 6. Report templates
- `SHOULD`: bundle preconfigured templates in `report_templates/` (JSON: `name`, `description`, `source_key`, `source_type`, `output_format`, `visibility`, `template_html`, `sorting_config`).

## 7. Calendar
- `MUST`: use `CalendarService` for cross-module events instead of a proprietary events table.
- `NOTE`: API — `createEvent()`, `getUpcomingEvents()`, `countUpcomingEvents()`. `SHOULD` check `isModuleEnabled('Calendar')` in the web context first.

## 8. Avatar cropper
- `MUST`: use the existing endpoint `POST /api/avatar/crop` (`api.avatar.crop`) + the existing frontend. Params: `cropped_image`, `context`, `context_id`.

## 9. Scheduler & CLI commands
- `MUST`: scheduled jobs use the Scheduler module and `php favilla scheduler:run`; CLI commands bootstrap the app via `bootstrap/app.php`.
- `MUST`: in CLI commands do not use `app('db')` (use a Repository/Service) and do not use `isModuleEnabled()` (use `class_exists()` as guard).
- `MUST`: register the command class in `app/Cli/Console.php`.
- `SHOULD`: declare schedulable jobs in `module.json["scheduled_jobs"]` instead of editing `app/Config/scheduler.php`. Each entry: `slug` (unique), `name`, `command` (must exist in Console), `interval_minutes` (default 1440), optional `args` (string[]), `enabled_by_default` (bool, default false). The Scheduler merges commands declared by **enabled** modules into its whitelist at runtime (`SchedulerService::getAllowedCommands()` ∪ module commands), so no core file changes are needed per module.
- `NOTE`: on module enable, declared jobs are seeded into `scheduler_jobs` **disabled** (admin turns them on); on disable they are deactivated, not deleted. Hook: `ModuleManagementService::upsertState()` → `SchedulerService::syncModuleJobs()`. Seeding is idempotent (keyed by `slug`).
- `NOTE`: a module command that resolves its own dedicated DB works as-is — each job runs as a subprocess and resolves its PDO via the Repository/`ModuleDatabaseResolver`.

### Backup of independent-DB modules
- `NOTE`: the Backup module dumps the main DB **plus every `independent` module DB** (`ModuleDatabaseResolver::allActiveIndependent()`), producing a `.zip` set (`backup_*.zip`) with one `*.sql.gz` per database + `manifest.json`. Legacy single-DB `*.sql.gz` backups remain restorable. Restore routes each dump back to the correct DB via the manifest (`module` → `pdoFor()`, else main). A module DB unreachable at backup time yields a `partial` set (warned, not silently green); `BACKUP_FAIL_ON_MISSING_MODULE_DB=true` makes it hard-fail.
- `MUST NOT`: name a DB column `databases` (reserved word; `BaseRepository` does not backtick column names) — see `backup_history.databases_json`.

## 10. `admin_panel` schema (`module.json`)
Auto-discovered admin panel — use this instead of hardcoding links in shared admin surfaces:
`section`, `eyebrow`, `groups[]` with `title`, `description`, `icon`, `module`, `flows`, and `links[]` with `label`, `route`, `permission`, `description`, `icon`, `keywords`.

## 11. Shared services (`app/Services/`)
`NotificationService` (template-first notifications) · `FileUploadService` (uploads) · `EncryptionService` (AES-256-GCM) · `AuditService` (audit log) · `MailService`/`MailerService` (email) · `SettingsService` (DB-backed settings) · `PasswordPolicyService` · `SecurityIncidentService` · `TotpService` (MFA) · `ModulePdoFactory` (independent-DB connections) · `CalendarService` · `CsvExportService` · `AuthService` · `UserService`.

## 12. Public REST API (token auth)
Expose a module through the API as a **thin JSON serializer over its existing Service** — never a parallel data path. Developer guide: [`docs/api/README.md`](../api/README.md); endpoint catalog: [`docs/api/openapi.json`](../api/openapi.json).
- `MUST`: put API controllers in the module under `Controllers/Api/` extending `App\Modules\Api\Http\ApiController`; register them in an `api/v1/…` route group with `middleware: [ApiTokenMiddleware, ApiRateLimitMiddleware]`. **No `CsrfMiddleware`** — the API is stateless Bearer auth, not cookie-based (guarded by `tests/Unit/RouteAuthzAuditTest`).
- `MUST`: gate every action with `$this->requireScope('perm.slug')`; the effective gate is `min(user permissions, token scopes)`. Emit responses only via the envelope helpers (`ok()`, `paginated()`, `fail()`), never a bare `json()` — so success/`{data,meta}` and error/`{error:{code,message}}` stay consistent (unknown-path 404 / 500 are handled centrally in `ErrorHandler`).
- `NOTE`: Personal Access Tokens live in the `Api` module (`personal_access_tokens`, SHA-256 at rest, shown once). Scopes are a subset of the caller's permissions and are **mandatory**. Public API + webhooks are plausibly edition-gated (Team/Dev) — see [`editions.md`](editions.md); Web Push ships in all editions.

## 13. Outgoing webhooks
Favilla notifies external systems (Zapier/n8n/custom) when an event fires. Guide: [`docs/api/README.md#2-outgoing-webhooks`](../api/README.md#2-outgoing-webhooks).
- `MUST`: do **not** build a parallel event feed — webhooks fan out from the **notification event registry**. Any event a module already publishes via `dispatchEventToUser()/dispatchEventToRole()` (§1) can also be delivered as a webhook; the fan-out is best-effort in `NotificationDispatcherService`, guarded by `isModuleEnabled('Webhooks')` and a try/catch (never breaks notification delivery).
- `NOTE`: deliveries are drained by the scheduler job `webhooks:dispatch` with exponential backoff and an **atomic per-row claim** (no double-send). Bodies are signed `HMAC-SHA256` with a **bound timestamp** (`X-Favilla-Signature: t=<unix>,v1=<hex>` over `{ts}.{body}`) for anti-replay. Destinations are **HTTPS-only and anti-SSRF** (reserved-range blocking + resolved-IP pinning, no redirect following). Secrets are shown once and excluded from audit logs (`WebhookEndpointRepository::$auditExclude`).

## 14. Web Push channel
- `NOTE`: `web_push` is the 4th `NotificationChannelDriverInterface` driver, registered in `NotificationQueueProcessorService` alongside `in_app`/`email`/`telegram`. It rides the existing queue, retry, delivery tracking and per-event user preferences — adding push to an event needs **no new wiring**, only the event + the user's channel preference.
- `MUST`: VAPID keys live in `app_settings` (generate from **Admin → Notifications → Web Push**). **Rotating them invalidates all existing subscriptions** (the server clears `push_subscriptions`; clients re-subscribe). Web Push requires HTTPS (or `localhost`); iOS needs the PWA installed to the Home Screen (Safari 16.4+).
