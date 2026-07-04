<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$heroButtons = '';
if (has_permission('admin.security.manage')) {
    $heroButtons .= '<form method="POST" action="' . e(route('admin.security.logs.rotate')) . '" class="d-inline">'
        . csrf_field()
        . '<button type="submit" class="btn btn-sm btn-outline-light me-1" data-bs-toggle="tooltip" title="' . e(t('admin.security.logs.rotate_tip')) . '">'
        . '<i class="fa-solid fa-rotate me-1"></i>' . e(t('admin.security.logs.rotate_now')) . '</button></form>';
    $heroButtons .= '<form method="POST" action="' . e(route('admin.security.logs.purge')) . '" class="d-inline">'
        . csrf_field()
        . '<button type="submit" class="btn btn-sm btn-outline-light" data-bs-toggle="tooltip" title="' . e(t('admin.security.logs.purge_tip')) . '">'
        . '<i class="fa-solid fa-broom me-1"></i>' . e(t('admin.security.logs.purge')) . '</button></form>';
}

$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('admin.security.logs.title'),
    'adminIcon'     => 'fa-solid fa-scroll',
    'adminSubtitle' => t('admin.security.logs.subtitle'),
    'adminButtons'  => $heroButtons,
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<!-- Status Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value adm-stat-date"><?= e(\App\Services\LogRotationService::humanSize($logStatus['active_size'])) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.security.logs.active_log')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value"><?= e($logStatus['rotated_count']) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.security.logs.rotated_files')) ?></div>
            <small class="text-muted"><?= e(\App\Services\LogRotationService::humanSize($logStatus['rotated_size'])) ?></small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value"><?= e($logStatus['retention_days']) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.security.logs.retention_days')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <?php
            $intIcon = 'fa-solid fa-check-circle text-success';
            $intLabel = t('admin.security.logs.ok');
            if ($verification['invalid'] > 0) {
                $intIcon = 'fa-solid fa-exclamation-triangle text-danger';
                $intLabel = t('admin.security.logs.invalid_count', ['count' => $verification['invalid']]);
            } elseif ($verification['total'] === 0) {
                $intIcon = 'fa-solid fa-minus-circle text-muted';
                $intLabel = t('admin.security.logs.na');
            }
            ?>
            <div class="adm-stat-value"><i class="<?= $intIcon ?>"></i></div>
            <div class="adm-stat-label"><?= e(t('admin.security.logs.integrity', ['label' => $intLabel])) ?></div>
        </div>
    </div>
</div>

<!-- Log File Integrity Table -->
<?php if ($verification['total'] > 0): ?>
<div class="card adm-card mb-4">
    <div class="card-header adm-card-header d-flex align-items-center justify-content-between">
        <div>
            <i class="fa-solid fa-fingerprint me-1"></i>
            <strong><?= e(t('admin.security.logs.integrity_title')) ?></strong>
            <span class="badge bg-secondary ms-1"><?= e(t('admin.security.logs.files_count', ['count' => $verification['total']])) ?></span>
        </div>
        <div>
            <span class="badge bg-success"><?= e(t('admin.security.logs.valid_count', ['count' => $verification['valid']])) ?></span>
            <?php if ($verification['invalid'] > 0): ?>
                <span class="badge bg-danger"><?= e(t('admin.security.logs.invalid_count', ['count' => $verification['invalid']])) ?></span>
            <?php endif; ?>
            <?php if ($verification['missing_hash'] > 0): ?>
                <span class="badge bg-warning text-dark"><?= e(t('admin.security.logs.missing_hmac_count', ['count' => $verification['missing_hash']])) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table adm-table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>File</th>
                        <th><?= e(t('admin.security.logs.col_status')) ?></th>
                        <th><?= e(t('admin.security.logs.col_notes')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verification['results'] as $r): ?>
                    <tr>
                        <td><code><?= e($r['file']) ?></code></td>
                        <td>
                            <?php if ($r['valid']): ?>
                                <span class="badge bg-success">
                                    <i class="fa-solid fa-check me-1"></i><?= e(t('admin.security.logs.file_valid')) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="fa-solid fa-times me-1"></i><?= e(t('admin.security.logs.file_invalid')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e($r['error'] ?? '') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    <?= e(t('admin.security.logs.no_rotated')) ?>
</div>
<?php endif; ?>

<!-- Archive Range -->
<?php if ($logStatus['oldest'] || $logStatus['newest']): ?>
<div class="card adm-card">
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-6">
                <div class="text-muted small mb-1"><?= e(t('admin.security.logs.oldest')) ?></div>
                <code><?= e($logStatus['oldest'] ?? '-') ?></code>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1"><?= e(t('admin.security.logs.newest')) ?></div>
                <code><?= e($logStatus['newest'] ?? '-') ?></code>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<?php $view->end(); ?>
