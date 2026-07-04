<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Services\AuditService;
use App\Services\RoleConstraintService;
use App\Traits\ControllerHelpers;

/**
 * ISO 27001 A.6.1.2 — Separation of Duties admin management.
 */
class RoleConstraintController extends Controller
{
    use ControllerHelpers;

    private RoleConstraintService $service;

    public function __construct()
    {
        $this->service = app(RoleConstraintService::class);
    }

    /**
     * GET /admin/security/sod — Separation of Duties dashboard.
     */
    public function index(): void
    {
        $constraints = $this->service->allConstraints();
        $violations  = $this->service->findViolations();
        $stats       = $this->service->getStats();
        $roles       = $this->service->getRolesList();

        $data = [
            'constraints' => $constraints,
            'violations'  => $violations,
            'stats'       => $stats,
            'roles'       => $roles,
            'pageTitle'   => t('admin.sod.page_title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.sod.breadcrumb_security'), 'route' => 'admin.security.incidents'],
                ['label' => t('admin.sod.breadcrumb')],
            ],
        ];

        $this->render('Admin/Views/security-sod', $data);
    }

    /**
     * POST /admin/security/sod/create — Create new constraint.
     */
    public function store(): void
    {
        $clean = $this->cleanPost(['role_id_1', 'role_id_2', 'reason']);

        $roleId1 = (int) ($clean['role_id_1'] ?? 0);
        $roleId2 = (int) ($clean['role_id_2'] ?? 0);
        $reason  = trim($clean['reason'] ?? '');

        if ($roleId1 <= 0 || $roleId2 <= 0 || $roleId1 === $roleId2) {
            flash_error(t('admin.sod.flash_select_two'));
            $this->redirect(route('admin.security.sod'));
            return;
        }

        if (empty($reason)) {
            flash_error(t('admin.sod.flash_reason_required'));
            $this->redirect(route('admin.security.sod'));
            return;
        }

        try {
            $id = $this->service->createConstraint($roleId1, $roleId2, $reason);
            AuditService::log('sod_constraint_created', 'role_constraint', $id, null, [
                'role_id_1' => $roleId1,
                'role_id_2' => $roleId2,
                'reason'    => $reason,
            ]);
            flash_success(t('admin.sod.flash_created'));
        } catch (\Throwable $e) {
            flash_error(t('admin.sod.flash_create_error'));
        }

        $this->redirect(route('admin.security.sod'));
    }

    /**
     * POST /admin/security/sod/{id}/toggle — Toggle constraint.
     */
    public function toggle(string $id): void
    {
        $constraintId = (int) $id;
        $constraint = $this->service->findConstraint($constraintId);

        if (!$constraint) {
            flash_error(t('admin.sod.flash_not_found'));
            $this->redirect(route('admin.security.sod'));
            return;
        }

        $this->service->toggleConstraint($constraintId);
        $newState = $constraint['enabled'] ? t('admin.sod.state_disabled') : t('admin.sod.state_enabled');

        AuditService::log('sod_constraint_toggled', 'role_constraint', $constraintId, null, [
            'new_state' => $newState,
        ]);

        flash_success(t('admin.sod.flash_toggled', ['state' => $newState]));
        $this->redirect(route('admin.security.sod'));
    }

    /**
     * POST /admin/security/sod/{id}/delete — Delete constraint.
     */
    public function delete(string $id): void
    {
        $constraintId = (int) $id;
        $constraint = $this->service->findConstraint($constraintId);

        if (!$constraint) {
            flash_error(t('admin.sod.flash_not_found'));
            $this->redirect(route('admin.security.sod'));
            return;
        }

        $this->service->deleteConstraint($constraintId);

        AuditService::log('sod_constraint_deleted', 'role_constraint', $constraintId, [
            'role1' => $constraint['role1_name'],
            'role2' => $constraint['role2_name'],
        ], null);

        flash_success(t('admin.sod.flash_deleted'));
        $this->redirect(route('admin.security.sod'));
    }
}
