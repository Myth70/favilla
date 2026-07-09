<?php
$view->layout('main');
$view->start('content');
?>

<div class="container-fluid py-3">

<?php
$whButtons = has_permission('webhooks.manage')
    ? '<a href="' . e(route('webhooks.create')) . '" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> ' . e(t('webhooks.create_title')) . '</a>'
    : '';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('webhooks.title'),
    'moduleIcon'     => 'fa-solid fa-bolt',
    'moduleSubtitle' => t('webhooks.subtitle'),
    'moduleButtons'  => $whButtons,
]);
?>

    <?php if (!empty($newSecret)): ?>
    <div class="alert alert-success shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="fa-solid fa-key"></i><strong><?= e(t('webhooks.secret_once_title')) ?></strong>
        </div>
        <p class="small mb-2"><?= e(t('webhooks.secret_once_hint')) ?></p>
        <input type="text" class="form-control font-monospace" value="<?= e($newSecret) ?>" readonly onclick="this.select()">
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="card shadow-sm"><div class="card-body">
            <div class="fs-4 fw-bold"><?= e((string) (int) ($stats['pending'] ?? 0)) ?></div>
            <div class="small text-secondary"><?= e(t('webhooks.stat_pending')) ?></div>
        </div></div></div>
        <div class="col-sm-4"><div class="card shadow-sm"><div class="card-body">
            <div class="fs-4 fw-bold text-success"><?= e((string) (int) ($stats['sent'] ?? 0)) ?></div>
            <div class="small text-secondary"><?= e(t('webhooks.stat_sent')) ?></div>
        </div></div></div>
        <div class="col-sm-4"><div class="card shadow-sm"><div class="card-body">
            <div class="fs-4 fw-bold text-danger"><?= e((string) (int) ($stats['failed'] ?? 0)) ?></div>
            <div class="small text-secondary"><?= e(t('webhooks.stat_failed')) ?></div>
        </div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2">
            <span class="app-card-icon"><i class="fa-solid fa-bolt"></i></span>
            <span class="fw-semibold"><?= e(t('webhooks.list_title')) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($endpoints)): ?>
                <div class="p-4 text-center text-secondary"><?= e(t('webhooks.empty')) ?></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('webhooks.col_url')) ?></th>
                            <th><?= e(t('webhooks.col_events')) ?></th>
                            <th><?= e(t('webhooks.col_status')) ?></th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoints as $ep): ?>
                        <tr>
                            <td>
                                <div class="font-monospace small text-truncate" style="max-width: 340px;"><?= e($ep['url']) ?></div>
                                <?php if (!empty($ep['description'])): ?>
                                    <div class="small text-secondary"><?= e($ep['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-info"><?= (int) count($ep['event_types']) ?></span></td>
                            <td>
                                <?php if (!empty($ep['is_active'])): ?>
                                    <span class="badge text-bg-success"><?= e(t('webhooks.active')) ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary"><?= e(t('webhooks.inactive')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= e(route('webhooks.deliveries', ['id' => $ep['id']])) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('webhooks.deliveries_title')) ?>"><i class="fa-solid fa-list"></i></a>
                                <?php if (has_permission('webhooks.manage')): ?>
                                <form method="POST" action="<?= e(route('webhooks.test', ['id' => $ep['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="<?= e(t('webhooks.test_cta')) ?>"><i class="fa-solid fa-paper-plane"></i></button>
                                </form>
                                <a href="<?= e(route('webhooks.edit', ['id' => $ep['id']])) ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="<?= e(route('webhooks.destroy', ['id' => $ep['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-app-confirm="<?= e(t('webhooks.delete_confirm')) ?>"
                                            data-app-confirm-label="<?= e(t('webhooks.delete')) ?>"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php $view->end(); ?>
