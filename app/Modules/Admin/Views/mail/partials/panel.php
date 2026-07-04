<?php
/**
 * Mail panel partial — embedded in Configuration tabs.
 * Variables: $templates, $stats
 */

$mailStats = [
    'sent'   => (int) ($stats['sent'] ?? 0),
    'failed' => (int) ($stats['failed'] ?? 0),
    'logged' => (int) ($stats['logged'] ?? 0),
];
$mailTotal = $mailStats['sent'] + $mailStats['failed'] + $mailStats['logged'];
$mailDriver = (string) setting('mail_driver', 'log');
?>

<div class="adm-mail-panel-wrap mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h5 class="mb-1"><?= e(t('admin.mail.panel_title')) ?></h5>
            <p class="text-muted mb-0"><?= e(t('admin.mail.panel_subtitle')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <span class="adm-mail-driver-badge" data-bs-toggle="tooltip" title="<?= e(t('admin.mail.driver_badge_tip')) ?>">
                <i class="fa-solid fa-plug-circle-check"></i>
                <?= e(t('admin.mail.driver_label')) ?> <?= e(strtoupper($mailDriver)) ?>
            </span>
            <a href="<?= e(route('admin.mail.index')) ?>"
               class="btn btn-outline-secondary btn-sm"
               data-bs-toggle="tooltip"
               title="<?= e(t('admin.mail.open_full_tip')) ?>">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e(t('admin.mail.open_module')) ?>
            </a>
        </div>
    </div>

    <div class="row g-2 mt-1">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="adm-mail-kpi">
                <div class="adm-mail-kpi-icon"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <div class="adm-mail-kpi-value"><?= number_format($mailStats['sent']) ?></div>
                    <div class="adm-mail-kpi-label"><?= e(t('admin.mail.kpi_sent')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="adm-mail-kpi">
                <div class="adm-mail-kpi-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div>
                    <div class="adm-mail-kpi-value"><?= number_format($mailStats['failed']) ?></div>
                    <div class="adm-mail-kpi-label"><?= e(t('admin.mail.kpi_failed')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="adm-mail-kpi">
                <div class="adm-mail-kpi-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div>
                    <div class="adm-mail-kpi-value"><?= number_format($mailStats['logged']) ?></div>
                    <div class="adm-mail-kpi-label"><?= e(t('admin.mail.kpi_logged')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="adm-mail-kpi">
                <div class="adm-mail-kpi-icon"><i class="fa-solid fa-inbox"></i></div>
                <div>
                    <div class="adm-mail-kpi-value"><?= number_format($mailTotal) ?></div>
                    <div class="adm-mail-kpi-label"><?= e(t('admin.mail.kpi_total')) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sub-tabs: Templates / Log -->
<ul class="nav nav-pills adm-mail-subtabs mb-3" id="mailSubTabs" data-adm-lazy role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="subtab-templates" data-bs-toggle="tab"
                data-bs-target="#subpane-templates" type="button" role="tab">
            <i class="fa-solid fa-file-lines me-1"></i><?= e(t('admin.mail.subtab_templates')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="subtab-log" data-bs-toggle="tab"
                data-bs-target="#subpane-log" type="button" role="tab">
            <i class="fa-solid fa-list me-1"></i><?= e(t('admin.mail.tab_log')) ?>
            <span class="badge ms-1 adm-mail-count-badge"><?= $mailTotal ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Templates sub-tab -->
    <div class="tab-pane fade show active" id="subpane-templates" role="tabpanel">
        <div class="card adm-card adm-mail-card mb-3">
            <div class="card-header adm-card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa-solid fa-file-lines me-2"></i><?= e(t('admin.mail.templates_heading')) ?></h6>
                <?php if (has_permission('admin.mail.manage')): ?>
                <a href="<?= e(route('admin.mail.templates.create')) ?>"
                   class="btn btn-primary btn-sm"
                   data-bs-toggle="tooltip"
                   title="<?= e(t('admin.mail.new_template_tip')) ?>">
                    <i class="fa-solid fa-plus me-1"></i><?= e(t('admin.mail.new_template')) ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div id="templates-table">
                    <?php $view->include('Admin/Views/mail/partials/templates_table', ['templates' => $templates]); ?>
                </div>
            </div>
        </div>

        <div class="card adm-card adm-mail-card">
            <div class="card-header adm-card-header">
                <h6 class="mb-0"><i class="fa-solid fa-paper-plane me-2"></i><?= e(t('admin.mail.test_card_title')) ?></h6>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= e(route('admin.mail.test')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="test_email" class="form-label"><?= e(t('admin.mail.test_email_label')) ?></label>
                            <input type="email" class="form-control" id="test_email" name="test_email"
                                   placeholder="test@example.com" required>
                            <div class="form-text"><?= e(t('admin.mail.test_email_hint')) ?></div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <button type="submit"
                                    class="btn btn-outline-primary w-100"
                                    data-bs-toggle="tooltip"
                                    title="<?= e(t('admin.mail.send_test_tip')) ?>">
                                <i class="fa-solid fa-paper-plane me-1"></i><?= e(t('admin.mail.send_test')) ?>
                            </button>
                        </div>
                        <div class="col-md-3 col-lg-4">
                            <div class="adm-mail-hint">
                                <i class="fa-solid fa-circle-info"></i>
                                <?= t('admin.mail.test_driver_hint', ['driver' => '<code>' . e($mailDriver) . '</code>']) ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Log sub-tab (lazy-loaded) -->
    <div class="tab-pane fade" id="subpane-log" role="tabpanel">
        <div class="card adm-card adm-mail-card">
            <div class="card-header adm-card-header">
                <h6 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i><?= e(t('admin.mail.history_title')) ?></h6>
            </div>
            <div class="card-body">
                <div id="mail-log-table"
                     hx-get="<?= e(route('admin.mail.log.table')) ?>"
                     hx-trigger="load"
                     hx-swap="innerHTML">
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.mail.loading')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function() {
    'use strict';
    // Init lazy tabs for sub-tabs (admin.js handles top-level, this handles nested)
    if (window.admInitLazyTabs) {
        window.admInitLazyTabs('mailSubTabs');
    }
})();
</script>
