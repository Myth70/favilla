<?php
/**
 * Badge pubblicazione + toggle.
 * Variables: $release
 */
$published = (bool) ($release['is_published'] ?? false);
$id = (int) $release['id'];
?>
<button type="button"
        class="btn btn-sm <?= $published ? 'btn-success' : 'btn-outline-secondary' ?>"
        data-adm-toggle="publish"
        data-bs-toggle="tooltip"
        title="<?= e($published ? t('admin.changelog.to_draft_tip') : t('admin.changelog.to_publish_tip')) ?>"
        hx-post="<?= e(route('admin.changelog.publish', ['id' => $id])) ?>"
        hx-target="#ch-badge-<?= $id ?>"
        hx-swap="innerHTML">
    <?php if ($published): ?>
        <i class="fa-solid fa-check"></i> <?= e(t('admin.changelog.published')) ?>
    <?php else: ?>
        <i class="fa-regular fa-clock"></i> <?= e(t('admin.changelog.draft')) ?>
    <?php endif; ?>
</button>
