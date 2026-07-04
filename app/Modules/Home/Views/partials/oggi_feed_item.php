<?php
/**
 * Oggi feed — single item markup.
 * Variables: $item (array), $groupId (string|null)
 *
 * Used by both the urgenza-grouped view and the timeline view.
 */
$item    = is_array($item ?? null) ? $item : [];
$groupId = (string) ($groupId ?? '');

$action         = is_array($item['action'] ?? null) ? $item['action'] : null;
$urgencyClass   = (string) ($item['urgency_class'] ?? 'secondary');
$subtitle       = trim((string) ($item['subtitle'] ?? ''));
$priorityScore  = (int) ($item['priority_score'] ?? 0);
$isUrgent       = $priorityScore >= 80 || in_array($urgencyClass, ['danger', 'warning'], true);
$sourceColor    = (string) ($item['source_color'] ?? 'secondary');
$rawItemId      = (string) ($item['id'] ?? '');
$notifNumericId = str_starts_with($rawItemId, 'notification:') ? (int) substr($rawItemId, 13) : 0;
?>
<div class="list-group-item list-group-item-action py-3 hm-today-item hm-today-item--<?= e($urgencyClass) ?>"
     role="listitem"
     data-hm-item-priority="<?= (int) $priorityScore ?>"
     data-hm-item-urgency="<?= e($urgencyClass) ?>"
     data-hm-item-urgent="<?= $isUrgent ? '1' : '0' ?>"
     data-hm-item-source="<?= e((string) ($item['source_key'] ?? '')) ?>"
     <?php if ($groupId !== ''): ?>data-hm-group="<?= e($groupId) ?>"<?php endif; ?>>
    <div class="d-flex gap-3 align-items-start">

        <?php /* Icona sorgente */ ?>
        <div class="hm-today-source-icon hm-today-icon--<?= e($sourceColor) ?> flex-shrink-0 mt-1" aria-hidden="true">
            <i class="fa-solid <?= e((string) ($item['source_icon'] ?? 'fa-bolt')) ?>"></i>
        </div>

        <?php /* Corpo */ ?>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                <span class="badge hm-today-source-pill"><?= e((string) ($item['source_label'] ?? t('home.item.fallback_source'))) ?></span>
                <span class="badge text-bg-<?= e($urgencyClass) ?>"><?= e((string) ($item['urgency_label'] ?? t('home.item.fallback_urgency'))) ?></span>
                <?php if (!empty($item['due_label'])): ?>
                    <span class="small text-body-secondary ms-1">
                        <i class="fa-regular fa-clock me-1"></i><?= e((string) $item['due_label']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="fw-semibold text-truncate hm-today-title"><?= e((string) ($item['title'] ?? t('home.item.fallback_title'))) ?></div>
            <?php if ($subtitle !== ''): ?>
                <div class="small text-body-secondary text-truncate mt-1"><?= e($subtitle) ?></div>
            <?php endif; ?>
        </div>

        <?php /* Azioni */ ?>
        <div class="d-flex flex-wrap gap-1 align-items-start flex-shrink-0">
            <?php if (!empty($item['link'])): ?>
                <a href="<?= e((string) $item['link']) ?>"
                   class="btn btn-sm btn-outline-secondary"
                   title="<?= e(t('home.item.open_detail')) ?>"
                   data-bs-toggle="tooltip">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            <?php endif; ?>

            <?php if ($action && ($action['kind'] ?? '') === 'complete_task'): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-success"
                        title="<?= e(t('home.item.complete_task')) ?>"
                        data-bs-toggle="tooltip"
                        data-hm-quick-action="complete-task"
                        data-app-confirm="<?= e(t('home.item.complete_confirm')) ?>"
                        data-app-confirm-label="<?= e(t('home.item.complete')) ?>"
                        hx-post="<?= e((string) ($action['url'] ?? '')) ?>"
                        hx-vals='{"_token":"<?= csrf_token() ?>"}'
                        hx-swap="none"
                        hx-disabled-elt="this">
                    <i class="fa-solid fa-check"></i>
                </button>
            <?php endif; ?>

            <?php if ($action && ($action['kind'] ?? '') === 'mark_notification_read'): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-warning"
                        title="<?= e(t('home.item.read_notif')) ?>"
                        data-bs-toggle="tooltip"
                        data-hm-quick-action="read-notification"
                        hx-post="<?= e((string) ($action['url'] ?? '')) ?>"
                        hx-vals='{"_token":"<?= csrf_token() ?>"}'
                        hx-swap="none"
                        hx-disabled-elt="this">
                    <i class="fa-solid fa-envelope-open"></i>
                </button>
            <?php endif; ?>

            <?php if ($notifNumericId > 0): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        title="<?= e(t('home.item.delete_notif')) ?>"
                        data-bs-toggle="tooltip"
                        data-hm-quick-action="delete-notification"
                        hx-delete="<?= e(route('notifications.destroy', ['id' => $notifNumericId])) ?>"
                        hx-vals='{"_token":"<?= csrf_token() ?>"}'
                        hx-swap="none"
                        hx-disabled-elt="this">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            <?php endif; ?>
        </div>

    </div>
</div>
