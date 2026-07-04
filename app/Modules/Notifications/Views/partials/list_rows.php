<?php
/**
 * Partial: righe lista notifiche (HTMX swap).
 * Variables: $items, $total, $page, $pages, $filter
 */

$typeIcons = [
    'info'    => 'fa-circle-info',
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'danger'  => 'fa-circle-exclamation',
];

$filterQs = $filter ? '&filter=' . urlencode($filter) : '';
?>

<?php if (empty($items)): ?>
    <div class="nt-empty py-4">
        <i class="fa-regular fa-bell-slash"></i>
        <?php if ($filter === 'unread'): ?>
            <?= e(t('notifications.list.empty_unread')) ?>
        <?php elseif ($filter === 'read'): ?>
            <?= e(t('notifications.list.empty_read')) ?>
        <?php else: ?>
            <?= e(t('notifications.list.empty_all')) ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php
    $bulkHiddenInputs = csrf_field()
        . '<input type="hidden" name="return_page" value="' . (int) $page . '">'
        . '<input type="hidden" name="return_filter" value="' . e((string) ($filter ?? '')) . '">';
    ?>
    <div class="border-bottom px-3 py-3">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="form-check m-0">
                    <input class="form-check-input"
                           type="checkbox"
                           id="nt-bulk-check-all"
                           aria-label="<?= e(t('notifications.list.select_all_aria')) ?>">
                </div>
                <div class="small text-muted">
                    <span class="fw-semibold text-body" id="nt-selected-count">0</span>
                    <?= e(t('notifications.list.selected_on_page')) ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if ($filter !== 'read'): ?>
                    <form id="nt-bulk-read-form"
                          method="POST"
                          action="<?= e(route('notifications.bulk-read')) ?>"
                          class="m-0"
                          data-nt-bulk-form="1">
                        <?= $bulkHiddenInputs ?>
                        <button type="submit"
                                class="btn btn-sm btn-outline-secondary"
                                data-nt-bulk-submit="1"
                                disabled>
                            <i class="fa-solid fa-check-double me-1"></i><?= e(t('notifications.list.mark_selected_read')) ?>
                        </button>
                    </form>
                <?php endif; ?>

                <form id="nt-bulk-delete-form"
                      method="POST"
                      action="<?= e(route('notifications.bulk-destroy')) ?>"
                      class="m-0"
                      data-nt-bulk-form="1">
                    <?= $bulkHiddenInputs ?>
                    <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            data-nt-bulk-submit="1"
                            data-app-confirm="<?= e(t('notifications.list.delete_selected_confirm')) ?>"
                            data-app-confirm-label="<?= e(t('notifications.list.delete')) ?>"
                            data-app-confirm-class="btn-danger"
                            disabled>
                        <i class="fa-solid fa-trash me-1"></i><?= e(t('notifications.list.delete_selected')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

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
         id="nt-row-<?= (int) $notif['id'] ?>">

        <div class="form-check m-0 align-self-start">
            <input class="form-check-input nt-row-check"
                   type="checkbox"
                   value="<?= (int) $notif['id'] ?>"
                   id="nt-check-<?= (int) $notif['id'] ?>"
                   aria-label="<?= e(t('notifications.list.select_one_aria', ['id' => (int) $notif['id']])) ?>">
        </div>

        <div class="nt-indicator nt-<?= e($typeClass) ?>"<?= $indicatorStyle ?>></div>

        <div class="nt-type-icon nt-<?= e($typeClass) ?>"<?= $iconStyle ?>>
            <i class="<?= e($iconClass) ?>"></i>
        </div>

        <div class="nt-item-body">
            <?php if (!empty($notif['link'])): ?>
                <a href="<?= e($notif['link']) ?>" class="nt-item-title nt-item-title-full text-decoration-none text-body">
                    <?= e($notif['title']) ?>
                </a>
            <?php else: ?>
                <div class="nt-item-title nt-item-title-full"><?= e($notif['title']) ?></div>
            <?php endif; ?>

            <?php if (!empty($notif['body'])): ?>
                <div class="nt-item-text nt-item-text-full">
                    <?= nl2br(e($notif['body'])) ?>
                </div>
            <?php endif; ?>

        </div>

        <div class="nt-item-actions d-flex flex-row gap-2 align-items-center">
            <?php if ($isUnread): ?>
            <button type="button"
                class="nt-action-btn"
                    data-bs-toggle="tooltip" title="<?= e(t('notifications.list.mark_read')) ?>"
                    hx-post="<?= e(route('notifications.read', ['id' => $notif['id']])) ?>"
                    hx-vals='{"_token":"<?= csrf_token() ?>"}'
                    hx-target="#nt-row-<?= (int) $notif['id'] ?>"
                    hx-swap="outerHTML">
                <i class="fa-solid fa-check fa-sm"></i>
            </button>
            <?php endif; ?>
            <button type="button"
                class="nt-action-btn text-danger"
                    data-bs-toggle="tooltip" title="<?= e(t('notifications.list.delete')) ?>"
                    hx-delete="<?= e(route('notifications.destroy', ['id' => $notif['id']])) ?>"
                    hx-vals='{"_token":"<?= csrf_token() ?>"}'
                    hx-target="#nt-row-<?= (int) $notif['id'] ?>"
                    hx-swap="outerHTML">
                <i class="fa-solid fa-trash fa-sm"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Paginazione -->
    <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-center align-items-center gap-2 py-3">
        <?php if ($page > 1): ?>
            <a href="?page=<?= ($page - 1) . e($filterQs) ?>"
               class="btn btn-sm btn-outline-secondary"
               hx-get="<?= e(route('notifications.index')) ?>?page=<?= ($page - 1) . e($filterQs) ?>"
               hx-target="#nt-list-container"
               hx-swap="innerHTML">
                <i class="fa-solid fa-chevron-left fa-xs"></i>
            </a>
        <?php endif; ?>

        <span class="text-muted small"><?= e(t('notifications.list.page_of', ['page' => $page, 'pages' => $pages])) ?></span>

        <?php if ($page < $pages): ?>
            <a href="?page=<?= ($page + 1) . e($filterQs) ?>"
               class="btn btn-sm btn-outline-secondary"
               hx-get="<?= e(route('notifications.index')) ?>?page=<?= ($page + 1) . e($filterQs) ?>"
               hx-target="#nt-list-container"
               hx-swap="innerHTML">
                <i class="fa-solid fa-chevron-right fa-xs"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
