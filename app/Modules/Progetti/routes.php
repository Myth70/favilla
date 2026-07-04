<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Progetti\Controllers\AdminProgettiController;
use App\Modules\Progetti\Controllers\ChecklistController;
use App\Modules\Progetti\Controllers\ProgettiController;

$router->group([
    'prefix' => 'admin/projects',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {
    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.manage_all')]], function ($r) {
        $r->get('/', [AdminProgettiController::class, 'index'])->name('projects.admin.index');
        $r->get('/trash', [AdminProgettiController::class, 'trash'])->name('projects.admin.trash');
        $r->get('/table', [AdminProgettiController::class, 'table'])->name('projects.admin.table');

        $r->post('/{id}/trash', [AdminProgettiController::class, 'moveToTrash'])->name('projects.admin.move_to_trash');
        $r->post('/{id}/restore', [AdminProgettiController::class, 'restore'])->name('projects.admin.restore');
        $r->delete('/{id}', [AdminProgettiController::class, 'purge'])->name('projects.admin.purge');
    });
});

$router->group([
    'prefix' => 'projects',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.view')]], function ($r) {
        $r->get('/', [ProgettiController::class, 'index'])->name('projects.index');
        $r->get('/search', [ProgettiController::class, 'index'])->name('projects.search');
        $r->get('/my-tasks', [ProgettiController::class, 'myTasks'])->name('projects.my_tasks');
    });

    // Template checklist — route STATICHE, devono stare PRIMA dei gruppi /{id}
    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.edit')]], function ($r) {
        $r->get('/checklist-templates', [ChecklistController::class, 'listTemplates'])->name('projects.checklist_templates.index');
        $r->get('/checklist-templates/{tplId}', [ChecklistController::class, 'showTemplate'])->name('projects.checklist_templates.show');
        $r->post('/checklist-templates', [ChecklistController::class, 'storeTemplate'])->name('projects.checklist_templates.store');
        $r->put('/checklist-templates/{tplId}', [ChecklistController::class, 'updateTemplate'])->name('projects.checklist_templates.update');
        $r->delete('/checklist-templates/{tplId}', [ChecklistController::class, 'destroyTemplate'])->name('projects.checklist_templates.destroy');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.create')]], function ($r) {
        $r->get('/create', [ProgettiController::class, 'create'])->name('projects.create');
        $r->post('/', [ProgettiController::class, 'store'])->name('projects.store');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.edit')]], function ($r) {
        $r->get('/{id}/edit', [ProgettiController::class, 'edit'])->name('projects.edit');
        $r->put('/{id}', [ProgettiController::class, 'update'])->name('projects.update');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.delete')]], function ($r) {
        $r->delete('/{id}', [ProgettiController::class, 'destroy'])->name('projects.destroy');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.view')]], function ($r) {
        $r->get('/{id}', [ProgettiController::class, 'show'])->name('projects.show');
        $r->get('/{id}/kanban', [ProgettiController::class, 'kanban'])->name('projects.kanban');
        $r->get('/{id}/gantt', [ProgettiController::class, 'gantt'])->name('projects.gantt');
        $r->get('/{id}/timesheet', [ProgettiController::class, 'timesheet'])->name('projects.timesheet');
        $r->get('/{id}/report', [ProgettiController::class, 'report'])->name('projects.report');
        $r->post('/{id}/members', [ProgettiController::class, 'storeMember'])->name('projects.members.store');
        $r->put('/{id}/members/{memberId}', [ProgettiController::class, 'updateMember'])->name('projects.members.update');
        $r->delete('/{id}/members/{memberId}', [ProgettiController::class, 'destroyMember'])->name('projects.members.destroy');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.edit')]], function ($r) {
        $r->post('/{id}/milestones', [ProgettiController::class, 'storeMilestone'])->name('projects.milestones.store');
        $r->put('/{id}/milestones/{milestoneId}', [ProgettiController::class, 'updateMilestone'])->name('projects.milestones.update');
        $r->delete('/{id}/milestones/{milestoneId}', [ProgettiController::class, 'destroyMilestone'])->name('projects.milestones.destroy');

        $r->post('/{id}/tasks', [ProgettiController::class, 'storeTask'])->name('projects.tasks.store');
        $r->put('/{id}/tasks/{taskId}', [ProgettiController::class, 'updateTask'])->name('projects.tasks.update');
        $r->delete('/{id}/tasks/{taskId}', [ProgettiController::class, 'destroyTask'])->name('projects.tasks.destroy');

        $r->post('/{id}/tasks/{taskId}/dependencies', [ProgettiController::class, 'storeDependency'])->name('projects.dependencies.store');
        $r->delete('/{id}/tasks/{taskId}/dependencies/{predecessorId}', [ProgettiController::class, 'destroyDependency'])->name('projects.dependencies.destroy');

        $r->post('/{id}/files', [ProgettiController::class, 'storeFile'])->name('projects.files.store');
        $r->delete('/{id}/files/{fileId}', [ProgettiController::class, 'destroyFile'])->name('projects.files.destroy');
    });

    // Cambio stato task: accessibile anche all'utente assegnato.
    // Verifica granulare in ProgettiService::quickStatusTask() / moveTask().
    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.view')]], function ($r) {
        $r->post('/{id}/tasks/{taskId}/status', [ProgettiController::class, 'quickStatusTask'])->name('projects.tasks.quick_status');
        $r->post('/{id}/tasks/{taskId}/move', [ProgettiController::class, 'moveTask'])->name('projects.tasks.move');
    });

    // Checklist task — verifica granulare (edit OR assegnato) in ChecklistService.
    // Le route con sub-path STATICI (/reorder, /from-template, /{itemId}/done) devono stare
    // PRIMA di quelle parametriche (/{itemId}) per evitare conflitti di routing.
    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.view')]], function ($r) {
        $r->get('/{id}/tasks/{taskId}/checklist', [ChecklistController::class, 'getChecklist'])->name('projects.tasks.checklist.index');
        $r->post('/{id}/tasks/{taskId}/checklist/reorder', [ChecklistController::class, 'reorderItems'])->name('projects.tasks.checklist.reorder');
        $r->post('/{id}/tasks/{taskId}/checklist/from-template', [ChecklistController::class, 'applyTemplate'])->name('projects.tasks.checklist.apply_template');
        $r->post('/{id}/tasks/{taskId}/checklist', [ChecklistController::class, 'storeItem'])->name('projects.tasks.checklist.store');
        $r->post('/{id}/tasks/{taskId}/checklist/{itemId}/done', [ChecklistController::class, 'checkItem'])->name('projects.tasks.checklist.check');
        $r->put('/{id}/tasks/{taskId}/checklist/{itemId}', [ChecklistController::class, 'updateItem'])->name('projects.tasks.checklist.update');
        $r->delete('/{id}/tasks/{taskId}/checklist/{itemId}', [ChecklistController::class, 'destroyItem'])->name('projects.tasks.checklist.destroy');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('progetti.log_time')]], function ($r) {
        $r->post('/{id}/timesheet', [ProgettiController::class, 'storeTimesheet'])->name('projects.timesheet.store');
        $r->put('/{id}/timesheet/{timesheetId}', [ProgettiController::class, 'updateTimesheet'])->name('projects.timesheet.update');
        $r->delete('/{id}/timesheet/{timesheetId}', [ProgettiController::class, 'destroyTimesheet'])->name('projects.timesheet.destroy');
    });
});
