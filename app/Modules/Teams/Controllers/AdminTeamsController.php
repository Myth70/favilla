<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Core\Controller;
use App\Modules\Teams\Services\AdminTeamsService;
use App\Traits\ControllerHelpers;

class AdminTeamsController extends Controller
{
    use ControllerHelpers;

    private AdminTeamsService $service;

    private const DEFAULT_MONTHS = 6;
    private const PER_PAGE       = 20;
    private const ALLOWED_FILTERS = ['all', 'active', 'archived', 'direct', 'group'];

    public function __construct()
    {
        $this->service = app(AdminTeamsService::class);
    }

    /**
     * Pagina principale admin Teams.
     */
    public function index(): void
    {
        $clean   = $this->cleanGet(['search', 'filter']);
        $search  = $clean['search'];
        $filter  = $this->sanitizeFilter($clean['filter'] ?: 'all');
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $data = $this->service->getIndexData($search, $filter, $page, self::PER_PAGE, self::DEFAULT_MONTHS);

        $this->render('Teams/Views/admin/index', [
            'pageTitle'     => t('teams.admin.title'),
            'breadcrumbs'   => [['label' => t('teams.admin.title')]],
            'stats'         => $data['stats'],
            'conversations' => $data['conversations'],
            'search'        => $search,
            'filter'        => $filter,
            'page'          => $page,
            'perPage'       => self::PER_PAGE,
            'total'         => $data['total'],
            'cleanupCount'  => $data['cleanupCount'],
            'defaultMonths' => self::DEFAULT_MONTHS,
        ]);
    }

    /**
     * HTMX: tabella conversazioni paginata.
     */
    public function conversationTable(): void
    {
        $clean  = $this->cleanGet(['search', 'filter']);
        $search = $clean['search'];
        $filter = $this->sanitizeFilter($clean['filter'] ?: 'all');
        $page   = max(1, (int) ($_GET['page'] ?? 1));

        $this->renderTablePartial($search, $filter, $page);
    }

    /**
     * HTMX: anteprima numero messaggi da pulire.
     */
    public function cleanupPreview(): void
    {
        $months = max(1, (int) ($_GET['months'] ?? self::DEFAULT_MONTHS));
        $count  = $this->service->getCleanupPreviewCount($months);
        echo '<span id="cleanup-preview-count" class="fw-bold text-warning">' . (int) $count . '</span>';
    }

    /**
     * POST: esegue cleanup messaggi (form classico, flash + redirect).
     */
    public function triggerCleanup(): void
    {
        $months  = max(1, (int) ($_POST['months'] ?? self::DEFAULT_MONTHS));
        $deleted = $this->service->cleanupOldMessages($months);

        flash_success(t('teams.admin.cleanup_completed', ['count' => $deleted, 'months' => $months]));
        $this->redirect(route('teams.admin.index'));
    }

    /**
     * POST HTMX: archivia una conversazione attiva.
     */
    public function archiveConversation(string $id): void
    {
        $id = (int) $id;
        $status = $this->service->archiveConversation($id, (int) ($_SESSION['user_id'] ?? 0), (string) ($_SESSION['user_name'] ?? t('teams.admin.default_admin_name')));
        if ($status === 'not_found') {
            $this->hxToast(t('teams.admin.conversation_not_found'), 'danger');
        } elseif ($status === 'already_archived') {
            $this->hxToast(t('teams.admin.already_archived'), 'warning');
        } else {
            $this->hxToast(t('teams.admin.conversation_archived'), 'success');
        }

        $this->renderTableFromPost();
    }

    /**
     * DELETE HTMX: hard-delete di una conversazione già archiviata.
     */
    public function destroy(string $id): void
    {
        $id = (int) $id;
        $status = $this->service->destroyConversation($id);
        if ($status === 'not_found') {
            $this->hxToast(t('teams.admin.conversation_not_found'), 'danger');
            $this->renderTableFromPost();
            return;
        }
        if ($status === 'must_archive_first') {
            $this->hxToast(t('teams.admin.must_archive_first'), 'warning');
            $this->renderTableFromPost();
            return;
        }

        $this->hxToast(t('teams.admin.conversation_deleted'), 'success');
        $this->renderTableFromPost();
    }

    // ── Helpers privati ───────────────────────────────────────────

    private function sanitizeFilter(string $filter): string
    {
        return in_array($filter, self::ALLOWED_FILTERS, true) ? $filter : 'all';
    }

    private function renderTableFromPost(): void
    {
        $search = trim((string) ($_POST['search'] ?? ''));
        $filter = $this->sanitizeFilter((string) ($_POST['filter'] ?? 'all'));
        $page   = max(1, (int) ($_POST['page'] ?? 1));
        $this->renderTablePartial($search, $filter, $page);
    }

    private function renderTablePartial(string $search, string $filter, int $page): void
    {
        $data = $this->service->getConversationTableData($search, $filter, $page, self::PER_PAGE);

        $this->renderPartial('Teams/Views/admin/partials/table', [
            'conversations' => $data['conversations'],
            'search'        => $search,
            'filter'        => $filter,
            'page'          => $page,
            'perPage'       => self::PER_PAGE,
            'total'         => $data['total'],
        ]);
    }
}
