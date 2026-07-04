<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Controllers;

use App\Core\Controller;
use App\Modules\HelpOnline\Services\HelpAdminService;
use App\Modules\HelpOnline\Services\HelpOnlineService;
use App\Traits\ControllerHelpers;

class HelpOnlineController extends Controller
{
    use ControllerHelpers;

    private HelpOnlineService $service;
    private HelpAdminService $adminService;

    public function __construct()
    {
        $this->service = app(HelpOnlineService::class);
        $this->adminService = app(HelpAdminService::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // User-facing endpoints
    // ─────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $clean = $this->cleanGet(['q', 'chunk'], 255);
        $query = trim((string) ($clean['q'] ?? ''));
        $entryId = isset($clean['chunk']) && $clean['chunk'] !== '' ? max(0, (int) $clean['chunk']) : null;

        $data = $this->service->getPageData($query, $entryId, '/help');

        $this->render('HelpOnline/Views/index', array_merge($data, [
            'pageTitle' => t('helponline.title'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'home.index', 'Home'), 'route' => 'home.index'],
                ['label' => t('helponline.breadcrumb_guide')],
            ],
        ]));
    }

    public function panel(): void
    {
        $clean = $this->cleanGet(['contextPath', 'pageTitle']);
        $contextPath = trim((string) ($clean['contextPath'] ?? ''));
        $pageTitle = trim((string) ($clean['pageTitle'] ?? ''));

        $this->renderPartial('HelpOnline/Views/partials/panel', $this->service->getPanelData($contextPath, $pageTitle));
    }

    public function ask(): void
    {
        $clean = $this->cleanPost(['message', 'context_path', 'page_title', 'chunk']);
        $message = trim((string) ($clean['message'] ?? ''));
        $contextPath = trim((string) ($clean['context_path'] ?? ''));
        $pageTitle = trim((string) ($clean['page_title'] ?? ''));
        $entryId = isset($clean['chunk']) && $clean['chunk'] !== '' ? max(0, (int) $clean['chunk']) : null;

        $payload = $this->service->answerQuestion(
            $message,
            (int) ($_SESSION['user_id'] ?? 0),
            $contextPath,
            $pageTitle,
            $entryId
        );

        $status = ($payload['ok'] ?? false) ? 200 : 422;
        $this->json($payload, $status);
    }

    public function feedback(): void
    {
        $clean = $this->cleanPost(['query_id', 'helpful']);
        $queryId = max(0, (int) ($clean['query_id'] ?? 0));
        $helpful = match ((string) ($clean['helpful'] ?? '')) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $accepted = $this->service->recordFeedback($queryId, $helpful, $userId);

        if (!$accepted) {
            $this->json(['ok' => false, 'message' => t('helponline.flash.feedback_rejected')], 403);
            return;
        }

        $this->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin: overview + sync
    // ─────────────────────────────────────────────────────────────────────

    public function adminIndex(): void
    {
        $this->renderAdmin('overview', $this->adminService->getAdminOverviewData());
    }

    public function sync(): void
    {
        $result = $this->adminService->sync();
        if (!($result['ok'] ?? false)) {
            flash_error((string) ($result['message'] ?? t('helponline.flash.reindex_failed')));
            $this->redirect(route('helponline.admin.index'));
            return;
        }

        $_SESSION['_flash_success'] = t('helponline.flash.reindexed', [
            'entries' => (int) ($result['entries'] ?? 0),
            'terms'   => (int) ($result['terms'] ?? 0),
        ]);
        $this->redirect(route('helponline.admin.index'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin: moduli
    // ─────────────────────────────────────────────────────────────────────

    public function adminModules(): void
    {
        $this->renderAdmin('modules', $this->adminService->getAdminModulesData());
    }

    public function adminModuleCreate(): void
    {
        $clean = $this->cleanPost([
            'module_key', 'module_name', 'label', 'description', 'audience_default',
            'locale_default', 'route_name', 'permission_slug', 'sort_order', 'is_active',
        ]);

        $result = $this->adminService->createQaModule($clean);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));
        $this->redirect(route('helponline.admin.modules'));
    }

    public function adminModuleEdit(string $id): void
    {
        $moduleId = max(0, (int) $id);
        $data = $this->adminService->getAdminModuleEditData($moduleId);
        if ($data === null) {
            flash_error(t('helponline.flash.module_not_found'));
            $this->redirect(route('helponline.admin.modules'));
            return;
        }

        $this->renderAdmin('module_edit', $data);
    }

    public function adminModuleUpdate(string $id): void
    {
        $moduleId = max(0, (int) $id);
        $clean = $this->cleanPost([
            'module_key', 'module_name', 'label', 'description', 'audience_default',
            'locale_default', 'route_name', 'permission_slug', 'sort_order', 'is_active',
        ]);
        $result = $this->adminService->updateQaModule($moduleId, $clean);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));
        $this->redirect(route('helponline.admin.modules.edit', ['id' => $moduleId]));
    }

    public function adminModuleDelete(string $id): void
    {
        $moduleId = max(0, (int) $id);
        $result = $this->adminService->deleteQaModule($moduleId);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));
        $this->redirect(route('helponline.admin.modules'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin: domande/risposte (entries)
    // ─────────────────────────────────────────────────────────────────────

    public function adminEntries(): void
    {
        $clean = $this->cleanGet(['search', 'module', 'audience']);
        $this->renderAdmin('entries', $this->adminService->getAdminEntriesData([
            'search' => trim((string) ($clean['search'] ?? '')),
            'module' => trim((string) ($clean['module'] ?? '')),
            'audience' => trim((string) ($clean['audience'] ?? '')),
        ]));
    }

    public function adminEntryCreate(): void
    {
        $clean = $this->cleanPost([
            'module_id', 'source_entry_id', 'question', 'answer_markdown', 'excerpt', 'audience', 'locale',
            'route_name', 'permission_slug', 'ranking_weight', 'sort_order', 'is_active', 'aliases',
        ]);
        $result = $this->adminService->createQaEntry($clean);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));

        if (($result['ok'] ?? false) && !empty($result['id'])) {
            $this->redirect(route('helponline.admin.entries.edit', ['id' => (int) $result['id']]));
            return;
        }

        $this->redirect(route('helponline.admin.entries'));
    }

    public function adminEntryEdit(string $id): void
    {
        $entryId = max(0, (int) $id);
        $data = $this->adminService->getAdminEntryEditData($entryId);
        if ($data === null) {
            flash_error(t('helponline.flash.entry_not_found'));
            $this->redirect(route('helponline.admin.entries'));
            return;
        }

        $this->renderAdmin('entry_edit', $data);
    }

    public function adminEntryUpdate(string $id): void
    {
        $entryId = max(0, (int) $id);
        $clean = $this->cleanPost([
            'module_id', 'source_entry_id', 'question', 'answer_markdown', 'excerpt', 'audience', 'locale',
            'route_name', 'permission_slug', 'ranking_weight', 'sort_order', 'is_active', 'aliases',
        ]);
        $result = $this->adminService->updateQaEntry($entryId, $clean);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));
        $this->redirect(route('helponline.admin.entries.edit', ['id' => $entryId]));
    }

    public function adminEntryDelete(string $id): void
    {
        $entryId = max(0, (int) $id);
        $result = $this->adminService->deleteQaEntry($entryId);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));
        $this->redirect(route('helponline.admin.entries'));
    }

    public function adminEntryAliasesSave(string $id): void
    {
        $entryId = max(0, (int) $id);
        $rawAliases = (string) ($_POST['aliases'] ?? '');
        $result = $this->adminService->saveQaEntryAliases($entryId, $rawAliases);
        $_SESSION[$result['ok'] ? '_flash_success' : '_flash_error'] = (string) ($result['message'] ?? t('helponline.flash.op_failed'));
        $this->redirect(route('helponline.admin.entries.edit', ['id' => $entryId]));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin: query analytics
    // ─────────────────────────────────────────────────────────────────────

    public function adminQueries(): void
    {
        $clean = $this->cleanGet(['search', 'module', 'status']);
        $this->renderAdmin('queries', $this->adminService->getAdminQueriesData([
            'search' => trim((string) ($clean['search'] ?? '')),
            'module' => trim((string) ($clean['module'] ?? '')),
            'status' => trim((string) ($clean['status'] ?? '')),
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Render helper
    // ─────────────────────────────────────────────────────────────────────

    private function renderAdmin(string $tab, array $data): void
    {
        if (!array_key_exists('schemaReady', $data)) {
            $data['schemaReady'] = $this->service->isSchemaReady();
        }

        $this->render('HelpOnline/Views/admin/index', array_merge($data, [
            'pageTitle' => t('helponline.admin_title'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'admin.dashboard', 'Amministrazione'), 'route' => 'admin.index'],
                ['label' => 'Help Online'],
            ],
            'activeTab' => $tab,
        ]));
    }
}
