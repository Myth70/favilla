<?php
$view->layout('main');
$view->start('content');

$isEdit = $endpoint !== null;
$subscribed = $isEdit ? (array) ($endpoint['event_types'] ?? []) : [];
$formAction = $isEdit ? route('webhooks.update', ['id' => $endpoint['id']]) : route('webhooks.store');
?>

<div class="container-fluid py-3">

<?php $view->include('partials/pf-hero-module', [
    'moduleName'     => $isEdit ? t('webhooks.edit_title') : t('webhooks.create_title'),
    'moduleIcon'     => 'fa-solid fa-bolt',
    'moduleSubtitle' => t('webhooks.form_subtitle'),
    'moduleButtons'  => '<a href="' . e(route('webhooks.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> ' . e(t('webhooks.back')) . '</a>',
]); ?>

    <form method="POST" action="<?= e($formAction) ?>">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="wh-url"><?= e(t('webhooks.field_url')) ?></label>
                    <input type="url" class="form-control font-monospace" id="wh-url" name="url" required
                           value="<?= e($isEdit ? $endpoint['url'] : '') ?>" placeholder="https://example.com/webhook">
                    <div class="form-text"><?= e(t('webhooks.field_url_hint')) ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="wh-desc"><?= e(t('webhooks.field_description')) ?></label>
                    <input type="text" class="form-control" id="wh-desc" name="description" maxlength="255"
                           value="<?= e($isEdit ? (string) ($endpoint['description'] ?? '') : '') ?>">
                </div>

                <?php if ($isEdit): ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="wh-active" name="is_active" value="1"
                           <?= !empty($endpoint['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="wh-active"><?= e(t('webhooks.field_active')) ?></label>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="app-card-icon"><i class="fa-solid fa-diagram-project"></i></span>
                <span class="fw-semibold"><?= e(t('webhooks.field_events')) ?></span>
            </div>
            <div class="card-body">
                <p class="small text-secondary"><?= e(t('webhooks.field_events_hint')) ?></p>
                <?php if (empty($eventCatalog)): ?>
                    <span class="small text-secondary"><?= e(t('webhooks.no_events')) ?></span>
                <?php else: ?>
                    <?php foreach ($eventCatalog as $group): ?>
                    <div class="mb-3">
                        <div class="fw-semibold small text-uppercase text-secondary mb-2"><?= e($group['module']) ?></div>
                        <div class="row">
                            <?php foreach ($group['events'] as $event): ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="event_types[]"
                                           value="<?= e($event['slug']) ?>" id="ev-<?= e($event['slug']) ?>"
                                           <?= in_array($event['slug'], $subscribed, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ev-<?= e($event['slug']) ?>">
                                        <?= e($event['name']) ?>
                                        <span class="d-block font-monospace small text-secondary"><?= e($event['slug']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= e(route('webhooks.index')) ?>" class="btn btn-outline-secondary"><?= e(t('webhooks.cancel')) ?></a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('webhooks.save')) ?>
            </button>
        </div>
    </form>

    <?php if ($isEdit): ?>
    <div class="card shadow-sm mt-3 border-warning-subtle">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-semibold"><?= e(t('webhooks.secret_section')) ?></div>
                <div class="small text-secondary"><?= e(t('webhooks.secret_section_hint')) ?></div>
            </div>
            <form method="POST" action="<?= e(route('webhooks.secret.regenerate', ['id' => $endpoint['id']])) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning"
                        data-app-confirm="<?= e(t('webhooks.secret_regenerate_confirm')) ?>"
                        data-app-confirm-label="<?= e(t('webhooks.secret_regenerate')) ?>">
                    <i class="fa-solid fa-rotate me-1"></i><?= e(t('webhooks.secret_regenerate')) ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php $view->end(); ?>
