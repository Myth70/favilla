<?php
/**
 * Blog admin: tags management.
 * Variables: $view, $tags, $errors, $old
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'  => 'fa-solid fa-tags',
    'adminTitle' => t('blog.admin.tags.breadcrumb'),
]); ?>

<div class="row">
    <!-- Create form -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><?= e(t('blog.admin.tags.new')) ?></div>
            <div class="card-body">
                <form method="post" action="<?= e(route('blog.admin.tags.store')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.admin.form.name_label')) ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               maxlength="80" required>
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
                <?php if (empty($tags)): ?>
                <div class="text-center text-muted py-5"><?= e(t('blog.admin.tags.empty')) ?></div>
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
                            <?php foreach ($tags as $tag): ?>
                            <tr>
                                <td class="fw-medium"><?= e($tag['name']) ?></td>
                                <td><code class="small"><?= e($tag['slug']) ?></code></td>
                                <td><?= (int) ($tag['article_count'] ?? 0) ?></td>
                                <td class="text-end">
                                    <?php
                                        $artCount = (int) ($tag['article_count'] ?? 0);
                                        $confirmMsg = $artCount > 0
                                            ? tc('blog.admin.tags.delete_confirm_with_count', $artCount, ['name' => $tag['name'], 'count' => $artCount])
                                            : t('blog.admin.tags.delete_confirm', ['name' => $tag['name']]);
                                    ?>
                                    <form method="post"
                                          action="<?= e(route('blog.admin.tags.destroy', ['id' => $tag['id']])) ?>"
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
