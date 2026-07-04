<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Scheduler\Controllers\SchedulerController;

$router->group([
    'prefix'     => 'scheduler',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {
    $r->group(['middleware' => [RoleMiddleware::withPermission('scheduler.view')]], function ($r) {
        $r->get('/', [SchedulerController::class, 'index'])->name('scheduler.index');
        $r->get('/poll', [SchedulerController::class, 'pollTable'])->name('scheduler.poll');
        $r->get('/{id}/log', [SchedulerController::class, 'jobLog'])->name('scheduler.job_log');
        $r->get('/output/{filename}', [SchedulerController::class, 'viewLogFile'])->name('scheduler.output_file');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('scheduler.manage')]], function ($r) {
        // Statiche prima di parametriche
        $r->get('/create', [SchedulerController::class, 'create'])->name('scheduler.create');
        $r->post('/', [SchedulerController::class, 'store'])->name('scheduler.store');
        $r->post('/toggle', [SchedulerController::class, 'toggle'])->name('scheduler.toggle');
        $r->post('/prune-log', [SchedulerController::class, 'pruneLog'])->name('scheduler.prune_log');
        // Parametriche
        $r->get('/{id}/edit', [SchedulerController::class, 'edit'])->name('scheduler.edit');
        $r->put('/{id}', [SchedulerController::class, 'update'])->name('scheduler.update');
        $r->delete('/{id}', [SchedulerController::class, 'destroy'])->name('scheduler.destroy');
        $r->post('/{id}/run', [SchedulerController::class, 'runNow'])->name('scheduler.run');
        $r->post('/{id}/reset', [SchedulerController::class, 'resetJob'])->name('scheduler.reset');
    });
});
