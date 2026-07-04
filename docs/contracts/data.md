# Data — schema, migrations, Repository contract

> On-demand contract. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Tags: `MUST` non-negotiable · `SHOULD` default unless justified · `NOTE` practical reference.

## 1. Source of truth
- `MUST`: the DB source of truth is `database/schema.sql`; required seeds live in `database/seeds/required.sql`.
- `MUST`: archived historical migrations are immutable.

## 2. Migrations
- `MUST`: new core migrations in `database/migrations/NNN_*.sql`; new module migrations in `app/Modules/<Module>/migrations/NNN_*.sql` (or `001_*.sql` for the first release).
- `MUST`: migrations are idempotent — use `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE INTO permissions`.
- `NOTE`: run with `php database/migrate.php` (`--status`, `--module=Module`).

## 3. SQL conventions
| Aspect | Contract |
|---|---|
| charset/collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| timestamps | `created_at`, `updated_at` with default + `ON UPDATE` |
| soft delete | `deleted_at TIMESTAMP NULL DEFAULT NULL` when used |
| ownership | `created_by INT UNSIGNED NULL REFERENCES users(id) ON DELETE SET NULL` |
| status | ENUM with a sensible default |
| table name | snake_case plural |

## 4. Repository contract
- `MUST`: every module Repository extends `BaseRepository` and sets `protected string $table = 'table_name'`.
- `MUST`: prepared statements with `?` placeholders; never interpolate user input.

Opt-in properties:
| Property | Meaning |
|---|---|
| `$fillable` | whitelist of columns allowed in create/update |
| `$guarded` | alternative blacklist |
| `$timestamps` | auto `created_at` / `updated_at` |
| `$auditable` / `$auditEntity` | auto audit log / custom audit entity name |
| `$softDelete` | soft delete via `deleted_at` |

- `MUST`: use at least `$fillable` (or an equivalent whitelisting strategy).
- `SHOULD`: enable `$timestamps` + `$auditable` on normal application modules; `$softDelete` when the entity needs history/trash.

Lifecycle hooks: `beforeCreate` / `afterCreate` / `beforeUpdate` / `afterUpdate` / `beforeDelete` / `afterDelete`.
Base methods (do not rewrite without need): `find` · `all` · `where` · `findBy` · `create` · `update` · `delete` · `count` · `transaction`.

## 5. Dynamic queries & safety
- `MUST`: any user-derived sort goes through a whitelist (`in_array(..., true)`).
- `SHOULD`: pagination in a custom repo method (e.g. `listPaginated()`); custom queries (`search()`, `findWithAuthor()`, `countByStatus()`) live in the Repository, not the Controller.
- → SQL-injection invariants: [`security.md`](security.md).

## 6. Independent-database modules
- `NOTE`: some modules use their own database (e.g. `Documenti` → `favilla_documenti`). Their `.sql` runs against the module DB; connect through `App\Services\ModulePdoFactory::get('PREFIX')` (reads `PREFIX_DB_*` from `.env`).
- `MUST`: never cross-DB JOIN to `users` (it does not exist in the module DB) — enrich names via `app(\PDO::class)` on the main DB. → [`gotchas.md`](gotchas.md).
- `NOTE`: permissions, roles, sessions and the audit log always live in the **main** DB. Declare module permissions in `permissions.php` as usual regardless of where the module's data lives.
