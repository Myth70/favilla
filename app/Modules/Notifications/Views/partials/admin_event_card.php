<?php
$event = $event ?? [];
$eventSlug = (string) ($event['slug'] ?? '');
$cardId = 'ntas-event-' . preg_replace('/[^a-z0-9_-]+/i', '-', $eventSlug);
$channels = $event['channels'] ?? [];
$contextVariables = is_array($event['context_variables'] ?? null) ? $event['context_variables'] : [];
?>

<article class="ntas-event-card ntas-event-card-wrapper" id="<?= e($cardId) ?>">
    <form method="POST"
          action="<?= e(route('admin.notifications.settings.events.update', ['slug' => $eventSlug])) ?>"
          hx-post="<?= e(route('admin.notifications.settings.events.update', ['slug' => $eventSlug])) ?>"
          hx-target="#<?= e($cardId) ?>"
          hx-swap="outerHTML"
          class="ntas-event-form">
        <?= csrf_field() ?>

        <div class="ntas-event-head">
            <div>
                <div class="ntas-event-title mb-1">
                    <i class="<?= e((string) ($event['icon'] ?? 'fa-solid fa-bell')) ?> me-2"></i><?= e((string) ($event['name'] ?? $eventSlug)) ?>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge text-bg-light border"><?= e($eventSlug) ?></span>
                    <?php if (!empty($event['is_system'])): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border"><?= e(t('notifications.admin.system_badge')) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($event['description'])): ?>
                        <span class="text-muted small"><?= e((string) $event['description']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('notifications.admin.save_event')) ?>
            </button>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-4">
                <?php $iconInputId = 'ntas-icon-' . md5($eventSlug); ?>
                <label class="form-label"><?= e(t('notifications.admin.icon')) ?></label>
                <input type="hidden" id="<?= e($iconInputId) ?>" name="icon" value="<?= e((string) ($event['icon'] ?? 'fa-solid fa-bell')) ?>">
                <button type="button"
                        class="btn btn-outline-secondary w-100 ntas-picker-btn js-ntas-open-icon-modal"
                        data-target-input="<?= e($iconInputId) ?>"
                        data-preview-scope="<?= e($cardId) ?>">
                    <i class="js-ntas-icon-preview <?= e((string) ($event['icon'] ?? 'fa-solid fa-bell')) ?>" data-preview-scope="<?= e($cardId) ?>"></i>
                    <span class="js-ntas-icon-label" data-preview-scope="<?= e($cardId) ?>"><?= e((string) ($event['icon'] ?? 'fa-solid fa-bell')) ?></span>
                    <span class="ms-auto text-muted small"><?= e(t('notifications.admin.change')) ?></span>
                </button>
            </div>
            <div class="col-md-6 col-xl-4">
                <?php $colorInputId = 'ntas-color-' . md5($eventSlug); ?>
                <?php $eventColor = (string) ($event['color'] ?? ''); ?>
                <label class="form-label"><?= e(t('notifications.admin.color')) ?></label>
                <input type="hidden" id="<?= e($colorInputId) ?>" name="color" value="<?= e($eventColor) ?>">
                <button type="button"
                        class="btn btn-outline-secondary w-100 ntas-picker-btn js-ntas-open-color-modal"
                        data-target-input="<?= e($colorInputId) ?>"
                        data-preview-scope="<?= e($cardId) ?>">
                    <span class="ntas-color-dot js-ntas-color-preview <?= $eventColor === '' ? 'is-default' : '' ?>" data-preview-scope="<?= e($cardId) ?>" <?= $eventColor !== '' ? 'style="background-color:' . e($eventColor) . '"' : '' ?>></span>
                    <span class="js-ntas-color-label" data-preview-scope="<?= e($cardId) ?>"><?= e($eventColor !== '' ? $eventColor : t('notifications.admin.color_default')) ?></span>
                    <span class="ms-auto text-muted small"><?= e(t('notifications.admin.change')) ?></span>
                </button>
            </div>
            <div class="col-xl-4">
                <div class="form-label mb-2"><?= e(t('notifications.admin.context_vars')) ?></div>
                <div class="ntas-context-chip-wrap">
                    <?php if (!empty($contextVariables)): ?>
                        <?php foreach ($contextVariables as $key => $label): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary ntas-context-chip" data-template-token="{{<?= e((string) $key) ?>}}" title="<?= e((string) $label) ?>">{{<?= e((string) $key) ?>}}</button>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted small"><?= e(t('notifications.admin.no_vars')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ntas-channel-grid">
            <?php foreach ($channels as $channel): ?>
                <section class="ntas-channel-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="mb-0"><?= e((string) $channel['name']) ?></h6>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="channels[<?= e((string) $channel['slug']) ?>][enabled]" value="1" <?= !empty($channel['enabled']) ? 'checked' : '' ?>>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-muted"><?= e(t('notifications.admin.subject_template')) ?></label>
                        <input type="text"
                               class="form-control form-control-sm ntas-template-input"
                               name="channels[<?= e((string) $channel['slug']) ?>][subject_template]"
                               value="<?= e((string) ($channel['subject_template'] ?? '')) ?>"
                               placeholder="{{title}}">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-muted"><?= e(t('notifications.admin.body_template')) ?></label>
                        <textarea class="form-control form-control-sm ntas-template-input"
                                  rows="3"
                                  name="channels[<?= e((string) $channel['slug']) ?>][body_template]"
                                  placeholder="{{body}}<?= (string) ($channel['slug'] ?? '') === 'telegram' ? '\n\n{{link}}' : '' ?>"><?= e((string) ($channel['body_template'] ?? '')) ?></textarea>
                    </div>

                    <input type="hidden" name="channels[<?= e((string) $channel['slug']) ?>][layout_config]" value="<?= e((string) ($channel['layout_config'] ?? '')) ?>">
                </section>
            <?php endforeach; ?>
        </div>
    </form>
</article>
