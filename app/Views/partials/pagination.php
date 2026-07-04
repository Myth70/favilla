<?php
/**
 * Shared pagination partial — HTMX-compatible.
 *
 * Required variables:
 *   $page        int   Current page number.
 *   $total_pages int   Total number of pages.
 *   $total       int   Total item count.
 *   $routeName   string Named route for links.
 *   $hxTarget    string HTMX target selector (e.g. '#items-table').
 *
 * Optional variables:
 *   $filters     array  Query params to preserve across pages (default []).
 *   $extraParams array  Additional params e.g. sort/dir (default []).
 *   $label       string Entity label for count text (default 'elementi').
 */

$filters     ??= [];
$extraParams ??= [];
$label       ??= t('common.pagination.items_default');

if (($total_pages ?? 1) <= 1) {
    return;
}

$buildQs = function (int $p) use ($filters, $extraParams): string {
    return http_build_query(array_merge($filters, $extraParams, ['page' => $p]), '', '&amp;');
};
$routeUrl = e(route($routeName));
?>
<div class="card-footer d-flex align-items-center justify-content-between">
    <small class="text-muted">
        <?= $total ?> <?= e($label) ?> — <?= e(t('common.pagination.page_info', ['page' => $page, 'pages' => $total_pages])) ?>
    </small>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link"
                   href="?<?= $buildQs($page - 1) ?>"
                   hx-get="<?= $routeUrl ?>?<?= $buildQs($page - 1) ?>"
                   hx-target="<?= e($hxTarget) ?>"
                   hx-push-url="true">&laquo;</a>
            </li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link"
                   href="?<?= $buildQs($i) ?>"
                   hx-get="<?= $routeUrl ?>?<?= $buildQs($i) ?>"
                   hx-target="<?= e($hxTarget) ?>"
                   hx-push-url="true"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link"
                   href="?<?= $buildQs($page + 1) ?>"
                   hx-get="<?= $routeUrl ?>?<?= $buildQs($page + 1) ?>"
                   hx-target="<?= e($hxTarget) ?>"
                   hx-push-url="true">&raquo;</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
