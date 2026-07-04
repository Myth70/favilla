<?php $view->layout('main'); ?>

<?php $view->start('content'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'  => 'fa-solid fa-envelope',
        'adminTitle' => t('admin.mail.title'),
    ]); ?>

    <!-- Tabs (Bootstrap client-side, no full-page reload) -->
    <div class="card">
        <div class="card-header p-0">
            <ul class="nav nav-tabs card-header-tabs px-2" id="mailTabs" data-adm-lazy role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= ($activeTab ?? 'templates') === 'templates' ? 'active' : '' ?>"
                            id="tab-templates" data-bs-toggle="tab" data-bs-target="#pane-templates"
                            type="button" role="tab">
                        <i class="fa-solid fa-file-lines me-1"></i><?= e(t('admin.mail.tab_templates')) ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= ($activeTab ?? '') === 'log' ? 'active' : '' ?>"
                            id="tab-log" data-bs-toggle="tab" data-bs-target="#pane-log"
                            type="button" role="tab">
                        <i class="fa-solid fa-list me-1"></i><?= e(t('admin.mail.tab_log')) ?>
                        <?php if (!empty($stats)): ?>
                            <span class="badge bg-secondary ms-1"><?= ($stats['sent'] ?? 0) + ($stats['failed'] ?? 0) + ($stats['logged'] ?? 0) ?></span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content">

            <!-- ── TEMPLATES ──────────────────────────────────── -->
            <div class="tab-pane fade <?= ($activeTab ?? 'templates') === 'templates' ? 'show active' : '' ?>"
                 id="pane-templates" role="tabpanel">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><?= e(t('admin.mail.templates_heading')) ?></h5>
                        <?php if (has_permission('admin.mail.manage')): ?>
                            <a href="<?= e(route('admin.mail.templates.create')) ?>" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-plus me-1"></i><?= e(t('admin.mail.new_template')) ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div id="templates-table">
                        <?php $view->include('Admin/Views/mail/partials/templates_table', get_defined_vars()); ?>
                    </div>

                    <!-- Test send card -->
                    <div class="card adm-card mt-4">
                        <div class="card-header adm-card-header">
                            <h5 class="mb-0"><i class="fa-solid fa-paper-plane me-2"></i><?= e(t('admin.mail.test_card_title')) ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?= e(route('admin.mail.test')) ?>">
                                <?= csrf_field() ?>
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <label for="test_email" class="form-label"><?= e(t('admin.mail.test_email_label')) ?></label>
                                        <input type="email" class="form-control" id="test_email" name="test_email"
                                               placeholder="test@example.com" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="fa-solid fa-paper-plane me-1"></i><?= e(t('admin.mail.send_test')) ?>
                                        </button>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <?= e(t('admin.mail.driver_label')) ?> <code><?= e(setting('mail_driver', 'log')) ?></code>
                                        </small>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── LOG ────────────────────────────────────────── -->
            <div class="tab-pane fade <?= ($activeTab ?? '') === 'log' ? 'show active' : '' ?>"
                 id="pane-log" role="tabpanel">
                <div class="card-body">
                    <div id="mail-log-table"
                         hx-get="<?= e(route('admin.mail.log.table')) ?>"
                         hx-trigger="load"
                         hx-swap="innerHTML">
                        <?php if (($activeTab ?? '') === 'log'): ?>
                            <?php $view->include('Admin/Views/mail/partials/log_table', get_defined_vars()); ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.mail.loading')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /tab-content -->
    </div>
</div>


<?php $view->end(); ?>
