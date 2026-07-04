<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/teams.css'); ?>
<?php $view->start('content'); ?>

<?php
$_teamsAdminButtons = '';
if (isModuleEnabled('Reports') && has_permission('reports.export')) {
    ob_start();
    $view->include('Reports/Views/partials/export-button', [
        'exportModule' => 'Teams', 'exportSourceKey' => 'conversations', 'filters' => $filters ?? [],
    ]);
    $_teamsAdminButtons = ob_get_clean();
}
$view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-comments',
    'adminTitle'    => t('teams.admin.title'),
    'adminSubtitle' => t('teams.admin.subtitle'),
    'adminButtons'  => $_teamsAdminButtons,
]);
?>

<?php $view->include('Teams/Views/admin/partials/stats_cards', get_defined_vars()); ?>

<!-- ── Filtri + Tabella ───────────────────────────────────────── -->
<div class="card mt-4">
    <div class="card-header">
        <form id="tm-admin-filter"
              class="d-flex gap-2 flex-wrap align-items-center"
              hx-get="<?= e(route('teams.admin.conversations')) ?>"
              hx-target="#tm-admin-table"
              hx-swap="innerHTML"
              hx-trigger="input changed delay:350ms from:[name='search'], change from:[name='filter']">
            <input type="text"
                     class="form-control form-control-sm tm-admin-search"
                   name="search"
                   value="<?= e($search) ?>"
                   placeholder="<?= e(t('teams.admin.search_placeholder')) ?>">
            <select name="filter" class="form-select form-select-sm tm-admin-filter-auto">
                <option value="all"<?= $filter === 'all' ? ' selected' : '' ?>><?= e(t('teams.admin.filter_all')) ?></option>
                <option value="active"<?= $filter === 'active' ? ' selected' : '' ?>><?= e(t('teams.admin.filter_active')) ?></option>
                <option value="archived"<?= $filter === 'archived' ? ' selected' : '' ?>><?= e(t('teams.admin.filter_archived')) ?></option>
                <option value="direct"<?= $filter === 'direct' ? ' selected' : '' ?>><?= e(t('teams.admin.filter_direct')) ?></option>
                <option value="group"<?= $filter === 'group' ? ' selected' : '' ?>><?= e(t('teams.admin.filter_group')) ?></option>
            </select>
            <input type="hidden" name="page" value="1">
            <span class="ms-auto text-muted small"><?= e(t('teams.admin.total_conversations', ['total' => $total])) ?></span>
        </form>
    </div>
    <div class="card-body p-0">
        <div id="tm-admin-table">
            <?php $view->include('Teams/Views/admin/partials/table', get_defined_vars()); ?>
        </div>
    </div>
</div>

<!-- ── Cleanup Messaggi ──────────────────────────────────────── -->
<div class="card mt-4 mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa fa-broom me-2 text-warning"></i><?= e(t('teams.admin.cleanup_title')) ?></h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            <?= e(t('teams.admin.cleanup_description')) ?>
        </p>
        <form method="POST"
              action="<?= e(route('teams.admin.cleanup')) ?>"
              id="tm-admin-cleanup-form">
            <?= csrf_field() ?>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0"><?= e(t('teams.admin.cleanup_older_than_label')) ?></label>
                    <input type="number"
                           name="months"
                              class="form-control form-control-sm tm-admin-months"
                           value="<?= e($defaultMonths) ?>"
                           min="1"
                           max="120"
                           hx-get="<?= e(route('teams.admin.cleanup_preview')) ?>"
                           hx-trigger="change delay:400ms"
                           hx-target="#cleanup-preview-count"
                           hx-swap="outerHTML"
                           hx-include="this">
                    <span class="text-muted"><?= e(t('teams.admin.months_label')) ?></span>
                </div>
                <div class="text-muted small">
                    <?= e(t('teams.admin.messages_to_remove_label')) ?> <span id="cleanup-preview-count" class="fw-bold text-warning"><?= e($cleanupCount) ?></span>
                </div>
                <button type="submit"
                        class="btn btn-warning btn-sm ms-auto"
                        id="tm-admin-cleanup-submit"
                        <?= $cleanupCount === 0 ? 'disabled' : '' ?>>
                    <i class="fa fa-broom me-1"></i><?= e(t('teams.admin.run_cleanup_btn')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    var form = document.getElementById('tm-admin-cleanup-form');
    var btn  = document.getElementById('tm-admin-cleanup-submit');
    if (!form || !btn) return;
    btn.addEventListener('click', function (e) {
        if (btn._appConfirmed) return;
        e.preventDefault();
        var months = parseInt(form.querySelector('input[name="months"]').value, 10) || 0;
        window.appConfirm({
            title:        <?= json_encode(t('teams.admin.confirm_cleanup_title')) ?>,
            body:         <?= json_encode(t('teams.admin.confirm_cleanup_body')) ?>.replace('__MONTHS__', months),
            confirmLabel: <?= json_encode(t('teams.admin.run_cleanup_btn')) ?>,
            confirmClass: 'btn-warning'
        }).then(function (ok) {
            if (!ok) return;
            btn._appConfirmed = true;
            form.submit();
        });
    });
})();
</script>

<?php $view->end(); ?>
