<?php
$currentUserId = (int) ($currentUserId ?? 0);
?>

<div class="d-flex flex-column" id="cal-agenda-list">
    <?php if (empty($upcomingEvents)): ?>
    <div class="p-4 text-center text-muted" id="cal-agenda-empty">
        <i class="fa-regular fa-calendar-check fa-2x mb-2 d-block opacity-50"></i>
        <div class="fw-semibold mb-1"><?= e(t('calendar.agenda.no_upcoming')) ?></div>
        <div class="small"><?= e(t('calendar.agenda.free')) ?></div>
    </div>
    <?php else: ?>
    <div class="px-3 py-2 border-bottom bg-body-tertiary small text-muted" id="cal-agenda-meta">
        <?= e(t('calendar.agenda.results', ['count' => count($upcomingEvents)])) ?>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($upcomingEvents as $agendaEvent): ?>
        <?php
        $agendaOwned = (int) ($agendaEvent['created_by'] ?? 0) === $currentUserId;
        $agendaVisibility = (string) ($agendaEvent['visibility'] ?? 'personal');
        $agendaDateLabel = format_date(
            $agendaEvent['start_datetime'],
            !empty($agendaEvent['all_day']) ? 'compact' : 'long'
        );
        ?>
        <a href="<?= e(route('calendar.show', ['id' => $agendaEvent['id']])) ?>"
           class="list-group-item list-group-item-action"
           data-cal-agenda-item="1"
           data-title="<?= e((string) ($agendaEvent['title'] ?? '')) ?>"
           data-description="<?= e((string) ($agendaEvent['description'] ?? '')) ?>"
           data-location="<?= e((string) ($agendaEvent['location'] ?? '')) ?>"
           data-visibility="<?= e($agendaVisibility) ?>"
           data-owned="<?= $agendaOwned ? '1' : '0' ?>"
           data-all-day="<?= !empty($agendaEvent['all_day']) ? '1' : '0' ?>">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= e($agendaEvent['title']) ?></div>
                    <div class="small text-muted d-flex flex-wrap gap-2 mt-1">
                        <span><i class="fa-regular fa-clock me-1"></i><?= e($agendaDateLabel) ?></span>
                        <?php if (!empty($agendaEvent['location'])): ?>
                        <span><i class="fa-solid fa-location-dot me-1"></i><?= e($agendaEvent['location']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                    <?php if (!empty($agendaEvent['all_day'])): ?>
                    <span class="badge text-bg-warning"><i class="fa-solid fa-sun me-1"></i><?= e(t('calendar.agenda.badge_full')) ?></span>
                    <?php endif; ?>
                    <?php if ($agendaVisibility === 'role' || $agendaVisibility === 'public'): ?>
                    <span class="badge text-bg-primary"><i class="fa-solid fa-users me-1"></i><?= e(t('calendar.agenda.badge_shared')) ?></span>
                    <?php elseif ($agendaOwned): ?>
                    <span class="badge text-bg-secondary"><i class="fa-solid fa-user me-1"></i><?= e(t('calendar.agenda.badge_mine')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="p-3 text-center text-muted d-none" id="cal-agenda-empty">
        <i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block opacity-50"></i>
        <div class="fw-semibold mb-1"><?= e(t('calendar.agenda.no_match')) ?></div>
        <div class="small"><?= e(t('calendar.agenda.change')) ?></div>
    </div>
    <?php endif; ?>
</div>