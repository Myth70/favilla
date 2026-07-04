<?php
/**
 * PARTIAL — History table with sort and pagination.
 *
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters, $adminView
 */

$formatIcons = [
    'pdf'   => '<i class="fa-solid fa-file-pdf text-danger"></i>',
    'excel' => '<i class="fa-solid fa-file-excel text-success"></i>',
    'csv'   => '<i class="fa-solid fa-file-csv text-info"></i>',
];

$formatSize = function (int $bytes): string {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
};

$sh = sort_context(
    $filters['sort'] ?? '',
    $filters['dir'] ?? 'DESC',
    $filters,
    route('reports.history.index'),
    '#history-table'
);
?>

<?php if (empty($items)): ?>
    <div class="card-body">
        <div class="text-muted text-center py-5">
            <i class="fa-solid fa-clock-rotate-left fa-2x mb-2 d-block opacity-50"></i>
            <?= e(t('reports.history.empty')) ?>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="rp-col-id">#</th>
                    <th><?= $sh('template_name', t('reports.history.col_report')) ?></th>
                    <th><?= $sh('module', t('reports.history.col_module')) ?></th>
                    <th class="rp-col-format"><?= e(t('reports.history.col_format')) ?></th>
                    <th class="text-end"><?= $sh('row_count', t('reports.history.col_rows')) ?></th>
                    <th class="text-end"><?= $sh('file_size', t('reports.history.col_size')) ?></th>
                    <th><?= $sh('generated_at', t('reports.history.col_date')) ?></th>
                    <?php if ($adminView): ?>
                    <th><?= e(t('reports.history.col_generated_by')) ?></th>
                    <?php endif; ?>
                    <th class="text-end"><?= e(t('reports.history.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="text-muted"><?= (int) $item['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($item['template_name']) ?></div>
                        <small class="text-muted"><?= e($item['source_key']) ?></small>
                    </td>
                    <td><span class="badge bg-light text-dark"><?= e($item['module']) ?></span></td>
                    <td class="text-center">
                        <?= $formatIcons[$item['output_format']] ?? e($item['output_format']) ?>
                    </td>
                    <td class="text-end"><?= number_format((int) $item['row_count'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= $formatSize((int) $item['file_size']) ?></td>
                    <td class="text-muted small"><?= format_date($item['generated_at'], 'compact') ?></td>
                    <?php if ($adminView): ?>
                    <td>
                        <?php if (!empty($item['generator_name'])): ?>
                        <small><?= e($item['generator_name']) ?></small>
                        <?php else: ?>
                        <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-end">
                        <?php if (!empty($item['stored_filename'])): ?>
                        <a href="<?= e(route('reports.history.download', ['id' => $item['id']])) ?>"
                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="<?= e(t('reports.history.download')) ?>">
                            <i class="fa-solid fa-download"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (has_permission('reports.delete') || ($adminView && has_permission('reports.admin'))): ?>
                        <form method="POST" action="<?= e(route('reports.history.destroy', ['id' => $item['id']])) ?>"
                              class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    data-app-confirm="<?= e(t('reports.history.delete_confirm')) ?>"
                                    data-bs-toggle="tooltip" title="<?= e(t('reports.history.delete')) ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <?php
    $routeName   = 'reports.history.index';
    $hxTarget    = '#history-table';
    $label       = t('reports.history.pagination_label');
    $extraParams = [];
    ?>
    <?php $view->include('partials/pagination', compact(
        'page', 'total_pages', 'total', 'routeName', 'hxTarget', 'filters', 'extraParams', 'label'
    )); ?>
    <?php endif; ?>
<?php endif; ?>
