<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('admin.security.hardening.title'),
    'adminIcon'     => 'fa-solid fa-shield-halved',
    'adminSubtitle' => t('admin.security.hardening.subtitle'),
    'adminButtons'  => '<a href="' . e(route('healthcheck.index')) . '" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-heartbeat me-1"></i> ' . e(t('admin.security.hardening.healthcheck_btn')) . '</a>',
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

    <?php if (!empty($checks)): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="adm-stat-box text-center h-100">
                <div class="adm-stat-value text-success"><?= e($summary['ok'] ?? 0) ?></div>
                <div class="adm-stat-label"><?= e(t('admin.security.hardening.cfg_ok')) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="adm-stat-box text-center h-100">
                <div class="adm-stat-value text-warning"><?= e($summary['warn'] ?? 0) ?></div>
                <div class="adm-stat-label"><?= e(t('admin.security.hardening.cfg_warn')) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="adm-stat-box text-center h-100">
                <div class="adm-stat-value text-danger"><?= e($summary['fail'] ?? 0) ?></div>
                <div class="adm-stat-label"><?= e(t('admin.security.hardening.cfg_fail')) ?></div>
            </div>
        </div>
    </div>

    <div class="card adm-card mb-4">
        <div class="card-header adm-card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-list-check text-muted"></i>
            <span class="fw-semibold"><?= e(t('admin.security.hardening.checks_title')) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table adm-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="adm-col-40"><?= e(t('admin.security.hardening.col_status')) ?></th>
                            <th><?= e(t('admin.security.hardening.col_directive')) ?></th>
                            <th><?= e(t('admin.security.hardening.col_detail')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $check): ?>
                        <tr>
                            <td>
                                <?php if ($check['status'] === 'ok'): ?>
                                    <span class="badge bg-success rounded-pill" data-bs-toggle="tooltip" title="<?= e(t('admin.security.hardening.tip_ok')) ?>"><i class="fa-solid fa-check"></i></span>
                                <?php elseif ($check['status'] === 'warn'): ?>
                                    <span class="badge bg-warning text-dark rounded-pill" data-bs-toggle="tooltip" title="<?= e(t('admin.security.hardening.tip_warn')) ?>"><i class="fa-solid fa-exclamation"></i></span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill" data-bs-toggle="tooltip" title="<?= e(t('admin.security.hardening.tip_fail')) ?>"><i class="fa-solid fa-times"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-medium font-monospace"><?= e($check['name']) ?></td>
                            <td class="text-muted"><?= e($check['detail']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card adm-card">
        <div class="card-header adm-card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-book text-muted"></i>
            <span class="fw-semibold"><?= e(t('admin.security.hardening.recommendations')) ?></span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3"><?= t('admin.security.hardening.recommendations_intro') ?></p>
            <div class="bg-body-secondary rounded p-3 font-monospace small">
                <div class="mb-1">expose_php = Off</div>
                <div class="mb-1">display_errors = Off</div>
                <div class="mb-1">log_errors = On</div>
                <div class="mb-1">allow_url_include = Off</div>
                <div class="mb-1">allow_url_fopen = Off</div>
                <div class="mb-1">open_basedir = /var/www/html:/tmp</div>
                <div class="mb-1">disable_functions = exec,passthru,shell_exec,system,proc_open,popen</div>
                <div class="mb-1">session.cookie_httponly = 1</div>
                <div class="mb-1">session.cookie_secure = 1</div>
                <div class="mb-1">session.cookie_samesite = Strict</div>
                <div class="mb-1">session.use_strict_mode = 1</div>
                <div class="mb-1">max_execution_time = 60</div>
                <div class="mb-1">memory_limit = 256M</div>
                <div class="mb-1">upload_max_filesize = 50M</div>
                <div class="mb-1">post_max_size = 55M</div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <span class="badge bg-info text-dark"><i class="fa-solid fa-info-circle me-1"></i> XAMPP</span>
                <span class="text-muted small"><?= t('admin.security.hardening.xampp_note') ?></span>
            </div>
        </div>
    </div>
</div>

<?php $view->end(); ?>
