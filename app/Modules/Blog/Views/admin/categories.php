<?php
/**
 * Blog admin: categories management.
 * Variables: $view, $categories, $errors, $old
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'  => 'fa-solid fa-folder-tree',
    'adminTitle' => t('blog.admin.categories.breadcrumb'),
]); ?>

<div class="row">
    <!-- Create form -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><?= e(t('blog.admin.categories.new')) ?></div>
            <div class="card-body">
                <form method="post" action="<?= e(route('blog.admin.categories.store')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.admin.form.name_label')) ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               maxlength="100" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.admin.form.description_label')) ?></label>
                        <textarea name="description" rows="2" class="form-control"
                                  maxlength="500"><?= e($old['description'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fa-solid fa-plus me-1"></i> <?= e(t('blog.admin.form.create')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                <div class="text-center text-muted py-5"><?= e(t('blog.admin.categories.empty')) ?></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?= e(t('blog.admin.form.name_label')) ?></th>
                                <th>Slug</th>
                                <th><?= e(t('blog.admin.form.articles_col')) ?></th>
                                <th class="text-end"><?= e(t('blog.author.col_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td class="fw-medium"><?= e($cat['name']) ?></td>
                                <td><code class="small"><?= e($cat['slug']) ?></code></td>
                                <td><?= (int) ($cat['article_count'] ?? 0) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php
                                        $artCount = (int) ($cat['article_count'] ?? 0);
                                        $confirmMsg = $artCount > 0
                                            ? tc('blog.admin.categories.delete_confirm_with_count', $artCount, ['name' => $cat['name'], 'count' => $artCount])
                                            : t('blog.admin.categories.delete_confirm', ['name' => $cat['name']]);
                                    ?>
                                    <form method="post"
                                          action="<?= e(route('blog.admin.categories.destroy', ['id' => $cat['id']])) ?>"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger" title="<?= e(t('blog.author.delete')) ?>"
                                                data-app-confirm="<?= e($confirmMsg) ?>" data-app-confirm-label="<?= e(t('blog.author.delete')) ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
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
</div>
</div>

<?php $view->end(); ?>
