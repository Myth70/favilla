<?php
/**
 * Partial: form aggiunta/modifica ricorrenza (inline HTMX)
 * Variabili: $contattoId, $contatto, $ric (null=new), $errors, $old
 */
$isEdit   = $ric !== null;
$nomeC    = trim($contatto['nome'] . ' ' . ($contatto['cognome'] ?? ''));
$defTipo  = $old['tipo'] ?? ($ric['tipo'] ?? 'evento');
$defTitolo = $old['titolo'] ?? ($ric['titolo'] ?? '');
$defData  = $old['data_ricorrenza'] ?? ($ric['data_ricorrenza'] ?? '');
$defAnno  = $old['anno_riferimento'] ?? ($ric['anno_riferimento'] ?? '');
$defGiorni = $old['promemoria_giorni_prima'] ?? ($ric['promemoria_giorni_prima'] ?? 7);
$defGiorno = isset($old['notifica_giorno_stesso']) ? (bool)$old['notifica_giorno_stesso']
           : (isset($ric['notifica_giorno_stesso']) ? (bool)$ric['notifica_giorno_stesso'] : true);
$defAnnuale = isset($old['annuale']) ? (bool)$old['annuale']
            : (isset($ric['annuale']) ? (bool)$ric['annuale'] : true);
$defCalendario = $old['crea_evento_calendario'] ?? ($ric['crea_evento_calendario'] ?? 'no');
$defNote  = $old['note'] ?? ($ric['note'] ?? '');

$actionUrl = $isEdit
  ? route('contacts.recurrences.update', ['id' => $contattoId, 'rid' => $ric['id']])
  : route('contacts.recurrences.store', ['id' => $contattoId]);
$hxMethod  = 'hx-post';

$errors = $errors ?? [];
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control form-control-sm') => $base . ($fe($k) ? ' is-invalid' : '');
?>

<div class="ct-ric-form-wrap mb-3" id="ct-ric-add-form">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="mb-0 fw-semibold">
      <i class="fa-solid fa-<?= $isEdit ? 'pen' : 'plus' ?> me-1 text-muted"></i>
      <?= e($isEdit ? t('contacts.recurrences.form_title_edit') : t('contacts.recurrences.form_title_new')) ?>
      <span class="text-muted fw-normal small ms-1"><?= e(t('contacts.recurrences.form_for')) ?> <?= e($nomeC) ?></span>
    </h6>
    <button type="button" class="btn-close btn-close-sm"
            hx-get="<?= e(route('contacts.recurrences.list', ['id' => $contattoId])) ?>"
            hx-target="#ct-ric-section" hx-swap="innerHTML"
            hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
            aria-label="<?= e(t('common.action.close')) ?>">
    </button>
  </div>

  <form <?= $hxMethod ?>="<?= e($actionUrl) ?>"
        hx-target="#ct-ric-section"
        hx-swap="innerHTML"
        hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'
        novalidate data-app-form>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <!-- Nascondi nome contatto per JS autofill -->
    <span data-contatto-nome class="d-none"><?= e($nomeC) ?></span>

    <div class="row g-2">
      <!-- Tipo -->
      <div class="col-sm-4">
        <label class="form-label small fw-semibold"><?= e(t('common.label.type')) ?></label>
        <select name="tipo" class="form-select form-select-sm" data-ric-tipo>
          <option value="evento"       <?= $defTipo==='evento'       ? 'selected':'' ?>>📅 <?= e(t('contacts.widget.type_event')) ?></option>
          <option value="compleanno"   <?= $defTipo==='compleanno'   ? 'selected':'' ?>>🎂 <?= e(t('contacts.widget.type_birthday')) ?></option>
          <option value="anniversario" <?= $defTipo==='anniversario' ? 'selected':'' ?>>💍 <?= e(t('contacts.widget.type_anniversary')) ?></option>
        </select>
      </div>

      <!-- Titolo -->
      <div class="col-sm-8">
        <label class="form-label small fw-semibold"><?= e(t('contacts.recurrences.field_title')) ?> <span class="text-danger">*</span></label>
        <input type="text" name="titolo" class="<?= $fc('titolo') ?>"
               value="<?= e($defTitolo) ?>" placeholder="<?= e(t('contacts.recurrences.ph_title')) ?>" required
               aria-required="true" maxlength="255"
               aria-invalid="<?= $fe('titolo') ? 'true' : 'false' ?>"
               data-ric-titolo>
        <div class="invalid-feedback"><?= e($fe('titolo') ?? t('contacts.recurrences.err_title')) ?></div>
      </div>

      <!-- Data -->
      <div class="col-sm-4">
        <label class="form-label small fw-semibold"><?= e(t('common.label.date')) ?> <span class="text-danger">*</span></label>
        <input type="date" name="data_ricorrenza"
               class="<?= $fc('data_ricorrenza') ?>"
               value="<?= e($defData) ?>" required aria-required="true"
               aria-invalid="<?= $fe('data_ricorrenza') ? 'true' : 'false' ?>">
        <div class="invalid-feedback"><?= e($fe('data_ricorrenza') ?? t('contacts.recurrences.err_date')) ?></div>
      </div>

      <!-- Anno riferimento (solo compleanno) -->
      <div class="col-sm-4 <?= $defTipo !== 'compleanno' ? 'd-none' : '' ?>" data-ric-anno-row>
        <label class="form-label small fw-semibold"><?= e(t('contacts.recurrences.field_birth_year')) ?></label>
        <input type="number" name="anno_riferimento" class="form-control form-control-sm"
               value="<?= e($defAnno) ?>" placeholder="<?= e(t('contacts.recurrences.ph_year')) ?>" min="1900" max="<?= date('Y') ?>">
        <div class="form-text ct-form-text-xs"><?= e(t('contacts.recurrences.age_help')) ?></div>
      </div>

      <!-- Annuale toggle -->
      <div class="col-sm-4 d-flex align-items-end">
        <div class="form-check form-switch pb-1">
          <input class="form-check-input" type="checkbox" name="annuale" value="1" id="ric-annuale"
                 <?= $defAnnuale ? 'checked' : '' ?>
                 <?= $defTipo === 'compleanno' ? 'disabled' : '' ?>
                 data-ric-annuale>
          <label class="form-check-label small" for="ric-annuale"><?= e(t('contacts.recurrences.repeats_yearly')) ?></label>
        </div>
      </div>
    </div>

    <!-- Reminder -->
    <div class="row g-2 mt-1">
      <div class="col-12">
        <label class="form-label small fw-semibold">
          <i class="fa-solid fa-bell me-1 text-muted"></i><?= e(t('contacts.recurrences.section_notif')) ?>
        </label>
      </div>
      <div class="col-sm-5">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><?= e(t('contacts.recurrences.advance_label')) ?></span>
          <input type="number" name="promemoria_giorni_prima"
                 class="form-control form-control-sm" min="0" max="90"
                 value="<?= (int)$defGiorni ?>">
          <span class="input-group-text"><?= e(t('contacts.recurrences.advance_days')) ?></span>
        </div>
        <div class="form-text ct-form-text-xs"><?= e(t('contacts.recurrences.no_advance_help')) ?></div>
      </div>
      <div class="col-sm-7 d-flex align-items-center">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="notifica_giorno_stesso" value="1"
                 id="ric-gdx" <?= $defGiorno ? 'checked' : '' ?>>
          <label class="form-check-label small" for="ric-gdx"><?= e(t('contacts.recurrences.same_day_label')) ?></label>
        </div>
      </div>
    </div>

    <!-- Calendario -->
    <div class="row g-2 mt-1">
      <div class="col-12">
        <label class="form-label small fw-semibold">
          <i class="fa-solid fa-calendar me-1 text-muted"></i><?= e(t('contacts.recurrences.section_cal')) ?>
        </label>
      </div>
      <div class="col-12">
        <div class="d-flex flex-wrap gap-3">
          <?php foreach (['no' => t('contacts.recurrences.cal_no'), 'prossimo' => t('contacts.recurrences.cal_next'), 'annuale' => t('contacts.recurrences.cal_annual')] as $val => $lbl): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="crea_evento_calendario"
                   id="cal-<?= $val ?>" value="<?= $val ?>"
                   <?= $defCalendario === $val ? 'checked' : '' ?>>
            <label class="form-check-label small" for="cal-<?= $val ?>"><?= $lbl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Note ricorrenza -->
    <div class="mt-2">
      <label class="form-label small fw-semibold"><?= e(t('contacts.recurrences.field_note_opt')) ?></label>
      <input type="text" name="note" class="form-control form-control-sm"
             value="<?= e($defNote) ?>" placeholder="<?= e(t('contacts.recurrences.ph_note')) ?>" maxlength="500">
    </div>

    <!-- Bottoni -->
    <div class="d-flex gap-2 mt-3">
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="fa-solid fa-check me-1"></i><?= e($isEdit ? t('common.action.update') : t('contacts.recurrences.submit_add')) ?>
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary"
              hx-get="<?= e(route('contacts.recurrences.list', ['id' => $contattoId])) ?>"
              hx-target="#ct-ric-section" hx-swap="innerHTML"
              hx-headers='{"X-CSRF-TOKEN": "<?= csrf_token() ?>"}'>
        <?= e(t('common.action.cancel')) ?>
      </button>
    </div>
  </form>
</div>
