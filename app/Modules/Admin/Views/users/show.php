<?php
/**
 * Admin user detail — redesigned.
 * Variables: $view, $profileUser, $allRoles, $userRoleIds, $stats, $recentActivity, $activeSessions
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');

use App\Modules\Auth\Helpers\AvatarHelper;

$isActive   = (bool) $profileUser['is_active'];
$avatarUrl  = AvatarHelper::url($profileUser['avatar_path'] ?? null);
$initials   = AvatarHelper::initials($profileUser['name']);
$roleNames  = array_column($profileUser['roles'] ?? [], 'name');
$roleSlugs  = array_column($profileUser['roles'] ?? [], 'slug');
$targetIsAdmin  = in_array('admin', $roleSlugs, true);
$canEdit        = has_permission('admin.users.edit');
$canImpersonate = has_permission('admin.users.impersonate')
    && (int) $profileUser['id'] !== (int) auth()['id']
    && !$targetIsAdmin
    && $isActive;
$canNotify = has_permission('notifications.admin.send');

$adminHeroButtons = '<a href="' . e(route('admin.users.index')) . '" class="btn btn-sm btn-outline-secondary">'
    . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('admin.users.show_back_list')) . '</a>';
if ($canEdit) {
    $adminHeroButtons .= ' <a href="' . e(route('admin.users.edit', ['id' => $profileUser['id']])) . '" class="btn btn-sm btn-primary">'
        . '<i class="fa-solid fa-pen me-1"></i>' . e(t('admin.users.edit')) . '</a>';
}
if ($canImpersonate) {
    $adminHeroButtons .= ' <form method="POST" action="' . e(route('admin.users.impersonate', ['id' => $profileUser['id']])) . '" class="d-inline-block">'
        . csrf_field()
        . '<button type="submit" class="btn btn-sm btn-outline-secondary" data-app-confirm="' . e(t('admin.users.impersonate_confirm', ['name' => $profileUser['name']])) . '">'
        . '<i class="fa-solid fa-user-secret me-1"></i>' . e(t('admin.users.impersonate')) . '</button></form>';
}
if ($canNotify) {
    $adminHeroButtons .= ' <a href="' . e(route('admin.notifications.send')) . '?user_id=' . (int) $profileUser['id'] . '" class="btn btn-sm btn-outline-info">'
        . '<i class="fa-solid fa-bell me-1"></i>' . e(t('admin.users.notify_short')) . '</a>';
}
?>

<div class="container-fluid">

    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-user-shield',
        'adminTitle'    => t('admin.users.show_title'),
        'adminSubtitle' => e($profileUser['name']) . ' &middot; @' . e($profileUser['username']) . ' &middot; ' . e($profileUser['email']),
        'adminButtons'  => $adminHeroButtons,
    ]); ?>

    <!-- Riepilogo utente -->
    <div class="adm-user-hero mb-4">
        <div class="d-flex flex-column flex-md-row align-items-center gap-3">
            <div class="adm-user-avatar-lg-wrap">
                <?php if ($avatarUrl): ?>
                <img src="<?= e($avatarUrl) ?>" alt="" class="adm-user-avatar-lg">
                <?php else: ?>
                <span class="adm-user-initials-lg"><?= e($initials) ?></span>
                <?php endif; ?>
                <span class="adm-user-status-indicator <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"
                      data-bs-toggle="tooltip" title="<?= $isActive ? e(t('admin.users.active')) : e(t('admin.users.inactive')) ?>"></span>
            </div>
            <div class="text-center text-md-start flex-grow-1">
                <h2 class="mb-1 fw-bold"><?= e($profileUser['name']) ?></h2>
                <p class="text-muted mb-2">
                    <code>@<?= e($profileUser['username']) ?></code>
                    &middot;
                    <a href="mailto:<?= e($profileUser['email']) ?>" class="text-muted text-decoration-none"><?= e($profileUser['email']) ?></a>
                </p>
                <div class="d-flex flex-wrap gap-1 justify-content-center justify-content-md-start">
                    <?php if (!empty($roleNames)): ?>
                        <?php foreach ($roleNames as $role): ?>
                        <span class="badge adm-role-badge"><?= e($role) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge bg-light text-muted border"><?= e(t('admin.users.no_roles')) ?></span>
                    <?php endif; ?>
                    <?php if ($profileUser['must_change_password']): ?>
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-key me-1"></i><?= e(t('admin.users.change_pw_badge')) ?></span>
                    <?php endif; ?>
                    <?php if (!$isActive): ?>
                        <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i><?= e(t('admin.users.inactive_badge')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="adm-mini-stat">
                <div class="adm-mini-stat-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <div class="adm-mini-stat-value"><?= number_format($stats['days_registered'] ?? 0) ?></div>
                    <div class="adm-mini-stat-label"><?= e(t('admin.users.stat_days')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="adm-mini-stat">
                <div class="adm-mini-stat-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-right-to-bracket"></i>
                </div>
                <div>
                    <div class="adm-mini-stat-value"><?= number_format($stats['total_logins'] ?? 0) ?></div>
                    <div class="adm-mini-stat-label"><?= e(t('admin.users.stat_logins')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="adm-mini-stat">
                <div class="adm-mini-stat-icon bg-info-subtle text-info">
                    <i class="fa-solid fa-file-arrow-up"></i>
                </div>
                <div>
                    <div class="adm-mini-stat-value"><?= number_format($stats['files_uploaded'] ?? 0) ?></div>
                    <div class="adm-mini-stat-label"><?= e(t('admin.users.stat_files')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="adm-mini-stat">
                <div class="adm-mini-stat-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <div>
                    <div class="adm-mini-stat-value"><?= number_format($stats['messages_sent'] ?? 0) ?></div>
                    <div class="adm-mini-stat-label"><?= e(t('admin.users.stat_messages')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Left column: info + actions -->
        <div class="col-lg-4">

            <!-- Profile card -->
            <div class="card adm-card mb-4">
                <div class="card-header adm-card-header d-flex align-items-center">
                    <span class="app-card-icon me-2"><i class="fa-solid fa-circle-info"></i></span>
                    <span class="fw-semibold"><?= e(t('admin.users.account_details')) ?></span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><?= e(t('admin.users.id')) ?></span>
                            <code>#<?= (int) $profileUser['id'] ?></code>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><?= e(t('admin.users.status')) ?></span>
                            <?php if ($isActive): ?>
                            <span class="badge bg-success-subtle text-success"><?= e(t('admin.users.active')) ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger"><?= e(t('admin.users.inactive')) ?></span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><?= e(t('admin.users.registered')) ?></span>
                            <span class="small"><?= e(format_date($profileUser['created_at'], 'long')) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><?= e(t('admin.users.last_update')) ?></span>
                            <span class="small"><?= e(format_date($profileUser['updated_at'], 'relative')) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><?= e(t('admin.users.active_sessions')) ?></span>
                            <span class="badge bg-secondary" id="sessions-badge"><?= count($activeSessions) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="text-muted small"><?= e(t('admin.users.twofa')) ?></span>
                            <?php if (!empty($twoFactorEnabled)): ?>
                            <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-shield-halved me-1"></i><?= e(t('admin.users.twofa_active')) ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary"><?= e(t('admin.users.twofa_inactive')) ?></span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick actions -->
            <?php if ($canEdit): ?>
            <div class="card adm-card mb-4">
                <div class="card-header adm-card-header d-flex align-items-center">
                    <span class="app-card-icon me-2"><i class="fa-solid fa-bolt"></i></span>
                    <span class="fw-semibold"><?= e(t('admin.users.quick_actions')) ?></span>
                </div>
                <div class="card-body d-grid gap-2">
                    <button class="btn btn-outline-warning btn-sm"
                            hx-post="<?= e(route('admin.users.reset-password', ['id' => $profileUser['id']])) ?>"
                            hx-target="#reset-pw-result"
                            hx-swap="innerHTML"
                            hx-vals='{"_token": "<?= csrf_token() ?>"}'>
                        <i class="fa-solid fa-key me-1"></i> <?= e(t('admin.users.gen_temp_pw')) ?>
                    </button>
                    <div id="reset-pw-result"></div>

                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                            hx-post="<?= e(route('admin.users.toggle-active', ['id' => $profileUser['id']])) ?>"
                            hx-swap="none"
                            hx-vals='{"_token": "<?= csrf_token() ?>"}'>
                        <?= $isActive
                            ? '<i class="fa-solid fa-ban me-1"></i> ' . e(t('admin.users.deactivate_user'))
                            : '<i class="fa-solid fa-check me-1"></i> ' . e(t('admin.users.reactivate_user')) ?>
                    </button>

                    <button class="btn btn-sm btn-outline-warning"
                            hx-post="<?= e(route('admin.users.revoke-sessions', ['id' => $profileUser['id']])) ?>"
                            hx-target="#sessions-badge"
                            hx-vals='{"_token": "<?= csrf_token() ?>"}'
                            data-app-confirm="<?= e(t('admin.users.revoke_confirm', ['name' => $profileUser['name']])) ?>">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> <?= e(t('admin.users.force_logout')) ?>
                    </button>

                    <?php if (!empty($twoFactorEnabled)): ?>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="<?= e(route('admin.users.reset-2fa', ['id' => $profileUser['id']])) ?>"
                            hx-swap="none"
                            hx-vals='{"_token": "<?= csrf_token() ?>"}'
                            data-app-confirm="<?= e(t('admin.users.reset_2fa_confirm', ['name' => $profileUser['name']])) ?>">
                        <i class="fa-solid fa-shield-halved me-1"></i> <?= e(t('admin.users.reset_2fa')) ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (has_permission('admin.users.delete')): ?>
            <div class="card border-danger mb-4">
                <div class="card-body py-2">
                    <form method="post"
                          action="<?= e(route('admin.users.destroy', ['id' => $profileUser['id']])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                                data-app-confirm="<?= e(t('admin.users.delete_confirm')) ?>">
                            <i class="fa-solid fa-trash me-1"></i> <?= e(t('admin.users.delete_user')) ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column: roles + activity -->
        <div class="col-lg-8">

            <!-- Roles card -->
            <div id="roles-card" class="mb-4">
                <?php $view->include('Admin/Views/users/partials/roles', [
                    'profileUser' => $profileUser,
                    'allRoles'    => $allRoles,
                    'userRoleIds' => $userRoleIds,
                ]); ?>
            </div>

            <!-- Recent activity -->
            <div class="card adm-card mb-4">
                <div class="card-header adm-card-header d-flex align-items-center justify-content-between">
                    <span class="d-flex align-items-center gap-2"><span class="app-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="fw-semibold"><?= e(t('admin.users.recent_activity')) ?></span></span>
                    <?php if (has_permission('admin.logs.view')): ?>
                    <a href="<?= e(route('admin.logs.index')) ?>?user_id=<?= (int) $profileUser['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> <?= e(t('admin.users.all_logs')) ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentActivity)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa-solid fa-clock fa-lg mb-2 d-block"></i>
                        <?= e(t('admin.users.no_activity')) ?>
                    </div>
                    <?php else: ?>
                    <div class="adm-timeline">
                        <?php foreach ($recentActivity as $act): ?>
                        <div class="adm-timeline-item">
                            <div class="adm-timeline-dot bg-<?= e($act['meta']['color'] ?? 'secondary') ?>">
                                <i class="<?= e($act['meta']['icon'] ?? 'fa-solid fa-circle-dot') ?>"></i>
                            </div>
                            <div class="adm-timeline-content">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="fw-medium"><?= e($act['meta']['label'] ?? $act['action']) ?></span>
                                        <?php if (!empty($act['entity'])): ?>
                                        <span class="text-muted small ms-1">(<?= e($act['entity']) ?><?= $act['entity_id'] ? ' #' . (int) $act['entity_id'] : '' ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted text-nowrap ms-2"><?= e(format_date($act['created_at'], 'relative')) ?></small>
                                </div>
                                <?php if (!empty($act['ip'])): ?>
                                <span class="text-muted small"><i class="fa-solid fa-globe me-1"></i><?= e($act['ip']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active sessions -->
            <?php if (!empty($activeSessions)): ?>
            <div class="card adm-card">
                <div class="card-header adm-card-header d-flex align-items-center">
                    <span class="app-card-icon me-2"><i class="fa-solid fa-desktop"></i></span>
                    <span class="fw-semibold"><?= e(t('admin.users.sessions_card')) ?></span>
                    <span class="badge bg-success-subtle text-success ms-2"><?= count($activeSessions) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle adm-table">
                            <thead>
                                <tr>
                                    <th><?= e(t('admin.users.col_device')) ?></th>
                                    <th><?= e(t('admin.users.col_ip')) ?></th>
                                    <th><?= e(t('admin.users.col_last_use')) ?></th>
                                    <th><?= e(t('admin.users.col_expires')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeSessions as $sess):
                                    $ua = $sess['parsed_ua'] ?? ['browser' => t('admin.users.unknown_browser'), 'browser_icon' => 'fa-globe', 'os' => ''];
                                ?>
                                <tr>
                                    <td class="small">
                                        <i class="<?= e($ua['browser_icon'] ?? 'fa-solid fa-globe') ?> me-1 text-muted"></i>
                                        <?= e($ua['browser'] ?? t('admin.users.unknown_browser')) ?>
                                        <?php if (!empty($ua['os'])): ?>
                                        <span class="text-muted"> / <?= e($ua['os']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><code><?= e($sess['ip'] ?? '—') ?></code></td>
                                    <td class="small text-muted"><?= e(format_date($sess['last_activity'], 'relative')) ?></td>
                                    <td class="small text-muted"><?= e(format_date($sess['expires_at'], 'compact')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php $view->end(); ?>
