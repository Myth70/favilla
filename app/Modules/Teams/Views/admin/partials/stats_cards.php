<div class="row g-3">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-primary"><?= e($stats['total']) ?></div>
                <div class="text-muted small mt-1"><?= e(t('teams.admin.stat_conversations')) ?></div>
                <div class="text-muted tm-admin-sub"><?= e(t('teams.admin.stat_active_archived', ['active' => $stats['active'], 'archived' => $stats['archived']])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-success"><?= e($stats['total_messages']) ?></div>
                <div class="text-muted small mt-1"><?= e(t('teams.admin.stat_active_messages')) ?></div>
                <div class="text-muted tm-admin-sub"><?= e(t('teams.admin.stat_direct_group', ['direct' => $stats['direct'], 'group' => $stats['group']])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-info"><?= e($stats['online_now']) ?></div>
                <div class="text-muted small mt-1"><?= e(t('teams.admin.stat_online_now')) ?></div>
                <div class="text-muted tm-admin-sub"><?= e(t('teams.admin.stat_presence_threshold')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="display-6 fw-bold text-warning"><?= e($cleanupCount) ?></div>
                <div class="text-muted small mt-1"><?= e(t('teams.admin.stat_to_clean')) ?></div>
                <div class="text-muted tm-admin-sub"><?= e(t('teams.admin.stat_older_than_months', ['months' => $defaultMonths])) ?></div>
            </div>
        </div>
    </div>
</div>
