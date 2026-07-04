<?php
/**
 * Variabili: $item, $ricorrenze, $categorie
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');
$view->pushScript('js/contacts.js');
$view->pushScript('js/contacts-osm.js');

use App\Modules\Contacts\Helpers\ContactsHelper;
use App\Modules\Contacts\Services\ContactsService;

$nomeCompleto = trim($item['nome'] . ' ' . ($item['cognome'] ?? ''));
$initials     = ContactsService::initials($item['nome'], $item['cognome'] ?? '');
$color        = ContactsService::avatarColor($item['nome']);
$avatarUrl    = ContactsHelper::avatarUrl($item);

// Sharing context (passato da ContactsController::show)
$isOwner   = $isOwner ?? !empty($item['is_owner']);
$shares    = $shares  ?? [];
$ownerName = $item['owner_name'] ?? null;
$canShare  = $isOwner && has_permission('contacts.share') && !is_single_user();

$socialDock   = ContactsHelper::socialDescriptors($item);
$prossimaRic  = ContactsHelper::prossimaRicorrenza($ricorrenze);

$numTag = !empty($item['tags'])
    ? count(array_filter(array_map('trim', explode(',', $item['tags']))))
    : 0;

if ($prossimaRic !== null) {
    $g = (int) ($prossimaRic['giorni_mancanti'] ?? 0);
    $prossimaLabel = $g === 0
        ? t('contacts.widget.today')
        : ($g === 1 ? t('contacts.widget.tomorrow') : t('contacts.upcoming.in_days', ['days' => $g]));
} else {
    $prossimaLabel = t('contacts.show.no_upcoming');
}

$hasUrgentRic = $prossimaRic !== null
    && in_array($prossimaRic['urgenza'] ?? null, ['oggi', 'urgente'], true);

$hasAddress = !empty($item['indirizzo']);
$hasMap     = $hasAddress && !empty($item['latitude']) && !empty($item['longitude']);

$directChannelKeys  = ['email', 'telefono', 'telefono_alt', 'whatsapp'];
$identityChannelKeys = ['sito_web', 'telegram', 'linkedin', 'twitter', 'instagram', 'facebook'];

$directChannels = array_values(array_filter(
  $socialDock,
  static fn(array $s): bool => !empty($s['value']) && in_array($s['key'] ?? '', $directChannelKeys, true)
));

$identityChannels = array_values(array_filter(
  $socialDock,
  static fn(array $s): bool => !empty($s['value']) && in_array($s['key'] ?? '', $identityChannelKeys, true)
));

$hasTextContacts = !empty($directChannels) || !empty($identityChannels);
$hasCalendarAccess = isModuleEnabled('Calendar') && has_permission('calendar.view');
$hasFilesAccess = isModuleEnabled('Files') && has_permission('files.access');

$primaryEmailChannel = null;
$primaryPhoneChannel = null;
foreach ($socialDock as $channel) {
  if ($primaryEmailChannel === null && ($channel['key'] ?? '') === 'email' && !empty($channel['href'])) {
    $primaryEmailChannel = $channel;
  }
  if ($primaryPhoneChannel === null && in_array(($channel['key'] ?? ''), ['telefono', 'telefono_alt', 'whatsapp'], true) && !empty($channel['href'])) {
    $primaryPhoneChannel = $channel;
  }
}

$linkedCalendarRecurrence = null;
if ($hasCalendarAccess) {
  foreach ($ricorrenze as $ricorrenza) {
    if (!empty($ricorrenza['calendario_event_id'])) {
      $linkedCalendarRecurrence = $ricorrenza;
      break;
    }
  }
}

$linkedCalendarUrl = $linkedCalendarRecurrence
  ? route('calendar.show', ['id' => $linkedCalendarRecurrence['calendario_event_id']])
  : null;

ob_start();
if ($isOwner && has_permission('contacts.edit')) {
    $view->include('Contacts/Views/partials/star_button', [
        'id' => (int) $item['id'],
        'preferito' => (bool) $item['preferito'],
    ]);
}
$contactFavoriteButton = trim(ob_get_clean());

ob_start();
?>
<a href="<?= e(route('contacts.index')) ?>"
   class="btn btn-sm btn-outline-secondary" title="<?= e(t('contacts.show.back_tip')) ?>"
   data-bs-toggle="tooltip">
  <i class="fa-solid fa-arrow-left"></i>
</a>
<?php if ($isOwner && has_permission('contacts.edit')): ?>
<a href="<?= e(route('contacts.edit', ['id' => $item['id']])) ?>"
   class="btn btn-sm btn-outline-secondary"
   title="<?= e(t('contacts.show.edit_tip')) ?>" data-bs-toggle="tooltip">
  <i class="fa-solid fa-pen"></i>
</a>
<?php endif; ?>
<?php if ($canShare): ?>
<a href="<?= e(route('contacts.sharing.edit', ['id' => $item['id']])) ?>"
   class="btn btn-sm btn-outline-secondary"
   title="<?= e(t('contacts.show.share_tip')) ?>" data-bs-toggle="tooltip">
  <i class="fa-solid fa-users"></i>
</a>
<?php endif; ?>
<?php if ($isOwner && has_permission('contacts.delete')): ?>
<form method="POST"
      action="<?= e(route('contacts.destroy', ['id' => $item['id']])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="_method" value="DELETE">
  <button type="submit" class="btn btn-sm btn-outline-danger"
          data-app-confirm="<?= e(t('contacts.show.delete_confirm', ['name' => $nomeCompleto])) ?>"
          data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
          title="<?= e(t('contacts.show.delete_tip')) ?>" data-bs-toggle="tooltip">
    <i class="fa-solid fa-trash"></i>
  </button>
</form>
<?php endif; ?>
<?php
$contactButtons = trim(ob_get_clean());

$contactHeroStats = [
    [
        'icon' => 'fa-solid fa-calendar-day',
        'value' => $prossimaLabel,
        'label' => t('contacts.show.stat_next_rec'),
        'color' => $hasUrgentRic ? 'danger' : 'primary',
        'className' => $hasUrgentRic ? 'ct-stat-urgent' : '',
    ],
    [
        'icon' => 'fa-solid fa-bell',
        'value' => (string) count($ricorrenze),
        'label' => t('contacts.stats.recurrences'),
        'color' => 'warning',
    ],
    [
        'icon' => 'fa-solid fa-tag',
        'value' => (string) $numTag,
        'label' => t('contacts.fields.tags'),
        'color' => 'info',
    ],
    [
        'icon' => 'fa-regular fa-pen-to-square',
        'value' => format_date_it($item['updated_at'], 'compact'),
        'label' => t('contacts.show.stat_updated'),
        'color' => 'secondary',
    ],
];
?>
<?php $view->start('content'); ?>

<div class="container-fluid">

  <?php $view->include('partials/pf-hero-contact', [
      'contactName' => $nomeCompleto,
      'contactSubtitle' => !empty($item['ruolo']) || !empty($item['azienda'])
          ? implode(' · ', array_filter([$item['ruolo'] ?? '', $item['azienda'] ?? '']))
          : null,
      'contactAvatar' => $avatarUrl,
      'contactInitials' => $initials,
      'contactAvatarBg' => $color,
      'contactCategoryName' => $item['categoria_nome'] ?? null,
      'contactCategoryColor' => $item['categoria_colore'] ?? '#6c757d',
      'contactFavoriteButton' => $contactFavoriteButton,
      'contactButtons' => $contactButtons,
      'contactStats' => $contactHeroStats,
  ]); ?>

  <?php if (!$isOwner): ?>
  <div class="alert alert-info py-2 px-3 d-flex align-items-center gap-2 mb-3" role="status">
    <i class="fa-solid fa-users" aria-hidden="true"></i>
    <div class="small">
      <?= $ownerName ? e(t('contacts.show.shared_by', ['name' => $ownerName])) : e(t('contacts.show.shared_notice')) ?>.
      <?= e(t('contacts.show.shared_notice_detail')) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Social dock ──────────────────────────────────────────── -->
  <div class="card ct-social-dock-card mb-3">
    <div class="card-body py-3">
      <div class="ct-social-dock" role="group" aria-label="<?= e(t('contacts.show.social_dock_aria')) ?>">
        <?php foreach ($socialDock as $s): ?>
          <?php if (!empty($s['href'])): ?>
            <a href="<?= e($s['href']) ?>"
               class="ct-social-btn ct-social-btn--active"
               <?= $s['target'] === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>
               data-bs-toggle="tooltip"
               title="<?= e($s['label']) ?><?= !empty($s['display']) ? ': ' . e($s['display']) : '' ?>">
              <i class="<?= e($s['icon']) ?>" aria-hidden="true"></i>
              <span class="visually-hidden"><?= e($s['label']) ?></span>
            </a>
          <?php else: ?>
            <span class="ct-social-btn ct-social-btn--inactive"
                  data-bs-toggle="tooltip"
                  title="<?= e($s['label']) ?> <?= e(t('contacts.show.not_set')) ?>"
                  aria-label="<?= e($s['label']) ?> <?= e(t('contacts.show.not_set')) ?>">
              <i class="<?= e($s['icon']) ?>" aria-hidden="true"></i>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<div class="row g-4">

  <!-- ── Colonna destra (primaria): Ricorrenze + Note ────────── -->
  <div class="col-lg-8 order-lg-2">

    <!-- Riepilogo contatti (testuale) -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-address-card"></i></span>
        <span class="fw-semibold"><?= e(t('contacts.show.section_contacts')) ?></span>
      </div>
      <div class="card-body p-3">
        <?php if ($hasTextContacts): ?>
        <div class="row g-4">

          <?php if (!empty($directChannels)): ?>
          <div class="col-12 col-md-6">
            <div class="small text-uppercase text-muted fw-semibold mb-2">
              <i class="fa-solid fa-comment-dots icon-xs me-1"></i><?= e(t('contacts.show.direct_channels')) ?>
            </div>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($directChannels as $channel): ?>
              <div class="ct-channel-row d-flex align-items-center gap-2">
                <span class="ct-channel-icon text-muted" data-bs-toggle="tooltip" title="<?= e($channel['label']) ?>">
                  <i class="<?= e($channel['icon'] ?? 'fa-solid fa-circle-info') ?>"></i>
                </span>
                <?php if (!empty($channel['href'])): ?>
                <a href="<?= e($channel['href']) ?>"
                   class="text-decoration-none text-break flex-grow-1"
                   <?= ($channel['target'] ?? '_self') === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>
                   data-bs-toggle="tooltip"
                   title="<?= e(t('contacts.show.open_channel', ['label' => $channel['label']])) ?>">
                  <?= e($channel['display'] ?? $channel['value']) ?>
                </a>
                <?php else: ?>
                <span class="text-break flex-grow-1"><?= e($channel['display'] ?? $channel['value']) ?></span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($identityChannels)): ?>
          <div class="col-12 col-md-6">
            <div class="small text-uppercase text-muted fw-semibold mb-2">
              <i class="fa-solid fa-globe icon-xs me-1"></i><?= e(t('contacts.show.web_social')) ?>
            </div>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($identityChannels as $channel): ?>
              <div class="ct-channel-row d-flex align-items-center gap-2">
                <span class="ct-channel-icon text-muted" data-bs-toggle="tooltip" title="<?= e($channel['label']) ?>">
                  <i class="<?= e($channel['icon'] ?? 'fa-solid fa-circle-info') ?>"></i>
                </span>
                <?php if (!empty($channel['href'])): ?>
                <a href="<?= e($channel['href']) ?>"
                   class="text-decoration-none text-break flex-grow-1"
                   <?= ($channel['target'] ?? '_self') === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>
                   data-bs-toggle="tooltip"
                   title="<?= e(t('contacts.show.open_channel', ['label' => $channel['label']])) ?>">
                  <?= e($channel['display'] ?? $channel['value']) ?>
                </a>
                <?php else: ?>
                <span class="text-break flex-grow-1"><?= e($channel['display'] ?? $channel['value']) ?></span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

        </div>
        <?php else: ?>
        <div class="text-muted small py-3 text-center">
          <i class="fa-regular fa-address-card me-1 opacity-50"></i>
          <?= e(t('contacts.show.no_contacts')) ?>
          <?php if ($isOwner && has_permission('contacts.edit')): ?>
          <a href="<?= e(route('contacts.edit', ['id' => $item['id']])) ?>" class="ms-1"><?= e(t('contacts.show.complete_data_link')) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ricorrenze (priorità visiva) -->
    <div class="card mb-3 ct-ric-section<?= $hasUrgentRic ? ' ct-ric-section-urgent' : '' ?>">
      <div class="card-header d-flex align-items-center justify-content-between py-2 px-3">
        <span class="d-flex align-items-center gap-2">
          <span class="app-card-icon"><i class="fa-solid fa-bell"></i></span>
          <span class="fw-semibold"><?= e(t('contacts.show.section_rec')) ?></span>
          <?php if (!empty($ricorrenze)): ?>
          <span class="badge bg-secondary rounded-pill"><?= count($ricorrenze) ?></span>
          <?php endif; ?>
          <?php if ($hasUrgentRic): ?>
          <span class="badge rounded-pill ct-badge-urgente ms-1">
            <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($prossimaLabel) ?>
          </span>
          <?php endif; ?>
        </span>
        <?php if ($isOwner && has_permission('contacts.edit')): ?>
        <button class="btn btn-sm btn-primary"
                hx-get="<?= e(route('contacts.recurrences.edit', ['id' => $item['id'], 'rid' => 'new'])) ?>"
                hx-target="#ct-ric-section"
                hx-swap="innerHTML"
                hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
                data-bs-toggle="tooltip" title="<?= e(t('contacts.show.add_rec_tip')) ?>">
          <i class="fa-solid fa-plus me-1"></i><?= e(t('contacts.show.add_btn')) ?>
        </button>
        <?php endif; ?>
      </div>
      <div class="card-body p-3">
        <div id="ct-ric-section">
          <?php $view->include('Contacts/Views/partials/recurrences_list', [
            'ricorrenze'  => $ricorrenze,
            'contattoId'  => $item['id'],
            'contatto'    => $item,
            'showAddForm' => false,
          ]); ?>
        </div>
      </div>
    </div>

    <!-- Note -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-note-sticky"></i></span>
        <span class="fw-semibold"><?= e(t('common.label.notes')) ?></span>
      </div>
      <div class="card-body">
        <?php if (!empty($item['note'])): ?>
        <p class="mb-0 ct-note-prewrap"><?= e($item['note']) ?></p>
        <?php else: ?>
        <div class="text-muted small py-3 text-center">
          <i class="fa-regular fa-note-sticky me-1 opacity-50"></i>
          <?= e(t('contacts.show.no_notes')) ?>
          <?php if ($isOwner && has_permission('contacts.edit')): ?>
          <a href="<?= e(route('contacts.edit', ['id' => $item['id']])) ?>" class="ms-1"><?= e(t('contacts.show.add_note_link')) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ── Colonna sinistra (secondaria): Indirizzo + Tag ──────── -->
  <div class="col-lg-4 order-lg-1">

    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-bolt"></i></span>
        <span class="fw-semibold"><?= e(t('contacts.show.quick_actions')) ?></span>
      </div>
      <div class="card-body d-grid gap-2">
        <?php if ($primaryEmailChannel): ?>
        <a href="<?= e($primaryEmailChannel['href']) ?>"
           class="btn btn-outline-primary"
           <?= ($primaryEmailChannel['target'] ?? '_self') === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
          <i class="fa-solid fa-envelope me-1"></i><?= e(t('contacts.show.email_btn')) ?>
        </a>
        <?php endif; ?>

        <?php if ($primaryPhoneChannel): ?>
        <a href="<?= e($primaryPhoneChannel['href']) ?>"
           class="btn btn-outline-secondary"
           <?= ($primaryPhoneChannel['target'] ?? '_self') === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
          <i class="fa-solid fa-phone me-1"></i><?= e(t('contacts.show.call_btn')) ?>
        </a>
        <?php endif; ?>

        <?php if ($hasMap): ?>
        <a href="https://www.openstreetmap.org/?mlat=<?= urlencode((string) $item['latitude']) ?>&amp;mlon=<?= urlencode((string) $item['longitude']) ?>#map=16/<?= urlencode((string) $item['latitude']) ?>/<?= urlencode((string) $item['longitude']) ?>"
           class="btn btn-outline-secondary"
           target="_blank" rel="noopener">
          <i class="fa-solid fa-map-location-dot me-1"></i><?= e(t('contacts.show.map_btn')) ?>
        </a>
        <?php endif; ?>

        <?php if ($linkedCalendarUrl): ?>
        <a href="<?= e($linkedCalendarUrl) ?>" class="btn btn-outline-info">
          <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('contacts.show.linked_event_btn')) ?>
        </a>
        <?php elseif ($hasCalendarAccess && !empty($ricorrenze)): ?>
        <div class="small text-muted">
          <i class="fa-solid fa-circle-info me-1"></i>
          <?= e(t('contacts.show.no_calendar_event')) ?>
        </div>
        <?php endif; ?>

        <?php if ($hasFilesAccess): ?>
        <a href="<?= e(route('files.index')) ?>" class="btn btn-outline-secondary">
          <i class="fa-solid fa-folder-open me-1"></i><?= e(t('contacts.show.files_btn')) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Indirizzo + mappa (unica card dedicata, senza duplicare il dock) -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-location-dot"></i></span>
        <span class="fw-semibold"><?= e(t('contacts.fields.indirizzo')) ?></span>
      </div>
      <div class="card-body p-3">
        <?php if ($hasAddress): ?>
        <div class="ct-address-text mb-2"><?= nl2br(e($item['indirizzo'])) ?></div>

        <?php if ($hasMap): ?>
        <div class="ct-osm-preview-wrap" data-ct-osm-preview-wrap>
          <div class="ct-osm-preview ct-osm-preview-lg"
               data-ct-osm-preview
               data-lat="<?= e((string) $item['latitude']) ?>"
               data-lng="<?= e((string) $item['longitude']) ?>"></div>
          <a class="small d-inline-flex align-items-center gap-1 mt-2" target="_blank" rel="noopener"
             href="https://www.openstreetmap.org/?mlat=<?= urlencode((string) $item['latitude']) ?>&amp;mlon=<?= urlencode((string) $item['longitude']) ?>#map=16/<?= urlencode((string) $item['latitude']) ?>/<?= urlencode((string) $item['longitude']) ?>"
             data-bs-toggle="tooltip" title="<?= e(t('contacts.show.osm_tip')) ?>">
            <i class="fa-solid fa-up-right-from-square"></i> <?= e(t('contacts.show.osm_open_text')) ?>
          </a>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="text-muted small py-3 text-center">
          <i class="fa-regular fa-map me-1 opacity-50"></i>
          <?= e(t('contacts.show.no_address')) ?>
          <?php if ($isOwner && has_permission('contacts.edit')): ?>
          <a href="<?= e(route('contacts.edit', ['id' => $item['id']])) ?>" class="ms-1"><?= e(t('contacts.show.add_address_link')) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tag -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-tag"></i></span>
        <span class="fw-semibold"><?= e(t('contacts.fields.tags')) ?></span>
        <?php if ($numTag > 0): ?>
        <span class="badge bg-secondary rounded-pill ms-auto"><?= (int) $numTag ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body py-2 px-3">
        <?php if (!empty($item['tags'])): ?>
        <div class="ct-tags">
          <?php foreach (array_filter(array_map('trim', explode(',', $item['tags']))) as $tag): ?>
          <a href="<?= e(route('contacts.index')) ?>?tag=<?= urlencode($tag) ?>" class="ct-tag"><?= e($tag) ?></a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-muted small py-2 text-center">
          <i class="fa-solid fa-tags me-1 opacity-50"></i>
          <?= e(t('contacts.show.no_tags')) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($canShare): ?>
    <!-- Condivisione per ruolo (owner) -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
        <span class="app-card-icon"><i class="fa-solid fa-users"></i></span>
        <span class="fw-semibold"><?= e(t('contacts.show.section_sharing')) ?></span>
        <?php if (!empty($shares)): ?>
        <span class="badge bg-secondary rounded-pill ms-auto"><?= count($shares) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-3">
        <?php $view->include('Contacts/Views/partials/sharing_panel', [
          'item'   => $item,
          'shares' => $shares,
        ]); ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── Meta footer strip ─────────────────────────────────────── -->
<div class="ct-meta-footer d-flex flex-wrap gap-3 justify-content-center mt-2 mb-4">
  <small class="text-muted">
    <i class="fa-regular fa-clock me-1"></i>
    <?= e(t('common.label.created_at')) ?> <?= e(format_date_it($item['created_at'], 'long')) ?>
  </small>
  <small class="text-muted d-none d-sm-inline">·</small>
  <small class="text-muted">
    <i class="fa-regular fa-pen-to-square me-1"></i>
    <?= e(t('common.label.updated_at')) ?> <?= e(format_date_it($item['updated_at'], 'long')) ?>
  </small>
</div>

</div>

<?php $view->end(); ?>
