<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$view->include('partials/pf-hero-admin', [
    'adminTitle'    => 'Data Retention',
    'adminIcon'     => 'fa-solid fa-clock-rotate-left',
    'adminSubtitle' => t('admin.retention.subtitle'),
    'adminButtons'  => '
        <form method="POST" action="' . e(route('admin.retention.execute')) . '" class="d-inline">
            ' . csrf_field() . '
            <input type="hidden" name="dry_run" value="1">
            <button type="submit" class="btn btn-outline-light btn-sm" data-bs-toggle="tooltip" title="' . e(t('admin.retention.preview_tip')) . '">
                <i class="fa-solid fa-eye me-1"></i> ' . e(t('admin.retention.preview')) . '
            </button>
        </form>
        <form method="POST" action="' . e(route('admin.retention.execute')) . '" class="d-inline ms-1">
            ' . csrf_field() . '
            <button type="submit" class="btn btn-light btn-sm"
                    data-app-confirm="' . e(t('admin.retention.execute_confirm')) . '"
                    data-app-confirm-label="' . e(t('admin.retention.execute_label')) . '"
                    data-app-confirm-class="btn-danger">
                <i class="fa-solid fa-play me-1"></i> ' . e(t('admin.retention.execute_now')) . '
            </button>
        </form>',
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value adm-text-accent"><?= e($stats['total']) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.retention.total')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value text-success"><?= e($stats['enabled']) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.retention.active')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="adm-stat-box text-center h-100">
            <div class="adm-stat-value <?= $stats['overdue'] > 0 ? 'text-warning' : 'text-muted' ?>"><?= e($stats['overdue']) ?></div>
            <div class="adm-stat-label"><?= e(t('admin.retention.overdue')) ?></div>
        </div>
    </div>
</div>

<!-- Policies Table -->
<div class="card adm-card">
    <div class="card-header adm-card-header d-flex align-items-center gap-2">
        <i class="fa-solid fa-table-list"></i>
        <span class="fw-semibold"><?= e(t('admin.retention.policies_title')) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table adm-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(t('admin.retention.col_entity')) ?></th>
                        <th><?= e(t('admin.retention.col_table')) ?></th>
                        <th><?= e(t('admin.retention.col_date_column')) ?></th>
                        <th class="text-center"><?= e(t('admin.retention.col_days')) ?></th>
                        <th class="text-center"><?= e(t('admin.retention.col_action')) ?></th>
                        <th class="text-center"><?= e(t('admin.retention.col_status')) ?></th>
                        <th><?= e(t('admin.retention.col_last_run')) ?></th>
                        <th class="text-end"><?= e(t('admin.retention.col_operations')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($policies)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fa-solid fa-inbox me-1"></i> <?= e(t('admin.retention.empty')) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($policies as $p): ?>
                            <tr class="<?= !$p['enabled'] ? 'opacity-50' : '' ?>">
                                <td>
                                    <span class="fw-semibold"><?= e($p['entity']) ?></span>
                                    <?php if (!empty($p['description'])): ?>
                                        <br><small class="text-muted"><?= e($p['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($p['table_name']) ?></code></td>
                                <td><code><?= e($p['date_column']) ?></code></td>
                                <td class="text-center">
                                    <form method="POST" action="<?= e(route('admin.retention.update', ['id' => $p['id']])) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_method" value="POST">
                                        <input type="hidden" name="action" value="<?= e($p['action']) ?>">
                                                                                <input type="number" name="retention_days" value="<?= (int) $p['retention_days'] ?>"
                                                                                                 class="form-control form-control-sm d-inline-block text-center adm-input-days" min="0" max="3650"
                                                                                                 data-bs-toggle="tooltip" title="<?= e(t('admin.retention.days_tip')) ?>"
                                               data-adm-autosubmit="change">
                                    </form>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="<?= e(route('admin.retention.update', ['id' => $p['id']])) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="retention_days" value="<?= (int) $p['retention_days'] ?>">
                                        <select name="action" class="form-select form-select-sm d-inline-block adm-select-action" data-adm-autosubmit="change">
                                            <option value="delete" <?= $p['action'] === 'delete' ? 'selected' : '' ?>><?= e(t('admin.retention.action_delete')) ?></option>
                                            <option value="anonymize" <?= $p['action'] === 'anonymize' ? 'selected' : '' ?>><?= e(t('admin.retention.action_anonymize')) ?></option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="<?= e(route('admin.retention.toggle', ['id' => $p['id']])) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm <?= $p['enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                                data-bs-toggle="tooltip" title="<?= e($p['enabled'] ? t('admin.retention.toggle_off') : t('admin.retention.toggle_on')) ?>">
                                            <i class="fa-solid <?= $p['enabled'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($p['last_run_at']): ?>
                                        <small><?= e(format_date_it($p['last_run_at'], 'compact')) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted"><?= e(t('admin.retention.never')) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                    $actionBadge = $p['action'] === 'anonymize'
                                        ? '<span class="badge bg-info">' . e(t('admin.retention.action_anonymize')) . '</span>'
                                        : '<span class="badge bg-danger">' . e(t('admin.retention.action_delete')) . '</span>';
                                    ?>
                                    <?= $actionBadge ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Info Note -->
<div class="card adm-card mt-3">
    <div class="card-body">
        <h6 class="card-title"><i class="fa-solid fa-circle-info me-1 text-info"></i> <?= e(t('admin.retention.info_title')) ?></h6>
        <ul class="mb-0 small text-muted">
            <li><?= t('admin.retention.info_delete') ?></li>
            <li><?= t('admin.retention.info_anonymize') ?></li>
            <li><?= t('admin.retention.info_zero') ?></li>
            <li><?= t('admin.retention.info_scheduler') ?></li>
            <li><?= e(t('admin.retention.info_softdelete')) ?></li>
        </ul>
    </div>
</div>

</div>

<?php $view->end(); ?>
