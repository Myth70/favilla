<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Api\Middleware\ApiRateLimitMiddleware;
use App\Modules\Api\Middleware\ApiTokenMiddleware;
use App\Modules\Documenti\Controllers\Admin\AdminAuditController;
use App\Modules\Documenti\Controllers\Admin\AdminCategorieController;
use App\Modules\Documenti\Controllers\Admin\AdminDashboardController;
use App\Modules\Documenti\Controllers\Admin\AdminDocumentiController;
use App\Modules\Documenti\Controllers\Admin\AdminHealthController;
use App\Modules\Documenti\Controllers\Admin\AdminMimeController;
use App\Modules\Documenti\Controllers\Admin\AdminSequenzeController;
use App\Modules\Documenti\Controllers\Admin\AdminTrashController;
use App\Modules\Documenti\Controllers\Api\DocumentsApiController;
use App\Modules\Documenti\Controllers\ApprovazioniController;
use App\Modules\Documenti\Controllers\CategorieController;
use App\Modules\Documenti\Controllers\CollegamentiController;
use App\Modules\Documenti\Controllers\DocumentiController;
use App\Modules\Documenti\Controllers\VersioniController;

// ── API v1 — token-based, stateless (riusa DocumentoService). Static prima di {id}.
$router->group([
    'prefix'     => 'api/v1/documents',
    'middleware' => [ApiTokenMiddleware::class, ApiRateLimitMiddleware::class],
], function ($r) {
    $r->get('/', [DocumentsApiController::class, 'index'])->name('api.documents.index');
    $r->get('/{id}', [DocumentsApiController::class, 'show'])->name('api.documents.show');
});

// ── ADMIN: /admin/documenti (Auth + Csrf + documenti.admin) ───────────────────
$router->group([
    'prefix'     => 'admin/documenti',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('documenti.admin'),
    ],
], function ($r) {
    $r->get('/', [AdminDashboardController::class,  'index'])         ->name('documenti.admin.dashboard');
    $r->get('/elenco', [AdminDocumentiController::class,  'elenco'])        ->name('documenti.admin.elenco');
    $r->get('/trash', [AdminTrashController::class,      'index'])         ->name('documenti.admin.trash');
    $r->post('/trash/{id}/restore', [AdminTrashController::class,      'restore'])       ->name('documenti.admin.trash.restore');
    $r->delete('/trash/{id}', [AdminTrashController::class,      'purge'])         ->name('documenti.admin.trash.purge');
    $r->get('/categorie', [AdminCategorieController::class,  'index'])         ->name('documenti.admin.categorie');
    $r->get('/audit', [AdminAuditController::class,      'index'])         ->name('documenti.admin.audit');
    $r->get('/audit/export', [AdminAuditController::class,      'exportCsv'])     ->name('documenti.admin.audit.export');
    $r->get('/audit/{entity}/{id}', [AdminAuditController::class,      'dettaglio'])     ->name('documenti.admin.audit.dettaglio');
    $r->get('/sequenze', [AdminSequenzeController::class,   'index'])         ->name('documenti.admin.sequenze');
    $r->post('/sequenze/{categoriaId}/reset', [AdminSequenzeController::class,   'reset'])         ->name('documenti.admin.sequenze.reset');
    $r->get('/health', [AdminHealthController::class,     'index'])         ->name('documenti.admin.health');
    $r->get('/mime', [AdminMimeController::class,       'index'])         ->name('documenti.admin.mime');
    $r->post('/mime/{mime}/toggle', [AdminMimeController::class,       'toggle'])        ->name('documenti.admin.mime.toggle');
    $r->post('/jobs/reminders/run', [AdminDashboardController::class,  'runReminders'])  ->name('documenti.admin.jobs.reminders');
    $r->post('/jobs/expire/run', [AdminDashboardController::class,  'runExpire'])     ->name('documenti.admin.jobs.expire');
    $r->post('/{id}/riassegna-owner', [AdminDocumentiController::class,  'riassegnaOwner'])->name('documenti.admin.riassegna_owner');
});

// ── CATEGORIE: /documenti/categorie (Auth + Csrf + documenti.manage_categorie) ──
$router->group([
    'prefix'     => 'documenti/categorie',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('documenti.manage_categorie'),
    ],
], function ($r) {
    $r->get('/', [CategorieController::class, 'index'])     ->name('documenti.categorie.index');
    $r->post('/', [CategorieController::class, 'store'])     ->name('documenti.categorie.store');
    $r->post('/quick', [CategorieController::class, 'quickStore'])->name('documenti.categorie.quickStore');
    $r->put('/{id}', [CategorieController::class, 'update'])    ->name('documenti.categorie.update');
    $r->put('/{id}/sposta', [CategorieController::class, 'sposta'])    ->name('documenti.categorie.sposta');
    $r->delete('/{id}', [CategorieController::class, 'destroy'])   ->name('documenti.categorie.destroy');
});

// ── USER: /documenti (Auth + Csrf + documenti.access) ─────────────────────────
$router->group([
    'prefix'     => 'documenti',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        RoleMiddleware::withPermission('documenti.access'),
    ],
], function ($r) {

    // ── Static routes first ───────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.view')]], function ($r) {
        $r->get('/', [DocumentiController::class, 'index'])->name('documenti.index');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.create')]], function ($r) {
        $r->get('/create', [DocumentiController::class, 'create'])->name('documenti.create');
        $r->post('/', [DocumentiController::class, 'store']) ->name('documenti.store');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.inbox')]], function ($r) {
        $r->get('/inbox', [DocumentiController::class, 'inbox'])->name('documenti.inbox');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.view')]], function ($r) {
        $r->get('/scadenze', [DocumentiController::class, 'scadenze'])->name('documenti.scadenze');
        $r->get('/tree', [DocumentiController::class, 'tree'])    ->name('documenti.tree');
    });

    // ── Parametric routes ─────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.view')]], function ($r) {
        $r->get('/{id}', [DocumentiController::class, 'show'])->name('documenti.show');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.redazione')]], function ($r) {
        $r->get('/{id}/edit', [DocumentiController::class, 'edit'])  ->name('documenti.edit');
        $r->put('/{id}', [DocumentiController::class, 'update'])->name('documenti.update');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.delete')]], function ($r) {
        $r->delete('/{id}', [DocumentiController::class, 'destroy'])->name('documenti.destroy');
    });

    // ── Versioni ─────────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.redazione')]], function ($r) {
        $r->post('/{id}/versioni', [VersioniController::class, 'store'])->name('documenti.versioni.store');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.view')]], function ($r) {
        $r->get('/{id}/versioni/{vid}/download', [VersioniController::class, 'download'])->name('documenti.versioni.download');
        $r->get('/{id}/versioni/{vid}/preview', [VersioniController::class, 'preview']) ->name('documenti.versioni.preview');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.redazione')]], function ($r) {
        $r->post('/{id}/versioni/{vid}/ripristina', [VersioniController::class, 'ripristina'])->name('documenti.versioni.ripristina');
    });

    // ── Approvazioni ─────────────────────────────────────────────────────
    // invia / riprendi / ritira (owner/redazione)
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.redazione')]], function ($r) {
        $r->post('/{id}/approvazioni/invia', [ApprovazioniController::class, 'invia'])   ->name('documenti.approvazioni.invia');
        $r->post('/{id}/approvazioni/riprendi', [ApprovazioniController::class, 'riprendi'])->name('documenti.approvazioni.riprendi');
        $r->post('/{id}/approvazioni/ritira', [ApprovazioniController::class, 'ritira'])  ->name('documenti.approvazioni.ritira');
    });

    // prende_in_carico (controllo): inviato → in_controllo
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.controllo')]], function ($r) {
        $r->post('/{id}/approvazioni/prende-in-carico', [ApprovazioniController::class, 'prendeInCarico'])->name('documenti.approvazioni.prende_in_carico');
    });

    // approva / rifiuta / restituisci: gating inbox, permesso fine-grained nel WorkflowApprovazioneService
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.inbox')]], function ($r) {
        $r->post('/{id}/approvazioni/approva', [ApprovazioniController::class, 'approva'])    ->name('documenti.approvazioni.approva');
        $r->post('/{id}/approvazioni/rifiuta', [ApprovazioniController::class, 'rifiuta'])    ->name('documenti.approvazioni.rifiuta');
        $r->post('/{id}/approvazioni/restituisci', [ApprovazioniController::class, 'restituisci'])->name('documenti.approvazioni.restituisci');
    });

    // pubblica / archivia (admin)
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.admin')]], function ($r) {
        $r->post('/{id}/approvazioni/pubblica', [ApprovazioniController::class, 'pubblica'])->name('documenti.approvazioni.pubblica');
        $r->post('/{id}/approvazioni/archivia', [ApprovazioniController::class, 'archivia'])->name('documenti.approvazioni.archivia');
    });

    // ── Collegamenti ─────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('documenti.manage_collegamenti')]], function ($r) {
        $r->post('/{id}/collegamenti', [CollegamentiController::class, 'store'])  ->name('documenti.collegamenti.store');
        $r->delete('/{id}/collegamenti/{lid}', [CollegamentiController::class, 'destroy'])->name('documenti.collegamenti.destroy');
    });
});
