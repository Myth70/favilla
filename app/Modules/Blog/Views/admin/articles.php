<?php
/**
 * Blog admin: all articles.
 * Variables: $view, $items, $total, $page, $per_page, $total_pages, $filters
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php
$_blogArticlesButtons = '';
if (isModuleEnabled('Reports') && has_permission('reports.export')) {
    ob_start();
    $view->include('Reports/Views/partials/export-button', [
        'exportModule' => 'Blog', 'exportSourceKey' => 'articles', 'filters' => $filters ?? [],
    ]);
    $_blogArticlesButtons = ob_get_clean();
}
$view->include('partials/pf-hero-admin', [
    'adminIcon'    => 'fa-solid fa-newspaper',
    'adminTitle'   => t('blog.admin.articles.hero_title'),
    'adminButtons' => $_blogArticlesButtons,
]);
?>

<div class="card mb-4">
    <div class="card-body">
        <form hx-get="<?= e(route('blog.admin.articles')) ?>"
              hx-target="#admin-article-table"
              hx-push-url="true"
              hx-trigger="change from:select, submit"
              class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control" placeholder="<?= e(t('blog.admin.articles.search_placeholder')) ?>"
                       hx-trigger="keyup changed delay:400ms"
                       hx-get="<?= e(route('blog.admin.articles')) ?>"
                       hx-target="#admin-article-table"
                       hx-push-url="true"
                       hx-include="[name='status']">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value=""><?= e(t('blog.admin.articles.status_all')) ?></option>
                    <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>><?= e(t('blog.status.draft')) ?></option>
                    <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= e(t('blog.status.published')) ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="<?= e(route('blog.admin.articles')) ?>" class="btn btn-outline-secondary w-100"><?= e(t('blog.admin.articles.reset_filters')) ?></a>
            </div>
        </form>
    </div>
</div>

<form id="ba-batch-form" method="post" action="<?= e(route('blog.admin.articles.batch')) ?>">
    <?= csrf_field() ?>
    <div id="ba-batch-bar" class="card mb-3 d-none">
        <div class="card-body py-2 d-flex align-items-center gap-3">
            <span class="text-muted"><strong id="ba-count">0</strong> <?= e(t('blog.admin.articles.batch_selected')) ?></span>
            <select name="batch_action" class="form-select form-select-sm bl-batch-action-select">
                <option value=""><?= e(t('blog.admin.articles.batch_action_placeholder')) ?></option>
                <option value="publish"><?= e(t('blog.admin.articles.batch_publish')) ?></option>
                <option value="unpublish"><?= e(t('blog.admin.articles.batch_unpublish')) ?></option>
                <option value="pin"><?= e(t('blog.admin.articles.batch_pin')) ?></option>
                <option value="unpin"><?= e(t('blog.admin.articles.batch_unpin')) ?></option>
                <option value="delete"><?= e(t('blog.admin.articles.batch_delete')) ?></option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary" id="ba-batch-submit">
                <?= e(t('blog.admin.articles.batch_execute')) ?>
            </button>
        </div>
    </div>
</form>

<div id="admin-article-table">
    <?php $view->include('Blog/Views/admin/partials/article_table', compact('items', 'total', 'page', 'per_page', 'total_pages', 'filters')); ?>
</div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function() {
    'use strict';
    function update() {
        var checks = document.querySelectorAll('.ba-row-check:checked');
        var bar = document.getElementById('ba-batch-bar');
        var cnt = document.getElementById('ba-count');
        if (bar) { bar.classList.toggle('d-none', checks.length === 0); }
        if (cnt) { cnt.textContent = checks.length; }
        var all = document.getElementById('ba-select-all');
        var total = document.querySelectorAll('.ba-row-check');
        if (all) { all.checked = total.length > 0 && checks.length === total.length; }
    }
    document.addEventListener('change', function(e) {
        if (e.target.id === 'ba-select-all') {
            var checked = e.target.checked;
            document.querySelectorAll('.ba-row-check').forEach(function(cb) { cb.checked = checked; });
            update();
        } else if (e.target.classList.contains('ba-row-check')) {
            update();
        }
    });
    var batchForm = document.getElementById('ba-batch-form');
    if (batchForm) {
        batchForm.addEventListener('submit', function (e) {
            var action = batchForm.querySelector('[name="batch_action"]');
            if (!action || !action.value) {
                e.preventDefault();
                alert(t('js.blog.batch_select_action', 'Seleziona un\'azione.'));
                return;
            }

            var countEl = document.getElementById('ba-count');
            var count = countEl ? countEl.textContent : '0';
            var confirmMsg = t('js.blog.batch_confirm', 'Eseguire l\'azione su {count} articoli?').replace('{count}', count);
            if (!confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    }
    document.body.addEventListener('htmx:afterSwap', function() { update(); });
})();
</script>

<?php $view->end(); ?>
