<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\RoleService;
use App\Traits\ControllerHelpers;
use InvalidArgumentException;
use RuntimeException;

class RoleController extends Controller
{
    use ControllerHelpers;

    private RoleService $service;

    public function __construct()
    {
        $this->service = app(RoleService::class);
    }

    public function index(): void
    {
        $roles = $this->service->listWithUserCount();

        $this->render('Admin/Views/roles/index', [
            'roles'       => $roles,
            'pageTitle'   => t('admin.roles.page_title'),
            'breadcrumbs' => [['label' => 'Admin', 'route' => 'admin.dashboard'], ['label' => t('admin.roles.breadcrumb')]],
        ]);
    }

    /**
     * HTMX partial: roles table for embedding in the Users & Roles tab.
     */
    public function rolesTable(): void
    {
        $roles = $this->service->listWithUserCount();

        $this->renderPartial('Admin/Views/roles/partials/table', [
            'roles' => $roles,
        ]);
    }

    public function create(): void
    {
        $this->render('Admin/Views/roles/form', [
            'role'        => null,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old'] ?? [],
            'pageTitle'   => t('admin.roles.title_new'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.roles.breadcrumb'), 'route' => 'admin.roles.index'],
                ['label' => t('admin.roles.breadcrumb_new')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function store(): void
    {
        $clean  = $this->cleanPost(['name', 'slug', 'description']);
        $data   = ['name' => $clean['name'], 'slug' => $clean['slug']];
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = t('admin.roles.flash_name_required');
        }
        if (empty($data['slug'])) {
            $errors['slug'] = t('admin.roles.flash_slug_required');
        }
        if (!empty($data['slug']) && !preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            $errors['slug'] = t('admin.roles.flash_slug_format');
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $data;
            $this->redirect(route('admin.roles.create'));
            return;
        }

        try {
            $this->service->create(array_merge($data, ['description' => $clean['description']]));
        } catch (InvalidArgumentException $e) {
            $_SESSION['_errors'] = ['slug' => $e->getMessage()];
            $_SESSION['_old']    = $data;
            $this->redirect(route('admin.roles.create'));
            return;
        }

        flash_success(t('admin.roles.flash_created', ['name' => $data['name']]));
        $this->redirect(route('admin.roles.index'));
    }

    public function edit(string $id): void
    {
        $roleId = (int) $id;

        try {
            $role = $this->service->findOrFail($roleId);
        } catch (RuntimeException) {
            flash_error(t('admin.roles.flash_not_found'));
            $this->redirect(route('admin.roles.index'));
            return;
        }

        $permissions = $this->service->getPermissionsPayload($roleId);
        $userCount   = $this->service->countUsersForRole($roleId);

        $this->render('Admin/Views/roles/form', [
            'role'        => $role,
            'grouped'     => $permissions['grouped'],
            'assignedIds' => $permissions['assignedIds'],
            'userCount'   => $userCount,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old'] ?? [],
            'pageTitle'   => t('admin.roles.breadcrumb_edit') . ' ' . $role['name'],
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.roles.breadcrumb'), 'route' => 'admin.roles.index'],
                ['label' => t('admin.roles.breadcrumb_edit')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function update(string $id): void
    {
        $roleId = (int) $id;
        $clean  = $this->cleanPost(['name', 'description']);
        $data   = ['name' => $clean['name'], 'description' => $clean['description']];
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = t('admin.roles.flash_name_required');
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $data;
            $this->redirect(route('admin.roles.edit', ['id' => $roleId]));
            return;
        }

        try {
            $this->service->update($roleId, $data);
        } catch (RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('admin.roles.index'));
            return;
        }

        flash_success(t('admin.roles.flash_updated'));
        $this->redirect(route('admin.roles.index'));
    }

    public function destroy(string $id): void
    {
        $roleId = (int) $id;

        try {
            $this->service->delete($roleId);
        } catch (RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('admin.roles.index'));
            return;
        }

        flash_success(t('admin.roles.flash_deleted'));
        $this->redirect(route('admin.roles.index'));
    }

    public function cloneRole(string $id): void
    {
        $roleId = (int) $id;

        try {
            $newId = $this->service->cloneRole($roleId);
        } catch (RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('admin.roles.index'));
            return;
        }

        flash_success(t('admin.roles.flash_cloned'));
        $this->redirect(route('admin.roles.edit', ['id' => $newId]));
    }

    public function permissions(string $id): void
    {
        // Backward-compatible route: permissions page is now unified in edit.
        $this->redirect(route('admin.roles.edit', ['id' => (int) $id]) . '#permissions');
    }

    public function updatePermissions(string $id): void
    {
        $roleId        = (int) $id;
        $permissionIds = array_map('intval', $_POST['permission_ids'] ?? []);

        try {
            $this->service->updatePermissions($roleId, $permissionIds);
        } catch (RuntimeException $e) {
            if ($this->isHtmxRequest()) {
                header('HX-Trigger: ' . json_encode(['notify' => ['message' => $e->getMessage(), 'type' => 'danger']]));
                http_response_code(422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('admin.roles.edit', ['id' => $roleId]) . '#permissions');
            return;
        }

        if ($this->isHtmxRequest()) {
            header('HX-Trigger: ' . json_encode(['notify' => ['message' => t('admin.roles.flash_perms_updated'), 'type' => 'success']]));
            http_response_code(204);
            return;
        }

        flash_success(t('admin.roles.flash_perms_updated'));
        $this->redirect(route('admin.roles.edit', ['id' => $roleId]) . '#permissions');
    }
}
