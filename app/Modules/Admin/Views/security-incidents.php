<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$heroButtons = '';
if (has_permission('admin.security.manage')) {
    $heroButtons .= '<span class="badge bg-light text-dark">'
        . '<i class="fa-solid fa-chart-line me-1"></i>'
        . e(t('admin.security.incidents.total_label', ['count' => $total])) . '</span>';
}

$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('admin.security.incidents.title'),
    'adminIcon'     => 'fa-solid fa-shield-exclamation',
    'adminSubtitle' => t('admin.security.incidents.subtitle'),
    'adminButtons'  => $heroButtons,
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $periods = [
        '24h' => t('admin.incidents_summary.period_24h'),
        '7d'  => t('admin.incidents_summary.period_7d'),
        '30d' => t('admin.incidents_summary.period_30d'),
    ];
    foreach ($periods as $key => $label):
        $items = $summary[$key] ?? [];
        $count = 0;
        foreach ($items as $item) $count += (int) $item['cnt'];
        $highCount = 0;
        foreach ($items as $item) {
            if (in_array($item['severity'], ['high', 'critical'])) $highCount += (int) $item['cnt'];
        }
        $badgeClass = $highCount > 0 ? 'bg-danger' : ($count > 0 ? 'bg-warning text-dark' : 'bg-success');
    ?>
    <div class="col-md-4">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-label mb-1"><?= e($label) ?></div>
            <div class="adm-stat-value mb-1"><?= e($count) ?></div>
            <?php if ($highCount > 0): ?>
                <span class="badge bg-danger"><?= e(t('admin.security.incidents.high_count', ['count' => $highCount])) ?></span>
            <?php else: ?>
                <span class="badge <?= $badgeClass ?>">
                    <?= e($count === 0 ? t('admin.security.incidents.none') : t('admin.security.incidents.none_critical')) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card adm-card mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <select name="type" class="form-select form-select-sm"
                        hx-get="<?= e(route('admin.security.incidents')) ?>"
                        hx-target="#incidents-table"
                        hx-push-url="true"
                        hx-include="[name='severity']">
                    <option value=""><?= e(t('admin.security.incidents.all_types')) ?></option>
                    <option value="brute_force" <?= ($filters['type'] ?? '') === 'brute_force' ? 'selected' : '' ?>><?= e(t('admin.security.incident_type.brute_force')) ?></option>
                    <option value="csrf_violation" <?= ($filters['type'] ?? '') === 'csrf_violation' ? 'selected' : '' ?>><?= e(t('admin.security.incident_type.csrf_violation')) ?></option>
                    <option value="access_denied" <?= ($filters['type'] ?? '') === 'access_denied' ? 'selected' : '' ?>><?= e(t('admin.security.incident_type.access_denied')) ?></option>
                    <option value="csrf_flood" <?= ($filters['type'] ?? '') === 'csrf_flood' ? 'selected' : '' ?>><?= e(t('admin.security.incident_type.csrf_flood')) ?></option>
                    <option value="ip_change" <?= ($filters['type'] ?? '') === 'ip_change' ? 'selected' : '' ?>><?= e(t('admin.security.incident_type.ip_change')) ?></option>
                    <option value="file_integrity_failure" <?= ($filters['type'] ?? '') === 'file_integrity_failure' ? 'selected' : '' ?>><?= e(t('admin.security.incident_type.file_integrity_failure')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <select name="severity" class="form-select form-select-sm"
                        hx-get="<?= e(route('admin.security.incidents')) ?>"
                        hx-target="#incidents-table"
                        hx-push-url="true"
                        hx-include="[name='type']">
                    <option value=""><?= e(t('admin.security.incidents.all_severities')) ?></option>
                    <option value="critical" <?= ($filters['severity'] ?? '') === 'critical' ? 'selected' : '' ?>><?= e(t('admin.security.severity.critical')) ?></option>
                    <option value="high" <?= ($filters['severity'] ?? '') === 'high' ? 'selected' : '' ?>><?= e(t('admin.security.severity.high')) ?></option>
                    <option value="medium" <?= ($filters['severity'] ?? '') === 'medium' ? 'selected' : '' ?>><?= e(t('admin.security.severity.medium')) ?></option>
                    <option value="low" <?= ($filters['severity'] ?? '') === 'low' ? 'selected' : '' ?>><?= e(t('admin.security.severity.low')) ?></option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Incidents Table -->
<div id="incidents-table">
    <?php $view->include('Admin/Views/partials/security_incidents_table', get_defined_vars()); ?>
</div>

</div>

<?php $view->end(); ?>
