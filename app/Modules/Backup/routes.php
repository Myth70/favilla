<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Backup\Controllers\BackupController;

$router->group([
    'prefix'     => 'admin/backup',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {
    // Gestione backup: index, crea, elimina
    $r->group(['middleware' => [RoleMiddleware::withPermission('backup.manage')]], function ($r) {
        $r->get('/', [BackupController::class, 'index'])->name('backup.index');
        $r->post('/', [BackupController::class, 'store'])->name('backup.store');
        $r->post('/delete', [BackupController::class, 'destroy'])->name('backup.destroy');
    });
    // Download backup: permesso separato
    $r->group(['middleware' => [RoleMiddleware::withPermission('backup.download')]], function ($r) {
        $r->get('/download', [BackupController::class, 'download'])->name('backup.download');
    });
    // Restore backup: permesso separato
    $r->group(['middleware' => [RoleMiddleware::withPermission('backup.restore')]], function ($r) {
        $r->post('/restore', [BackupController::class, 'restore'])->name('backup.restore');
    });
});
