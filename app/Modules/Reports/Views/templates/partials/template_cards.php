<?php
/**
 * PARTIAL — Template cards with pagination.
 *
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */

$formatIcons = [
    'pdf'   => '<i class="fa-solid fa-file-pdf text-danger"></i>',
    'excel' => '<i class="fa-solid fa-file-excel text-success"></i>',
    'csv'   => '<i class="fa-solid fa-file-csv text-info"></i>',
];

$visibilityBadges = [
    'private' => '<span class="badge bg-secondary">' . e(t('reports.templates.badge_private')) . '</span>',
    'role'    => '<span class="badge bg-info">' . e(t('reports.templates.badge_role')) . '</span>',
    'global'  => '<span class="badge bg-success">' . e(t('reports.templates.badge_global')) . '</span>',
];
?>

<?php if (empty($items)): ?>
    <div class="text-muted text-center py-5">
        <i class="fa-solid fa-wand-magic-sparkles fa-2x mb-2 d-block opacity-50"></i>
        <?= e(t('reports.templates.empty')) ?>
        <?php if (has_permission('reports.create')): ?>
        <div class="mt-2">
            <a href="<?= e(route('reports.templates.new')) ?>" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-plus me-1"></i><?= e(t('reports.templates.create_first')) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($items as $item): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border rp-template-card h-100">
                <div class="card-body">
                    <!-- Header: format icon + name -->
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="fs-4"><?= $formatIcons[$item['output_format']] ?? '<i class="fa-solid fa-file"></i>' ?></span>
                        <div class="flex-grow-1 min-width-0">
                            <h6 class="mb-0 text-truncate"><?= e($item['name']) ?></h6>
                            <small class="text-muted">
                                <?= e($item['module']) ?> / <?= e($item['source_key']) ?>
                            </small>
                        </div>
                        <?php if (!empty($item['bundled_module'])): ?>
                        <span class="badge bg-info text-dark"><?= e(t('reports.templates.badge_bundled')) ?></span>
                        <?php endif; ?>
                        <?= $visibilityBadges[$item['visibility']] ?? '' ?>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($item['description'])): ?>
                    <p class="small text-muted mb-2 rp-card-description"><?= e($item['description']) ?></p>
                    <?php endif; ?>

                    <!-- Meta -->
                    <div class="small text-muted">
                        <?php if (!empty($item['style_name'])): ?>
                        <span class="me-2"><i class="fa-solid fa-palette me-1"></i><?= e($item['style_name']) ?></span>
                        <?php endif; ?>
                        <span>
                            <i class="fa-solid fa-clock me-1"></i><?= format_date($item['created_at'], 'compact') ?>
                        </span>
                        <?php if (!empty($item['creator_name'])): ?>
                        <span class="ms-2">
                            <i class="fa-solid fa-user me-1"></i><?= e($item['creator_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <div class="d-flex gap-1 flex-wrap">
                        <?php if (has_permission('reports.export')): ?>
                        <a href="<?= e(route('reports.export.generate', ['id' => $item['id']])) ?>"
                           class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?= e(t('reports.templates.generate_tip')) ?>">
                            <i class="fa-solid fa-play me-1"></i><?= e(t('reports.templates.generate')) ?>
                        </a>
                        <?php endif; ?>

                        <?php if (has_permission('reports.edit')): ?>
                        <a href="<?= e(route('reports.templates.edit', ['id' => $item['id']])) ?>"
                           class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?= e(t('reports.templates.designer_tip')) ?>">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i><?= e(t('reports.templates.designer')) ?>
                        </a>
                        <?php endif; ?>

                        <?php if (has_permission('reports.create')): ?>
                        <form method="POST" action="<?= e(route('reports.templates.duplicate', ['id' => $item['id']])) ?>"
                              class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.templates.duplicate_tip')) ?>">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (has_permission('reports.delete')): ?>
                        <form method="POST" action="<?= e(route('reports.templates.destroy', ['id' => $item['id']])) ?>"
                              class="d-inline ms-auto">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                        data-app-confirm="<?= e(t('reports.templates.delete_confirm', ['name' => $item['name']])) ?>"
                                    data-bs-toggle="tooltip" title="<?= e(t('reports.templates.delete_tip')) ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <?php
    $routeName   = 'reports.templates.index';
    $hxTarget    = '#template-cards';
    $label       = t('reports.templates.pagination_label');
    $extraParams = [];
    ?>
    <?php $view->include('partials/pagination', compact(
        'page', 'total_pages', 'total', 'routeName', 'hxTarget', 'filters', 'extraParams', 'label'
    )); ?>
    <?php endif; ?>
<?php endif; ?>
