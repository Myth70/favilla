# Building a module — workflow & reference skeletons

> On-demand guide. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Contracts referenced here: [`architecture`](architecture.md) · [`security`](security.md) · [`data`](data.md) · [`ui`](ui.md) · [`integrations`](integrations.md) · [`gotchas`](gotchas.md).
> Do not copy long examples that drift — copy a real module (`Contacts`, `Tasks`) and the stubs in `app/Modules/_Template/stubs/`, or run `php favilla make:module`. Below is **one minimal reference per layer**.

## Checklist (minimum CRUD)
1. Scaffold: `php favilla make:module Clienti` (or copy `_Template/`).
2. Module migration `migrations/001_clienti.sql` — idempotent (`CREATE TABLE IF NOT EXISTS`, permissions via `INSERT IGNORE`).
3. `permissions.php` — 4 base permissions (`view/create/edit/delete`).
4. Repository — `$table`, `$fillable`, whitelisted sort, safe queries.
5. Service — all business logic; talks to the Repository.
6. Controller — talks only to the Service.
7. `routes.php` — Auth/Csrf group, per-action permission, **static routes before parametric**.
8. Views — `index.php`, `form.php`, `show.php`, partials.
9. **i18n** — `make:module` scaffolds `resources/lang/{it,en,fr,de,es}/<module>.php` (Italian canonical + identical copies to translate). Wrap every user-facing string in `e(t('<module>.key'))`; build form-error messages from the global `validation` namespace (`t('validation.required', ['field' => t('<module>.fields.x')])`). **Never hardcode copy.** `module.json` and DB permission names stay Italian (overlays translate at render).
10. `module.json` — `tables`, `navigation` (new modules expose their main entry in `sidebar`; do not use `user_menu` or `radial`), providers, metadata. Optional `scheduled_jobs[]` to declare periodic jobs (see §9 in `integrations.md`): `{slug, name, command, interval_minutes, enabled_by_default}`. Commands of enabled modules are auto-merged into the Scheduler whitelist and seeded (disabled) on module enable — no edit to `app/Config/scheduler.php`.
11. Assets only if needed.

Then: `php database/migrate.php --module=Clienti` → translate `resources/lang/{en,fr,de,es}/clienti.php` then `php favilla lang:check` → `php favilla context:generate` → logout/login → verify in light + dark.

Action patterns: `index / show / create / store / edit / update / destroy / search`. Step details live in the contract docs linked above.

## Repository
```php
namespace App\Modules\Clienti\Repositories;

use App\Repositories\BaseRepository;

class ClientiRepository extends BaseRepository
{
    protected string $table = 'clienti';
    protected array $fillable = ['nome', 'email', 'status', 'created_by'];
    protected bool $timestamps = true;
    protected bool $auditable  = true;
    protected bool $softDelete = true;

    private const SORTS = ['nome', 'created_at']; // whitelist — never trust user input

    public function listPaginated(array $f, int $page = 1, int $perPage = 20): array
    {
        $sort = in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : 'created_at';
        // prepared statement: WHERE ... ? ..., ORDER BY {$sort}, LIMIT/OFFSET
    }
}
```

## Service
```php
namespace App\Modules\Clienti\Services;

use App\Modules\Clienti\Repositories\ClientiRepository;

class ClientiService
{
    public function __construct(private ClientiRepository $repo) {}

    public function create(array $data): int
    {
        $data['created_by'] = auth()['id'] ?? null;
        // domain rules here; throw a typed exception on failure
        return $this->repo->create($data);
    }
}
```

## Controller
```php
namespace App\Modules\Clienti\Controllers;

use App\Core\Controller;
use App\Modules\Clienti\Services\ClientiService;

class ClientiController extends Controller
{
    private ClientiService $service;

    public function __construct()
    {
        $this->service = app(ClientiService::class);
    }

    public function index()
    {
        $filters = $this->cleanGet(['q', 'sort']);
        return $this->htmxOrRender(
            'Clienti/Views/index',
            'Clienti/Views/partials/table',
            [
                'items'       => $this->service->list($filters),
                'pageTitle'   => t('clienti.title'),
                'breadcrumbs' => [['label' => t('clienti.title'), 'route' => 'clienti.index']],
            ]
        );
    }

    public function store()
    {
        $data = $this->cleanPost(['nome', 'email', 'status']);
        // validate; on error: $this->flashErrors($errors, $data); return $this->redirect(route('clienti.create'));
        $this->service->create($data);
        flash_success(t('clienti.flash.created'));
        return $this->redirect(route('clienti.index'));
    }
}
```

## Routes (`routes.php`)
```php
$r->group(['prefix' => 'clienti', 'middleware' => [AuthMiddleware::class, CsrfMiddleware::class]], function ($r) {
    $r->group(['middleware' => [RoleMiddleware::withPermission('clienti.create')]], function ($r) {
        $r->get('/create', [ClientiController::class, 'create'])->name('clienti.create'); // static BEFORE /{id}
        $r->post('/',      [ClientiController::class, 'store'])->name('clienti.store');
    });
    $r->group(['middleware' => [RoleMiddleware::withPermission('clienti.view')]], function ($r) {
        $r->get('/',     [ClientiController::class, 'index'])->name('clienti.index');
        $r->get('/{id}', [ClientiController::class, 'show'])->name('clienti.show');
    });
});
```

## View (`Views/index.php`)
```php
<?php $view->layout('main'); ?>
<?php $view->start('content'); ?>
  <!-- shared hero partial (pf-hero-user); see ui.md -->
  <!-- filters: hx-get=route('clienti.index'), hx-target="#list", hx-include other filters -->
  <div id="list">
    <?= $view->include('Clienti/Views/partials/table', ['items' => $items]) ?>
  </div>
<?php $view->end(); ?>
```
Inside a mutating `form.php`: `<?= csrf_field() ?>`, `_method=PUT` for update, `.is-invalid` + `.invalid-feedback` reading `['field' => ['msg']]`, repopulate with `$old['x'] ?? $item['x'] ?? ''`. Escape every output with `e()` and wrap user-facing copy in `t()` — i.e. `e(t('clienti.fields.nome'))`.

## Tests (portable, in `Tests/Unit/`)
- `Tests\ModuleTestCase` for DB tests (SQLite in-memory); plain `PHPUnit\Framework\TestCase` for pure logic. Copy `_Template/Tests/Unit/Example*Test.php`. Type mapping and quirks → [`gotchas.md`](gotchas.md) §3.
