<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Webhooks\Controllers\WebhooksController;

$router->group([
    'prefix'     => 'webhooks',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── View (static before parametric) ──────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('webhooks.view')]], function ($r) {
        $r->get('/', [WebhooksController::class, 'index'])->name('webhooks.index');
        $r->get('/create', [WebhooksController::class, 'create'])->name('webhooks.create');
    });

    // ── Manage (create/update/delete/test) ───────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('webhooks.manage')]], function ($r) {
        $r->post('/', [WebhooksController::class, 'store'])->name('webhooks.store');
        $r->post('/{id}/test', [WebhooksController::class, 'test'])->name('webhooks.test');
        $r->post('/{id}/secret', [WebhooksController::class, 'regenerateSecret'])->name('webhooks.secret.regenerate');
        $r->get('/{id}/edit', [WebhooksController::class, 'edit'])->name('webhooks.edit');
        $r->put('/{id}', [WebhooksController::class, 'update'])->name('webhooks.update');
        $r->delete('/{id}', [WebhooksController::class, 'destroy'])->name('webhooks.destroy');
    });

    // ── Deliveries log (view) ────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('webhooks.view')]], function ($r) {
        $r->get('/{id}/deliveries', [WebhooksController::class, 'deliveries'])->name('webhooks.deliveries');
    });
});
