<?php
$moduleOptions = $moduleOptions ?? [];
$aliasesDefault = (string) ($aliases ?? '');
$aliasesForTextarea = $aliasesDefault !== '' ? str_replace('||', "\n", $aliasesDefault) : '';
?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(t('helponline.admin.edit_entry')) ?></span>
        <a href="<?= e(route('helponline.admin.entries')) ?>" class="btn btn-sm btn-outline-secondary"><?= e(t('helponline.admin.back_entries')) ?></a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= e(route('helponline.admin.entries.update', ['id' => (int) ($id ?? 0)])) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <label class="form-label"><?= e(t('helponline.admin.col_module')) ?></label>
                <select name="module_id" class="form-select" required>
                    <option value=""><?= e(t('helponline.admin.select_module')) ?></option>
                    <?php foreach ($moduleOptions as $module): ?>
                        <?php $mid = (int) ($module['id'] ?? 0); ?>
                        <option value="<?= $mid ?>" <?= ((int) ($module_id ?? 0) === $mid) ? 'selected' : '' ?>>
                            <?= e((string) ($module['label'] ?? $module['module_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label"><?= e(t('helponline.admin.question')) ?></label>
                <input type="text" name="question" class="form-control" value="<?= e((string) ($question ?? '')) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('helponline.admin.answer_markdown_full')) ?></label>
                <textarea name="answer_markdown" class="form-control" rows="12" required><?= e((string) ($answer_markdown ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('helponline.admin.excerpt')) ?></label>
                <textarea name="excerpt" class="form-control" rows="2"><?= e((string) ($excerpt ?? '')) ?></textarea>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label"><?= e(t('helponline.admin.audience')) ?></label>
                <select name="audience" class="form-select">
                    <option value="user" <?= (($audience ?? 'user') === 'user') ? 'selected' : '' ?>><?= e(t('helponline.admin.audience_user')) ?></option>
                    <option value="admin" <?= (($audience ?? '') === 'admin') ? 'selected' : '' ?>><?= e(t('helponline.admin.audience_admin')) ?></option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label"><?= e(t('helponline.admin.locale')) ?></label>
                <input type="text" name="locale" class="form-control" value="<?= e((string) ($locale ?? 'it')) ?>">
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label"><?= e(t('helponline.admin.translation_of')) ?></label>
                <select name="source_entry_id" class="form-select">
                    <option value=""><?= e(t('helponline.admin.translation_of_none')) ?></option>
                    <?php foreach (($canonicalEntries ?? []) as $cand): ?>
                        <?php $cid = (int) ($cand['id'] ?? 0); ?>
                        <option value="<?= $cid ?>" <?= ((int) ($source_entry_id ?? 0) === $cid) ? 'selected' : '' ?>>
                            <?= e((string) ($cand['module_name'] ?? '')) ?> — <?= e((string) ($cand['question'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= e(t('helponline.admin.translation_of_hint')) ?></div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label"><?= e(t('helponline.admin.route')) ?></label>
                <input type="text" name="route_name" class="form-control" value="<?= e((string) ($route_name ?? '')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label"><?= e(t('helponline.admin.permission')) ?></label>
                <input type="text" name="permission_slug" class="form-control" value="<?= e((string) ($permission_slug ?? '')) ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label"><?= e(t('helponline.admin.weight')) ?></label>
                <input type="number" name="ranking_weight" class="form-control" value="<?= (int) ($ranking_weight ?? 0) ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label"><?= e(t('helponline.admin.order')) ?></label>
                <input type="number" name="sort_order" class="form-control" value="<?= (int) ($sort_order ?? 0) ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label"><?= e(t('common.label.status')) ?></label>
                <select name="is_active" class="form-select">
                    <option value="1" <?= ((int) ($is_active ?? 1) === 1) ? 'selected' : '' ?>><?= e(t('helponline.admin.on')) ?></option>
                    <option value="0" <?= ((int) ($is_active ?? 1) === 0) ? 'selected' : '' ?>><?= e(t('helponline.admin.off')) ?></option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label d-flex align-items-center gap-2">
                    <span><?= e(t('helponline.admin.aliases_block_label')) ?></span>
                    <span class="badge text-bg-light fw-normal small"><?= e(t('helponline.admin.aliases_block_hint')) ?></span>
                </label>
                <textarea name="aliases" class="form-control" rows="5" placeholder="<?= e(t('helponline.admin.aliases_block_placeholder')) ?>"><?= e($aliasesForTextarea) ?></textarea>
                <div class="form-text">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i>
                    <?= t('helponline.admin.aliases_note') ?>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= e(t('helponline.admin.save_entry')) ?></button>
                <a href="<?= e(route('helponline.admin.entries')) ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
