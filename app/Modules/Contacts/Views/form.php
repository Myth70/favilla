<?php
/**
 * Variabili: $item (null=create), $categorie, $errors, $old
 * $errors formato Validator: ['campo' => ['messaggio', ...]]
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');
$view->pushStyle('css/cropper.min.css');
$view->pushStyle('css/avatar-cropper.css');
$view->pushScript('js/vendor/cropper.min.js');
$view->pushScript('js/contacts.js');
$view->pushScript('js/contacts-osm.js');

use App\Modules\Contacts\Helpers\ContactsHelper;
use App\Modules\Contacts\Services\ContactsService;

$isEdit       = $item !== null;
$nomeCompleto = $isEdit ? trim($item['nome'] . ' ' . ($item['cognome'] ?? '')) : '';
$initials     = $isEdit ? ContactsService::initials($item['nome'], $item['cognome'] ?? '') : '';
$color        = $isEdit ? ContactsService::avatarColor($item['nome']) : '#6c757d';
$avatarUrl    = $isEdit ? ContactsHelper::avatarUrl($item) : null;

/* Override opzionali (riuso del form da ImportController). Default = create/edit normale. */
$formAction   = $formAction   ?? ($isEdit ? route('contacts.update', ['id' => $item['id']]) : route('contacts.store'));
$formMethod   = $formMethod   ?? ($isEdit ? 'PUT' : 'POST');
$formTitle    = $formTitle    ?? ($isEdit ? t('contacts.form.title_edit') : t('contacts.action.new'));
$formSubtitle = $formSubtitle ?? ($isEdit ? $nomeCompleto : t('contacts.form.subtitle_new'));
$cancelUrl    = $cancelUrl    ?? ($isEdit ? route('contacts.show', ['id' => $item['id']]) : route('contacts.index'));
$submitLabel  = $submitLabel  ?? ($isEdit ? t('contacts.form.submit_save') : t('contacts.form.submit_create'));

/* Helper inline: valore campo (old → item → default) */
$fv = function (string $k, string $default = '') use ($old, $item) {
    if (array_key_exists($k, $old))                    return $old[$k];
    if (is_array($item) && array_key_exists($k, $item)) return $item[$k];
    return $default;
};
/* Helper inline: primo errore per il campo (Validator restituisce array) */
$fe = fn(string $k) => $errors[$k][0] ?? null;
/* Helper inline: class per input, con is-invalid condizionale */
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');
?>
<?php $view->start('content'); ?>

<?php
// Hero module standardizzato (pagina secondaria user-facing)
$moduleButtonsHtml  = '<a href="' . e($cancelUrl) . '"';
$moduleButtonsHtml .= ' class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('contacts.form.cancel_tip')) . '">';
$moduleButtonsHtml .= '<i class="fa-solid fa-xmark"></i> ' . e(t('common.action.cancel')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => $formTitle,
    'moduleIcon'     => 'fa-solid fa-address-book',
    'moduleSubtitle' => e($formSubtitle),
    'moduleButtons'  => $moduleButtonsHtml,
]);
?>

<div class="container-fluid">
<div class="row justify-content-center">
<div class="col-xl-8 col-lg-10">

<?php $view->include('partials/app-form-errors', [
    'errors' => $errors,
    'summaryTitle' => t('contacts.form.errors_title'),
    'summaryAriaLive' => 'polite',
    'summaryBodyClass' => 'flex-grow-1',
    'summaryListClass' => 'mb-0 small ps-3',
]); ?>

<form method="POST" enctype="multipart/form-data" novalidate
      data-app-form
      action="<?= e($formAction) ?>">
  <?= csrf_field() ?>
  <?php if ($formMethod !== 'POST'): ?>
  <input type="hidden" name="_method" value="<?= e($formMethod) ?>">
  <?php endif; ?>

  <!-- ── Avatar upload (prima sezione del form) ─────────────── -->
  <div class="d-flex align-items-center gap-3 mb-4">
    <div class="ct-avatar-upload">
      <div class="ct-avatar ct-avatar-xl ct-avatar-dynamic" id="ct-avatar-preview"
           style="--ct-avatar-bg: <?= $avatarUrl ? 'transparent' : e($color) ?>;">
        <?php if ($avatarUrl): ?>
          <img src="<?= e($avatarUrl) ?>" alt="">
        <?php else: ?>
          <?= $initials ?: '<i class="fa-solid fa-camera ct-camera-icon" aria-hidden="true"></i>' ?>
        <?php endif; ?>
        <div class="ct-avatar-upload-overlay"><i class="fa-solid fa-camera" aria-hidden="true"></i>&nbsp;<?= e(t('contacts.form.avatar_change')) ?></div>
      </div>
      <input type="file" name="avatar" accept="image/*"
             aria-label="<?= e(t('contacts.form.avatar_aria')) ?>"
             data-avatar-crop="ct-avatar-preview">
    </div>
    <div>
      <div class="fw-semibold"><?= e($isEdit ? t('contacts.form.avatar_profile') : t('contacts.form.avatar_add_photo')) ?></div>
      <div class="text-muted small"><?= e(t('contacts.form.avatar_help')) ?></div>
    </div>
    <?php if ($isEdit && !empty($item['avatar'])): ?>
    <div class="ms-auto">
      <label class="form-check form-switch d-flex align-items-center gap-2 text-muted small">
        <input class="form-check-input" type="checkbox" name="rimuovi_avatar" value="1">
        <?= e(t('contacts.form.avatar_remove')) ?>
      </label>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── SEZIONE 1: Dati personali ────────────────────────────── -->
  <fieldset class="app-form-section ct-form-section">
    <legend class="visually-hidden"><?= e(t('contacts.form.section_personal')) ?></legend>
    <div class="app-form-section-header ct-form-section-header open" role="button" tabindex="0"
         aria-expanded="true" aria-controls="app-sec-dati">
      <i class="fa-solid fa-user" aria-hidden="true"></i>
      <?= e(t('contacts.form.section_personal')) ?>
      <i class="fa-solid fa-chevron-down app-chevron ct-chevron" aria-hidden="true"></i>
    </div>
    <div class="app-form-section-body ct-form-section-body" id="app-sec-dati">
      <div class="row g-3">
        <div class="col-sm-6">
          <label for="ct-nome" class="form-label fw-semibold small"><?= e(t('contacts.fields.nome')) ?> <span class="text-danger" aria-hidden="true">*</span></label>
          <input type="text" id="ct-nome" name="nome"
                 class="<?= $fc('nome') ?>"
                 value="<?= e($fv('nome')) ?>"
                 placeholder="<?= e(t('contacts.form.ph_nome')) ?>" maxlength="100"
                 autocomplete="given-name"
                 required aria-required="true"
                 <?= $fe('nome') ? 'aria-invalid="true" aria-describedby="ct-nome-feedback"' : '' ?>
                 autofocus>
          <div id="ct-nome-feedback" class="invalid-feedback"><?= e($fe('nome') ?? t('contacts.form.err_invalid')) ?></div>
        </div>
        <div class="col-sm-6">
          <label for="ct-cognome" class="form-label fw-semibold small"><?= e(t('contacts.fields.cognome')) ?></label>
          <input type="text" id="ct-cognome" name="cognome"
                 class="<?= $fc('cognome') ?>"
                 value="<?= e($fv('cognome')) ?>"
                 placeholder="<?= e(t('contacts.form.ph_cognome')) ?>" maxlength="100"
                 autocomplete="family-name"
                 <?= $fe('cognome') ? 'aria-invalid="true" aria-describedby="ct-cognome-feedback"' : '' ?>>
          <div id="ct-cognome-feedback" class="invalid-feedback"><?= e($fe('cognome') ?? t('contacts.form.err_invalid')) ?></div>
        </div>
        <div class="col-sm-6">
          <label for="ct-azienda" class="form-label fw-semibold small"><?= e(t('contacts.fields.azienda')) ?></label>
          <input type="text" id="ct-azienda" name="azienda"
                 class="<?= $fc('azienda') ?>"
                 value="<?= e($fv('azienda')) ?>"
                 placeholder="<?= e(t('contacts.form.ph_azienda')) ?>" maxlength="100"
                 autocomplete="organization"
                 <?= $fe('azienda') ? 'aria-invalid="true" aria-describedby="ct-azienda-feedback"' : '' ?>>
          <div id="ct-azienda-feedback" class="invalid-feedback"><?= e($fe('azienda') ?? t('contacts.form.err_invalid')) ?></div>
        </div>
        <div class="col-sm-6">
          <label for="ct-ruolo" class="form-label fw-semibold small"><?= e(t('contacts.form.field_role')) ?></label>
          <input type="text" id="ct-ruolo" name="ruolo"
                 class="<?= $fc('ruolo') ?>"
                 value="<?= e($fv('ruolo')) ?>"
                 placeholder="<?= e(t('contacts.form.ph_ruolo')) ?>" maxlength="100"
                 autocomplete="organization-title"
                 <?= $fe('ruolo') ? 'aria-invalid="true" aria-describedby="ct-ruolo-feedback"' : '' ?>>
          <div id="ct-ruolo-feedback" class="invalid-feedback"><?= e($fe('ruolo') ?? t('contacts.form.err_invalid')) ?></div>
        </div>
        <div class="col-12">
          <label for="ct-categoria" class="form-label fw-semibold small"><?= e(t('contacts.fields.categoria_id')) ?></label>
          <select id="ct-categoria" name="categoria_id" class="form-select<?= $fe('categoria_id') ? ' is-invalid' : '' ?>"
                  <?= $fe('categoria_id') ? 'aria-invalid="true" aria-describedby="ct-categoria-feedback"' : '' ?>>
            <option value=""><?= e(t('contacts.form.no_category')) ?></option>
            <?php foreach ($categorie as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"
              <?= ((int)$fv('categoria_id', 0)) === (int)$cat['id'] ? 'selected' : '' ?>>
              <?= e($cat['nome']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div id="ct-categoria-feedback" class="invalid-feedback"><?= e($fe('categoria_id') ?? t('contacts.form.err_category')) ?></div>
          <div class="form-text">
            <a href="<?= e(route('contacts.categories.index')) ?>" target="_blank" rel="noopener" class="small">
              <i class="fa-solid fa-tags me-1" aria-hidden="true"></i><?= e(t('contacts.form.manage_categories')) ?>
            </a>
          </div>
        </div>
        <div class="col-12">
          <label class="form-check form-switch d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" name="preferito" value="1"
                   <?= !empty($fv('preferito')) ? 'checked' : '' ?>>
            <span class="form-check-label small fw-semibold">
              <i class="fa-solid fa-star me-1 ct-star-indicator" aria-hidden="true"></i><?= e(t('contacts.form.add_to_favorites')) ?>
            </span>
          </label>
        </div>
      </div>
    </div>
  </fieldset>

  <!-- ── SEZIONE 2: Recapiti ───────────────────────────────────── -->
  <fieldset class="app-form-section ct-form-section">
    <legend class="visually-hidden"><?= e(t('contacts.form.section_contacts')) ?></legend>
    <div class="app-form-section-header ct-form-section-header open" role="button" tabindex="0"
         aria-expanded="true" aria-controls="app-sec-recapiti">
      <i class="fa-solid fa-phone" aria-hidden="true"></i>
      <?= e(t('contacts.form.section_contacts')) ?>
      <i class="fa-solid fa-chevron-down app-chevron ct-chevron" aria-hidden="true"></i>
    </div>
    <div class="app-form-section-body ct-form-section-body" id="app-sec-recapiti">
      <div class="row g-3">
        <div class="col-sm-6">
          <label for="ct-email" class="form-label fw-semibold small"><?= e(t('contacts.fields.email')) ?></label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
            <input type="email" id="ct-email" name="email"
                   class="<?= $fc('email') ?>"
                   value="<?= e($fv('email')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_email')) ?>" maxlength="255"
                   inputmode="email" autocomplete="email"
                   <?= $fe('email') ? 'aria-invalid="true" aria-describedby="ct-email-feedback"' : '' ?>>
            <div id="ct-email-feedback" class="invalid-feedback"><?= e($fe('email') ?? t('contacts.form.err_email')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-telefono" class="form-label fw-semibold small"><?= e(t('contacts.form.field_phone_main')) ?></label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
            <input type="tel" id="ct-telefono" name="telefono"
                   class="<?= $fc('telefono') ?>"
                   value="<?= e($fv('telefono')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_telefono')) ?>" maxlength="30"
                   pattern="[\d\s\+\-\.\(\)]{6,20}"
                   inputmode="tel" autocomplete="tel"
                   <?= $fe('telefono') ? 'aria-invalid="true" aria-describedby="ct-telefono-feedback"' : '' ?>>
            <div id="ct-telefono-feedback" class="invalid-feedback"><?= e($fe('telefono') ?? t('contacts.form.err_phone')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-telefono-alt" class="form-label fw-semibold small"><?= e(t('contacts.fields.telefono_alt')) ?></label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-solid fa-phone-flip" aria-hidden="true"></i></span>
            <input type="tel" id="ct-telefono-alt" name="telefono_alt"
                   class="<?= $fc('telefono_alt') ?>"
                   value="<?= e($fv('telefono_alt')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_telefono_alt')) ?>" maxlength="30"
                   pattern="[\d\s\+\-\.\(\)]{6,20}"
                   inputmode="tel" autocomplete="tel"
                   <?= $fe('telefono_alt') ? 'aria-invalid="true" aria-describedby="ct-telefono-alt-feedback"' : '' ?>>
            <div id="ct-telefono-alt-feedback" class="invalid-feedback"><?= e($fe('telefono_alt') ?? t('contacts.form.err_phone')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-sito-web" class="form-label fw-semibold small"><?= e(t('contacts.fields.sito_web')) ?></label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
            <input type="url" id="ct-sito-web" name="sito_web"
                   class="<?= $fc('sito_web') ?>"
                   value="<?= e($fv('sito_web')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_sito_web')) ?>" maxlength="255"
                   inputmode="url" autocomplete="url"
                   <?= $fe('sito_web') ? 'aria-invalid="true" aria-describedby="ct-sito-web-feedback"' : '' ?>>
            <div id="ct-sito-web-feedback" class="invalid-feedback"><?= e($fe('sito_web') ?? t('contacts.form.err_url_https')) ?></div>
          </div>
        </div>
        <div class="col-12" data-ct-osm-form>
          <label for="ct-indirizzo" class="form-label fw-semibold small"><?= e(t('contacts.fields.indirizzo')) ?></label>
          <textarea id="ct-indirizzo" name="indirizzo"
                    class="<?= $fc('indirizzo') ?>" rows="2"
                    maxlength="500"
                    autocomplete="street-address"
                    placeholder="<?= e(t('contacts.form.ph_indirizzo')) ?>"
                    <?= $fe('indirizzo') ? 'aria-invalid="true" aria-describedby="ct-indirizzo-feedback"' : '' ?>><?= e($fv('indirizzo')) ?></textarea>
          <div id="ct-indirizzo-feedback" class="invalid-feedback"><?= e($fe('indirizzo') ?? t('contacts.form.err_invalid')) ?></div>

          <input type="hidden" id="ct-latitude" name="latitude" value="<?= e((string) $fv('latitude', '')) ?>">
          <input type="hidden" id="ct-longitude" name="longitude" value="<?= e((string) $fv('longitude', '')) ?>">
          <input type="hidden" id="ct-geocoding-source" name="geocoding_source" value="<?= e((string) $fv('geocoding_source', 'manual')) ?>">

          <div class="d-flex align-items-center justify-content-between mt-2 gap-2 flex-wrap">
            <div class="small text-muted" id="ct-geo-status-pill" data-ct-geo-status-pill>
              <?= e(t('contacts.form.geo_status', ['status' => !empty($fv('latitude')) && !empty($fv('longitude')) ? t('contacts.form.geo_available') : t('contacts.form.geo_manual')])) ?>
            </div>
            <button type="button" id="ct-open-osm-panel" class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="tooltip" title="<?= e(t('contacts.form.osm_search_tip')) ?>">
              <i class="fa-solid fa-map-location-dot"></i> <?= e(t('contacts.form.osm_select_btn')) ?>
            </button>
          </div>

          <div id="ct-osm-panel" class="ct-osm-panel d-none mt-2" data-ct-osm-panel>
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label for="ct-osm-query" class="form-label fw-semibold small mb-1"><?= e(t('contacts.form.osm_search_label')) ?></label>
                <input type="text"
                       id="ct-osm-query"
                       class="form-control form-control-sm"
                       placeholder="<?= e(t('contacts.form.ph_osm_query')) ?>"
                       value="<?= e($fv('indirizzo')) ?>">
              </div>
              <div class="col-md-4 d-grid">
                <button type="button" id="ct-osm-search" class="btn btn-sm btn-primary"
                        data-bs-toggle="tooltip" title="<?= e(t('contacts.form.osm_start_tip')) ?>">
                  <i class="fa-solid fa-magnifying-glass"></i> <?= e(t('common.action.search')) ?>
                </button>
              </div>
            </div>

            <div id="ct-osm-status" class="alert alert-secondary py-2 px-3 small mt-2 mb-2 d-none" role="status" aria-live="polite"></div>

            <div id="ct-osm-results" class="list-group ct-osm-results" aria-live="polite"></div>

            <div id="ct-osm-preview" class="ct-osm-preview mt-2 d-none" aria-label="<?= e(t('contacts.form.osm_preview_aria')) ?>"></div>

            <div class="d-flex justify-content-end mt-2">
              <button type="button" id="ct-close-osm-panel" class="btn btn-sm btn-outline-secondary"
                      data-bs-toggle="tooltip" title="<?= e(t('contacts.form.osm_close_tip')) ?>">
                <i class="fa-solid fa-xmark"></i> <?= e(t('common.action.close')) ?>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </fieldset>

  <!-- ── SEZIONE 3: Social ─────────────────────────────────────── -->
  <fieldset class="app-form-section ct-form-section">
    <legend class="visually-hidden"><?= e(t('contacts.form.section_social')) ?></legend>
    <div class="app-form-section-header ct-form-section-header" role="button" tabindex="0"
         aria-expanded="false" aria-controls="app-sec-social">
      <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
      <?= e(t('contacts.form.section_social')) ?>
      <i class="fa-solid fa-chevron-down app-chevron ct-chevron" aria-hidden="true"></i>
    </div>
    <div class="app-form-section-body ct-form-section-body app-form-section-collapsed" id="app-sec-social">
      <div class="row g-3">
        <div class="col-sm-6">
          <label for="ct-linkedin" class="form-label fw-semibold small">LinkedIn</label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-brands fa-linkedin" aria-hidden="true"></i></span>
            <input type="url" id="ct-linkedin" name="linkedin"
                   class="<?= $fc('linkedin') ?>"
                   value="<?= e($fv('linkedin')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_linkedin')) ?>" maxlength="255"
                   inputmode="url" autocomplete="url"
                   <?= $fe('linkedin') ? 'aria-invalid="true" aria-describedby="ct-linkedin-feedback"' : '' ?>>
            <div id="ct-linkedin-feedback" class="invalid-feedback"><?= e($fe('linkedin') ?? t('contacts.form.err_url')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-instagram" class="form-label fw-semibold small">Instagram</label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-brands fa-instagram" aria-hidden="true"></i></span>
            <input type="text" id="ct-instagram" name="instagram"
                   class="<?= $fc('instagram') ?>"
                   value="<?= e($fv('instagram')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_username')) ?>" maxlength="100"
                   autocomplete="off"
                   <?= $fe('instagram') ? 'aria-invalid="true" aria-describedby="ct-instagram-feedback"' : '' ?>>
            <div id="ct-instagram-feedback" class="invalid-feedback"><?= e($fe('instagram') ?? t('contacts.form.err_invalid')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-twitter" class="form-label fw-semibold small">Twitter / X</label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></span>
            <input type="text" id="ct-twitter" name="twitter"
                   class="<?= $fc('twitter') ?>"
                   value="<?= e($fv('twitter')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_username')) ?>" maxlength="100"
                   autocomplete="off"
                   <?= $fe('twitter') ? 'aria-invalid="true" aria-describedby="ct-twitter-feedback"' : '' ?>>
            <div id="ct-twitter-feedback" class="invalid-feedback"><?= e($fe('twitter') ?? t('contacts.form.err_invalid')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-facebook" class="form-label fw-semibold small">Facebook</label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></span>
            <input type="url" id="ct-facebook" name="facebook"
                   class="<?= $fc('facebook') ?>"
                   value="<?= e($fv('facebook')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_facebook')) ?>" maxlength="255"
                   inputmode="url" autocomplete="url"
                   <?= $fe('facebook') ? 'aria-invalid="true" aria-describedby="ct-facebook-feedback"' : '' ?>>
            <div id="ct-facebook-feedback" class="invalid-feedback"><?= e($fe('facebook') ?? t('contacts.form.err_url')) ?></div>
          </div>
        </div>
        <div class="col-sm-6">
          <label for="ct-whatsapp" class="form-label fw-semibold small">WhatsApp</label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
            <input type="tel" id="ct-whatsapp" name="whatsapp"
                   class="<?= $fc('whatsapp') ?>"
                   value="<?= e($fv('whatsapp')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_telefono')) ?>" maxlength="30"
                   pattern="[\d\s\+\-\.\(\)]{6,20}"
                   inputmode="tel" autocomplete="tel"
                   <?= $fe('whatsapp') ? 'aria-invalid="true" aria-describedby="ct-whatsapp-feedback"' : '' ?>>
            <div id="ct-whatsapp-feedback" class="invalid-feedback"><?= e($fe('whatsapp') ?? t('contacts.form.err_phone')) ?></div>
          </div>
          <div class="form-text"><?= e(t('contacts.form.whatsapp_help')) ?></div>
        </div>
        <div class="col-sm-6">
          <label for="ct-telegram" class="form-label fw-semibold small">Telegram</label>
          <div class="input-group has-validation">
            <span class="input-group-text app-input-icon"><i class="fa-brands fa-telegram" aria-hidden="true"></i></span>
            <input type="text" id="ct-telegram" name="telegram"
                   class="<?= $fc('telegram') ?>"
                   value="<?= e($fv('telegram')) ?>"
                   placeholder="<?= e(t('contacts.form.ph_username')) ?>" maxlength="100"
                   autocomplete="off"
                   <?= $fe('telegram') ? 'aria-invalid="true" aria-describedby="ct-telegram-feedback"' : '' ?>>
            <div id="ct-telegram-feedback" class="invalid-feedback"><?= e($fe('telegram') ?? t('contacts.form.err_invalid')) ?></div>
          </div>
        </div>
      </div>
    </div>
  </fieldset>

  <!-- ── SEZIONE 4: Tag e note ─────────────────────────────────── -->
  <fieldset class="app-form-section ct-form-section">
    <legend class="visually-hidden"><?= e(t('contacts.form.section_tags_notes')) ?></legend>
    <div class="app-form-section-header ct-form-section-header" role="button" tabindex="0"
         aria-expanded="false" aria-controls="app-sec-extra">
      <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
      <?= e(t('contacts.form.section_tags_notes')) ?>
      <i class="fa-solid fa-chevron-down app-chevron ct-chevron" aria-hidden="true"></i>
    </div>
    <div class="app-form-section-body ct-form-section-body app-form-section-collapsed" id="app-sec-extra">
      <div class="row g-3">
        <div class="col-12">
          <label for="ct-tags" class="form-label fw-semibold small"><?= e(t('contacts.fields.tags')) ?></label>
          <input type="text" id="ct-tags" name="tags"
                 class="<?= $fc('tags') ?>"
                 value="<?= e($fv('tags')) ?>"
                 placeholder="<?= e(t('contacts.form.ph_tags')) ?>" maxlength="500"
                 data-tag-preview="ct-tags-preview"
                 <?= $fe('tags') ? 'aria-invalid="true" aria-describedby="ct-tags-feedback"' : '' ?>>
          <div id="ct-tags-feedback" class="invalid-feedback"><?= e($fe('tags') ?? t('contacts.form.err_invalid')) ?></div>
          <div class="form-text"><?= e(t('contacts.form.tags_help')) ?></div>
          <div id="ct-tags-preview" class="app-tag-preview" aria-live="polite"></div>
        </div>
        <div class="col-12">
          <label for="ct-note" class="form-label fw-semibold small"><?= e(t('contacts.form.field_notes_private')) ?></label>
          <textarea id="ct-note" name="note"
                    class="<?= $fc('note') ?>" rows="4"
                    maxlength="2000"
                    data-char-counter="ct-note-counter"
                    placeholder="<?= e(t('contacts.form.ph_note')) ?>"
                    aria-describedby="ct-note-help<?= $fe('note') ? ' ct-note-feedback' : '' ?>"
                    <?= $fe('note') ? 'aria-invalid="true"' : '' ?>><?= e($fv('note')) ?></textarea>
          <div id="ct-note-feedback" class="invalid-feedback"><?= e($fe('note') ?? t('contacts.form.err_invalid')) ?></div>
          <div class="form-text d-flex justify-content-between gap-2">
            <span id="ct-note-help"><?= e(t('contacts.form.notes_help')) ?></span>
            <span id="ct-note-counter" class="app-char-counter" aria-live="off">0 / 2000</span>
          </div>
        </div>
      </div>
    </div>
  </fieldset>

  <!-- ── Bottoni ──────────────────────────────────────────────── -->
  <div class="d-flex justify-content-end gap-2 mt-3 mb-4">
    <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid fa-check" aria-hidden="true"></i>
      <?= e($submitLabel) ?>
    </button>
  </div>

</form>
</div>
</div>
</div>

<?php $view->include('Auth/Views/partials/cropper_modal', []); ?>

<?php $view->end(); ?>
