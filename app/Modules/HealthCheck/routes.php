<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\HealthCheck\Controllers\HealthCheckController;

$router->group([
    'prefix'     => 'admin/health',
    'middleware' => [AuthMiddleware::class],
], function ($r) {
    // Visualizzazione
    $r->group(['middleware' => [RoleMiddleware::withPermission('healthcheck.view')]], function ($r) {
        $r->get('/', [HealthCheckController::class, 'index'])->name('healthcheck.index');
        // Scansione approfondita (check deep: DNS email, fetch .env, composer audit)
        $r->get('/deep', [HealthCheckController::class, 'deepScan'])->name('healthcheck.deep');
    });

    // Storico
    $r->group(['middleware' => [RoleMiddleware::withPermission('healthcheck.history')]], function ($r) {
        $r->get('/history', [HealthCheckController::class, 'history'])->name('healthcheck.history');
    });

    // Export
    $r->group(['middleware' => [RoleMiddleware::withPermission('healthcheck.export')]], function ($r) {
        $r->get('/export', [HealthCheckController::class, 'export'])->name('healthcheck.export');
    });
});
