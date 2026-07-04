<?php
// Trend logins: oggi vs ieri
$loginTrend = 0;
$yLogins = (int) ($stats['yesterday_logins'] ?? 0);
if ($yLogins > 0) {
    $loginTrend = (int) round(($stats['today_logins'] - $yLogins) / $yLogins * 100);
} elseif ((int) $stats['today_logins'] > 0) {
    $loginTrend = 100;
}
$loginTrendUp = $loginTrend >= 0;

$activeRatio = ($stats['total_users'] > 0)
    ? (int) round($stats['active_users'] / $stats['total_users'] * 100)
    : 0;

$newUsersWeek = (int) ($stats['new_users_week'] ?? 0);

$cards = [
    [
        'label'     => t('admin.stats.total_users'),
        'value'     => number_format((int) $stats['total_users']),
        'icon'      => 'fa-users',
        'color'     => 'primary',
        'sub'       => t('admin.stats.total_users_sub', ['active' => $stats['active_users'], 'inactive' => $stats['inactive_users']]),
        'progress'  => $activeRatio,
        'prog_col'  => 'success',
        'extra'     => ($newUsersWeek > 0 ? '<span class="badge bg-success bg-opacity-10 text-success adm-kpi-extra-badge">' . e(t('admin.stats.new_week', ['count' => $newUsersWeek])) . '</span>' : null),
    ],
    [
        'label'     => t('admin.stats.active_sessions'),
        'value'     => number_format((int) $stats['active_sessions']),
        'icon'      => 'fa-circle-dot',
        'color'     => 'success',
        'live'      => true,
        'sub'       => t('admin.stats.active_sessions_sub'),
    ],
    [
        'label'     => t('admin.stats.today_logins'),
        'value'     => number_format((int) $stats['today_logins']),
        'icon'      => 'fa-right-to-bracket',
        'color'     => $loginTrendUp ? 'info' : 'warning',
        'trend'     => $loginTrend,
        'trend_up'  => $loginTrendUp,
        'sub'       => t('admin.stats.today_logins_sub', ['count' => $yLogins]),
        'extra'     => ($stats['failed_logins_today'] ?? 0) > 0
            ? '<span class="badge bg-danger bg-opacity-10 text-danger adm-kpi-extra-badge">'
              . '<i class="fa-solid fa-triangle-exclamation me-1 fa-xs"></i>'
              . e(t('admin.stats.failed_today', ['count' => $stats['failed_logins_today']])) . '</span>'
            : null,
    ],
    [
        'label'     => t('admin.stats.roles'),
        'value'     => number_format((int) $stats['roles_count']),
        'icon'      => 'fa-user-tag',
        'color'     => 'warning',
        'sub'       => t('admin.stats.roles_sub'),
    ],
    [
        'label'     => t('admin.stats.modules'),
        'value'     => number_format((int) $stats['modules_count']),
        'icon'      => 'fa-cubes',
        'color'     => 'secondary',
        'sub'       => t('admin.stats.modules_sub'),
    ],
    [
        'label'     => t('admin.stats.audit'),
        'value'     => number_format((int) $stats['total_audit']),
        'icon'      => 'fa-shield-halved',
        'color'     => 'danger',
        'sub'       => t('admin.stats.audit_sub'),
    ],
];
?>
<div class="row g-3">
    <?php foreach ($cards as $card): ?>
    <div class="col-sm-6 col-lg-4 col-xl-2">
        <div class="card border-0 shadow-sm adm-kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="adm-kpi-icon bg-<?= e($card['color']) ?> bg-opacity-10 text-<?= e($card['color']) ?>">
                        <i class="fa-solid <?= e($card['icon']) ?>"></i>
                    </div>
                    <?php if (!empty($card['live'])): ?>
                        <span class="adm-live-badge">
                            <span class="adm-live-dot"></span>Live
                        </span>
                    <?php elseif (isset($card['trend'])): ?>
                        <span class="adm-trend-badge <?= $card['trend_up'] ? 'text-success' : 'text-danger' ?>"
                              data-bs-toggle="tooltip"
                              title="<?= e(t('admin.stats.trend_tip', ['trend' => ($card['trend_up'] ? '+' : '') . $card['trend']])) ?>">
                            <i class="fa-solid <?= $card['trend_up'] ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                            <?= abs($card['trend']) ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <div class="adm-kpi-value"><?= e($card['value']) ?></div>
                <div class="adm-kpi-label text-muted"><?= e($card['label']) ?></div>
                <?php if (!empty($card['progress'])): ?>
                    <div class="progress adm-kpi-progress mt-2" role="progressbar"
                         aria-valuenow="<?= (int) $card['progress'] ?>" aria-valuemin="0" aria-valuemax="100"
                         data-bs-toggle="tooltip" title="<?= e(t('admin.stats.active_tip', ['count' => (int) $card['progress']])) ?>">
                        <div class="progress-bar bg-<?= e($card['prog_col'] ?? 'primary') ?> adm-progress-fill"
                             style="--adm-progress:<?= min(100, (int) $card['progress']) ?>%"></div>
                    </div>
                <?php endif; ?>
                <div class="small text-muted mt-1"><?= e($card['sub']) ?></div>
                <?php if (!empty($card['extra'])): ?>
                    <div class="mt-1"><?= $card['extra'] ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>


