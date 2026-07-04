<?php
/**
 * Oggi feed — shell che orchestra header, progress, quick-add, toggle, viste, completate.
 * Variables: $todayFeed, $completedToday
 */
$todayFeed      = is_array($todayFeed ?? null) ? $todayFeed : [];
$items          = is_array($todayFeed['items'] ?? null) ? $todayFeed['items'] : [];
$counts         = is_array($todayFeed['counts'] ?? null) ? $todayFeed['counts'] : [];
$stats          = is_array($todayFeed['stats']  ?? null) ? $todayFeed['stats']  : [];
$generatedAt    = (string) ($todayFeed['generated_at'] ?? '');
$generatedTime  = $generatedAt !== '' ? date('H:i', strtotime($generatedAt) ?: time()) : date('H:i');
$totalItems     = count($items);
$completedToday = is_array($completedToday ?? null) ? $completedToday : [];

$completedN  = (int) ($stats['completed_today'] ?? 0);
$openN       = (int) ($stats['open_today']      ?? 0);
$progressTot = (int) ($stats['progress_total']  ?? 0);
$progressPct = $progressTot > 0 ? (int) round(($completedN / $progressTot) * 100) : 0;

$activeSources = 0;
foreach (['tasks', 'calendar', 'contacts', 'notifications'] as $k) {
    if ((int) ($counts[$k] ?? 0) > 0) {
        $activeSources++;
    }
}
$showSourceFilters = $activeSources >= 2;

$urgentCount = 0;
foreach ($items as $item) {
    $ps = (int) ($item['priority_score'] ?? 0);
    $uc = (string) ($item['urgency_class'] ?? '');
    if ($ps >= 80 || in_array($uc, ['danger', 'warning'], true)) {
        $urgentCount++;
    }
}

$canQuickAdd = isModuleEnabled('Tasks') && has_permission('tasks.create');
?>

<div class="card shadow-sm hm-today-card">

    <?php /* ── Header ─────────────────────────────────────────────────────────── */ ?>
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="app-card-icon"><i class="fa-solid fa-bolt"></i></span>
            <span class="fw-semibold"><?= e(t('home.feed.today_actions')) ?></span>
            <?php if ($totalItems > 0): ?>
                <span class="badge bg-secondary fw-normal ms-1"><?= (int) $totalItems ?></span>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge rounded-pill text-bg-danger hm-today-kpi" title="<?= e(t('home.feed.urgent_tasks')) ?>" data-bs-toggle="tooltip">
                <i class="fa-solid fa-list-check me-1"></i><?= e(t('home.feed.tasks_count', ['count' => (int) ($counts['tasks'] ?? 0)])) ?>
            </span>
            <span class="badge rounded-pill text-bg-info hm-today-kpi" title="<?= e(t('home.feed.calendar_events')) ?>" data-bs-toggle="tooltip">
                <i class="fa-solid fa-calendar-days me-1"></i><?= e(t('home.feed.events_count', ['count' => (int) ($counts['calendar'] ?? 0)])) ?>
            </span>
            <span class="badge rounded-pill text-bg-secondary hm-today-kpi" title="<?= e(t('home.feed.contact_recurrences')) ?>" data-bs-toggle="tooltip">
                <i class="fa-solid fa-address-book me-1"></i><?= e(t('home.feed.recurrences_count', ['count' => (int) ($counts['contacts'] ?? 0)])) ?>
            </span>
            <span class="badge rounded-pill text-bg-warning hm-today-kpi" title="<?= e(t('home.feed.unread_notifs')) ?>" data-bs-toggle="tooltip">
                <i class="fa-solid fa-bell me-1"></i><?= e(t('home.feed.notifs_count', ['count' => (int) ($counts['notifications'] ?? 0)])) ?>
            </span>
            <span class="text-body-secondary small ms-1 hm-today-timestamp" title="<?= e(t('home.feed.last_update')) ?>">
                <i class="fa-regular fa-clock me-1"></i><?= e($generatedTime) ?>
            </span>
            <?php if (($counts['notifications'] ?? 0) > 0): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-warning hm-today-read-all"
                    title="<?= e(t('home.feed.mark_all_read')) ?>"
                    data-bs-toggle="tooltip"
                    data-hm-quick-action="read-all-notifications"
                    hx-post="<?= e(route('notifications.read-all')) ?>"
                    hx-vals='{"_token":"<?= csrf_token() ?>"}'
                    hx-swap="none"
                    hx-disabled-elt="this">
                <i class="fa-solid fa-check-double"></i>
            </button>
            <?php endif; ?>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary rounded-circle hm-today-refresh"
                    title="<?= e(t('home.feed.refresh_now')) ?>"
                    aria-label="<?= e(t('home.feed.refresh_now')) ?>"
                    data-bs-toggle="tooltip"
                    hx-get="<?= e(route('home.today.feed')) ?>"
                    hx-target="#oggi-feed"
                    hx-swap="innerHTML"
                    hx-indicator="#oggi-feed-indicator">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>
    </div>

    <?php /* ── Progress bar (solo se c'è un denominatore) ──────────────────────── */ ?>
    <?php if ($progressTot > 0): ?>
    <div class="hm-today-progress px-3 py-2 border-bottom">
        <div class="d-flex justify-content-between align-items-center small mb-1">
            <span class="text-body-secondary">
                <i class="fa-regular fa-circle-check me-1"></i><?= e(t('home.feed.day_progress')) ?>
            </span>
            <span class="fw-semibold"><?= (int) $completedN ?> / <?= (int) $progressTot ?></span>
        </div>
        <div class="progress hm-today-progress-bar" role="progressbar"
             aria-valuenow="<?= (int) $progressPct ?>" aria-valuemin="0" aria-valuemax="100"
             aria-label="<?= e(t('home.feed.day_progress')) ?>">
            <div class="progress-bar bg-success" style="width: <?= (int) $progressPct ?>%"></div>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── Quick-add (solo se permesso e modulo attivo) ─────────────────────── */ ?>
    <?php if ($canQuickAdd): ?>
    <form class="hm-today-quickadd px-3 py-2 border-bottom d-flex gap-2 align-items-center"
          hx-post="<?= e(route('home.today.action.quick-add-task')) ?>"
          hx-swap="none"
          hx-disabled-elt="find button">
        <?= csrf_field() ?>
        <i class="fa-solid fa-plus text-body-secondary" aria-hidden="true"></i>
        <input type="text"
               name="title"
               class="form-control form-control-sm border-0 shadow-none hm-today-quickadd-input"
               placeholder="<?= e(t('home.feed.quickadd_ph')) ?>"
               maxlength="255"
               aria-label="<?= e(t('home.feed.quickadd_aria')) ?>"
               required>
        <button type="submit" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-arrow-right d-md-none"></i>
            <span class="d-none d-md-inline"><?= e(t('home.feed.add')) ?></span>
        </button>
    </form>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <?php /* ── Stato vuoto ──────────────────────────────────────────────────── */ ?>
    <div class="card-body text-center py-5 text-body-secondary hm-today-empty">
        <i class="fa-solid fa-circle-check fa-2x mb-3 d-block"></i>
        <p class="mb-3"><?= e(t('home.feed.empty')) ?></p>
        <a href="<?= e(route('home.index')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-house me-1"></i><?= e(t('home.feed.back_dashboard')) ?>
        </a>
    </div>

    <?php else: ?>

    <?php /* ── Toggle view-mode + barra filtri ────────────────────────────────── */ ?>
    <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center justify-content-between gap-2 hm-today-filters-wrap">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group btn-group-sm hm-today-viewmode" role="group" aria-label="<?= e(t('home.feed.viewmode')) ?>">
                <button type="button" class="btn btn-outline-secondary" data-hm-today-viewmode="timeline" aria-pressed="false">
                    <i class="fa-regular fa-clock me-1"></i><?= e(t('home.feed.timeline')) ?>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-hm-today-viewmode="urgenza" aria-pressed="false">
                    <i class="fa-solid fa-layer-group me-1"></i><?= e(t('home.feed.urgency')) ?>
                </button>
            </div>

            <div class="vr d-none d-md-block"></div>

            <div class="d-flex flex-wrap gap-1 align-items-center" role="group" aria-label="<?= e(t('home.feed.filter_aria')) ?>">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary active"
                        data-hm-today-filter="all"
                        aria-pressed="true"><?= e(t('home.feed.filter_all')) ?><?php if ($totalItems > 0): ?> <span class="hm-today-filter-badge badge bg-secondary ms-1"><?= (int) $totalItems ?></span><?php endif; ?></button>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-hm-today-filter="urgent"
                        aria-pressed="false">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e(t('home.feed.filter_urgent')) ?><?php if ($urgentCount > 0): ?> <span class="hm-today-filter-badge badge bg-danger ms-1"><?= (int) $urgentCount ?></span><?php endif; ?>
                </button>
                <?php if ($showSourceFilters): ?>
                    <?php if (($counts['tasks'] ?? 0) > 0): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-hm-today-filter="source:tasks"
                            aria-pressed="false">
                        <i class="fa-solid fa-list-check me-1"></i><?= e(t('home.feed.filter_tasks')) ?> <span class="hm-today-filter-badge badge bg-secondary ms-1"><?= (int) ($counts['tasks'] ?? 0) ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if (($counts['calendar'] ?? 0) > 0): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-hm-today-filter="source:calendar"
                            aria-pressed="false">
                        <i class="fa-solid fa-calendar-days me-1"></i><?= e(t('home.feed.filter_calendar')) ?> <span class="hm-today-filter-badge badge bg-secondary ms-1"><?= (int) ($counts['calendar'] ?? 0) ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if (($counts['contacts'] ?? 0) > 0): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-hm-today-filter="source:contacts"
                            aria-pressed="false">
                        <i class="fa-solid fa-address-book me-1"></i><?= e(t('home.feed.filter_contacts')) ?> <span class="hm-today-filter-badge badge bg-secondary ms-1"><?= (int) ($counts['contacts'] ?? 0) ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if (($counts['notifications'] ?? 0) > 0): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-hm-today-filter="source:notifications"
                            aria-pressed="false">
                        <i class="fa-solid fa-bell me-1"></i><?= e(t('home.feed.filter_notifs')) ?> <span class="hm-today-filter-badge badge bg-secondary ms-1"><?= (int) ($counts['notifications'] ?? 0) ?></span>
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="small text-body-secondary hm-today-filter-count">
            <?= str_replace(
                [':visible', ':total'],
                ['<span data-hm-visible-count>' . (int) $totalItems . '</span>', (string) (int) $totalItems],
                e(t('home.feed.visible_count'))
            ) ?>
        </div>
    </div>

    <?php /* ── Viste: timeline (default visible) + urgenza (nascosta) ──────────── */ ?>
    <div class="hm-today-mode hm-today-mode--timeline" data-hm-today-mode="timeline">
        <?php $view->include('Home/Views/partials/oggi_feed_timeline', ['items' => $items]); ?>
    </div>
    <div class="hm-today-mode hm-today-mode--urgenza d-none" data-hm-today-mode="urgenza">
        <?php $view->include('Home/Views/partials/oggi_feed_urgenza', ['items' => $items]); ?>
    </div>

    <?php /* ── Vuoto per filtro attivo ───────────────────────────────────────── */ ?>
    <div class="card-body text-center py-4 text-body-secondary d-none" data-hm-empty-filter>
        <i class="fa-solid fa-filter-circle-xmark fa-2x mb-3 d-block"></i>
        <p class="mb-2"><?= e(t('home.feed.no_filter_items')) ?></p>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-hm-reset-filter>
            <i class="fa-solid fa-list me-1"></i><?= e(t('home.feed.show_all')) ?>
        </button>
    </div>

    <?php endif; ?>

    <?php /* ── Sezione completate oggi ───────────────────────────────────────── */ ?>
    <?php $view->include('Home/Views/partials/oggi_completed_today', ['completedToday' => $completedToday]); ?>

</div>
