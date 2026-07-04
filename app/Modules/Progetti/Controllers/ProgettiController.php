<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Controllers;

use App\Core\Controller;
use App\Modules\Progetti\Services\ProgettiService;
use App\Traits\ControllerHelpers;

class ProgettiController extends Controller
{
    use ControllerHelpers;

    private ProgettiService $service;

    public function __construct()
    {
        $this->service = app(ProgettiService::class);
    }

    public function index(): void
    {
        $userId = (int) auth()['id'];
        $filters = $this->cleanGet(['q', 'status', 'sort', 'dir', 'page']);
        $result = $this->service->listForUser($userId, $filters);

        $viewData = [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['lastPage'],
            'filters' => $filters,
        ];

        if ($this->isPartialRequest()) {
            $this->renderPartial('Progetti/Views/partials/table', $viewData);
            return;
        }

        $this->render('Progetti/Views/index', array_merge($viewData, [
            'pageTitle' => t('progetti.title'),
            'breadcrumbs' => [['label' => t('progetti.breadcrumb.index')]],
        ]));
    }

    public function create(): void
    {
        $this->render('Progetti/Views/form', [
            'pageTitle' => t('progetti.form.new_title'),
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => t('progetti.breadcrumb.new')],
            ],
            'errors' => $_SESSION['_errors'] ?? [],
            'old' => $_SESSION['_old'] ?? [],
        ]);

        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function store(): void
    {
        $userId = (int) auth()['id'];
        $data = $this->cleanPost([
            'name',
            'code',
            'description',
            'client_name',
            'status',
            'start_date',
            'end_date',
            'estimated_hours',
            'budget_planned',
        ]);

        $errors = ProgettiService::validateProjectData($data);
        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            $this->redirect(route('projects.create'));
            return;
        }

        try {
            $projectId = $this->service->create($data, $userId, (string) (auth()['name'] ?? t('progetti.exception.default_pm_name')));
        } catch (\Throwable $e) {
            $this->logUnexpectedError('create project', $e);
            flash_error(t('progetti.flash.project_create_error'));
            $_SESSION['_old'] = $data;
            $this->redirect(route('projects.create'));
            return;
        }

        flash_success(t('progetti.flash.project_created'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function edit(string $id): void
    {
        $userId = (int) auth()['id'];
        $projectId = (int) $id;
        $project = $this->service->findForUser($projectId, $userId);

        if (!$project) {
            if ($this->isAjaxRequest()) {
                http_response_code(404);
                echo e(t('progetti.flash.project_not_found'));
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $payload = [
            'pageTitle' => t('progetti.form.edit_title'),
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => $project['name'], 'route' => 'projects.show', 'params' => ['id' => $projectId]],
                ['label' => t('progetti.breadcrumb.edit')],
            ],
            'isEdit' => true,
            'project' => $project,
            'errors' => $_SESSION['_errors'] ?? [],
            'old' => $_SESSION['_old'] ?? [],
        ];

        if ($this->isAjaxRequest()) {
            $this->renderPartial('Progetti/Views/partials/edit_modal', $payload);
            unset($_SESSION['_errors'], $_SESSION['_old']);
            return;
        }

        $this->render('Progetti/Views/form', $payload);

        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function update(string $id): void
    {
        $userId = (int) auth()['id'];
        $projectId = (int) $id;

        $project = $this->service->findForUser($projectId, $userId);
        if (!$project) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $data = $this->cleanPost([
            'name',
            'code',
            'description',
            'client_name',
            'status',
            'start_date',
            'end_date',
            'estimated_hours',
            'budget_planned',
        ]);

        $errors = ProgettiService::validateProjectData($data);
        if (!empty($errors)) {
            if ($this->isAjaxRequest()) {
                http_response_code(422);
                $this->renderPartial('Progetti/Views/partials/edit_modal', [
                    'isEdit' => true,
                    'project' => $project,
                    'errors' => $errors,
                    'old' => $data,
                ]);
                return;
            }
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            $this->redirect(route('projects.edit', ['id' => $projectId]));
            return;
        }

        try {
            $this->service->updateProject($projectId, $data, $userId);
        } catch (\Throwable $e) {
            $this->logUnexpectedError('update project', $e);
            if ($this->isAjaxRequest()) {
                http_response_code(422);
                $errors = ['generic' => [t('progetti.flash.project_update_error')]];
                $this->renderPartial('Progetti/Views/partials/edit_modal', [
                    'isEdit' => true,
                    'project' => $project,
                    'errors' => $errors,
                    'old' => $data,
                ]);
                return;
            }
            flash_error(t('progetti.flash.project_update_error'));
            $_SESSION['_old'] = $data;
            $this->redirect(route('projects.edit', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json([
                'success' => true,
                'message' => t('progetti.flash.project_updated'),
            ]);
            return;
        }
        flash_success(t('progetti.flash.project_updated'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroy(string $id): void
    {
        $userId = (int) auth()['id'];
        $projectId = (int) $id;

        $project = $this->service->findForUser($projectId, $userId);
        if (!$project) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->deleteProject($projectId, $userId);
        } catch (\Throwable $e) {
            $this->logUnexpectedError('delete project', $e);
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_delete_error')], 422);
                return;
            }
            flash_error(t('progetti.flash.project_delete_error'));
            $this->redirect(route('projects.index'));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json([
                'success' => true,
                'message' => t('progetti.flash.project_deleted'),
                'redirect' => route('projects.index'),
            ]);
            return;
        }
        flash_success(t('progetti.flash.project_deleted'));
        $this->redirect(route('projects.index'));
    }

    public function show(string $id): void
    {
        $userId = (int) auth()['id'];
        $project = $this->service->findForUser((int) $id, $userId);

        if (!$project) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $this->render('Progetti/Views/show', [
            'pageTitle' => $project['name'],
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => $project['name']],
            ],
            'project' => $project,
            'management' => $this->service->getManagementData((int) $id),
            'kpi' => $this->service->getDashboardKpi((int) $id),
            'canManageMembers' => $this->service->canManageMembers((int) $id, $userId),
        ]);
    }

    public function storeMember(string $id): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['user_id', 'role', 'hourly_rate_override']);
        $rate  = ($clean['hourly_rate_override'] ?? '') !== ''
            ? (float) str_replace(',', '.', $clean['hourly_rate_override'])
            : null;

        try {
            $this->service->addMember(
                $projectId,
                (int) ($clean['user_id'] ?? 0),
                (string) ($clean['role'] ?? 'member'),
                $rate,
                $userId
            );
            flash_success(t('progetti.flash.member_added'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        } catch (\Throwable) {
            flash_error(t('progetti.flash.member_add_error'));
        }

        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function updateMember(string $id, string $memberId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['role', 'hourly_rate_override']);
        $rate  = ($clean['hourly_rate_override'] ?? '') !== ''
            ? (float) str_replace(',', '.', $clean['hourly_rate_override'])
            : null;

        try {
            $this->service->updateMember(
                $projectId,
                (int) $memberId,
                (string) ($clean['role'] ?? 'member'),
                $rate,
                $userId
            );
            flash_success(t('progetti.flash.member_updated'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        } catch (\Throwable) {
            flash_error(t('progetti.flash.member_update_error'));
        }

        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroyMember(string $id, string $memberId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->removeMember($projectId, (int) $memberId, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.member_remove_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.member_remove_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json(['success' => true, 'message' => t('progetti.flash.member_removed')]);
            return;
        }
        flash_success(t('progetti.flash.member_removed'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function storeMilestone(string $id): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['name', 'description', 'due_date', 'status', 'billable']);
        $data = [
            'name' => $clean['name'] ?? '',
            'description' => $clean['description'] ?? null,
            'due_date' => $clean['due_date'] ?? null,
            'status' => $clean['status'] ?? 'pending',
            'billable' => $clean['billable'] ?? null,
        ];

        try {
            $this->service->createMilestone($projectId, $data, $userId);
            flash_success(t('progetti.flash.milestone_created'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        } catch (\Throwable) {
            flash_error(t('progetti.flash.milestone_create_error'));
        }

        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function updateMilestone(string $id, string $milestoneId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['name', 'description', 'due_date', 'status', 'billable']);
        $data = [
            'name' => $clean['name'] ?? '',
            'description' => $clean['description'] ?? null,
            'due_date' => $clean['due_date'] ?? null,
            'status' => $clean['status'] ?? 'pending',
            'billable' => $clean['billable'] ?? null,
        ];

        try {
            $this->service->updateMilestone($projectId, (int) $milestoneId, $data, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.milestone_update_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.milestone_update_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json(['success' => true, 'message' => t('progetti.flash.milestone_updated')]);
            return;
        }
        flash_success(t('progetti.flash.milestone_updated'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroyMilestone(string $id, string $milestoneId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->deleteMilestone($projectId, (int) $milestoneId, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.milestone_delete_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.milestone_delete_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json(['success' => true, 'message' => t('progetti.flash.milestone_deleted')]);
            return;
        }
        flash_success(t('progetti.flash.milestone_deleted'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function storeTask(string $id): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['title', 'description', 'priority', 'status', 'start_date', 'due_date', 'milestone_id', 'assigned_user_id', 'estimated_hours']);
        $data = [
            'title' => $clean['title'] ?? '',
            'description' => $clean['description'] ?? null,
            'milestone_id' => !empty($clean['milestone_id']) ? (int) $clean['milestone_id'] : null,
            'assigned_user_id' => !empty($clean['assigned_user_id']) ? (int) $clean['assigned_user_id'] : null,
            'priority' => $clean['priority'] ?? 'medium',
            'status' => $clean['status'] ?? 'todo',
            'start_date' => $clean['start_date'] ?? null,
            'due_date' => $clean['due_date'] ?? null,
            'estimated_hours' => (float) ($clean['estimated_hours'] ?? 0),
        ];

        try {
            $this->service->createTask($projectId, $data, $userId);
            flash_success(t('progetti.flash.task_created'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        } catch (\Throwable) {
            flash_error(t('progetti.flash.task_create_error'));
        }

        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function updateTask(string $id, string $taskId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['title', 'description', 'priority', 'status', 'start_date', 'due_date', 'milestone_id', 'assigned_user_id', 'estimated_hours']);
        $data = [
            'title' => $clean['title'] ?? '',
            'description' => $clean['description'] ?? null,
            'milestone_id' => !empty($clean['milestone_id']) ? (int) $clean['milestone_id'] : null,
            'assigned_user_id' => !empty($clean['assigned_user_id']) ? (int) $clean['assigned_user_id'] : null,
            'priority' => $clean['priority'] ?? 'medium',
            'status' => $clean['status'] ?? 'todo',
            'start_date' => $clean['start_date'] ?? null,
            'due_date' => $clean['due_date'] ?? null,
            'estimated_hours' => (float) ($clean['estimated_hours'] ?? 0),
        ];

        try {
            $this->service->updateTask($projectId, (int) $taskId, $data, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.task_update_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.task_update_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json(['success' => true, 'message' => t('progetti.flash.task_updated')]);
            return;
        }
        flash_success(t('progetti.flash.task_updated'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroyTask(string $id, string $taskId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->deleteTask($projectId, (int) $taskId, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.task_delete_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.task_delete_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json(['success' => true, 'message' => t('progetti.flash.task_deleted')]);
            return;
        }
        flash_success(t('progetti.flash.task_deleted'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function storeDependency(string $id, string $taskId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        $isAjax = $this->isPartialRequest();

        if (!$this->service->findForUser($projectId, $userId)) {
            if ($isAjax) {
                $this->json(['ok' => false, 'error' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $data = $this->cleanPost(['predecessor_task_id']);

        try {
            $this->service->addTaskDependency($projectId, (int) $taskId, (int) ($data['predecessor_task_id'] ?? 0), $userId);
        } catch (\RuntimeException $e) {
            if ($isAjax) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($isAjax) {
                $this->json(['ok' => false, 'error' => t('progetti.flash.dependency_add_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.dependency_add_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($isAjax) {
            $this->json(['ok' => true, 'message' => t('progetti.flash.dependency_added')]);
            return;
        }
        flash_success(t('progetti.flash.dependency_added'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroyDependency(string $id, string $taskId, string $predecessorId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        $isAjax = $this->isPartialRequest();

        if (!$this->service->findForUser($projectId, $userId)) {
            if ($isAjax) {
                $this->json(['ok' => false, 'error' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->removeTaskDependency($projectId, (int) $taskId, (int) $predecessorId, $userId);
        } catch (\RuntimeException $e) {
            if ($isAjax) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($isAjax) {
                $this->json(['ok' => false, 'error' => t('progetti.flash.dependency_remove_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.dependency_remove_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($isAjax) {
            $this->json(['ok' => true, 'message' => t('progetti.flash.dependency_removed')]);
            return;
        }
        flash_success(t('progetti.flash.dependency_removed'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function kanban(string $id): void
    {
        $userId = (int) auth()['id'];
        $project = $this->service->findForUser((int) $id, $userId);
        if (!$project) {
            http_response_code(404);
            echo e(t('progetti.flash.project_not_found'));
            return;
        }

        $data = $this->service->getKanbanData((int) $id);
        $payload = ['project' => $project] + $data;

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Progetti/Views/partials/kanban', $payload);
            return;
        }

        $this->render('Progetti/Views/show', [
            'pageTitle' => $project['name'],
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => $project['name']],
            ],
            'project' => $project,
            'management' => $this->service->getManagementData((int) $id),
            'kpi' => $this->service->getDashboardKpi((int) $id),
            'canManageMembers' => $this->service->canManageMembers((int) $id, $userId),
            'kanbanData' => $data,
        ]);
    }

    public function gantt(string $id): void
    {
        $userId = (int) auth()['id'];
        $project = $this->service->findForUser((int) $id, $userId);
        if (!$project) {
            http_response_code(404);
            echo e(t('progetti.flash.project_not_found'));
            return;
        }

        $data = $this->service->getGanttData((int) $id);
        $payload = ['project' => $project] + $data;

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Progetti/Views/partials/gantt', $payload);
            return;
        }

        $this->render('Progetti/Views/show', [
            'pageTitle' => $project['name'],
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => $project['name']],
            ],
            'project' => $project,
            'management' => $this->service->getManagementData((int) $id),
            'kpi' => $this->service->getDashboardKpi((int) $id),
            'canManageMembers' => $this->service->canManageMembers((int) $id, $userId),
            'ganttData' => $data,
        ]);
    }

    public function timesheet(string $id): void
    {
        $userId = (int) auth()['id'];
        $project = $this->service->findForUser((int) $id, $userId);
        if (!$project) {
            http_response_code(404);
            echo e(t('progetti.flash.project_not_found'));
            return;
        }

        $data = $this->service->getTimesheetData((int) $id);
        $taskOptions = $this->service->getManagementData((int) $id)['task_options'];
        $payload = ['project' => $project, 'task_options' => $taskOptions] + $data;

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Progetti/Views/partials/timesheet', $payload);
            return;
        }

        $this->render('Progetti/Views/show', [
            'pageTitle' => $project['name'],
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => $project['name']],
            ],
            'project' => $project,
            'management' => $this->service->getManagementData((int) $id),
            'kpi' => $this->service->getDashboardKpi((int) $id),
            'canManageMembers' => $this->service->canManageMembers((int) $id, $userId),
            'timesheetData' => $data,
        ]);
    }

    public function storeTimesheet(string $id): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];

        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('progetti.flash.project_not_found'), 'danger');
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['note', 'work_date', 'task_id', 'hours']);
        $data = [
            'task_id'   => !empty($clean['task_id']) ? (int) $clean['task_id'] : null,
            'work_date' => $clean['work_date'] ?? null,
            'hours'     => (float) ($clean['hours'] ?? 0),
            'note'      => $clean['note'] ?? null,
        ];

        try {
            $this->service->logTime($projectId, $data, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isHtmxRequest()) {
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('progetti.flash.time_log_error'), 'danger');
                return;
            }
            flash_error(t('progetti.flash.time_log_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('progetti.flash.time_logged'));
            header('HX-Trigger: prjTimesheetRefresh');
            return;
        }
        flash_success(t('progetti.flash.time_logged'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function updateTimesheet(string $id, string $timesheetId): void
    {
        $projectId = (int) $id;
        $userId    = (int) auth()['id'];

        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('progetti.flash.project_not_found'), 'danger');
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $clean = $this->cleanPost(['note', 'hours']);
        $hours = (float) ($clean['hours'] ?? 0);
        $note  = $clean['note'] ?? null;

        try {
            $this->service->updateTimesheet($projectId, (int) $timesheetId, $hours, $note, $userId);
        } catch (\RuntimeException $e) {
            if ($this->isHtmxRequest()) {
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        } catch (\Throwable) {
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('progetti.flash.time_update_error'), 'danger');
                return;
            }
            flash_error(t('progetti.flash.time_update_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isHtmxRequest()) {
            header('HX-Trigger: ' . json_encode([
                'notify'               => ['message' => t('progetti.flash.time_updated'), 'type' => 'success'],
                'prjTimesheetRefresh'  => true,
            ]));
            return;
        }
        flash_success(t('progetti.flash.time_updated'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroyTimesheet(string $id, string $timesheetId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];

        if (!$this->service->findForUser($projectId, $userId)) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->removeTimesheet($projectId, (int) $timesheetId, $userId);
            flash_success(t('progetti.flash.time_removed'));
        } catch (\Throwable) {
            flash_error(t('progetti.flash.time_remove_error'));
        }

        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function quickStatusTask(string $id, string $taskId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];

        if (!$this->service->findForUser($projectId, $userId)) {
            $this->json(['error' => t('progetti.flash.project_not_found')], 403);
            return;
        }

        $newStatus = $this->cleanPost(['status'])['status'] ?? '';

        try {
            $this->service->quickUpdateTaskStatus($projectId, (int) $taskId, $newStatus, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['error' => t('progetti.flash.task_update_error')], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    public function moveTask(string $id, string $taskId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];

        if (!$this->service->findForUser($projectId, $userId)) {
            $this->json(['error' => t('progetti.flash.project_not_found')], 403);
            return;
        }

        $clean       = $this->cleanPost(['status', 'position']);
        $newStatus   = $clean['status'] ?? '';
        $newPosition = max(0, (int) ($clean['position'] ?? 0));

        try {
            $this->service->moveTask($projectId, (int) $taskId, $newStatus, $newPosition, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['error' => t('progetti.flash.task_update_error')], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    public function storeFile(string $id): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_error(t('progetti.flash.file_invalid'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        $meta = $this->cleanPost(['description', 'folder']);
        $meta['visibility'] = 'internal';

        try {
            $this->service->uploadProjectFile($projectId, $file, $meta, $userId);
            flash_success(t('progetti.flash.file_attached'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        } catch (\Throwable) {
            flash_error(t('progetti.flash.file_upload_error'));
        }

        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function destroyFile(string $id, string $fileId): void
    {
        $projectId = (int) $id;
        $userId = (int) auth()['id'];
        if (!$this->service->findForUser($projectId, $userId)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.project_not_found')], 404);
                return;
            }
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        try {
            $this->service->unlinkProjectFile($projectId, (int) $fileId, $userId);
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'message' => t('progetti.flash.file_remove_error')], 500);
                return;
            }
            flash_error(t('progetti.flash.file_remove_error'));
            $this->redirect(route('projects.show', ['id' => $projectId]));
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->json(['success' => true, 'message' => t('progetti.flash.file_removed')]);
            return;
        }
        flash_success(t('progetti.flash.file_removed'));
        $this->redirect(route('projects.show', ['id' => $projectId]));
    }

    public function report(string $id): void
    {
        $userId = (int) auth()['id'];
        $project = $this->service->findForUser((int) $id, $userId);

        if (!$project) {
            flash_error(t('progetti.flash.project_not_found'));
            $this->redirect(route('projects.index'));
            return;
        }

        $this->render('Progetti/Views/report', [
            'pageTitle' => $project['name'],
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => $project['name'], 'route' => 'projects.show', 'params' => ['id' => (int) $id]],
                ['label' => t('progetti.breadcrumb.report')],
            ],
            'project' => $project,
            'report'  => $this->service->getReportData((int) $id),
        ]);
    }

    public function myTasks(): void
    {
        $userId  = (int) auth()['id'];
        $filters = $this->cleanGet(['status', 'priority', 'sort', 'dir']);

        $tasks = $this->service->getMyTasks($userId, $filters);

        $this->render('Progetti/Views/my_tasks', [
            'pageTitle'   => t('progetti.breadcrumb.my_tasks'),
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => t('progetti.breadcrumb.my_tasks')],
            ],
            'tasks'       => $tasks,
            'filters'     => $filters,
        ]);
    }

    private function logUnexpectedError(string $context, \Throwable $e): void
    {
        error_log(sprintf(
            '[ProgettiController] %s failed: %s in %s:%d',
            $context,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }
}
