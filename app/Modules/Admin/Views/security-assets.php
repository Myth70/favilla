<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$totalPkgs = count($packages) + count($devPackages);
$vulnCount = count($audit['advisories'] ?? []);

$heroButtons = '<span class="badge bg-light text-dark">'
    . '<i class="fa-solid fa-cube me-1"></i>'
    . e(t('admin.security.assets.packages_count', ['count' => $totalPkgs])) . '</span>';

if ($vulnCount > 0) {
    $heroButtons .= ' <span class="badge bg-danger">'
        . '<i class="fa-solid fa-bug me-1"></i>'
        . e(t('admin.security.assets.vuln_count', ['count' => $vulnCount])) . '</span>';
}

$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('admin.security.assets.title'),
    'adminIcon'     => 'fa-solid fa-boxes-stacked',
    'adminSubtitle' => t('admin.security.assets.subtitle'),
    'adminButtons'  => $heroButtons,
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value adm-stat-date"><?= e($phpVersion) ?></div>
            <div class="adm-stat-label">PHP</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value"><?= e(count($phpExtensions)) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.security.assets.php_extensions')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value"><?= e(count($packages)) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.security.assets.composer_packages')) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value <?= $vulnCount > 0 ? 'text-danger' : 'text-success' ?>"><?= e($vulnCount) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.security.assets.vulnerabilities')) ?></div>
        </div>
    </div>
</div>

<!-- Vulnerabilities Section -->
<?php if ($vulnCount > 0): ?>
<div class="card adm-card mb-4">
    <div class="card-header bg-danger text-white d-flex align-items-center">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong><?= e(t('admin.security.assets.vuln_detected')) ?></strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table adm-table table-hover mb-0">
                <thead>
                    <tr>
                        <th><?= e(t('admin.security.assets.col_package')) ?></th>
                        <th>CVE</th>
                        <th><?= e(t('admin.security.assets.col_title')) ?></th>
                        <th><?= e(t('admin.security.assets.col_severity')) ?></th>
                        <th><?= e(t('admin.security.assets.col_affected')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit['advisories'] as $adv): ?>
                    <tr>
                        <td><code><?= e($adv['package']) ?></code></td>
                        <td>
                            <?php if (!empty($adv['link'])): ?>
                                <a href="<?= e($adv['link']) ?>" target="_blank" rel="noopener"><?= e($adv['cve'] ?: t('admin.security.assets.details')) ?></a>
                            <?php else: ?>
                                <?= e($adv['cve'] ?: '-') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($adv['title']) ?></td>
                        <td>
                            <?php
                            $sevClass = match($adv['severity'] ?? '') {
                                'critical' => 'bg-danger',
                                'high'     => 'bg-warning text-dark',
                                'medium'   => 'bg-info text-dark',
                                default    => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?= $sevClass ?>"><?= e(ucfirst($adv['severity'] ?? 'N/D')) ?></span>
                        </td>
                        <td><small><?= e($adv['affected']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($audit['available']): ?>
<div class="alert alert-success d-flex align-items-center mb-4" role="alert">
    <i class="fa-solid fa-shield-check me-2"></i>
    <div><?= e(t('admin.security.assets.no_vuln')) ?></div>
</div>
<?php endif; ?>

<?php if (!empty($audit['error'])): ?>
<div class="alert alert-warning mb-4" role="alert">
    <i class="fa-solid fa-exclamation-triangle me-2"></i>
    <?= e($audit['error']) ?>
</div>
<?php endif; ?>

<!-- Production Packages -->
<div class="card adm-card mb-4">
    <div class="card-header adm-card-header d-flex align-items-center justify-content-between">
        <div>
            <i class="fa-solid fa-cube me-1"></i>
            <strong><?= e(t('admin.security.assets.prod_packages')) ?></strong>
            <span class="badge bg-secondary ms-1"><?= e(count($packages)) ?></span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table adm-table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?= e(t('admin.security.assets.col_package')) ?></th>
                        <th><?= e(t('admin.security.assets.col_version')) ?></th>
                        <th><?= e(t('admin.security.assets.col_license')) ?></th>
                        <th><?= e(t('admin.security.assets.col_description')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td><code><?= e($pkg['name']) ?></code></td>
                        <td><span class="badge bg-primary"><?= e($pkg['version']) ?></span></td>
                        <td><small class="text-muted"><?= e($pkg['license'] ?: '-') ?></small></td>
                        <td><small><?= e(mb_strimwidth($pkg['description'], 0, 80, '…')) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Dev Packages -->
<?php if (!empty($devPackages)): ?>
<div class="card adm-card mb-4">
    <div class="card-header adm-card-header d-flex align-items-center">
        <i class="fa-solid fa-wrench me-1"></i>
        <strong><?= e(t('admin.security.assets.dev_packages')) ?></strong>
        <span class="badge bg-secondary ms-1"><?= e(count($devPackages)) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table adm-table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?= e(t('admin.security.assets.col_package')) ?></th>
                        <th><?= e(t('admin.security.assets.col_version')) ?></th>
                        <th><?= e(t('admin.security.assets.col_license')) ?></th>
                        <th><?= e(t('admin.security.assets.col_description')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devPackages as $pkg): ?>
                    <tr>
                        <td><code><?= e($pkg['name']) ?></code></td>
                        <td><span class="badge bg-secondary"><?= e($pkg['version']) ?></span></td>
                        <td><small class="text-muted"><?= e($pkg['license'] ?: '-') ?></small></td>
                        <td><small><?= e(mb_strimwidth($pkg['description'], 0, 80, '…')) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- PHP Extensions -->
<div class="card adm-card mb-4">
    <div class="card-header adm-card-header d-flex align-items-center">
        <i class="fa-brands fa-php me-1"></i>
        <strong><?= e(t('admin.security.assets.php_extensions')) ?></strong>
        <span class="badge bg-secondary ms-1"><?= e(count($phpExtensions)) ?></span>
    </div>
    <div class="card-body">
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-2">
            <?php
            $sorted = $phpExtensions;
            sort($sorted);
            foreach ($sorted as $ext): ?>
            <div class="col"><code class="small"><?= e($ext) ?></code></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</div>

<?php $view->end(); ?>
