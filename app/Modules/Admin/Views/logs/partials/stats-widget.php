<?php
/**
 * HTMX partial — Stats cards (auto-refresh ogni 60s).
 * Variables: $auditStats, $attemptsStats, $sessionsStats
 */
?>
<div class="col-6 col-md-3">
    <div class="card text-center h-100">
        <div class="card-body py-3">
            <div class="adm-stat-value text-primary"><?= number_format($auditStats['total']) ?></div>
            <div class="adm-stat-label text-muted small"><?= e(t('admin.logs.stat_audit_total')) ?></div>
        </div>
    </div>
</div>
<div class="col-6 col-md-3">
    <div class="card text-center h-100">
        <div class="card-body py-3">
            <div class="adm-stat-value text-info"><?= number_format($auditStats['today']) ?></div>
            <div class="adm-stat-label text-muted small"><?= e(t('admin.logs.stat_audit_today')) ?></div>
        </div>
    </div>
</div>
<div class="col-6 col-md-3">
    <div class="card text-center h-100">
        <div class="card-body py-3">
            <div class="adm-stat-value text-danger"><?= number_format($attemptsStats['todayFailed']) ?></div>
            <div class="adm-stat-label text-muted small"><?= e(t('admin.logs.stat_login_failed_today')) ?></div>
        </div>
    </div>
</div>
<div class="col-6 col-md-3">
    <div class="card text-center h-100">
        <div class="card-body py-3">
            <div class="adm-stat-value text-success"><?= number_format($sessionsStats['active']) ?></div>
            <div class="adm-stat-label text-muted small"><?= e(t('admin.logs.stat_sessions_active')) ?></div>
        </div>
    </div>
</div>
