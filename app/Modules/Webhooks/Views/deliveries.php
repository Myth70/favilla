<?php
$view->layout('main');
$view->start('content');

$statusBadge = [
    'sent'    => 'text-bg-success',
    'failed'  => 'text-bg-danger',
    'pending' => 'text-bg-secondary',
];
?>

<div class="container-fluid py-3">

<?php $view->include('partials/pf-hero-module', [
    'moduleName'     => t('webhooks.deliveries_title'),
    'moduleIcon'     => 'fa-solid fa-list',
    'moduleSubtitle' => $endpoint['url'],
    'moduleButtons'  => '<a href="' . e(route('webhooks.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> ' . e(t('webhooks.back')) . '</a>',
]); ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($deliveries)): ?>
                <div class="p-4 text-center text-secondary"><?= e(t('webhooks.deliveries_empty')) ?></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('webhooks.col_event')) ?></th>
                            <th><?= e(t('webhooks.col_status')) ?></th>
                            <th><?= e(t('webhooks.col_attempts')) ?></th>
                            <th><?= e(t('webhooks.col_response')) ?></th>
                            <th><?= e(t('webhooks.col_created')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveries as $d): ?>
                        <tr>
                            <td class="font-monospace small"><?= e($d['event_type']) ?></td>
                            <td><span class="badge <?= e($statusBadge[$d['status']] ?? 'text-bg-secondary') ?>"><?= e($d['status']) ?></span></td>
                            <td><?= (int) $d['attempts'] ?></td>
                            <td>
                                <?= $d['response_code'] !== null ? e((string) $d['response_code']) : '<span class="text-secondary">—</span>' ?>
                                <?php if (!empty($d['last_error'])): ?>
                                    <div class="small text-danger text-truncate" style="max-width: 280px;"><?= e($d['last_error']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e(format_date_it($d['created_at'])) ?></td>
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
