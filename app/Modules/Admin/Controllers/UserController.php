<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;
use App\Services\ProfileService;
use App\Services\RoleConstraintService;
use App\Traits\ControllerHelpers;

class UserController extends Controller
{
    use ControllerHelpers;
    private AdminUserService $service;

    public function __construct()
    {
        $this->service = app(AdminUserService::class);
    }

    public function index(): void
    {
        $filters = [
            'search'    => $_GET['search'] ?? '',
            'role_id'   => $_GET['role_id'] ?? null,
            'is_active' => $_GET['is_active'] ?? null,
        ];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->service->listWithRoles($filters, $page);
        $roles  = $this->service->getRoles();

        $this->htmxOrRender(
            'Admin/Views/users/partials/table',
            'Admin/Views/users/index',
            array_merge($result, ['total_pages' => $result['lastPage']], compact('roles', 'filters'), [
                'pageTitle'   => t('admin.users.page_title'),
                'breadcrumbs' => [['label' => 'Admin', 'route' => 'admin.dashboard'], ['label' => t('admin.users.breadcrumb')]],
            ])
        );
    }

    public function show(string $id): void
    {
        $user = $this->service->findWithPermissions((int) $id);
        if (!$user) {
            flash_error(t('admin.users.flash_not_found'));
            $this->redirect(route('admin.users.index'));
            return;
        }

        $allRoles    = $this->service->getRoles();
        $userRoleIds = array_map('intval', array_column($user['roles'], 'id'));

        $profileService = app(ProfileService::class);
        $stats          = $profileService->getAccountStats((int) $id, $user['email']);
        $recentActivity = $profileService->getRecentActivity((int) $id, 10);
        $activeSessions = $profileService->getActiveSessions((int) $id);
        $twoFactorEnabled = app(\App\Services\TotpService::class)->isEnabled((int) $id);

        $this->render(
            'Admin/Views/users/show',
            ['profileUser' => $user, 'allRoles' => $allRoles, 'userRoleIds' => $userRoleIds,
             'stats' => $stats, 'recentActivity' => $recentActivity, 'activeSessions' => $activeSessions,
             'twoFactorEnabled' => $twoFactorEnabled] + [
                'pageTitle'   => $user['name'],
                'breadcrumbs' => [
                    ['label' => 'Admin', 'route' => 'admin.dashboard'],
                    ['label' => t('admin.users.breadcrumb'), 'route' => 'admin.users.index'],
                    ['label' => $user['name']],
                ],
            ]
        );
    }

    public function create(): void
    {
        $roles = $this->service->getRoles();
        $this->render('Admin/Views/users/form', [
            'profileUser' => null,
            'roles'       => $roles,
            'userRoleIds' => [],
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old'] ?? [],
            'pageTitle'   => t('admin.users.title_new'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.users.breadcrumb'), 'route' => 'admin.users.index'],
                ['label' => t('admin.users.breadcrumb_new')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function store(): void
    {
        $clean = $this->cleanPost(['name', 'email', 'username']);
        $data = [
            'name'     => $clean['name'],
            'email'    => $clean['email'],
            'username' => $clean['username'],
            'password' => $_POST['password'] ?? '',
        ];

        $validator = new Validator();
        $validator->validate($data, [
            'name'     => 'required|max:120',
            'email'    => 'required|email|max:255',
            'username' => 'required|max:64',
            'password' => 'required|min:8',
        ], [
            'name'     => t('admin.users.field_name'),
            'email'    => t('admin.users.field_email'),
            'username' => t('admin.users.field_username'),
            'password' => t('admin.users.field_password'),
        ]);
        $errors = $validator->errors();

        if (!$errors) {
            if ($this->service->emailExists($data['email'])) {
                $errors['email'] = [t('admin.users.flash_email_in_use')];
            }
            if ($this->service->usernameExists($data['username'])) {
                $errors['username'] = [t('admin.users.flash_username_in_use')];
            }
        }

        if ($errors) {
            $this->flashErrors($errors, $data, 'admin.users.create');
            return;
        }

        $roleIds = array_map('intval', $_POST['role_ids'] ?? []);

        // Utente + preferenze + ruoli in un'unica transazione.
        $userId = $this->service->createWithSetup([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'username'             => $data['username'],
            'password'             => password_hash($data['password'], PASSWORD_ARGON2ID),
            'is_active'            => 1,
            'must_change_password' => 1,
        ], $roleIds);

        AuditService::log('user_created', 'user', $userId, null, [
            'name' => $data['name'], 'email' => $data['email'], 'username' => $data['username'],
        ]);

        flash_success(t('admin.users.flash_created', ['name' => $data['name']]));
        $this->redirect(route('admin.users.show', ['id' => $userId]));
    }

    public function edit(string $id): void
    {
        $user = $this->service->findWithPermissions((int) $id);
        if (!$user) {
            flash_error(t('admin.users.flash_not_found'));
            $this->redirect(route('admin.users.index'));
            return;
        }
        $roles       = $this->service->getRoles();
        $userRoleIds = array_map('intval', array_column($user['roles'], 'id'));

        $this->render('Admin/Views/users/form', [
            'profileUser' => $user,
            'roles'       => $roles,
            'userRoleIds' => $userRoleIds,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old'] ?? [],
            'pageTitle'   => t('admin.users.breadcrumb_edit') . ' ' . $user['name'],
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.users.breadcrumb'), 'route' => 'admin.users.index'],
                ['label' => t('admin.users.breadcrumb_edit')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function update(string $id): void
    {
        $userId = (int) $id;
        $user   = $this->service->find($userId);
        if (!$user) {
            flash_error(t('admin.users.flash_not_found'));
            $this->redirect(route('admin.users.index'));
            return;
        }

        $isActive           = isset($_POST['is_active']) ? 1 : 0;
        $mustChangePassword = isset($_POST['must_change_password']) ? 1 : 0;

        $clean  = $this->cleanPost(['name', 'email', 'username']);
        $data   = [
            'name'                 => $clean['name'],
            'email'                => $clean['email'],
            'username'             => $clean['username'],
            'is_active'            => $isActive,
            'must_change_password' => $mustChangePassword,
        ];
        $validator = new Validator();
        $validator->validate($data, [
            'name'     => 'required|max:120',
            'email'    => 'required|email|max:255',
            'username' => 'required|max:64',
        ], [
            'name'     => t('admin.users.field_name'),
            'email'    => t('admin.users.field_email'),
            'username' => t('admin.users.field_username'),
        ]);
        $errors = $validator->errors();

        // Impedisce all'admin di disattivare se stesso
        $adminId = $_SESSION['user_id'] ?? null;
        if (!$isActive && (int) $adminId === $userId) {
            $errors['is_active'] = [t('admin.users.flash_cannot_deactivate_self')];
        }

        if (!$errors) {
            if ($this->service->emailExists($data['email'], $userId)) {
                $errors['email'] = [t('admin.users.flash_email_in_use_other')];
            }
            if ($this->service->usernameExists($data['username'], $userId)) {
                $errors['username'] = [t('admin.users.flash_username_in_use_other')];
            }
        }

        if ($errors) {
            $this->flashErrors($errors, $data, 'admin.users.edit', ['id' => $userId]);
            return;
        }

        $wasActive = (bool) $user['is_active'];
        $this->service->update($userId, $data);

        // Se l'utente è stato disattivato, invalida le sue sessioni e logga
        if ($wasActive && !$isActive) {
            app(\App\Services\UserService::class)->invalidateUserSessions($userId);
            AuditService::log('user_disabled', 'user', $userId, ['is_active' => 1], ['is_active' => 0]);
        } elseif (!$wasActive && $isActive) {
            AuditService::log('user_activated', 'user', $userId, ['is_active' => 0], ['is_active' => 1]);
        }

        flash_success(t('admin.users.flash_updated'));
        $this->redirect(route('admin.users.show', ['id' => $userId]));
    }

    public function destroy(string $id): void
    {
        $userId = (int) $id;

        // Impedisce all'admin di eliminare se stesso
        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            flash_error(t('admin.users.flash_cannot_delete_self'));
            $this->redirect(route('admin.users.show', ['id' => $userId]));
            return;
        }

        $user   = $this->service->find($userId);
        if (!$user) {
            flash_error(t('admin.users.flash_not_found'));
            $this->redirect(route('admin.users.index'));
            return;
        }

        $this->service->update($userId, ['deleted_at' => date('Y-m-d H:i:s')]);
        app(\App\Services\UserService::class)->invalidateUserSessions($userId);

        AuditService::log('user_deleted', 'user', $userId, [
            'name' => $user['name'], 'email' => $user['email'],
        ], null);

        flash_success(t('admin.users.flash_deleted', ['name' => $user['name']]));
        $this->redirect(route('admin.users.index'));
    }

    public function updateRoles(string $id): void
    {
        $userId = (int) $id;
        $user   = $this->service->find($userId);
        if (!$user) {
            http_response_code(404);
            return;
        }

        $userRoleIds = array_map('intval', $_POST['role_ids'] ?? []);

        // ISO 27001 A.6.1.2 — Separation of Duties validation
        try {
            $violations = app(RoleConstraintService::class)->validateRoles($userRoleIds);
            if (!empty($violations)) {
                $msgs = array_map(fn ($v) => $v['role1_name'] . ' + ' . $v['role2_name'] . ': ' . $v['reason'], $violations);
                $errorMsg = t('admin.users.flash_sod', ['msgs' => implode('; ', $msgs)]);
                if ($this->isHtmxRequest()) {
                    $allRoles = $this->service->getRoles();
                    $currentRoleIds = $this->service->getUserRoleIds($userId);
                    header('HX-Trigger: ' . json_encode(['notify' => ['message' => $errorMsg, 'type' => 'danger']]));
                    $this->renderPartial(
                        'Admin/Views/users/partials/roles',
                        ['profileUser' => $user, 'allRoles' => $allRoles, 'userRoleIds' => array_map('intval', $currentRoleIds)]
                    );
                    return;
                }
                flash_error($errorMsg);
                $this->redirect(route('admin.users.show', ['id' => $userId]));
                return;
            }
        } catch (\Throwable) {
            // SoD table may not exist yet — skip validation
        }

        $this->service->syncRoles($userId, $userRoleIds);
        app(\App\Services\UserService::class)->invalidateUserSessions($userId);

        AuditService::log('roles_updated', 'user', $userId, null, ['role_ids' => $userRoleIds]);

        if ($this->isHtmxRequest()) {
            $allRoles = $this->service->getRoles();
            $this->renderPartial(
                'Admin/Views/users/partials/roles',
                ['profileUser' => $user, 'allRoles' => $allRoles, 'userRoleIds' => $userRoleIds]
            );
            return;
        }

        flash_success(t('admin.users.flash_roles_updated'));
        $this->redirect(route('admin.users.show', ['id' => $userId]));
    }

    public function resetPassword(string $id): void
    {
        $userId = (int) $id;

        // Rate limit (concern di trasporto): max 3 reset password al minuto per sessione admin.
        if (!$this->withinResetRateLimit()) {
            $msg = t('admin.users.flash_reset_rate_limit');
            if ($this->isHtmxRequest()) {
                echo '<div class="alert alert-danger">' . e($msg) . '</div>';
            } else {
                flash_error($msg);
                $this->redirect(route('admin.users.show', ['id' => $userId]));
            }
            return;
        }

        $result = $this->service->resetPassword($userId);

        if (!$result['success']) {
            if ($this->isHtmxRequest()) {
                echo '<div class="alert alert-danger">' . e($result['error']) . '</div>';
            } else {
                flash_error($result['error']);
                $this->redirect(route('admin.users.show', ['id' => $userId]));
            }
            return;
        }

        $user = $result['user'];
        $temp = $result['temp_password'];

        if ($this->isHtmxRequest()) {
            echo '<div class="alert alert-success">';
            echo '<strong>' . e(t('admin.users.flash_temp_pw_label', ['name' => $user['name']])) . '</strong><br>';
            echo '<code class="fs-5">' . e($temp) . '</code><br>';
            echo '<small class="text-muted">' . e(t('admin.users.flash_temp_pw_note')) . '</small>';
            echo '</div>';
            return;
        }

        flash_success(t('admin.users.flash_reset_done', ['name' => $user['name']]));
        $_SESSION['_temp_password'] = $temp;
        $this->redirect(route('admin.users.show', ['id' => $userId]));
    }

    /**
     * Rate limit di trasporto per il reset password: max 3 al minuto per sessione admin.
     * Ritorna false se la soglia è superata; altrimenti registra il tentativo.
     */
    private function withinResetRateLimit(): bool
    {
        $key         = '_reset_pw_timestamps';
        $now         = time();
        $window      = 60;
        $maxAttempts = 3;

        $timestamps = array_filter(
            $_SESSION[$key] ?? [],
            static fn ($t) => ($now - $t) < $window
        );

        if (count($timestamps) >= $maxAttempts) {
            $_SESSION[$key] = $timestamps;
            return false;
        }

        $timestamps[]   = $now;
        $_SESSION[$key] = $timestamps;
        return true;
    }

    public function toggleActive(string $id): void
    {
        $userId = (int) $id;
        $user   = $this->service->find($userId);
        if (!$user) {
            http_response_code(404);
            return;
        }

        // Impedisce all'admin di disattivare se stesso
        if ((bool) $user['is_active'] && $userId === (int) ($_SESSION['user_id'] ?? 0)) {
            header('HX-Trigger: ' . json_encode(['notify' => ['message' => t('admin.users.flash_cannot_deactivate_self'), 'type' => 'danger']]));
            http_response_code(400);
            return;
        }

        $newState = (int) !$user['is_active'];
        $this->service->update($userId, ['is_active' => $newState]);

        if (!$newState) {
            app(\App\Services\UserService::class)->invalidateUserSessions($userId);
        }

        $action = $newState ? 'user_activated' : 'user_disabled';
        AuditService::log($action, 'user', $userId, null, ['is_active' => $newState]);

        // Notifica utente riattivato
        if ($newState) {
            try {
                NotificationService::dispatchEventToUser(
                    'admin.user_reactivated',
                    'Admin',
                    $userId,
                    [
                        'user_id'    => $userId,
                        'activated_by' => (int) ($_SESSION['user_id'] ?? 0) ?: null,
                    ],
                    route('profile'),
                    (int) ($_SESSION['user_id'] ?? 0) ?: null
                );
            } catch (\Throwable $e) {
                app_log('error', 'Notification error (toggleActive): ' . $e->getMessage());
            }
        }

        $message = $newState
            ? t('admin.users.flash_activated', ['name' => $user['name']])
            : t('admin.users.flash_deactivated', ['name' => $user['name']]);
        $type  = $newState ? 'success' : 'warning';
        header('HX-Trigger: ' . json_encode(['notify' => ['message' => $message, 'type' => $type]]));
        header('HX-Refresh: true');
        http_response_code(204);
    }

    /**
     * Admin-force logout: revoke all active sessions for a user.
     * Accessible via POST /admin/users/{id}/revoke-sessions (HTMX).
     */
    public function revokeSessions(string $id): void
    {
        $userId = (int) $id;

        // Prevent admin from revoking their own sessions via this route
        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            header('HX-Trigger: ' . json_encode(['notify' => ['message' => t('admin.users.flash_cannot_revoke_own'), 'type' => 'danger']]));
            http_response_code(400);
            return;
        }

        $user = $this->service->find($userId);
        if (!$user) {
            http_response_code(404);
            return;
        }

        $profileService = app(\App\Services\ProfileService::class);
        $count          = $profileService->revokeAllSessions($userId);

        AuditService::log('sessions_revoked', 'user', $userId, null, ['session_count' => $count]);

        if ($this->isHtmxRequest()) {
            echo '<span class="badge bg-secondary">0</span>';
            header('HX-Trigger: ' . json_encode([
                'notify' => ['message' => t('admin.users.flash_sessions_revoked', ['name' => $user['name'], 'count' => $count]).($count === 0 ? t('admin.users.flash_sessions_revoked_none') : ''), 'type' => 'success'],
            ]));
            return;
        }

        flash_success(t('admin.users.flash_sessions_revoked', ['name' => $user['name'], 'count' => $count]));
        $this->redirect(route('admin.users.show', ['id' => $userId]));
    }

    /**
     * Admin-reset del 2FA: azzera secret + backup code dell'utente che ha perso
     * il dispositivo. Riusa TotpService::disable() (che registra l'audit mfa_disabled
     * con l'admin come attore). Accessibile via POST /admin/users/{id}/reset-2fa.
     */
    public function resetTotp(string $id): void
    {
        $userId = (int) $id;
        $user   = $this->service->find($userId);
        if (!$user) {
            http_response_code(404);
            return;
        }

        $totp = app(\App\Services\TotpService::class);
        if (!$totp->isEnabled($userId)) {
            $msg = t('admin.users.flash_no_2fa', ['name' => $user['name']]);
            if ($this->isHtmxRequest()) {
                header('HX-Trigger: ' . json_encode(['notify' => ['message' => $msg, 'type' => 'warning']]));
                http_response_code(400);
                return;
            }
            flash_error($msg);
            $this->redirect(route('admin.users.show', ['id' => $userId]));
            return;
        }

        $totp->disable($userId);

        $msg = t('admin.users.flash_reset_2fa_done', ['name' => $user['name']]);
        if ($this->isHtmxRequest()) {
            header('HX-Trigger: ' . json_encode(['notify' => ['message' => $msg, 'type' => 'success']]));
            header('HX-Refresh: true');
            http_response_code(204);
            return;
        }

        flash_success($msg);
        $this->redirect(route('admin.users.show', ['id' => $userId]));
    }

    public function bulk(): void
    {
        $action = $_POST['action'] ?? '';
        $rawIds = $_POST['user_ids'] ?? [];
        $ids    = is_array($rawIds) ? $rawIds : [];
        $selfId = (int) ($_SESSION['user_id'] ?? 0);

        if (empty($ids) || !in_array($action, ['activate', 'deactivate', 'assign_role'], true)) {
            $this->json(['success' => false, 'message' => t('admin.users.flash_bulk_invalid')], 400);
            return;
        }

        $roleId = $action === 'assign_role' ? (int) ($_POST['role_id'] ?? 0) : null;

        $result = $this->service->bulkAction($action, $ids, $selfId, $roleId);
        $status = $result['status'] ?? 200;
        unset($result['status']);

        $this->json($result, $status);
    }
}
