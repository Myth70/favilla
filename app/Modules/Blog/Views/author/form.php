<?php
/**
 * Blog article create/edit form.
 * Variables: $view, $article, $categories, $allTags, $articleTags, $roles, $errors, $old
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->pushStyle('css/quill.snow.css');
$view->pushScript('js/vendor/quill.min.js');
$view->pushScript('js/blog.js');
$view->start('content');

$isEdit = $article !== null;
$action = $isEdit
    ? route('blog.update', ['id' => $article['id']])
    : route('blog.store');

// Build current tags as comma-separated string
$currentTags = '';
if ($isEdit && !empty($articleTags)) {
    $currentTags = implode(', ', array_map(fn($t) => $t['name'], $articleTags));
}

$coverUrl = ($isEdit && !empty($article['cover_image']))
    ? cover_url($article['cover_image'], 'blog')
    : null;

// Visibility state
$visibility     = $article['visibility'] ?? 'all';
$visibilityType = ($visibility === 'all') ? 'all' : 'roles';
$visibilityRoles = ($visibility !== 'all') ? array_map('trim', explode(',', $visibility)) : [];

$roles = $roles ?? [];
?>

<div class="container-fluid">
<div class="row g-4">
    <div class="col-12">
        <!-- Status / actions bar for edit -->
        <?php if ($isEdit): ?>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <span class="badge <?= $article['status'] === 'published' ? 'bg-success' : ($article['status'] === 'scheduled' ? 'bg-info' : 'bg-secondary') ?> fs-6">
                    <?php
                        if ($article['status'] === 'published')      echo e(t('blog.status.published'));
                        elseif ($article['status'] === 'scheduled')  echo e(t('blog.status.scheduled'));
                        else                                          echo e(t('blog.status.draft'));
                    ?>
                </span>
                <?php if (!empty($article['is_pinned'])): ?>
                    <span class="badge bl-pinned-badge ms-1"><i class="fa-solid fa-thumbtack me-1"></i><?= e(t('blog.public.index.pinned_section')) ?></span>
                <?php endif; ?>
                <?php if ($article['published_at']): ?>
                    <small class="text-muted ms-2">
                        <?= e(t('blog.author.published_on', ['date' => format_date($article['published_at'], 'compact')])) ?>
                    </small>
                <?php endif; ?>
                <?php if (!empty($article['reading_time'])): ?>
                    <small class="text-muted ms-2">
                        <i class="fa-regular fa-clock me-1"></i> <?= (int) $article['reading_time'] ?> min
                    </small>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php if ($article['status'] === 'draft' || $article['status'] === 'scheduled'): ?>
                    <form method="post" action="<?= e(route('blog.publish', ['id' => $article['id']])) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-globe me-1"></i> <?= e(t('blog.author.publish_now')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(route('blog.unpublish', ['id' => $article['id']])) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fa-solid fa-eye-slash me-1"></i> <?= e(t('blog.author.revert_to_draft')) ?>
                        </button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(route('blog.destroy', ['id' => $article['id']])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            data-app-confirm="<?= e(t('blog.author.delete_confirm')) ?>" data-app-confirm-label="<?= e(t('blog.author.delete')) ?>">
                        <i class="fa-solid fa-trash me-1"></i> <?= e(t('blog.author.delete')) ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>

                    <!-- Title -->
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.author.form.title_label')) ?> <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="bl-title"
                               value="<?= e($old['title'] ?? $article['title'] ?? '') ?>"
                               class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               maxlength="255" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Excerpt -->
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.author.form.excerpt_label')) ?></label>
                        <textarea name="excerpt" rows="2"
                                  class="form-control <?= isset($errors['excerpt']) ? 'is-invalid' : '' ?>"
                                  maxlength="500"
                                  placeholder="<?= e(t('blog.author.form.excerpt_placeholder')) ?>"><?= e($old['excerpt'] ?? $article['excerpt'] ?? '') ?></textarea>
                        <?php if (isset($errors['excerpt'])): ?>
                            <div class="invalid-feedback"><?= e($errors['excerpt']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.author.form.content_label')) ?> <span class="text-danger">*</span></label>
                        <!-- Hidden field that holds the HTML sent on submit -->
                        <input type="hidden" name="content" id="bl-content-input">
                        <!-- Quill editor container -->
                            <div id="bl-quill-editor"
                                class="bl-quill-editor <?= isset($errors['content']) ? 'border border-danger rounded' : '' ?>">
                        </div>
                        <?php if (isset($errors['content'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['content']) ?></div>
                        <?php endif; ?>
                        <div class="form-text"><?= e(t('blog.author.form.content_hint')) ?></div>
                        <!-- Initial content for Quill (passed as JSON to avoid XSS issues) -->
                        <script id="bl-quill-initial" type="application/json"><?= json_encode($old['content'] ?? $article['content'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                    </div>

                    <!-- Category + Tags row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('blog.author.form.category_label')) ?></label>
                            <select name="category_id" class="form-select">
                                <option value=""><?= e(t('blog.author.form.category_none')) ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>"
                                        <?= (int)($old['category_id'] ?? $article['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('blog.author.form.tags_label')) ?></label>
                            <input type="text" name="tags"
                                   value="<?= e($old['tags'] ?? $currentTags) ?>"
                                   class="form-control"
                                   placeholder="<?= e(t('blog.author.form.tags_placeholder')) ?>"
                                   maxlength="500">
                            <div class="form-text"><?= e(t('blog.author.form.tags_hint')) ?></div>
                        </div>
                    </div>

                    <!-- Cover image -->
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.author.form.cover_label')) ?></label>
                        <?php if ($coverUrl): ?>
                            <div class="mb-2">
                                <img src="<?= e($coverUrl) ?>" class="img-thumbnail bl-cover-current" alt="<?= e(t('blog.author.form.cover_alt')) ?>" id="bl-cover-current">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_cover" id="bl-remove-cover" value="1">
                                    <label class="form-check-label small" for="bl-remove-cover"><?= e(t('blog.author.form.cover_remove')) ?></label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Hidden field for library picker -->
                        <input type="hidden" name="cover_image_url" id="bl-cover-image-url" value="">

                        <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                            <input type="file" name="cover_image"
                                class="form-control bl-cover-input <?= isset($errors['cover_image']) ? 'is-invalid' : '' ?>"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                id="bl-cover-input">
                            <?php if (isModuleEnabled('Files')): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bl-open-picker="1"
                                        data-picker-input="bl-cover-image-url"
                                        data-picker-preview="bl-cover-preview"
                                        data-picker-type="image">
                                    <i class="fa-solid fa-folder-open me-1"></i><?= e(t('blog.author.form.cover_from_library')) ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($errors['cover_image'])): ?>
                            <div class="text-danger small"><?= e($errors['cover_image']) ?></div>
                        <?php endif; ?>
                        <div id="bl-cover-preview" class="mt-2"></div>
                        <div class="form-text"><?= e(t('blog.author.form.cover_hint')) ?></div>
                    </div>

                    <!-- Visibility -->
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.author.form.visibility_label')) ?></label>
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visibility_type" value="all"
                                       id="bl-vis-all" <?= $visibilityType === 'all' ? 'checked' : '' ?>
                                        data-bl-vis-mode="all">
                                <label class="form-check-label" for="bl-vis-all"><?= e(t('blog.author.form.visibility_all')) ?></label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visibility_type" value="roles"
                                       id="bl-vis-roles" <?= $visibilityType === 'roles' ? 'checked' : '' ?>
                                        data-bl-vis-mode="roles">
                                <label class="form-check-label" for="bl-vis-roles"><?= e(t('blog.author.form.visibility_roles')) ?></label>
                            </div>
                        </div>
                        <div id="bl-vis-roles-box" class="<?= $visibilityType === 'roles' ? '' : 'd-none' ?>">
                            <?php foreach ($roles as $role): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="visibility_roles[]"
                                       value="<?= e($role['slug']) ?>" id="bl-role-<?= e($role['slug']) ?>"
                                       <?= in_array($role['slug'], $visibilityRoles, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bl-role-<?= e($role['slug']) ?>"><?= e($role['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text"><?= e(t('blog.author.form.visibility_hint')) ?></div>
                    </div>

                    <!-- Pin (admin only) -->
                    <?php if (has_permission('blog.admin')): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned" id="bl-pinned" value="1"
                                   <?= !empty($article['is_pinned']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="bl-pinned">
                                <i class="fa-solid fa-thumbtack me-1 text-warning"></i>
                                <?= e(t('blog.author.form.pin_label')) ?>
                            </label>
                            <div class="form-text"><?= e(t('blog.author.form.pin_hint')) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Scheduling -->
                    <?php
                    $currentPublishAt = $old['publish_at'] ?? ($article['publish_at'] ?? '');
                    $scheduleChecked  = !empty($currentPublishAt);
                    ?>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="bl-schedule-toggle"
                                   <?= $scheduleChecked ? 'checked' : '' ?>
                                   data-bl-schedule-toggle="1">
                            <label class="form-check-label" for="bl-schedule-toggle">
                                <i class="fa-regular fa-clock me-1"></i> <?= e(t('blog.author.form.schedule_label')) ?>
                            </label>
                        </div>
                        <div id="bl-schedule-box" class="<?= $scheduleChecked ? '' : 'd-none' ?>">
                            <input type="datetime-local" name="publish_at" id="bl-publish-at"
                                   value="<?= e($currentPublishAt ? date('Y-m-d\TH:i', strtotime($currentPublishAt)) : '') ?>"
                                   class="form-control bl-publish-at-input">
                            <div class="form-text"><?= e(t('blog.author.form.schedule_hint')) ?></div>
                        </div>
                    </div>

                    <!-- SEO (collapsible) -->
                    <div class="mb-3">
                        <button class="btn btn-link p-0 text-decoration-none" type="button"
                                data-bs-toggle="collapse" data-bs-target="#bl-seo-block"
                                aria-expanded="false" aria-controls="bl-seo-block">
                            <i class="fa-solid fa-search me-1"></i> <?= e(t('blog.author.form.seo_toggle')) ?>
                        </button>
                        <div class="collapse mt-2" id="bl-seo-block">
                            <div class="border rounded p-3">
                                <div class="mb-3">
                                    <label class="form-label"><?= e(t('blog.author.form.meta_description_label')) ?></label>
                                    <textarea name="meta_description" rows="2" maxlength="300"
                                              class="form-control"
                                              placeholder="<?= e(t('blog.author.form.meta_description_placeholder')) ?>"><?= e($old['meta_description'] ?? $article['meta_description'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= e(t('blog.author.form.meta_keywords_label')) ?></label>
                                    <input type="text" name="meta_keywords" maxlength="255"
                                           value="<?= e($old['meta_keywords'] ?? $article['meta_keywords'] ?? '') ?>"
                                           class="form-control"
                                           placeholder="<?= e(t('blog.author.form.meta_keywords_placeholder')) ?>">
                                </div>
                                <div class="mb-1">
                                    <label class="form-label"><?= e(t('blog.author.form.og_image_label')) ?></label>
                                    <input type="text" name="og_image" maxlength="255"
                                           value="<?= e($old['og_image'] ?? $article['og_image'] ?? '') ?>"
                                           class="form-control"
                                           placeholder="<?= e(t('blog.author.form.og_image_placeholder')) ?>">
                                    <div class="form-text"><?= e(t('blog.author.form.og_image_hint')) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            <?= e($isEdit ? t('blog.author.form.save_changes') : t('blog.author.form.create_draft')) ?>
                        </button>
                        <a href="<?= e(route('blog.author.index')) ?>" class="btn btn-outline-secondary"><?= e(t('blog.author.form.cancel')) ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<?php $view->end(); ?>
