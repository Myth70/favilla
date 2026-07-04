<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\SessionSecurityMiddleware;
// ── Gruppo Admin (/admin/files) — Auth + Csrf + files.admin ──────────────
// NOTA: gruppo admin registrato PRIMA del gruppo utente
//       per evitare conflitti con /{id} nelle route utente
use App\Modules\Files\Controllers\AdminFilesController;
use App\Modules\Files\Controllers\FilesController;

$router->group([
    'prefix'     => 'admin/files',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        SessionSecurityMiddleware::class,
        RoleMiddleware::withPermission('files.admin'),
    ],
], function ($r) {
    // Route statiche prima delle parametriche
    $r->get('/', [AdminFilesController::class, 'index'])->name('files.admin.index');
    $r->get('/trash', [AdminFilesController::class, 'trash'])->name('files.admin.trash');
    $r->get('/stats-widget', [AdminFilesController::class, 'statsWidget'])->name('files.admin.stats_widget');
    $r->get('/export', [AdminFilesController::class, 'export'])->name('files.admin.export');
    $r->post('/bulk-delete', [AdminFilesController::class, 'bulkDelete'])->name('files.admin.bulk_delete');
    $r->post('/bulk-purge', [AdminFilesController::class, 'bulkPurge'])->name('files.admin.bulk_purge');

    // Route parametriche dopo le statiche
    $r->post('/{id}/restore', [AdminFilesController::class, 'restore'])->name('files.admin.restore');
    $r->delete('/{id}', [AdminFilesController::class, 'purge'])->name('files.admin.purge');
});

// ── Gruppo Utente (/files) — Auth + Csrf ─────────────────────────────────
// Il modulo Files e' core: la parte utente e' disponibile a tutti gli utenti
// autenticati. L'unica area riservata resta /admin/files.
$router->group([
    'prefix'     => 'files',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        SessionSecurityMiddleware::class,
        RoleMiddleware::withPermission('files.access'),
        RateLimitMiddleware::perMinute(60),
    ],
], function ($r) {
    // Route STATICHE — devono precedere /{id}
    $r->get('/', [FilesController::class, 'index'])->name('files.index');
    $r->get('/picker', [FilesController::class, 'picker'])->name('files.picker');
    $r->get('/upload', [FilesController::class, 'upload'])->name('files.upload');
    $r->post('/', [FilesController::class, 'store'])->name('files.store');
    $r->get('/download-zip', [FilesController::class, 'downloadZip'])->name('files.download_zip');
    $r->post('/bulk-delete', [FilesController::class, 'bulkDestroy'])->name('files.bulk_destroy');
    $r->post('/folders', [FilesController::class, 'storeFolder'])->name('files.folders.store');
    $r->put('/folders/rename', [FilesController::class, 'renameFolder'])->name('files.folders.rename');
    $r->delete('/folders', [FilesController::class, 'destroyFolder'])->name('files.folders.destroy');
    $r->post('/{id}/versions/snapshot', [FilesController::class, 'snapshotVersion'])->name('files.versions.snapshot');
    $r->post('/{id}/versions/restore', [FilesController::class, 'restoreVersion'])->name('files.versions.restore');
    $r->post('/{id}/share/user', [FilesController::class, 'shareUser'])->name('files.share.user');
    $r->post('/{id}/share/role', [FilesController::class, 'shareRole'])->name('files.share.role');
    $r->post('/{id}/share/revoke', [FilesController::class, 'revokeShare'])->name('files.share.revoke');

    // Route parametriche
    $r->get('/{id}', [FilesController::class, 'show'])->name('files.show');
    $r->get('/{id}/download', [FilesController::class, 'download'])->name('files.download');
    $r->get('/{id}/preview', [FilesController::class, 'preview'])->name('files.preview');
    $r->get('/{id}/preview-modal', [FilesController::class, 'previewModal'])->name('files.preview_modal');
    $r->get('/{id}/edit', [FilesController::class, 'edit'])->name('files.edit');
    $r->put('/{id}', [FilesController::class, 'update'])->name('files.update');
    $r->delete('/{id}', [FilesController::class, 'destroy'])->name('files.destroy');
});
