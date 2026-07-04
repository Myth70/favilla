<?php
/**
 * HTMX partial: users table + pagination.
 * Variables: $items, $total, $page, $per_page, $total_pages, $roles, $filters
 */
use App\Modules\Auth\Helpers\AvatarHelper;

$currentUserId  = (int) ($_SESSION['user_id'] ?? 0);
$canView        = has_permission('admin.users.view');
$canEdit        = has_permission('admin.users.edit');
$canImpersonate = has_permission('admin.users.impersonate');
$canNotify      = has_permission('notifications.admin.send');
?>
<div class="card">
    <div class="card-body p-2">
        <?php if (empty($items)): ?>
        <div class="adm-empty py-5">
            <i class="fa-solid fa-users fa-2x mb-2 text-muted"></i>
            <span class="text-muted"><?= e(t('admin.users.empty')) ?></span>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle adm-table">
                <thead>
                    <tr>
                        <?php if (has_permission('admin.users.edit')): ?>
                        <th class="adm-col-check">
                            <input type="checkbox" class="form-check-input" id="bulk-select-all"
                                   data-bs-toggle="tooltip" title="<?= e(t('admin.users.select_all_tip')) ?>">
                        </th>
                        <?php endif; ?>
                        <th class="adm-col-avatar"></th>
                        <th><?= e(t('admin.users.col_user')) ?></th>
                        <th><?= e(t('admin.users.col_roles')) ?></th>
                        <th><?= e(t('admin.users.col_status')) ?></th>
                        <th><?= e(t('admin.users.col_last_login')) ?></th>
                        <th class="text-end"><?= e(t('admin.users.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $u):
                        $avatarUrl = AvatarHelper::url($u['avatar_path'] ?? null);
                        $initials  = AvatarHelper::initials($u['name']);
                        $isAdmin   = str_contains($u['roles_slugs'] ?? '', 'admin');
                        $canNotifyThis = $canNotify && (bool) $u['is_active'];
                        $canImpersonateThis = $canImpersonate
                            && (int) $u['id'] !== $currentUserId
                            && !$isAdmin
                            && $u['is_active'];
                    ?>
                    <tr class="<?= !$u['is_active'] ? 'adm-row-inactive' : '' ?> adm-clickable-row"
                        <?php if ($canView): ?>data-href="<?= e(route('admin.users.show', ['id' => $u['id']])) ?>"<?php endif; ?>>
                        <?php if (has_permission('admin.users.edit')): ?>
                        <td class="adm-col-check" onclick="event.stopPropagation()">
                            <input type="checkbox" class="form-check-input adm-bulk-check"
                                   value="<?= (int) $u['id'] ?>"
                                   <?= (int) $u['id'] === $currentUserId ? 'disabled title="' . e(t('admin.users.current_account')) . '"' : '' ?>>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if ($avatarUrl): ?>
                            <img src="<?= e($avatarUrl) ?>" alt="" class="adm-user-avatar-sm">
                            <?php else: ?>
                            <span class="adm-user-initials-sm"><?= e($initials) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-semibold">
                                    <?php if ($canView): ?>
                                    <a href="<?= e(route('admin.users.show', ['id' => $u['id']])) ?>" class="text-decoration-none text-body">
                                        <?= e($u['name']) ?>
                                    </a>
                                    <?php else: ?>
                                        <?= e($u['name']) ?>
                                    <?php endif; ?>
                                    <?php if ($u['must_change_password']): ?>
                                        <i class="fa-solid fa-key text-warning ms-1 small" data-bs-toggle="tooltip" title="<?= e(t('admin.users.change_pw_tip')) ?>"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="text-muted small"><?= e($u['email']) ?> &middot; <code class="small"><?= e($u['username']) ?></code></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['roles_list']): ?>
                                <?php foreach (explode(', ', $u['roles_list']) as $role): ?>
                                <span class="badge adm-role-badge"><?= e($role) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted small"><?= e(t('admin.users.no_roles')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="adm-status-dot adm-status-active" data-bs-toggle="tooltip" title="<?= e(t('admin.users.active')) ?>"></span>
                                <span class="small text-success"><?= e(t('admin.users.active')) ?></span>
                            <?php else: ?>
                                <span class="adm-status-dot adm-status-inactive" data-bs-toggle="tooltip" title="<?= e(t('admin.users.inactive')) ?>"></span>
                                <span class="small text-muted"><?= e(t('admin.users.inactive')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?php if (!empty($u['last_login'])): ?>
                                <?= e(format_date($u['last_login'], 'relative')) ?>
                            <?php else: ?>
                                <span class="text-muted"><?= e(t('admin.users.never')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($canImpersonateThis): ?>
                            <form method="POST" action="<?= e(route('admin.users.impersonate', ['id' => $u['id']])) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-icon adm-action-btn"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.users.impersonate')) ?>"
                                        data-app-confirm="<?= e(t('admin.users.impersonate_confirm', ['name' => $u['name']])) ?>">
                                    <i class="fa-solid fa-user-secret"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($canNotifyThis): ?>
                            <a href="<?= e(route('admin.notifications.send')) ?>?user_id=<?= (int) $u['id'] ?>"
                               class="btn btn-sm btn-icon adm-action-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.users.notify')) ?>">
                                <i class="fa-solid fa-bell"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canView): ?>
                            <a href="<?= e(route('admin.users.show', ['id' => $u['id']])) ?>"
                               class="btn btn-sm btn-icon adm-action-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.users.detail')) ?>">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <a href="<?= e(route('admin.users.edit', ['id' => $u['id']])) ?>"
                               class="btn btn-sm btn-icon adm-action-btn adm-action-primary" data-bs-toggle="tooltip" title="<?= e(t('admin.users.edit')) ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php $view->include('partials/pagination', array_merge(get_defined_vars(), [
        'routeName' => 'admin.users.index',
        'hxTarget'  => '#users-table',
        'label'     => t('admin.users.pagination_label'),
    ])); ?>
</div>
