<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Tasks\Controllers\TasksController;

$router->group([
    'prefix'     => 'tasks',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── View (static routes first) ───────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.view')]], function ($r) {
        $r->get('/', [TasksController::class, 'index'])->name('tasks.index');
        $r->get('/list', [TasksController::class, 'list'])->name('tasks.list');
        $r->get('/board', [TasksController::class, 'board'])->name('tasks.board');
        $r->get('/search', [TasksController::class, 'search'])->name('tasks.search');
    });

    // ── Create ───────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.create')]], function ($r) {
        $r->get('/create', [TasksController::class, 'create'])->name('tasks.create');
        $r->post('/', [TasksController::class, 'store'])->name('tasks.store');
    });

    // ── Tags management ──────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.create')]], function ($r) {
        $r->get('/tags', [TasksController::class, 'tags'])->name('tasks.tags');
        $r->post('/tags', [TasksController::class, 'storeTag'])->name('tasks.tags.store');
        $r->delete('/tags/{id}', [TasksController::class, 'destroyTag'])->name('tasks.tags.destroy');
    });

    // ── Show (parametric, after static) ──────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.view')]], function ($r) {
        $r->get('/{id}', [TasksController::class, 'show'])->name('tasks.show');
    });

    // ── Edit ─────────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.edit')]], function ($r) {
        $r->get('/{id}/edit', [TasksController::class, 'edit'])->name('tasks.edit');
        $r->put('/{id}', [TasksController::class, 'update'])->name('tasks.update');
        $r->put('/{id}/move', [TasksController::class, 'move'])->name('tasks.move');
        $r->put('/{id}/toggle', [TasksController::class, 'toggle'])->name('tasks.toggle');
    });

    // ── Checklist ────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.edit')]], function ($r) {
        $r->post('/{id}/checklist', [TasksController::class, 'addChecklist'])->name('tasks.checklist.store');
        $r->put('/{id}/checklist/{cid}', [TasksController::class, 'toggleChecklist'])->name('tasks.checklist.toggle');
        $r->delete('/{id}/checklist/{cid}', [TasksController::class, 'deleteChecklist'])->name('tasks.checklist.destroy');
    });

    // ── Delete ───────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('tasks.delete')]], function ($r) {
        $r->delete('/{id}', [TasksController::class, 'destroy'])->name('tasks.destroy');
    });
});
