<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Services\SecurityIncidentService;
use App\Traits\ControllerHelpers;

/**
 * ISO 27001 A.16.1 — Security incidents management in Admin panel.
 */
class SecurityIncidentController extends Controller
{
    use ControllerHelpers;

    private SecurityIncidentService $incidentService;

    public function __construct()
    {
        $this->incidentService = app(SecurityIncidentService::class);
    }

    /**
     * GET /admin/security/incidents — Main incidents list.
     */
    public function index(): void
    {
        $filters = $this->cleanGet(['type', 'severity', 'page']);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 30;

        $result = $this->incidentService->getRecent(
            $perPage,
            ($page - 1) * $perPage,
            $filters['type'] ?: null,
            $filters['severity'] ?: null
        );

        $summary = $this->incidentService->getSummary();
        $totalPages = (int) ceil($result['total'] / $perPage);

        $data = [
            'pageTitle'   => t('admin.security.incidents.title'),
            'incidents'   => $result['items'],
            'total'       => $result['total'],
            'summary'     => $summary,
            'filters'     => $filters,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.security.incidents.title')],
            ],
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Admin/Views/partials/security_incidents_table', $data);
            return;
        }

        $this->render('Admin/Views/security-incidents', $data);
    }

    /**
     * GET /admin/security/incidents/summary — HTMX partial for summary widget.
     */
    public function summaryWidget(): void
    {
        $summary = $this->incidentService->getSummary();

        $this->renderPartial('Admin/Views/partials/security_incidents_summary', [
            'summary' => $summary,
        ]);
    }
}
