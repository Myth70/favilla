<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Controllers;

use App\Core\Controller;
use App\Modules\Progetti\Services\AdminProgettiService;
use App\Traits\ControllerHelpers;

class AdminProgettiController extends Controller
{
    use ControllerHelpers;

    private const PER_PAGE = 20;
    private const ALLOWED_SCOPES = ['active', 'trash'];
    private const ALLOWED_STATUS = ['', 'planning', 'active', 'on_hold', 'completed', 'cancelled'];
    private const ALLOWED_SORTS = ['updated_at', 'created_at', 'name', 'status', 'end_date', 'budget_planned'];

    private AdminProgettiService $service;

    public function __construct()
    {
        $this->service = app(AdminProgettiService::class);
    }

    public function index(): void
    {
        $this->renderAdminPage('active');
    }

    public function trash(): void
    {
        $this->renderAdminPage('trash');
    }

    public function table(): void
    {
        $filters = $this->collectFilters();
        $page = max(1, (int) ($this->cleanGet(['page'])['page'] ?? 1));

        $data = $this->service->getTableData($filters, $page, self::PER_PAGE);

        $this->renderPartial('Progetti/Views/admin/partials/table', [
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $page,
            'pages' => (int) ceil($data['total'] / self::PER_PAGE),
            'perPage' => self::PER_PAGE,
            'filters' => $filters,
            'statusLabels' => $this->getStatusLabels(),
        ]);
    }

    public function moveToTrash(string $id): void
    {
        $projectId = (int) $id;
        $actorUserId = (int) (auth()['id'] ?? 0);

        if ($projectId <= 0 || !$this->service->moveToTrash($projectId, $actorUserId)) {
            flash_error(t('progetti.flash.trash_not_found'));
            $this->redirectBack(route('projects.admin.index'));
            return;
        }

        flash_success(t('progetti.flash.trash_moved'));
        $this->redirectBack(route('projects.admin.index'));
    }

    public function restore(string $id): void
    {
        $projectId = (int) $id;

        if ($projectId <= 0 || !$this->service->restoreFromTrash($projectId)) {
            flash_error(t('progetti.flash.trash_restore_not_found'));
            $this->redirectBack(route('projects.admin.trash'));
            return;
        }

        flash_success(t('progetti.flash.trash_restored'));
        $this->redirectBack(route('projects.admin.trash'));
    }

    public function purge(string $id): void
    {
        $projectId = (int) $id;

        if ($projectId <= 0 || !$this->service->purgeFromTrash($projectId)) {
            flash_error(t('progetti.flash.trash_restore_not_found'));
            $this->redirectBack(route('projects.admin.trash'));
            return;
        }

        flash_success(t('progetti.flash.trash_purged'));
        $this->redirectBack(route('projects.admin.trash'));
    }

    private function renderAdminPage(string $defaultScope): void
    {
        $filters = $this->collectFilters($defaultScope);
        $page = max(1, (int) ($this->cleanGet(['page'])['page'] ?? 1));

        $table = $this->service->getTableData($filters, $page, self::PER_PAGE);
        $stats = $this->service->getStats();
        $owners = $this->service->getOwnerOptions();

        $viewData = [
            'items' => $table['items'],
            'total' => $table['total'],
            'page' => $page,
            'pages' => (int) ceil($table['total'] / self::PER_PAGE),
            'perPage' => self::PER_PAGE,
            'filters' => $filters,
            'stats' => $stats,
            'owners' => $owners,
            'statusLabels' => $this->getStatusLabels(),
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Progetti/Views/admin/partials/table', $viewData);
            return;
        }

        $this->render('Progetti/Views/admin/index', array_merge($viewData, [
            'pageTitle' => t('progetti.admin.title'),
            'breadcrumbs' => [
                ['label' => t('progetti.breadcrumb.index'), 'route' => 'projects.index'],
                ['label' => t('progetti.breadcrumb.admin')],
            ],
        ]));
    }

    private function collectFilters(?string $fallbackScope = null): array
    {
        $clean = $this->cleanGet(['q', 'status', 'owner_id', 'scope', 'sort', 'dir']);

        // cleanGet() ritorna sempre una stringa (mai null): '' significa "non passato
        // in query string", nel qual caso si usa lo scope di default della pagina chiamante.
        $scopeParam = (string) ($clean['scope'] ?? '');
        $scope = $scopeParam !== '' ? $scopeParam : ($fallbackScope ?? 'active');
        if (!in_array($scope, self::ALLOWED_SCOPES, true)) {
            $scope = 'active';
        }

        $status = (string) ($clean['status'] ?? '');
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            $status = '';
        }

        $sort = (string) ($clean['sort'] ?? 'updated_at');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'updated_at';
        }

        $dir = strtolower((string) ($clean['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return [
            'q' => trim((string) ($clean['q'] ?? '')),
            'status' => $status,
            'owner_id' => (int) ($clean['owner_id'] ?? 0),
            'scope' => $scope,
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    private function getStatusLabels(): array
    {
        return \App\Modules\Progetti\Services\ProgettiService::getProjectStatuses();
    }

    private function redirectBack(string $fallback): void
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $parts = parse_url($referer);
            $sameHost = isset($parts['host']) && $parts['host'] === ($_SERVER['HTTP_HOST'] ?? '');
            $path = (string) ($parts['path'] ?? '');
            if ($sameHost && str_starts_with($path, '/admin/projects')) {
                $this->redirect($referer);
                return;
            }
        }

        $this->redirect($fallback);
    }
}
