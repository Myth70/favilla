<?php
/**
 * Contenuto dropdown notifiche (caricato via HTMX).
 * Variables: $items (array), $unreadCount (int)
 */

$typeIcons = [
    'info'    => 'fa-circle-info',
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'danger'  => 'fa-circle-exclamation',
];
?>

<?php $unreadCount = $unreadCount ?? 0; ?>
<?php if (!empty($items)): ?>
<div class="nt-subheader">
    <?php if ($unreadCount > 0): ?>
        <span class="nt-unread-badge">
            <i class="fa-solid fa-circle fa-2xs me-1"></i><?= e(tc('notifications.dropdown.unread_count', $unreadCount)) ?>
        </span>
    <?php else: ?>
        <span class="nt-all-read"><i class="fa-solid fa-check-double me-1"></i><?= e(t('notifications.dropdown.all_read')) ?></span>
    <?php endif; ?>
    <button class="nt-read-all-btn"
            hx-post="<?= e(route('notifications.read-all')) ?>"
            hx-vals='{"_token":"<?= csrf_token() ?>"}'
            hx-target="#nt-dropdown-content"
            hx-swap="innerHTML">
        <i class="fa-solid fa-check-double me-1"></i><?= e(t('notifications.dropdown.mark_all_read')) ?>
    </button>
</div>
<?php endif; ?>

<div class="nt-dropdown-body" id="nt-dropdown-body">
    <?php if (empty($items)): ?>
        <div class="nt-empty">
            <i class="fa-regular fa-bell-slash"></i>
            <?= e(t('notifications.dropdown.empty')) ?>
        </div>
    <?php else: ?>
        <?php foreach ($items as $notif): ?>
        <?php
            $isUnread  = empty($notif['read_at']);
            $typeClass = in_array($notif['type'], ['info','success','warning','danger'], true)
                         ? $notif['type'] : 'info';
            $icon      = !empty($notif['icon']) ? $notif['icon'] : ($typeIcons[$typeClass] ?? 'fa-circle-info');
            $iconClass = str_contains((string) $icon, 'fa-') && str_contains((string) $icon, ' ')
                         ? (string) $icon
                         : 'fa-solid ' . ltrim((string) $icon);
            $customColor = trim((string) ($notif['color'] ?? ''));
            $indicatorStyle = $customColor !== '' ? ' style="background-color:' . e($customColor) . '"' : '';
            $iconStyle = $customColor !== '' ? ' style="color:' . e($customColor) . '"' : '';
        ?>
        <div class="nt-item <?= $isUnread ? 'nt-unread' : '' ?>"
             id="nt-item-<?= (int) $notif['id'] ?>">

            <div class="nt-indicator nt-<?= e($typeClass) ?>"<?= $indicatorStyle ?>></div>

            <div class="nt-type-icon nt-<?= e($typeClass) ?>"<?= $iconStyle ?>>
                <i class="<?= e($iconClass) ?>"></i>
            </div>

            <div class="nt-item-body">
                <?php if (!empty($notif['link'])): ?>
                    <a href="<?= e($notif['link']) ?>"
                       class="nt-item-title text-decoration-none text-body"
                       data-nt-read-url="<?= e(route('notifications.read', ['id' => $notif['id']])) ?>"
                    ><?= e($notif['title']) ?></a>
                <?php else: ?>
                    <div class="nt-item-title"><?= e($notif['title']) ?></div>
                <?php endif; ?>

                <?php if (!empty($notif['body'])): ?>
                    <div class="nt-item-text"><?= nl2br(e($notif['body'])) ?></div>
                <?php endif; ?>

            </div>

            <div class="nt-item-actions">
                <?php if ($isUnread): ?>
                <button class="nt-action-btn"
                        data-bs-toggle="tooltip" title="<?= e(t('notifications.dropdown.mark_read')) ?>"
                        hx-post="<?= e(route('notifications.read', ['id' => $notif['id']])) ?>"
                        hx-vals='{"_token":"<?= csrf_token() ?>"}'
                        hx-target="#nt-item-<?= (int) $notif['id'] ?>"
                        hx-swap="outerHTML">
                    <i class="fa-solid fa-check"></i>
                </button>
                <?php endif; ?>
                <button class="nt-action-btn text-danger"
                        data-bs-toggle="tooltip" title="<?= e(t('notifications.dropdown.delete')) ?>"
                        hx-delete="<?= e(route('notifications.destroy', ['id' => $notif['id']])) ?>"
                        hx-vals='{"_token":"<?= csrf_token() ?>"}'
                        hx-target="#nt-item-<?= (int) $notif['id'] ?>"
                        hx-swap="outerHTML">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="nt-dropdown-footer">
    <a href="<?= e(route('notifications.index')) ?>" class="nt-footer-link">
        <i class="fa-solid fa-bell me-1"></i><?= e(t('notifications.dropdown.view_all')) ?><i class="fa-solid fa-arrow-right ms-2 fa-xs"></i>
    </a>
</div>
