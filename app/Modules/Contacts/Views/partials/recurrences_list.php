<?php
/**
 * Partial: lista ricorrenze nella scheda contatto
 * Variabili: $ricorrenze, $contattoId, $contatto, $showAddForm
 */
$icone    = ['compleanno' => '🎂', 'anniversario' => '💍', 'evento' => '📅'];
$tipoLbl  = [
    'compleanno'   => t('contacts.widget.type_birthday'),
    'anniversario' => t('contacts.widget.type_anniversary'),
    'evento'       => t('contacts.widget.type_event'),
];
$hasCalendarAccess = isModuleEnabled('Calendar') && has_permission('calendar.view');
?>

<?php if (empty($ricorrenze)): ?>
<div class="text-center py-4 text-muted">
  <i class="fa-regular fa-bell fa-2x mb-2 d-block opacity-25"></i>
  <p class="mb-1 small"><?= e(t('contacts.recurrences.empty')) ?></p>
  <p class="small opacity-75"><?= e(t('contacts.recurrences.empty_help')) ?></p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-2 mb-3">
<?php foreach ($ricorrenze as $ric):
  $urgenza = $ric['urgenza'] ?? 'lontano';
  $icona   = $icone[$ric['tipo']] ?? '📅';
  $giorni  = $ric['giorni_mancanti'] ?? null;
  $hasCalendarLink = $hasCalendarAccess && !empty($ric['calendario_event_id']);
  $calendarSyncPending = $hasCalendarAccess
      && ($ric['crea_evento_calendario'] ?? 'no') !== 'no'
      && empty($ric['calendario_event_id']);
  $deleteConfirm = $hasCalendarLink
      ? t('contacts.recurrences.delete_confirm_cal')
      : t('contacts.recurrences.delete_confirm_simple');
  $badgeCls = match($urgenza) {
    'oggi'    => 'ct-badge-oggi',
    'urgente' => 'ct-badge-urgente',
    'prossimo'=> 'ct-badge-prossimo',
    default   => 'ct-badge-lontano',
  };
  $dataDsp  = !empty($ric['prossima_data'])
              ? date('d/m', strtotime($ric['prossima_data'])) . ($ric['annuale'] ? '' : '/' . date('Y', strtotime($ric['prossima_data'])))
              : date('d/m/Y', strtotime($ric['data_ricorrenza']));
?>
<div class="ct-ric-card urgenza-<?= $urgenza ?>" id="ct-ric-<?= (int)$ric['id'] ?>">
  <div class="ct-ric-icon"><?= $icona ?></div>
  <div class="ct-ric-body">
    <div class="d-flex align-items-start justify-content-between gap-2">
      <div>
        <div class="ct-ric-title"><?= e($ric['titolo']) ?></div>
        <div class="ct-ric-meta">
          <span><?= e($tipoLbl[$ric['tipo']] ?? $ric['tipo']) ?></span>
          <span>·</span>
          <span><?= $dataDsp ?></span>
          <?php if (!empty($ric['eta_prossima'])): ?>
          <span>·</span>
          <span><?= e(t('contacts.recurrences.age_years', ['n' => (int)$ric['eta_prossima']])) ?></span>
          <?php endif; ?>
          <?php if ($ric['annuale']): ?>
          <span>·</span>
          <span class="text-muted"><?= e(t('contacts.recurrences.annual_badge')) ?></span>
          <?php endif; ?>
        </div>
        <!-- Indicatori reminder -->
        <div class="ct-ric-reminder-icons mt-1">
          <?php if ((int)$ric['promemoria_giorni_prima'] > 0): ?>
          <span class="badge rounded-pill bg-secondary" title="<?= e(t('contacts.recurrences.badge_advance')) ?>">
            <i class="fa-solid fa-hourglass me-1"></i><?= e(t('contacts.recurrences.advance_days_badge', ['days' => (int)$ric['promemoria_giorni_prima']])) ?>
          </span>
          <?php endif; ?>
          <?php if ($ric['notifica_giorno_stesso']): ?>
          <span class="badge rounded-pill bg-secondary" title="<?= e(t('contacts.recurrences.badge_same_day')) ?>">
            <i class="fa-solid fa-bell me-1"></i><?= e(t('contacts.recurrences.same_day_badge_short')) ?>
          </span>
          <?php endif; ?>
          <?php if ($ric['crea_evento_calendario'] !== 'no'): ?>
          <span class="badge rounded-pill bg-info text-white" title="<?= e(t('contacts.recurrences.badge_cal_event')) ?>">
            <i class="fa-solid fa-calendar me-1"></i>
            <?= e($ric['crea_evento_calendario'] === 'annuale' ? t('contacts.recurrences.cal_annual_badge') : t('contacts.recurrences.cal_next_badge')) ?>
          </span>
          <?php endif; ?>
          <?php if ($hasCalendarLink): ?>
          <a href="<?= e(route('calendar.show', ['id' => $ric['calendario_event_id']])) ?>"
             class="badge rounded-pill text-bg-light border text-decoration-none"
             title="<?= e(t('contacts.recurrences.cal_link_tip')) ?>">
            <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('contacts.recurrences.cal_open_badge')) ?>
          </a>
          <?php elseif ($calendarSyncPending): ?>
          <span class="badge rounded-pill text-bg-warning" title="<?= e(t('contacts.recurrences.sync_pending_tip')) ?>">
            <i class="fa-solid fa-link-slash me-1"></i><?= e(t('contacts.recurrences.sync_pending_badge')) ?>
          </span>
          <?php elseif ($hasCalendarAccess): ?>
          <span class="badge rounded-pill text-bg-light border text-body-secondary" title="<?= e(t('contacts.recurrences.reminder_only_tip')) ?>">
            <i class="fa-solid fa-bell me-1"></i>Solo reminder
          </span>
          <?php endif; ?>
        </div>
        <?php if (!empty($ric['note'])): ?>
        <div class="text-muted small mt-1"><?= e($ric['note']) ?></div>
        <?php endif; ?>
      </div>
      <!-- Giorni mancanti badge -->
      <?php if ($giorni !== null): ?>
      <div class="ct-days-badge <?= $badgeCls ?> ct-flex-noshrink">
        <?= $giorni === 0 ? e(t('contacts.widget.today')) . '!' : e(t('contacts.upcoming.in_days', ['days' => $giorni])) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php if (has_permission('contacts.edit')): ?>
  <div class="ct-ric-actions">
    <?php if ($hasCalendarLink): ?>
    <a href="<?= e(route('calendar.show', ['id' => $ric['calendario_event_id']])) ?>"
       class="btn btn-xs btn-outline-info ct-btn-xxs"
       title="<?= e(t('contacts.recurrences.cal_open_tip')) ?>" data-bs-toggle="tooltip">
      <i class="fa-solid fa-calendar-check"></i>
    </a>
    <?php endif; ?>
    <button class="btn btn-xs btn-outline-secondary ct-btn-xxs"
            hx-get="<?= e(route('contacts.recurrences.edit', ['id' => $contattoId, 'rid' => $ric['id']])) ?>"
            hx-target="#ct-ric-section" hx-swap="innerHTML"
            hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
            title="<?= e(t('contacts.recurrences.edit_tip')) ?>" data-bs-toggle="tooltip">
      <i class="fa-solid fa-pen"></i>
    </button>
    <form method="POST"
          action="<?= e(route('contacts.recurrences.destroy', ['id' => $contattoId, 'rid' => $ric['id']])) ?>"
          class="d-inline"
          hx-delete="<?= e(route('contacts.recurrences.destroy', ['id' => $contattoId, 'rid' => $ric['id']])) ?>"
          hx-target="#ct-ric-section" hx-swap="innerHTML"
          hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'>
      <?= csrf_field() ?>
      <input type="hidden" name="_method" value="DELETE">
      <button type="submit" class="btn btn-xs btn-outline-danger ct-btn-xxs"
              data-app-confirm="<?= e($deleteConfirm) ?>"
              data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
              title="<?= e(t('contacts.recurrences.delete_tip')) ?>" data-bs-toggle="tooltip">
        <i class="fa-solid fa-trash"></i>
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
