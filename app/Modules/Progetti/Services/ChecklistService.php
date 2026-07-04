<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Services;

use App\Modules\Progetti\Repositories\ChecklistRepository;
use App\Modules\Progetti\Repositories\ProgettiRepository;

class ChecklistService
{
    private ChecklistRepository $repo;
    private ProgettiRepository  $projectRepo;

    public function __construct()
    {
        $this->repo        = app(ChecklistRepository::class);
        $this->projectRepo = app(ProgettiRepository::class);
    }

    // ─── Autorizzazioni ──────────────────────────────────────────────────────

    public function canManageChecklist(array $task, int $userId): bool
    {
        return has_permission('progetti.edit')
            || (int) ($task['assigned_user_id'] ?? 0) === $userId;
    }

    public function canCheckItem(array $task, int $userId): bool
    {
        // Stessa regola: edit permission oppure assegnato al task
        return has_permission('progetti.edit')
            || (int) ($task['assigned_user_id'] ?? 0) === $userId;
    }

    // ─── Lettura ─────────────────────────────────────────────────────────────

    /**
     * Restituisce tutto il necessario per renderizzare la checklist di un task.
     * Usato dal ChecklistController::getChecklist (GET endpoint).
     */
    public function getChecklist(int $projectId, int $taskId, int $userId): array
    {
        $task = $this->findTaskOrFail($projectId, $taskId);

        $items     = $this->repo->getItemsByTask($taskId);
        $total     = count($items);
        $done      = count(array_filter($items, fn ($i) => (int) $i['is_done'] === 1));
        $canManage = $this->canManageChecklist($task, $userId);
        $templates = $this->repo->getTemplatesSimple();

        return [
            'items'      => $items,
            'canManage'  => $canManage,
            'stats'      => ['total' => $total, 'done' => $done],
            'templates'  => $templates,
            'allDone'    => $total > 0 && $done === $total,
        ];
    }

    /**
     * Conteggio leggero per blocco 'done' in ProgettiService.
     */
    public function getCountsForTask(int $taskId): array
    {
        return [
            'total' => $this->repo->countItems($taskId),
            'done'  => $this->repo->countDoneItems($taskId),
        ];
    }

    // ─── Mutazioni ───────────────────────────────────────────────────────────

    public function addItem(int $projectId, int $taskId, string $label, int $userId): array
    {
        $task = $this->findTaskOrFail($projectId, $taskId);

        if (!$this->canManageChecklist($task, $userId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_checklist'));
        }

        $label = trim($label);
        if ($label === '') {
            throw new \RuntimeException(t('progetti.exception.checklist_item_empty'));
        }
        if (mb_strlen($label) > 500) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_too_long'));
        }

        $position = $this->repo->getNextPosition($taskId);
        $itemId   = $this->repo->createItem([
            'task_id'    => $taskId,
            'label'      => $label,
            'position'   => $position,
            'created_by' => $userId,
        ]);

        return $this->repo->findItem($taskId, $itemId) ?? [];
    }

    public function updateItemLabel(int $projectId, int $taskId, int $itemId, string $label, int $userId): void
    {
        $task = $this->findTaskOrFail($projectId, $taskId);

        if (!$this->canManageChecklist($task, $userId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_checklist_item'));
        }

        $item = $this->repo->findItem($taskId, $itemId);
        if (!$item) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_not_found'));
        }
        if ((int) $item['is_done'] === 1) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_locked'));
        }

        $label = trim($label);
        if ($label === '') {
            throw new \RuntimeException(t('progetti.exception.checklist_item_empty'));
        }

        $this->repo->updateItemLabel($itemId, $label);
    }

    /**
     * Spunta una voce. Regola: commento obbligatorio se è l'ultima voce rimasta.
     * Ritorna true se ora tutte le voci sono completate.
     */
    public function checkItem(
        int    $projectId,
        int    $taskId,
        int    $itemId,
        ?string $comment,
        int    $userId
    ): bool {
        $task = $this->findTaskOrFail($projectId, $taskId);

        if (!$this->canCheckItem($task, $userId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_check_item'));
        }

        $item = $this->repo->findItem($taskId, $itemId);
        if (!$item) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_not_found'));
        }
        if ((int) $item['is_done'] === 1) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_already_done'));
        }

        // Regola ultima voce: conta quante voci non sono ancora done (esclusa quella corrente)
        $allItems      = $this->repo->getItemsByTask($taskId);
        $pendingCount  = count(array_filter($allItems, fn ($i) => (int) $i['is_done'] === 0));
        $isLastPending = $pendingCount === 1;

        if ($isLastPending) {
            $comment = trim((string) ($comment ?? ''));
            if ($comment === '') {
                throw new \RuntimeException(t('progetti.exception.checklist_last_item_comment'));
            }
        } else {
            $comment = ($comment !== null && trim($comment) !== '') ? trim($comment) : null;
        }

        $this->repo->checkItem($itemId, $userId, $comment);

        // Controlla se ora tutte le voci sono completate
        $newDoneCount = $this->repo->countDoneItems($taskId);
        $total        = count($allItems);

        return $total > 0 && $newDoneCount === $total;
    }

    public function deleteItem(int $projectId, int $taskId, int $itemId, int $userId): void
    {
        $task = $this->findTaskOrFail($projectId, $taskId);

        if (!$this->canManageChecklist($task, $userId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_delete_item'));
        }

        $item = $this->repo->findItem($taskId, $itemId);
        if (!$item) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_not_found'));
        }
        if ((int) $item['is_done'] === 1) {
            throw new \RuntimeException(t('progetti.exception.checklist_item_locked_delete'));
        }

        $this->repo->deleteItem($taskId, $itemId);
    }

    public function reorderItems(int $projectId, int $taskId, array $orderedIds, int $userId): void
    {
        $task = $this->findTaskOrFail($projectId, $taskId);

        if (!$this->canManageChecklist($task, $userId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_reorder'));
        }

        // Filtra solo interi validi
        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds)));
        if (empty($orderedIds)) {
            return;
        }

        $this->repo->reorderItems($taskId, $orderedIds);
    }

    public function applyTemplate(int $projectId, int $taskId, int $templateId, int $userId): void
    {
        $task = $this->findTaskOrFail($projectId, $taskId);

        if (!$this->canManageChecklist($task, $userId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_checklist'));
        }

        $template = $this->repo->findTemplate($templateId);
        if (!$template) {
            throw new \RuntimeException(t('progetti.exception.template_not_found'));
        }

        $templateItems = $this->repo->getTemplateItems($templateId);
        if (empty($templateItems)) {
            return;
        }

        $startPosition = $this->repo->getNextPosition($taskId);
        foreach ($templateItems as $i => $tItem) {
            $this->repo->createItem([
                'task_id'    => $taskId,
                'label'      => $tItem['label'],
                'position'   => $startPosition + $i,
                'created_by' => $userId,
            ]);
        }
    }

    // ─── Template CRUD ───────────────────────────────────────────────────────

    public function getTemplates(): array
    {
        return $this->repo->getTemplates();
    }

    public function getTemplateWithItems(int $tplId): ?array
    {
        $tpl = $this->repo->findTemplate($tplId);
        if ($tpl === null) {
            return null;
        }
        $items = $this->repo->getTemplateItems($tplId);
        return [
            'id'     => (int) $tpl['id'],
            'name'   => $tpl['name'],
            'labels' => array_column($items, 'label'),
        ];
    }

    public function createTemplate(string $name, array $labelList, int $userId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException(t('progetti.exception.template_name_empty'));
        }

        $labelList = array_values(array_filter(
            array_map(fn ($l) => trim((string) $l), $labelList),
            fn ($l) => $l !== ''
        ));

        if (empty($labelList)) {
            throw new \RuntimeException(t('progetti.exception.template_needs_item'));
        }

        $tplId = $this->repo->createTemplate($name, $userId);
        foreach ($labelList as $pos => $label) {
            $this->repo->createTemplateItem($tplId, $label, $pos);
        }

        return $tplId;
    }

    public function updateTemplate(int $tplId, string $name, array $labelList): bool
    {
        $template = $this->repo->findTemplate($tplId);
        if (!$template) {
            throw new \RuntimeException(t('progetti.exception.template_not_found'));
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException(t('progetti.exception.template_name_empty'));
        }

        $labelList = array_values(array_filter(
            array_map(fn ($l) => trim((string) $l), $labelList),
            fn ($l) => $l !== ''
        ));

        if (empty($labelList)) {
            throw new \RuntimeException(t('progetti.exception.template_needs_item'));
        }

        $this->repo->updateTemplateName($tplId, $name);
        $this->repo->deleteTemplateItems($tplId);
        foreach ($labelList as $pos => $label) {
            $this->repo->createTemplateItem($tplId, $label, $pos);
        }

        return true;
    }

    public function destroyTemplate(int $tplId): bool
    {
        $template = $this->repo->findTemplate($tplId);
        if (!$template) {
            throw new \RuntimeException(t('progetti.exception.template_not_found'));
        }
        return $this->repo->softDeleteTemplate($tplId);
    }

    // ─── Helpers privati ─────────────────────────────────────────────────────

    private function findTaskOrFail(int $projectId, int $taskId): array
    {
        $task = $this->projectRepo->findTask($projectId, $taskId);
        if (!$task) {
            throw new \RuntimeException(t('progetti.exception.task_not_found'));
        }
        return $task;
    }
}
