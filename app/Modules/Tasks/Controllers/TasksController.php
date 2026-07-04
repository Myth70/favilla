<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Core\Controller;
use App\Modules\Tasks\Services\TasksService;
use App\Traits\ControllerHelpers;

class TasksController extends Controller
{
    use ControllerHelpers;

    private TasksService $service;

    public function __construct()
    {
        $this->service = app(TasksService::class);
    }

    // ── Index: Kanban board (default) ────────────────────────────────

    public function index(): void
    {
        $userId = (int) auth()['id'];
        $board  = $this->service->getBoard($userId);
        $tags   = $this->service->getUserTags($userId);
        $stats  = $this->service->getStats($userId);

        $this->render('Tasks/Views/index', [
            'pageTitle'   => t('tasks.title'),
            'breadcrumbs' => [['label' => t('tasks.title')]],
            'board'       => $board,
            'tags'        => $tags,
            'stats'       => $stats,
            'statuses'    => TasksService::getStatuses(),
            'priorities'  => TasksService::getPriorities(),
            'canCreate'   => has_permission('tasks.create'),
            'canEdit'     => has_permission('tasks.edit'),
            'canDelete'   => has_permission('tasks.delete'),
        ]);
    }

    // ── Board partial (HTMX refresh) ─────────────────────────────────

    public function board(): void
    {
        $userId = (int) auth()['id'];
        $board  = $this->service->getBoard($userId);
        $tags   = $this->service->getUserTags($userId);

        $this->renderPartial('Tasks/Views/partials/kanban-board', [
            'board'      => $board,
            'tags'       => $tags,
            'statuses'   => TasksService::getStatuses(),
            'priorities' => TasksService::getPriorities(),
            'canCreate'  => has_permission('tasks.create'),
            'canEdit'    => has_permission('tasks.edit'),
            'canDelete'  => has_permission('tasks.delete'),
        ]);
    }

    // ── List view (table) ────────────────────────────────────────────

    public function list(): void
    {
        $userId  = (int) auth()['id'];
        $filters = $this->cleanGet(['q', 'status', 'priority', 'sort', 'dir', 'page', 'scope'], 255);

        $result = $this->service->list($userId, $filters);
        $stats  = $this->service->getStats($userId);

        $viewData = [
            'items'      => $result['data'],
            'total'      => $result['total'],
            'pages'      => $result['lastPage'],
            'page'       => $result['page'],
            'filters'    => $filters,
            'stats'      => $stats,
            'statuses'   => TasksService::getStatuses(),
            'priorities' => TasksService::getPriorities(),
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Tasks/Views/partials/table', $viewData);
            return;
        }

        $this->render('Tasks/Views/list', array_merge($viewData, [
            'pageTitle'   => t('tasks.list_title'),
            'breadcrumbs' => [
                ['label' => t('tasks.title'), 'route' => 'tasks.index'],
                ['label' => t('tasks.breadcrumb_list')],
            ],
        ]));
    }

    // ── Create (modal partial HTMX) ─────────────────────────────────

    public function create(): void
    {
        $userId = (int) auth()['id'];
        $tags   = $this->service->getUserTags($userId);
        $clean  = $this->cleanGet(['status']);

        $data = [
            'task'       => null,
            'isEdit'     => false,
            'tags'       => $tags,
            'statuses'   => TasksService::getStatuses(),
            'priorities' => TasksService::getPriorities(),
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
            'status'     => $clean['status'] ?: 'todo',
        ];

        unset($_SESSION['_errors'], $_SESSION['_old']);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Tasks/Views/partials/modal-form', $data);
            return;
        }

        $this->render('Tasks/Views/form', array_merge($data, [
            'pageTitle'   => t('tasks.new_page_title'),
            'breadcrumbs' => [
                ['label' => t('tasks.title'), 'route' => 'tasks.index'],
                ['label' => t('tasks.breadcrumb_new')],
            ],
        ]));
    }

    // ── Store ────────────────────────────────────────────────────────

    public function store(): void
    {
        $userId = (int) auth()['id'];
        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        $isAjax = $this->isHtmxRequest() || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        if (!empty($errors)) {
            if ($isAjax) {
                $this->json(['errors' => $errors], 422);
                return;
            }
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $data;
            $this->redirect(route('tasks.create'));
            return;
        }

        try {
            $taskId = $this->service->create($data, $userId);
        } catch (\Throwable $e) {
            if ($isAjax) {
                $this->json(['error' => t('tasks.validation.create_error')], 500);
                return;
            }

            $_SESSION['_errors'] = ['generic' => [t('tasks.validation.create_error')]];
            $_SESSION['_old']    = $data;
            $this->redirect(route('tasks.create'));
            return;
        }

        if ($isAjax) {
            $this->json(['success' => true, 'id' => $taskId]);
            return;
        }

        flash_success(t('tasks.flash.created'));
        $this->redirect(route('tasks.index'));
    }

    // ── Show ─────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $userId = (int) auth()['id'];
        $task   = $this->service->find((int) $id, $userId);

        if (!$task) {
            flash_error(t('tasks.flash.not_found'));
            $this->redirect(route('tasks.index'));
            return;
        }

        $data = [
            'task'       => $task,
            'statuses'   => TasksService::getStatuses(),
            'priorities' => TasksService::getPriorities(),
            'canEdit'    => has_permission('tasks.edit'),
            'canDelete'  => has_permission('tasks.delete'),
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Tasks/Views/partials/task-detail', $data);
            return;
        }

        $this->render('Tasks/Views/show', array_merge($data, [
            'pageTitle'   => $task['title'],
            'breadcrumbs' => [
                ['label' => 'Attività', 'route' => 'tasks.index'],
                ['label' => $task['title']],
            ],
        ]));
    }

    // ── Edit (modal partial HTMX) ────────────────────────────────────

    public function edit(string $id): void
    {
        $userId = (int) auth()['id'];
        $task   = $this->service->find((int) $id, $userId);

        if (!$task) {
            if ($this->isHtmxRequest()) {
                http_response_code(404);
                echo e(t('tasks.flash.not_found'));
                return;
            }
            flash_error(t('tasks.flash.not_found'));
            $this->redirect(route('tasks.index'));
            return;
        }

        $tags = $this->service->getUserTags($userId);

        $data = [
            'task'       => $task,
            'isEdit'     => true,
            'tags'       => $tags,
            'statuses'   => TasksService::getStatuses(),
            'priorities' => TasksService::getPriorities(),
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
        ];

        unset($_SESSION['_errors'], $_SESSION['_old']);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Tasks/Views/partials/modal-form', $data);
            return;
        }

        $this->render('Tasks/Views/form', array_merge($data, [
            'pageTitle'   => t('tasks.edit_page_title'),
            'breadcrumbs' => [
                ['label' => t('tasks.title'), 'route' => 'tasks.index'],
                ['label' => t('tasks.breadcrumb_edit')],
            ],
        ]));
    }

    // ── Update ───────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $userId = (int) auth()['id'];
        $taskId = (int) $id;
        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        $isAjax = $this->isHtmxRequest() || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        if (!empty($errors)) {
            if ($isAjax) {
                $this->json(['errors' => $errors], 422);
                return;
            }
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $data;
            $this->redirect(route('tasks.edit', ['id' => $taskId]));
            return;
        }

        try {
            $this->service->update($taskId, $data, $userId);
        } catch (\RuntimeException $e) {
            if ($isAjax) {
                $this->json(['error' => $e->getMessage()], 404);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('tasks.index'));
            return;
        } catch (\Throwable $e) {
            if ($isAjax) {
                $this->json(['error' => t('tasks.validation.update_error')], 500);
                return;
            }

            $_SESSION['_errors'] = ['generic' => [t('tasks.validation.update_error')]];
            $_SESSION['_old']    = $data;
            $this->redirect(route('tasks.edit', ['id' => $taskId]));
            return;
        }

        if ($isAjax) {
            $this->json(['success' => true]);
            return;
        }

        flash_success(t('tasks.flash.updated'));
        $this->redirect(route('tasks.index'));
    }

    // ── Move (kanban drag & drop) ────────────────────────────────────

    public function move(string $id): void
    {
        $userId   = (int) auth()['id'];
        $taskId   = (int) $id;
        $clean    = $this->cleanPost(['status', 'position']);
        $status   = $clean['status'] ?? '';
        $position = (int) ($clean['position'] ?? 0);

        $validStatuses = array_keys(TasksService::getStatuses());
        if (!in_array($status, $validStatuses, true)) {
            $this->json(['error' => t('tasks.validation.move_status_invalid')], 400);
            return;
        }

        try {
            $this->service->moveTask($taskId, $status, $position, $userId);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        }
    }

    // ── Toggle complete ──────────────────────────────────────────────

    public function toggle(string $id): void
    {
        $userId = (int) auth()['id'];

        try {
            $result = $this->service->toggleComplete((int) $id, $userId);
            $this->json($result);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        }
    }

    // ── Checklist ────────────────────────────────────────────────────

    public function addChecklist(string $id): void
    {
        $userId = (int) auth()['id'];
        $taskId = (int) $id;
        $text   = trim($_POST['text'] ?? '');

        if ($text === '') {
            $this->renderChecklistState($taskId, t('tasks.validation.checklist_text_required'));
            return;
        }

        if (mb_strlen($text) > 500) {
            $this->renderChecklistState($taskId, t('tasks.validation.checklist_text_max'));
            return;
        }

        try {
            $this->service->addChecklistItem($taskId, $text, $userId);
            $this->hxToast(t('tasks.checklist.item_added'), 'success', ['source' => 'tasks-checklist']);
            $this->renderChecklistState($taskId);
        } catch (\RuntimeException $e) {
            $this->renderChecklistState($taskId, $e->getMessage());
        } catch (\Throwable $e) {
            $this->renderChecklistState($taskId, t('tasks.validation.checklist_save_error'));
        }
    }

    public function toggleChecklist(string $id, string $cid): void
    {
        $userId = (int) auth()['id'];
        $taskId = (int) $id;

        try {
            $this->service->toggleChecklistItem($taskId, (int) $cid, $userId);
            $this->hxToast(t('tasks.checklist.updated'), 'info', ['source' => 'tasks-checklist']);
            $this->renderChecklistState($taskId);
        } catch (\RuntimeException $e) {
            $this->renderChecklistState($taskId, $e->getMessage());
        } catch (\Throwable $e) {
            $this->renderChecklistState($taskId, t('tasks.validation.checklist_update_error'));
        }
    }

    public function deleteChecklist(string $id, string $cid): void
    {
        $userId = (int) auth()['id'];
        $taskId = (int) $id;

        try {
            $this->service->deleteChecklistItem($taskId, (int) $cid, $userId);
            $this->hxToast(t('tasks.checklist.item_removed'), 'warning', ['source' => 'tasks-checklist']);
            $this->renderChecklistState($taskId);
        } catch (\RuntimeException $e) {
            $this->renderChecklistState($taskId, $e->getMessage());
        } catch (\Throwable $e) {
            $this->renderChecklistState($taskId, t('tasks.validation.checklist_remove_error'));
        }
    }

    // ── Tags ─────────────────────────────────────────────────────────

    public function tags(): void
    {
        $userId = (int) auth()['id'];
        $tags   = $this->service->getUserTags($userId);
        $this->json($tags);
    }

    public function storeTag(): void
    {
        $userId = (int) auth()['id'];
        $clean  = $this->cleanPost(['name', 'color']);
        $name   = $clean['name'] ?? '';
        $color  = $clean['color'] ?: '#6c757d';

        if ($name === '' || mb_strlen($name) > 50) {
            $this->json(['error' => t('tasks.validation.tag_name_invalid')], 422);
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6c757d';
        }

        try {
            $tagId = $this->service->createTag($name, $color, $userId);
            $this->json(['success' => true, 'id' => $tagId]);
        } catch (\Throwable $e) {
            $this->json(['error' => t('tasks.validation.tag_exists')], 409);
        }
    }

    public function destroyTag(string $id): void
    {
        $userId = (int) auth()['id'];
        $this->service->deleteTag((int) $id, $userId);
        $this->json(['success' => true]);
    }

    // ── Search ───────────────────────────────────────────────────────

    public function search(): void
    {
        $userId = (int) auth()['id'];
        $q      = $this->cleanGet(['q'], 255)['q'] ?? '';
        $results = $this->service->search($userId, $q);

        $this->renderPartial('Tasks/Views/partials/search-results', [
            'results'    => $results,
            'q'          => $q,
            'statuses'   => TasksService::getStatuses(),
            'priorities' => TasksService::getPriorities(),
        ]);
    }

    // ── Destroy ──────────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $userId = (int) auth()['id'];
        $isAjax = $this->isHtmxRequest() || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        try {
            $this->service->delete((int) $id, $userId);
        } catch (\RuntimeException $e) {
            if ($isAjax) {
                $this->json(['error' => $e->getMessage()], 404);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('tasks.index'));
            return;
        }

        if ($isAjax) {
            $this->json(['success' => true]);
            return;
        }

        flash_success(t('tasks.flash.deleted'));
        $this->redirect(route('tasks.index'));
    }

    // ── Helpers privati ──────────────────────────────────────────────

    private function readFormData(): array
    {
        $clean = $this->cleanPost(['title', 'description', 'color']);
        $rawTagIds = $_POST['tag_ids'] ?? [];
        $tagIds = is_array($rawTagIds) ? array_map('intval', $rawTagIds) : [];
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $dueTime = trim((string) ($_POST['due_time'] ?? ''));

        if ($dueDate === '') {
            $dueDate = null;
            $dueTime = null;
        } elseif ($dueTime === '') {
            $dueTime = null;
        }

        $status = (string) ($_POST['status'] ?? 'todo');
        $priority = (string) ($_POST['priority'] ?? 'medium');

        $validStatuses = array_keys(TasksService::getStatuses());
        if (!in_array($status, $validStatuses, true)) {
            $status = 'todo';
        }

        $validPriorities = array_keys(TasksService::getPriorities());
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'medium';
        }

        return [
            'title'       => $clean['title'] ?? '',
            'description' => $clean['description'] ?? '',
            'status'      => $status,
            'priority'    => $priority,
            'due_date'    => $dueDate,
            'due_time'    => $dueTime,
            'color'       => $clean['color'] ?? null,
            'tag_ids'     => $tagIds,
        ];
    }

    private function validateForm(array $data): array
    {
        $errors = [];

        if (($data['title'] ?? '') === '') {
            $errors['title'] = [t('tasks.validation.title_required')];
        } elseif (mb_strlen($data['title']) > 255) {
            $errors['title'] = [t('tasks.validation.title_max')];
        }

        $validStatuses = array_keys(TasksService::getStatuses());
        if (!in_array($data['status'] ?? '', $validStatuses, true)) {
            $errors['status'] = [t('tasks.validation.status_invalid')];
        }

        $validPriorities = array_keys(TasksService::getPriorities());
        if (!in_array($data['priority'] ?? '', $validPriorities, true)) {
            $errors['priority'] = [t('tasks.validation.priority_invalid')];
        }

        if (!empty($data['due_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
            $errors['due_date'] = [t('tasks.validation.due_date_invalid')];
        }

        if (!empty($data['due_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['due_time'])) {
            $errors['due_time'] = [t('tasks.validation.due_time_invalid')];
        }

        if (!empty($data['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            $errors['color'] = [t('tasks.validation.color_invalid')];
        }

        return $errors;
    }

    private function renderChecklistState(int $taskId, ?string $errorMessage = null): void
    {
        try {
            $checklist = $this->service->getChecklist($taskId);
        } catch (\Throwable $e) {
            $checklist = [];
        }

        $this->renderPartial('Tasks/Views/partials/checklist', [
            'checklist'    => $checklist,
            'taskId'       => $taskId,
            'canEdit'      => has_permission('tasks.edit'),
            'errorMessage' => $errorMessage,
        ]);
    }
}
