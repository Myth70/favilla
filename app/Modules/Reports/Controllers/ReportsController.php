<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Services\ReportsDashboardService;
use App\Modules\Reports\Services\ReportsHistoryQueryService;
use App\Traits\ControllerHelpers;

class ReportsController extends Controller
{
    use ControllerHelpers;

    private ReportsDashboardService $service;
    private ReportsHistoryQueryService $historyQuery;

    public function __construct()
    {
        $this->service      = app(ReportsDashboardService::class);
        $this->historyQuery = app(ReportsHistoryQueryService::class);
    }

    // ── index — Dashboard ───────────────────────────────────────────────────

    public function index(): void
    {
        $user      = auth();
        $userId    = (int) $user['id'];
        $adminView = in_array('admin', $user['roles'] ?? [], true) || has_permission('reports.admin');

        $data          = $this->service->getDashboardData($user);
        $recentHistory = $this->historyQuery->latestForUser($userId, $adminView, 10);

        $this->render('Reports/Views/index', [
            'stats'         => $data['stats'],
            'templateCount' => $data['templateCount'],
            'sources'       => $data['sources'],
            'recentHistory' => $recentHistory,
            'pageTitle'     => t('reports.title'),
            'breadcrumbs'   => [
                ['label' => t('reports.breadcrumb.report')],
            ],
        ]);
    }

    // ── sources — JSON endpoint ─────────────────────────────────────────────

    public function sources(): void
    {
        $sources = $this->service->getSourcesForUser(auth());

        $this->json(['sources' => $sources]);
    }

    // ── sourceFields — JSON endpoint ────────────────────────────────────────

    public function sourceFields(): void
    {
        $clean     = $this->cleanGet(['module', 'source_key']);
        $module    = $clean['module'] ?? '';
        $sourceKey = $clean['source_key'] ?? '';

        if (empty($module) || empty($sourceKey)) {
            $this->json(['error' => t('reports.flash.params_required')], 400);
            return;
        }

        $fields = $this->service->getSourceFields($module, $sourceKey);

        if ($fields === null) {
            http_response_code(404);
            $this->json(['error' => t('reports.flash.source_not_found')], 404);
            return;
        }

        $this->json(['fields' => $fields]);
    }
}
