<?php
/**
 * Admin Logs — Errors tab partial.
 * Variables: $items, $total, $page, $pages, $perPage, $filters, $logFile
 */
$levelBadge = [
    'CRITICAL' => 'bg-danger',
    'ERROR'    => 'bg-danger',
    'WARNING'  => 'bg-warning text-dark',
    'NOTICE'   => 'bg-info text-dark',
    'INFO'     => 'bg-primary',
    'DEBUG'    => 'bg-secondary',
];
?>

<?php if (empty($items) && $total === 0): ?>
<div class="p-4 text-center text-muted">
    <i class="fa-solid fa-circle-check fa-2x mb-2 text-success"></i>
    <p class="mb-0"><?= e(t('admin.logs.errors_empty')) ?>
        <?php if (!is_readable($logFile)): ?>
            <?= e(t('admin.logs.log_unreadable')) ?>
        <?php endif; ?>
    </p>
</div>
<?php else: ?>

<div class="p-2 border-bottom text-end">
    <small class="text-muted">
        <i class="fa-solid fa-file me-1"></i><?= e(basename($logFile ?? '')) ?>
        — <?= e($total === 1 ? t('admin.logs.recent_errors_one', ['count' => number_format($total)]) : t('admin.logs.recent_errors_many', ['count' => number_format($total)])) ?>
    </small>
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="adm-col-datetime"><?= e(t('admin.logs.col_datetime')) ?></th>
                <th class="adm-col-level"><?= e(t('admin.logs.col_level')) ?></th>
                <th><?= e(t('admin.logs.col_message')) ?></th>
                <th>File</th>
                <th class="adm-col-line"><?= e(t('admin.logs.col_line')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
            <tr>
                <td colspan="5" class="text-center text-muted py-3"><?= e(t('admin.logs.no_results_filters')) ?></td>
            </tr>
            <?php else: ?>
            <?php foreach ($items as $entry): ?>
            <tr>
                <td class="text-nowrap small text-muted"><?= e($entry['timestamp']) ?></td>
                <td>
                    <span class="badge <?= $levelBadge[$entry['level']] ?? 'bg-secondary' ?>">
                        <?= e($entry['level']) ?>
                    </span>
                </td>
                <td>
                    <span class="adm-err-msg small font-monospace" title="<?= e($entry['message']) ?>">
                        <?= e($entry['message']) ?>
                    </span>
                </td>
                <td><small class="text-muted font-monospace"><?= e($entry['file']) ?></small></td>
                <td class="text-center small"><?= e($entry['line']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<nav class="d-flex justify-content-center py-2">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= min($pages, 10); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <button type="button" class="page-link"
                    hx-get="<?= e(route('admin.logs.errors')) ?>"
                    hx-target="#adm-errors-table"
                    hx-include="#adm-errors-filter"
                    hx-vals='{"page": "<?= $p ?>"}'><?= $p ?></button>
        </li>
        <?php endfor; ?>
        <?php if ($pages > 10): ?>
        <li class="page-item disabled"><span class="page-link">… <?= $pages ?></span></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
