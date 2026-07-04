<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Services\DataRetentionService;
use App\Traits\ControllerHelpers;

/**
 * ISO 27001 A.18.1.3 — Data Retention Policy admin controller.
 */
class DataRetentionController extends Controller
{
    use ControllerHelpers;

    private DataRetentionService $service;

    public function __construct()
    {
        $this->service = app(DataRetentionService::class);
    }

    /**
     * GET /admin/retention — Dashboard policy retention.
     */
    public function index(): void
    {
        $policies = $this->service->allPolicies();
        $stats    = $this->service->getStats();

        $data = [
            'pageTitle'   => 'Data Retention',
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.security.breadcrumb')],
                ['label' => 'Data Retention'],
            ],
            'policies' => $policies,
            'stats'    => $stats,
        ];

        $this->render('Admin/Views/data-retention', $data);
    }

    /**
     * POST /admin/retention/{id}/update — Update policy retention days and action.
     */
    public function update(string $id): void
    {
        $id   = (int) $id;
        $data = $this->cleanPost(['retention_days', 'action']);

        $retentionDays = max(0, (int) ($data['retention_days'] ?? 30));
        $action        = $data['action'] ?? 'delete';

        $this->service->updatePolicy($id, $retentionDays, $action);

        flash_success(t('admin.retention.flash_updated'));
        header('Location: ' . route('admin.retention.index'));
        exit;
    }

    /**
     * POST /admin/retention/{id}/toggle — Toggle policy enabled/disabled.
     */
    public function toggle(string $id): void
    {
        $id     = (int) $id;
        $policy = $this->service->findPolicy($id);

        if (!$policy) {
            flash_error(t('admin.retention.flash_not_found'));
            header('Location: ' . route('admin.retention.index'));
            exit;
        }

        $this->service->togglePolicy($id, !$policy['enabled']);

        $status = $policy['enabled'] ? t('admin.retention.state_disabled') : t('admin.retention.state_enabled');
        flash_success(t('admin.retention.flash_toggled', ['entity' => $policy['entity'], 'status' => $status]));
        header('Location: ' . route('admin.retention.index'));
        exit;
    }

    /**
     * POST /admin/retention/execute — Execute all enabled policies (dry-run or real).
     */
    public function execute(): void
    {
        $data   = $this->cleanPost(['dry_run']);
        $dryRun = !empty($data['dry_run']);

        $results = $this->service->executeAll($dryRun);

        $total = array_sum(array_column($results, 'affected'));
        $errorCount = count(array_filter($results, static fn (array $row): bool => !empty($row['error'])));
        $label = $dryRun ? t('admin.retention.run_simulated') : t('admin.retention.run_completed');

        if ($errorCount > 0) {
            flash_error(t('admin.retention.flash_errors', ['label' => $label, 'count' => $errorCount]));
        }

        if ($errorCount < count($results) || $errorCount === 0) {
            flash_success(t('admin.retention.flash_done', ['label' => $label, 'total' => $total, 'count' => count($results)]));
        }

        header('Location: ' . route('admin.retention.index'));
        exit;
    }
}
