<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('admin.security.keys.title'),
    'adminIcon'     => 'fa-solid fa-key',
    'adminSubtitle' => t('admin.security.keys.subtitle'),
    'adminButtons'  => '',
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<div class="row g-3 mb-4">
    <?php foreach ($keys as $k): ?>
        <div class="col-md-6">
            <div class="card adm-card h-100 <?= $k['overdue'] && $k['present'] ? 'border-warning' : '' ?>">
                <div class="card-header adm-card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="fa-solid fa-key me-1"></i>
                        <?= e($k['key']) ?>
                    </span>
                    <?php if (!$k['present']): ?>
                        <span class="badge bg-secondary"><?= e(t('admin.security.keys.not_configured')) ?></span>
                    <?php elseif ($k['overdue']): ?>
                        <span class="badge bg-warning text-dark"><?= e(t('admin.security.keys.rotation_needed')) ?></span>
                    <?php else: ?>
                        <span class="badge bg-success"><?= e(t('admin.security.keys.valid')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?= e($k['purpose']) ?></p>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="small text-muted"><?= e(t('admin.security.keys.last_rotation')) ?></div>
                            <div class="fw-semibold">
                                <?php if ($k['last_rotated']): ?>
                                    <?= e(format_date_it($k['last_rotated'], 'compact')) ?>
                                <?php else: ?>
                                    <span class="text-warning"><?= e(t('admin.security.keys.never')) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted"><?= e(t('admin.security.keys.age')) ?></div>
                            <div class="fw-semibold">
                                <?php if ($k['age_days'] !== null): ?>
                                    <?= e(t('admin.security.keys.days', ['count' => $k['age_days']])) ?>
                                    <?php if ($k['age_days'] > $k['max_age_days']): ?>
                                        <i class="fa-solid fa-triangle-exclamation text-warning ms-1"
                                           data-bs-toggle="tooltip" title="<?= e(t('admin.security.keys.over_limit', ['max' => $k['max_age_days']])) ?>"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= e(t('admin.security.keys.na')) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted mb-1"><?= e(t('admin.security.keys.expiry', ['max' => $k['max_age_days']])) ?></div>
                        <?php
                        $pct = $k['age_days'] !== null ? min(100, round(($k['age_days'] / $k['max_age_days']) * 100)) : 100;
                        $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="progress adm-progress-thin">
                            <div class="progress-bar <?= $barClass ?> adm-progress-fill" style="--adm-progress:<?= $pct ?>%"></div>
                        </div>
                    </div>

                    <?php if ($k['present']): ?>
                        <form method="POST" action="<?= e(route('admin.security.keys.record', ['name' => $k['key']])) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                    data-app-confirm="<?= e(t('admin.security.keys.record_confirm', ['key' => $k['key']])) ?>"
                                    data-app-confirm-label="<?= e(t('admin.security.keys.confirm')) ?>"
                                    data-app-confirm-class="btn-primary"
                                    data-bs-toggle="tooltip" title="<?= e(t('admin.security.keys.record_tip')) ?>">
                                <i class="fa-solid fa-rotate me-1"></i> <?= e(t('admin.security.keys.record_btn')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Procedure Card -->
<div class="card adm-card">
    <div class="card-header adm-card-header">
        <i class="fa-solid fa-book me-1"></i>
        <span class="fw-semibold"><?= e(t('admin.security.keys.procedure_title')) ?></span>
    </div>
    <div class="card-body">
        <h6 class="fw-bold">APP_KEY</h6>
        <ol class="small mb-3">
            <li><?= t('admin.security.keys.step_generate') ?></li>
            <li><?= t('admin.security.keys.step_update_appkey') ?></li>
            <li><?= e(t('admin.security.keys.step_recrypt')) ?></li>
            <li><?= e(t('admin.security.keys.step_csrf_invalidated')) ?></li>
            <li><?= e(t('admin.security.keys.step_return')) ?></li>
        </ol>

        <h6 class="fw-bold">BACKUP_ENCRYPTION_KEY</h6>
        <ol class="small mb-3">
            <li><?= t('admin.security.keys.step_generate') ?></li>
            <li><?= t('admin.security.keys.step_update_backupkey') ?></li>
            <li><?= t('admin.security.keys.step_backup_warning') ?></li>
            <li><?= e(t('admin.security.keys.step_keep_old')) ?></li>
            <li><?= e(t('admin.security.keys.step_return')) ?></li>
        </ol>

        <div class="alert alert-info mb-0 small">
            <i class="fa-solid fa-circle-info me-1"></i>
            <?= t('admin.security.keys.frequency_note') ?>
        </div>
    </div>
</div>

</div>

<?php $view->end(); ?>
