<?php
/**
 * Admin Logs — Analisi e Pulizia.
 * Variables: $view, $auditStats, $attemptsStats, $sessionsStats,
 *            $users, $auditActions, $auditEntities, $activeTab
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'  => 'fa-solid fa-list-check',
    'adminTitle' => t('admin.logs.hero_title'),
]); ?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<!-- Stats cards (auto-refresh ogni 60s) -->
<div id="adm-log-stats" class="row g-3 mb-4"
     hx-get="<?= e(route('admin.logs.stats')) ?>"
     hx-trigger="every 60s"
     hx-swap="innerHTML">
    <?php $view->include('Admin/Views/logs/partials/stats-widget',
        compact('auditStats', 'attemptsStats', 'sessionsStats')); ?>
</div>

<!-- Tabs + tabelle log -->
<div class="card mb-4">
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs px-2" id="logTabs" data-adm-lazy role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'audit' ? 'active' : '' ?>"
                        id="tab-audit" data-bs-toggle="tab" data-bs-target="#pane-audit"
                        type="button" role="tab">
                    <i class="fa-solid fa-scroll me-1"></i><?= e(t('admin.logs.tab_audit')) ?>
                    <span class="badge bg-secondary ms-1"><?= number_format($auditStats['total']) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'attempts' ? 'active' : '' ?>"
                        id="tab-attempts" data-bs-toggle="tab" data-bs-target="#pane-attempts"
                        type="button" role="tab">
                    <i class="fa-solid fa-shield-halved me-1"></i><?= e(t('admin.logs.tab_attempts')) ?>
                    <?php if ($attemptsStats['todayFailed'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= e(t('admin.logs.badge_today', ['count' => $attemptsStats['todayFailed']])) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'sessions' ? 'active' : '' ?>"
                        id="tab-sessions" data-bs-toggle="tab" data-bs-target="#pane-sessions"
                        type="button" role="tab">
                    <i class="fa-solid fa-id-card me-1"></i><?= e(t('admin.logs.tab_sessions')) ?>
                    <span class="badge bg-success ms-1"><?= e(t('admin.logs.badge_active', ['count' => $sessionsStats['active']])) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'errors' ? 'active' : '' ?>"
                        id="tab-errors" data-bs-toggle="tab" data-bs-target="#pane-errors"
                        type="button" role="tab">
                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= e(t('admin.logs.tab_errors')) ?>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">

        <!-- ── AUDIT LOG ──────────────────────────────────────────── -->
        <div class="tab-pane fade <?= $activeTab === 'audit' ? 'show active' : '' ?>"
             id="pane-audit" role="tabpanel">
            <div class="card-body border-bottom p-2">
                <form id="adm-audit-filter"
                      hx-get="<?= e(route('admin.logs.audit')) ?>"
                      hx-target="#adm-audit-table"
                      hx-push-url="false"
                      hx-trigger="change from:select, submit"
                      class="row g-2 align-items-end">

                    <!-- Riga 1: filtri -->
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_action')) ?></label>
                        <select name="action" class="form-select form-select-sm">
                            <option value=""><?= e(t('admin.logs.opt_all_f')) ?></option>
                            <?php foreach ($auditActions as $act): ?>
                            <option value="<?= e($act) ?>"><?= e($act) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_user')) ?></label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value=""><?= e(t('admin.logs.opt_all_m')) ?></option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_entity')) ?></label>
                        <select name="entity" class="form-select form-select-sm">
                            <option value=""><?= e(t('admin.logs.opt_all_f')) ?></option>
                            <?php foreach ($auditEntities as $ent): ?>
                            <option value="<?= e($ent) ?>"><?= e($ent) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">IP</label>
                        <input type="text" name="ip" class="form-control form-control-sm"
                               placeholder="192.168…"
                               hx-get="<?= e(route('admin.logs.audit')) ?>"
                               hx-target="#adm-audit-table"
                               hx-push-url="false"
                               hx-trigger="keyup changed delay:400ms"
                               hx-include="#adm-audit-filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_from')) ?></label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.audit')) ?>"
                               hx-target="#adm-audit-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-audit-filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_to')) ?></label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.audit')) ?>"
                               hx-target="#adm-audit-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-audit-filter">
                    </div>

                    <!-- Riga 2: cerca contenuto + pulsanti -->
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i><?= e(t('admin.logs.f_search_content')) ?>
                            <span class="text-muted"><?= e(t('admin.logs.old_new_hint')) ?></span>
                        </label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="<?= e(t('admin.logs.f_search_ph')) ?>"
                               hx-get="<?= e(route('admin.logs.audit')) ?>"
                               hx-target="#adm-audit-table"
                               hx-push-url="false"
                               hx-trigger="keyup changed delay:500ms"
                               hx-include="#adm-audit-filter">
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.reset_tip')) ?>"
                            data-adm-reset-form="1"
                                hx-get="<?= e(route('admin.logs.audit')) ?>"
                                hx-target="#adm-audit-table">
                            <i class="fa-solid fa-xmark me-1"></i><?= e(t('admin.logs.reset')) ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.refresh_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.audit')) ?>"
                                hx-target="#adm-audit-table"
                                hx-include="#adm-audit-filter">
                            <i class="fa-solid fa-rotate me-1"></i><?= e(t('admin.logs.refresh')) ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success adm-export-btn"
                                data-type="audit"
                                data-url="<?= e(route('admin.logs.export')) ?>"
                                data-form="adm-audit-filter"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.export_csv_tip')) ?>">
                            <i class="fa-solid fa-file-csv me-1"></i>CSV
                        </button>
                    </div>
                </form>
            </div>
            <div id="adm-audit-table"
                 hx-get="<?= e(route('admin.logs.audit')) ?>"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.logs.loading')) ?>
                </div>
            </div>
        </div>

        <!-- ── LOGIN ATTEMPTS ─────────────────────────────────────── -->
        <div class="tab-pane fade <?= $activeTab === 'attempts' ? 'show active' : '' ?>"
             id="pane-attempts" role="tabpanel">
            <div class="card-body border-bottom p-2">
                <form id="adm-attempts-filter"
                      hx-get="<?= e(route('admin.logs.attempts')) ?>"
                      hx-target="#adm-attempts-table"
                      hx-push-url="false"
                      hx-trigger="change from:select, submit"
                      class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Email</label>
                        <input type="text" name="email" class="form-control form-control-sm"
                               placeholder="<?= e(t('admin.logs.f_email_ph')) ?>"
                               hx-get="<?= e(route('admin.logs.attempts')) ?>"
                               hx-target="#adm-attempts-table"
                               hx-push-url="false"
                               hx-trigger="keyup changed delay:400ms"
                               hx-include="#adm-attempts-filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">IP</label>
                        <input type="text" name="ip" class="form-control form-control-sm"
                               placeholder="192.168…"
                               hx-get="<?= e(route('admin.logs.attempts')) ?>"
                               hx-target="#adm-attempts-table"
                               hx-push-url="false"
                               hx-trigger="keyup changed delay:400ms"
                               hx-include="#adm-attempts-filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_result')) ?></label>
                        <select name="success" class="form-select form-select-sm">
                            <option value=""><?= e(t('admin.logs.opt_all_m')) ?></option>
                            <option value="0"><?= e(t('admin.logs.opt_failed')) ?></option>
                            <option value="1"><?= e(t('admin.logs.opt_succeeded')) ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_from')) ?></label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.attempts')) ?>"
                               hx-target="#adm-attempts-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-attempts-filter">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_to')) ?></label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.attempts')) ?>"
                               hx-target="#adm-attempts-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-attempts-filter">
                    </div>
                    <div class="col-md-2 d-flex align-items-end justify-content-end gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.reset_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.attempts')) ?>"
                                hx-target="#adm-attempts-table"
                                data-adm-reset-form="1">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.refresh_short_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.attempts')) ?>"
                                hx-target="#adm-attempts-table"
                                hx-include="#adm-attempts-filter">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success adm-export-btn"
                                data-type="attempts"
                                data-url="<?= e(route('admin.logs.export')) ?>"
                                data-form="adm-attempts-filter"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.export_csv_short')) ?>">
                            <i class="fa-solid fa-file-csv"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div id="adm-attempts-table"
                 hx-get="<?= e(route('admin.logs.attempts')) ?>"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.logs.loading')) ?>
                </div>
            </div>
        </div>

        <!-- ── SESSIONI ───────────────────────────────────────────── -->
        <div class="tab-pane fade <?= $activeTab === 'sessions' ? 'show active' : '' ?>"
             id="pane-sessions" role="tabpanel">
            <div class="card-body border-bottom p-2">
                <form id="adm-sessions-filter"
                      hx-get="<?= e(route('admin.logs.sessions')) ?>"
                      hx-target="#adm-sessions-table"
                      hx-push-url="false"
                      hx-trigger="change from:select, submit"
                      class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_user')) ?></label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value=""><?= e(t('admin.logs.opt_all_m')) ?></option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_status')) ?></label>
                        <select name="active_only" class="form-select form-select-sm">
                            <option value=""><?= e(t('admin.logs.opt_all_f')) ?></option>
                            <option value="1"><?= e(t('admin.logs.opt_active_only')) ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_from')) ?></label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.sessions')) ?>"
                               hx-target="#adm-sessions-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-sessions-filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_to')) ?></label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.sessions')) ?>"
                               hx-target="#adm-sessions-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-sessions-filter">
                    </div>
                    <div class="col-md-3 d-flex align-items-end justify-content-end gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.reset_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.sessions')) ?>"
                                hx-target="#adm-sessions-table"
                                data-adm-reset-form="1">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.refresh_short_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.sessions')) ?>"
                                hx-target="#adm-sessions-table"
                                hx-include="#adm-sessions-filter">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success adm-export-btn"
                                data-type="sessions"
                                data-url="<?= e(route('admin.logs.export')) ?>"
                                data-form="adm-sessions-filter"
                                data-bs-toggle="tooltip" title="<?= e(t('admin.logs.export_csv_short')) ?>">
                            <i class="fa-solid fa-file-csv"></i>
                        </button>
                        <?php if ($sessionsStats['expired'] > 0): ?>
                        <form method="post" action="<?= e(route('admin.logs.cleanup')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="target" value="sessions">
                            <input type="hidden" name="days" value="0">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="tooltip" title="<?= e(t('admin.logs.del_expired_tip')) ?>"
                                    data-app-confirm="<?= e(t('admin.logs.del_expired_confirm', ['count' => (int) $sessionsStats['expired']])) ?>">
                                <i class="fa-solid fa-trash me-1"></i><?= e(t('admin.logs.expired_count', ['count' => (int) $sessionsStats['expired']])) ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div id="adm-sessions-table"
                 hx-get="<?= e(route('admin.logs.sessions')) ?>"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.logs.loading')) ?>
                </div>
            </div>
        </div>

        <!-- ── ERRORI PHP ─────────────────────────────────────────── -->
        <div class="tab-pane fade <?= $activeTab === 'errors' ? 'show active' : '' ?>"
             id="pane-errors" role="tabpanel">
            <div class="card-body border-bottom p-2">
                <form id="adm-errors-filter"
                      hx-get="<?= e(route('admin.logs.errors')) ?>"
                      hx-target="#adm-errors-table"
                      hx-push-url="false"
                      hx-trigger="change from:select, submit"
                      class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_level')) ?></label>
                        <select name="level" class="form-select form-select-sm">
                            <option value="">ERROR + CRITICAL</option>
                            <option value="CRITICAL">CRITICAL</option>
                            <option value="ERROR">ERROR</option>
                            <option value="WARNING">WARNING</option>
                            <option value="NOTICE">NOTICE</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_from')) ?></label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.errors')) ?>"
                               hx-target="#adm-errors-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-errors-filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_to')) ?></label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               hx-get="<?= e(route('admin.logs.errors')) ?>"
                               hx-target="#adm-errors-table"
                               hx-push-url="false"
                               hx-trigger="change"
                               hx-include="#adm-errors-filter">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.f_search')) ?></label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="<?= e(t('admin.logs.f_search_err_ph')) ?>"
                               hx-get="<?= e(route('admin.logs.errors')) ?>"
                               hx-target="#adm-errors-table"
                               hx-push-url="false"
                               hx-trigger="keyup changed delay:500ms"
                               hx-include="#adm-errors-filter">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            title="<?= e(t('admin.logs.reset_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.errors')) ?>"
                                hx-target="#adm-errors-table"
                                data-adm-reset-form="1">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                title="<?= e(t('admin.logs.refresh_short_tip')) ?>"
                                hx-get="<?= e(route('admin.logs.errors')) ?>"
                                hx-target="#adm-errors-table"
                                hx-include="#adm-errors-filter">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div id="adm-errors-table"
                 hx-get="<?= e(route('admin.logs.errors')) ?>"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.logs.loading')) ?>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->
</div>

<?php if (has_permission('admin.logs.purge')): ?>
<!-- Pulizia log -->
<div class="accordion" id="adm-cleanup-accordion">
    <div class="accordion-item border">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#adm-cleanup-body">
                <i class="fa-solid fa-broom me-2 text-warning"></i>
                <?= e(t('admin.logs.cleanup_title')) ?>
            </button>
        </h2>
        <div id="adm-cleanup-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <p class="text-muted small mb-3">
                    <?= t('admin.logs.cleanup_intro') ?>
                </p>
                <form method="post" action="<?= e(route('admin.logs.cleanup')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.cleanup_table')) ?></label>
                            <select name="target" class="form-select">
                                <option value="audit"><?= e(t('admin.logs.opt_audit')) ?></option>
                                <option value="attempts"><?= e(t('admin.logs.opt_attempts')) ?></option>
                                <option value="password_resets"><?= e(t('admin.logs.opt_pw_resets')) ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1"><?= e(t('admin.logs.cleanup_keep')) ?></label>
                            <select name="days" class="form-select">
                                <option value="7"><?= e(t('admin.logs.days', ['count' => 7])) ?></option>
                                <option value="30"><?= e(t('admin.logs.days', ['count' => 30])) ?></option>
                                <option value="90" selected><?= e(t('admin.logs.days', ['count' => 90])) ?></option>
                                <option value="180"><?= e(t('admin.logs.days', ['count' => 180])) ?></option>
                                <option value="365"><?= e(t('admin.logs.days', ['count' => 365])) ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-warning"
                                    data-app-confirm="<?= e(t('admin.logs.cleanup_confirm')) ?>" data-app-confirm-class="btn-warning" data-app-confirm-label="<?= e(t('admin.logs.delete_label')) ?>">
                                <i class="fa-solid fa-trash-can me-1"></i><?= e(t('admin.logs.cleanup_submit')) ?>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Riepilogo dimensioni tabelle -->
                <hr class="my-3">
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="small text-muted"><?= e(t('admin.logs.sum_audit')) ?></div>
                        <strong><?= number_format($auditStats['total']) ?></strong> <?= e(t('admin.logs.record_suffix')) ?>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted"><?= e(t('admin.logs.sum_attempts')) ?></div>
                        <strong><?= number_format($attemptsStats['total']) ?></strong> <?= e(t('admin.logs.record_suffix')) ?>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted"><?= e(t('admin.logs.sum_expired')) ?></div>
                        <strong><?= number_format($sessionsStats['expired']) ?></strong> <?= e(t('admin.logs.record_suffix')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>


<?php $view->end(); ?>
