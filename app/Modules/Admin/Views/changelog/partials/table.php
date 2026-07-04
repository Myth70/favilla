<?php
/**
 * Changelog — tabella HTMX.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */
?>
<?php if (empty($items)): ?>
<div class="adm-empty">
    <i class="fa-solid fa-code-branch fa-2x text-muted mb-2"></i>
    <p class="text-muted mb-0"><?= e(t('admin.changelog.empty')) ?></p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle adm-table">
        <thead>
            <tr>
                <th class="adm-col-version"><?= e(t('admin.changelog.col_version')) ?></th>
                <th><?= e(t('admin.changelog.col_title')) ?></th>
                <th class="adm-col-date"><?= e(t('admin.changelog.col_date')) ?></th>
                <th class="adm-col-status text-center"><?= e(t('admin.changelog.col_status')) ?></th>
                <th class="adm-col-actions text-end"><?= e(t('admin.changelog.col_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $r): ?>
            <tr>
                <td>
                    <a href="<?= e(route('admin.changelog.show', ['id' => $r['id']])) ?>"
                       class="fw-semibold text-decoration-none adm-version-link">
                        v<?= e($r['version']) ?>
                    </a>
                </td>
                <td><?= e($r['title']) ?></td>
                <td class="text-muted small"><?= e(date('d/m/Y', strtotime($r['release_date']))) ?></td>
                <td class="text-center" id="ch-badge-<?= (int) $r['id'] ?>">
                    <?php $view->include('Admin/Views/changelog/partials/publish-badge', ['release' => $r]); ?>
                </td>
                <td class="text-end">
                    <a href="<?= e(route('admin.changelog.edit', ['id' => $r['id']])) ?>"
                       class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('admin.changelog.edit_tip')) ?>">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<nav class="d-flex justify-content-between align-items-center px-1 mt-2">
    <small class="text-muted">
        <?= e(t('admin.changelog.pager', ['total' => number_format($total), 'page' => $page, 'pages' => $total_pages])) ?>
    </small>
    <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link"
               href="#"
               hx-get="<?= e(route('admin.changelog.index')) ?>"
             hx-target="#ch-table-container"
               hx-push-url="true"
               hx-vals='{"page": <?= $p ?>, "search": "<?= e($filters['search']) ?>", "published": "<?= e($filters['published']) ?>"}'><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
