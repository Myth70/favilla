<?php
$modules = $modules ?? [];
$totalEvents = 0;
foreach ($modules as $m) {
    $totalEvents += count($m['events'] ?? []);
}
?>
<div id="ntas-events-table-wrap">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
        <div>
            <h5 class="mb-1"><?= e(t('notifications.admin.events_catalog')) ?></h5>
            <p class="text-muted mb-0 small"><?= t('notifications.admin.events_summary', ['events' => (int) $totalEvents, 'modules' => count($modules)]) ?></p>
        </div>
        <div class="ntas-table-filter">
            <div class="input-group input-group-sm ntas-table-filter-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" class="form-control" id="ntas-events-filter" placeholder="<?= e(t('notifications.admin.events_filter_ph')) ?>">
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle ntas-events-table mb-0">
            <thead>
                <tr>
                    <th class="ntas-col-icon"></th>
                    <th><?= e(t('notifications.admin.col_event')) ?></th>
                    <th class="text-center ntas-col-channel"><?= e(t('notifications.admin.col_in_app')) ?></th>
                    <th class="text-center ntas-col-channel"><?= e(t('notifications.admin.col_email')) ?></th>
                    <th class="text-center ntas-col-channel"><?= e(t('notifications.admin.col_telegram')) ?></th>
                    <th class="text-center ntas-col-actions"><?= e(t('notifications.admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                    <tr class="ntas-module-header-row" data-module="<?= e((string) ($module['slug'] ?? '')) ?>">
                        <td colspan="6">
                            <div class="d-flex align-items-center gap-2">
                                <i class="<?= e((string) ($module['icon'] ?? 'fa-solid fa-cube')) ?> text-muted"></i>
                                <strong><?= e((string) ($module['label'] ?? $module['slug'] ?? '')) ?></strong>
                                <span class="badge text-bg-light border"><?= e((string) count($module['events'] ?? [])) ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php foreach (($module['events'] ?? []) as $event): ?>
                        <?php
                        $eventSlug = (string) ($event['slug'] ?? '');
                        $eventIcon = (string) ($event['icon'] ?? 'fa-solid fa-bell');
                        $eventColor = (string) ($event['color'] ?? '');
                        $eventName = (string) ($event['name'] ?? $eventSlug);
                        $channels = $event['channels'] ?? [];
                        $channelStatus = [];
                        foreach ($channels as $ch) {
                            $channelStatus[(string) $ch['slug']] = !empty($ch['enabled']);
                        }
                        ?>
                        <tr class="ntas-event-row" data-event-slug="<?= e($eventSlug) ?>" data-event-name="<?= e(mb_strtolower($eventName)) ?>" data-module="<?= e((string) ($module['slug'] ?? '')) ?>">
                            <td class="text-center">
                                <span class="ntas-table-icon-dot" <?= $eventColor !== '' ? 'style="color:' . e($eventColor) . '"' : '' ?>>
                                    <i class="<?= e($eventIcon) ?>"></i>
                                </span>
                            </td>
                            <td>
                                <div class="ntas-table-event-name"><?= e($eventName) ?></div>
                                <div class="ntas-table-event-slug">
                                    <code><?= e($eventSlug) ?></code>
                                    <?php if (!empty($event['is_system'])): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border ms-1 ntas-system-badge"><?= e(t('notifications.admin.system_badge')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($channelStatus['in_app'] ?? false): ?>
                                    <span class="badge ntas-ch-badge ntas-ch-on"><i class="fa-solid fa-check fa-xs"></i></span>
                                <?php else: ?>
                                    <span class="badge ntas-ch-badge ntas-ch-off"><i class="fa-solid fa-minus fa-xs"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($channelStatus['email'] ?? false): ?>
                                    <span class="badge ntas-ch-badge ntas-ch-on"><i class="fa-solid fa-check fa-xs"></i></span>
                                <?php else: ?>
                                    <span class="badge ntas-ch-badge ntas-ch-off"><i class="fa-solid fa-minus fa-xs"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($channelStatus['telegram'] ?? false): ?>
                                    <span class="badge ntas-ch-badge ntas-ch-on"><i class="fa-solid fa-check fa-xs"></i></span>
                                <?php else: ?>
                                    <span class="badge ntas-ch-badge ntas-ch-off"><i class="fa-solid fa-minus fa-xs"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <form method="post"
                                      action="<?= e(route('admin.notifications.settings.events.simulate', ['slug' => $eventSlug])) ?>"
                                      class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-success"
                                            title="<?= e(t('notifications.admin.simulate_tip')) ?>">
                                        <i class="fa-solid fa-flask me-1"></i><?= e(t('notifications.admin.simulate')) ?>
                                    </button>
                                </form>
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary ntas-edit-event-btn ms-1"
                                        hx-get="<?= e(route('admin.notifications.settings.events.edit', ['slug' => $eventSlug])) ?>"
                                        hx-target="#ntas-event-modal-content"
                                        hx-swap="innerHTML"
                                        data-bs-toggle="modal"
                                        data-bs-target="#ntas-event-modal"
                                        title="<?= e(t('notifications.admin.edit_event_tip')) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
