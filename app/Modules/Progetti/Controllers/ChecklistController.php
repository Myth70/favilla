<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Controllers;

use App\Core\Controller;
use App\Modules\Progetti\Services\ChecklistService;
use App\Modules\Progetti\Services\ProgettiService;
use App\Traits\ControllerHelpers;

class ChecklistController extends Controller
{
    use ControllerHelpers;

    private ChecklistService $service;
    private ProgettiService  $projectService;

    public function __construct()
    {
        $this->service        = app(ChecklistService::class);
        $this->projectService = app(ProgettiService::class);
    }

    // ─── Checklist items ─────────────────────────────────────────────────────

    /**
     * GET /projects/{id}/tasks/{taskId}/checklist
     * Ritorna JSON con items, stats, templates e canManage.
     */
    public function getChecklist(string $id, string $taskId): void
    {
        $userId = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        try {
            $data = $this->service->getChecklist((int) $id, (int) $taskId, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 404);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.load_error')], 500);
            return;
        }

        $this->json(['ok' => true] + $data);
    }

    /**
     * POST /projects/{id}/tasks/{taskId}/checklist
     * Aggiunge una nuova voce.
     */
    public function storeItem(string $id, string $taskId): void
    {
        $userId = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        $clean = $this->cleanPost(['label']);
        $label = trim((string) ($clean['label'] ?? ''));

        try {
            $item = $this->service->addItem((int) $id, (int) $taskId, $label, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.add_item_error')], 500);
            return;
        }

        $this->json(['ok' => true, 'item' => $item]);
    }

    /**
     * PUT /projects/{id}/tasks/{taskId}/checklist/{itemId}
     * Modifica il testo di una voce non ancora completata.
     */
    public function updateItem(string $id, string $taskId, string $itemId): void
    {
        $userId = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        $clean = $this->cleanPost(['label']);
        $label = trim((string) ($clean['label'] ?? ''));

        try {
            $this->service->updateItemLabel((int) $id, (int) $taskId, (int) $itemId, $label, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.edit_item_error')], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /**
     * POST /projects/{id}/tasks/{taskId}/checklist/{itemId}/done
     * Spunta una voce. Richiede commento se è l'ultima voce rimasta.
     */
    public function checkItem(string $id, string $taskId, string $itemId): void
    {
        $userId  = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        $clean = $this->cleanPost(['comment']);
        $comment = $clean['comment'] ?? null;
        if ($comment !== null) {
            $comment = trim($comment);
            if ($comment === '') {
                $comment = null;
            }
        }

        try {
            $allDone = $this->service->checkItem(
                (int) $id,
                (int) $taskId,
                (int) $itemId,
                $comment,
                $userId
            );
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.check_item_error')], 500);
            return;
        }

        $this->json(['ok' => true, 'allDone' => $allDone]);
    }

    /**
     * DELETE /projects/{id}/tasks/{taskId}/checklist/{itemId}
     */
    public function destroyItem(string $id, string $taskId, string $itemId): void
    {
        $userId = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        try {
            $this->service->deleteItem((int) $id, (int) $taskId, (int) $itemId, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.delete_item_error')], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /**
     * POST /projects/{id}/tasks/{taskId}/checklist/reorder
     * body: order[] = [id1, id2, ...]
     */
    public function reorderItems(string $id, string $taskId): void
    {
        $userId = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        $orderedIds = $_POST['order'] ?? [];
        if (!is_array($orderedIds)) {
            $orderedIds = [];
        }
        $orderedIds = array_slice($orderedIds, 0, 1000);
        $orderedIds = array_values(array_filter(
            array_map('intval', $orderedIds),
            fn ($v) => $v > 0
        ));

        try {
            $this->service->reorderItems((int) $id, (int) $taskId, $orderedIds, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.reorder_error')], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /**
     * POST /projects/{id}/tasks/{taskId}/checklist/from-template
     * body: template_id = N
     */
    public function applyTemplate(string $id, string $taskId): void
    {
        $userId = (int) auth()['id'];
        if (!$this->findProject((int) $id, $userId)) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.project_not_found')], 403);
            return;
        }

        $clean = $this->cleanPost(['template_id']);
        $templateId = (int) ($clean['template_id'] ?? 0);
        if ($templateId <= 0) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.select_valid_template')], 422);
            return;
        }

        try {
            $this->service->applyTemplate((int) $id, (int) $taskId, $templateId, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.apply_template_error')], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    // ─── Template CRUD ───────────────────────────────────────────────────────

    /**
     * GET /projects/checklist-templates
     */
    public function listTemplates(): void
    {
        $userId = (int) auth()['id'];

        // Permesso garantito da RoleMiddleware::withPermission('progetti.edit') in routes.php
        $templates = $this->service->getTemplates();
        $this->render('Progetti/Views/checklist_templates', [
            'pageTitle'   => t('progetti.breadcrumb.checklist_templates'),
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => t('progetti.breadcrumb.checklist_templates')],
            ],
            'templates'   => $templates,
        ]);
    }

    /**
     * GET /projects/checklist-templates/{tplId}
     * Ritorna JSON con nome e voci del modello (usato dal modal di modifica).
     */
    public function showTemplate(string $tplId): void
    {
        $data = $this->service->getTemplateWithItems((int) $tplId);
        if ($data === null) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.template_not_found')], 404);
            return;
        }
        $this->json(['ok' => true, 'template' => $data]);
    }

    /**
     * POST /projects/checklist-templates
     */
    public function storeTemplate(): void
    {
        // Permesso garantito da RoleMiddleware::withPermission('progetti.edit') in routes.php
        $userId = (int) auth()['id'];
        $clean  = $this->cleanPost(['name']);
        $name   = trim((string) ($clean['name'] ?? ''));
        $labels = $_POST['labels'] ?? [];
        if (!is_array($labels)) {
            $labels = [];
        }
        $labels = array_slice($labels, 0, 500);
        $labels = array_values(array_filter(
            array_map(fn ($l) => trim(strip_tags((string) $l)), $labels),
            fn ($l) => $l !== ''
        ));

        try {
            $tplId = $this->service->createTemplate($name, $labels, $userId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.create_template_error')], 500);
            return;
        }

        $this->json(['ok' => true, 'id' => $tplId, 'message' => t('progetti.checklist_templates.template_created')]);
    }

    /**
     * PUT /projects/checklist-templates/{tplId}
     */
    public function updateTemplate(string $tplId): void
    {
        // Permesso garantito da RoleMiddleware::withPermission('progetti.edit') in routes.php
        $clean  = $this->cleanPost(['name']);
        $name   = trim((string) ($clean['name'] ?? ''));
        $labels = $_POST['labels'] ?? [];
        if (!is_array($labels)) {
            $labels = [];
        }
        $labels = array_slice($labels, 0, 500);
        $labels = array_values(array_filter(
            array_map(fn ($l) => trim(strip_tags((string) $l)), $labels),
            fn ($l) => $l !== ''
        ));

        try {
            $this->service->updateTemplate((int) $tplId, $name, $labels);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.update_template_error')], 500);
            return;
        }

        $this->json(['ok' => true, 'message' => t('progetti.checklist_templates.template_updated')]);
    }

    /**
     * DELETE /projects/checklist-templates/{tplId}
     */
    public function destroyTemplate(string $tplId): void
    {
        // Permesso garantito da RoleMiddleware::withPermission('progetti.edit') in routes.php
        try {
            $this->service->destroyTemplate((int) $tplId);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => t('progetti.checklist.delete_template_error')], 500);
            return;
        }

        $this->json(['ok' => true, 'message' => t('progetti.checklist_templates.template_deleted')]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function findProject(int $projectId, int $userId): mixed
    {
        return $this->projectService->findForUser($projectId, $userId);
    }
}
