<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\AdminDashboardService;
use App\Traits\ControllerHelpers;

class AdminDashboardController extends Controller
{
    use ControllerHelpers;

    private AdminDashboardService $service;

    public function __construct()
    {
        $this->service = app(AdminDashboardService::class);
    }

    public function index(): void
    {
        $this->render('Admin/Views/dashboard/index', [
            'pageTitle'       => t('admin.dashboard.page_title'),
            'breadcrumbs'     => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.dashboard.breadcrumb')],
            ],
            'stats'           => $this->service->getStats(),
            'unifiedTimeline' => $this->service->getUnifiedTimeline(20),
            'loginSecurity'   => $this->service->getLoginSecurityChartData(14),
            'auditDistrib'    => $this->service->getAuditTypeDistribution(7),
            'topUsers'        => $this->service->getTopActiveUsers(5, 30),
            'onlineSessions'  => $this->service->getOnlineSessions(6),
            'moduleStatus'    => $this->service->getModuleStatus(),
            'systemInfo'      => $this->service->getSystemInfo(),
        ]);
    }

    public function statsWidget(): void
    {
        $this->renderPartial('Admin/Views/dashboard/partials/stats-widget', [
            'stats' => $this->service->getStats(),
        ]);
    }

    public function recentLogs(): void
    {
        $this->renderPartial('Admin/Views/dashboard/partials/recent-logs', [
            'unifiedTimeline' => $this->service->getUnifiedTimeline(15),
        ]);
    }

    public function modulesWidget(): void
    {
        $this->renderPartial('Admin/Views/dashboard/partials/modules-widget', [
            'moduleStatus' => $this->service->getModuleStatus(),
        ]);
    }

    public function onlineWidget(): void
    {
        $this->renderPartial('Admin/Views/dashboard/partials/online-widget', [
            'onlineSessions' => $this->service->getOnlineSessions(6),
        ]);
    }
}
