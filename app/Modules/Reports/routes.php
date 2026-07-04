<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Reports\Controllers\DocumentController;
use App\Modules\Reports\Controllers\ExportController;
use App\Modules\Reports\Controllers\HistoryController;
use App\Modules\Reports\Controllers\ReportsController;
use App\Modules\Reports\Controllers\StyleController;
use App\Modules\Reports\Controllers\TemplateController;

// ── Dashboard + API (/reports) ─────────────────────────────────────────────
$router->group([
    'prefix'     => 'reports',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('reports.view'),
    ],
], function ($r) {
    $r->get('/', [ReportsController::class, 'index'])->name('reports.index');
    $r->get('/sources', [ReportsController::class, 'sources'])->name('reports.sources');
    $r->get('/source-fields', [ReportsController::class, 'sourceFields'])->name('reports.source_fields');
});

// ── Export (/reports/export) ───────────────────────────────────────────────
$router->group([
    'prefix'     => 'reports/export',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('reports.export'),
        RateLimitMiddleware::perMinute(10),
    ],
], function ($r) {
    $r->get('/quick', [ExportController::class, 'quickExport'])->name('reports.export.quick');
    $r->get('/{id}', [ExportController::class, 'generate'])->name('reports.export.generate');
});

// ── Templates (/reports/templates) ─────────────────────────────────────────
$router->group([
    'prefix'     => 'reports/templates',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
    ],
], function ($r) {
    // Create (reports.create) — BEFORE read-only group to prevent /create matching /{id}
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.create')],
    ], function ($r) {
        $r->get('/new', [TemplateController::class, 'wizard'])->name('reports.templates.new');
        $r->get('/create', [TemplateController::class, 'create'])->name('reports.templates.create');
        $r->post('/', [TemplateController::class, 'store'])->name('reports.templates.store');
        $r->post('/{id}/duplicate', [TemplateController::class, 'duplicate'])->name('reports.templates.duplicate');
    });

    // Read-only (reports.view)
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.view')],
    ], function ($r) {
        $r->get('/', [TemplateController::class, 'index'])->name('reports.templates.index');
        $r->get('/bundled', [TemplateController::class, 'bundled'])->name('reports.templates.bundled');
        $r->get('/{id}/preview', [TemplateController::class, 'preview'])->name('reports.templates.preview');
        $r->get('/{id}', [TemplateController::class, 'edit'])->name('reports.templates.edit');
    });

    // Admin: import bundled templates (reports.admin)
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.admin')],
    ], function ($r) {
        $r->post('/bundled/import', [TemplateController::class, 'importBundled'])->name('reports.templates.import_bundled');
    });

    // Edit (reports.edit)
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.edit')],
    ], function ($r) {
        $r->put('/{id}', [TemplateController::class, 'update'])->name('reports.templates.update');
    });

    // Delete (reports.delete)
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.delete')],
    ], function ($r) {
        $r->delete('/{id}', [TemplateController::class, 'destroy'])->name('reports.templates.destroy');
    });
});

// ── Styles (/reports/styles) ───────────────────────────────────────────────
$router->group([
    'prefix'     => 'reports/styles',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('reports.styles'),
    ],
], function ($r) {
    // Static routes BEFORE parametric
    $r->get('/create', [StyleController::class, 'create'])->name('reports.styles.create');
    $r->post('/', [StyleController::class, 'store'])->name('reports.styles.store');

    // Parametric routes
    $r->get('/{id}', [StyleController::class, 'edit'])->name('reports.styles.edit');
    // Anteprima loghi (uploads/reports non servita da Apache: contiene anche i PDF generati)
    $r->get('/{id}/logo/{slot}', [StyleController::class, 'logo'])->name('reports.styles.logo');
    $r->put('/{id}', [StyleController::class, 'update'])->name('reports.styles.update');
    $r->delete('/{id}', [StyleController::class, 'destroy'])->name('reports.styles.destroy');
});

// ── History (/reports/history) ─────────────────────────────────────────────
$router->group([
    'prefix'     => 'reports/history',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('reports.view'),
    ],
], function ($r) {
    // Static routes BEFORE parametric
    $r->get('/', [HistoryController::class, 'index'])->name('reports.history.index');
    $r->post('/cleanup', [HistoryController::class, 'cleanup'])->name('reports.history.cleanup');

    // Parametric routes
    $r->get('/{id}/download', [HistoryController::class, 'download'])->name('reports.history.download');
    $r->delete('/{id}', [HistoryController::class, 'destroy'])->name('reports.history.destroy');
});

// ── Document Templates (/reports/documents) ────────────────────────────────
$router->group([
    'prefix'     => 'reports/documents',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
    ],
], function ($r) {
    // View (reports.view — any user with reports access can generate)
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.view')],
    ], function ($r) {
        $r->get('/generate/{module}/{operation}/{recordId}', [DocumentController::class, 'generate'])->name('reports.documents.generate');
    });

    // Manage bindings (reports.documents) — CRUD inline from template edit page
    $r->group([
        'middleware' => [RoleMiddleware::withPermission('reports.documents')],
    ], function ($r) {
        $r->post('/', [DocumentController::class, 'storeBind'])->name('reports.documents.store');
        $r->get('/{id}/edit', [DocumentController::class, 'edit'])->name('reports.documents.edit');
        $r->put('/{id}', [DocumentController::class, 'update'])->name('reports.documents.update');
        $r->delete('/{id}', [DocumentController::class, 'destroyBind'])->name('reports.documents.destroy');
    });
});
