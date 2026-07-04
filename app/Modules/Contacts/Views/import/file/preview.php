<?php
/**
 * Step 2 — Anteprima + mapping (CSV) o lista (vCard).
 *
 * Variabili:
 *  - $token, $format ('csv'|'vcf'), $origName
 *  - $info: ['format', 'headers'?, 'rows', 'delimiter'?, 'totalRows', 'suggestedMapping'?, 'duplicateEmailsPreview']
 *  - $targets: ['nome' => 'Nome', ...]  — opzioni per la select di mapping
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');

$isCsv  = $format === 'csv';
$dupCnt = count($info['duplicateEmailsPreview'] ?? []);
?>
<?php $view->start('content'); ?>

<?php
$ctButtons  = '<a href="' . e(route('contacts.import.file.upload')) . '" class="btn btn-sm btn-outline-secondary">';
$ctButtons .= '<i class="fa-solid fa-arrow-left"></i> ' . e(t('contacts.import.change_file_btn')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => t('contacts.import.preview_title'),
    'moduleIcon'     => $isCsv ? 'fa-solid fa-file-csv' : 'fa-solid fa-id-card',
    'moduleSubtitle' => e((int) $info['totalRows'] === 1
        ? t('contacts.import.preview_subtitle_one', ['file' => $origName])
        : t('contacts.import.preview_subtitle_many', ['file' => $origName, 'count' => (int) $info['totalRows']])),
    'moduleButtons'  => $ctButtons,
]);
?>

<div class="container-fluid">

  <?php if ((int) $info['totalRows'] === 0): ?>
    <div class="card shadow-sm">
      <div class="card-body text-center py-5">
        <i class="fa-solid fa-circle-exclamation fa-3x text-warning mb-3"></i>
        <h2 class="h5 mb-2"><?= e(t('contacts.import.no_contacts_found')) ?></h2>
        <p class="text-muted">
          <?= e(t('contacts.import.no_valid_rows')) ?><?= $isCsv ? e(t('contacts.import.no_valid_rows_csv')) : '' ?>.
        </p>
        <a href="<?= e(route('contacts.import.file.upload')) ?>" class="btn btn-outline-primary">
          <i class="fa-solid fa-arrow-left me-1"></i> <?= e(t('contacts.import.try_another_btn')) ?>
        </a>
      </div>
    </div>
  <?php else: ?>

    <form method="POST" action="<?= e(route('contacts.import.file.commit')) ?>">
      <?= csrf_field() ?>

      <!-- Riepilogo -->
      <div class="row g-3 mb-3">
        <div class="col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small text-uppercase"><?= e(t('contacts.import.preview_col_total')) ?></div>
              <div class="h3 mb-0"><?= (int) $info['totalRows'] ?></div>
              <div class="small text-muted"><?= e(t('contacts.import.preview_total_sub')) ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small text-uppercase"><?= e(t('contacts.import.preview_col_dupes')) ?></div>
              <div class="h3 mb-0 <?= $dupCnt > 0 ? 'text-warning' : '' ?>"><?= $dupCnt ?></div>
              <div class="small text-muted"><?= e(t('contacts.import.preview_dupes_sub')) ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small text-uppercase"><?= e(t('contacts.import.preview_col_format')) ?></div>
              <div class="h3 mb-0"><?= $isCsv ? 'CSV' : 'vCard' ?></div>
              <?php if ($isCsv): ?>
                <div class="small text-muted">
                  <?= e(t('contacts.import.separator_label')) ?>
                  <code><?php
                    $d = $info['delimiter'] ?? ',';
                    echo $d === "\t" ? 'TAB' : e($d);
                  ?></code>
                </div>
              <?php else: ?>
                <div class="small text-muted"><?= e(t('contacts.import.auto_mapping')) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview / Mapping -->
      <div class="card shadow-sm mb-3">
        <div class="card-body">

          <?php if ($isCsv): ?>
            <h2 class="h6 text-muted text-uppercase small mb-3">
              <i class="fa-solid fa-table me-1"></i>
              <?= e(t('contacts.import.csv_mapping_title')) ?>
            </h2>
            <p class="text-muted small mb-3">
              <?= e(t('contacts.import.csv_mapping_help_a')) ?>
              <?= e(t('contacts.import.csv_mapping_help_b', ['n' => count($info['rows'])])) ?>
            </p>

            <div class="table-responsive">
              <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <?php foreach (($info['headers'] ?? []) as $i => $h): ?>
                      <th scope="col">
                        <div class="fw-semibold small text-truncate" style="max-width: 220px;" title="<?= e($h) ?>">
                          <?= e($h !== '' ? $h : t('contacts.import.col_number', ['n' => $i + 1])) ?>
                        </div>
                        <select class="form-select form-select-sm mt-1" name="mapping[<?= (int) $i ?>]">
                          <option value=""><?= e(t('contacts.import.ignore_option')) ?></option>
                          <?php
                            $suggested = $info['suggestedMapping'][$i] ?? '';
                            foreach ($targets as $key => $label):
                          ?>
                            <option value="<?= e($key) ?>" <?= $suggested === $key ? 'selected' : '' ?>>
                              <?= e($label) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($info['rows'] as $row): ?>
                    <tr>
                      <?php foreach (($info['headers'] ?? []) as $i => $_): ?>
                        <td class="small">
                          <div class="text-truncate" style="max-width: 220px;"
                               title="<?= e((string) ($row[$i] ?? '')) ?>">
                            <?= e((string) ($row[$i] ?? '')) ?>
                          </div>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          <?php else: ?>
            <h2 class="h6 text-muted text-uppercase small mb-3">
              <i class="fa-solid fa-list me-1"></i>
              <?= e(t('contacts.import.vcf_contacts_title')) ?>
            </h2>
            <div class="table-responsive">
              <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="small text-muted text-uppercase"><?= e(t('contacts.fields.nome')) ?></th>
                    <th class="small text-muted text-uppercase"><?= e(t('contacts.fields.azienda')) ?></th>
                    <th class="small text-muted text-uppercase d-none d-md-table-cell"><?= e(t('contacts.fields.email')) ?></th>
                    <th class="small text-muted text-uppercase d-none d-md-table-cell"><?= e(t('contacts.fields.telefono')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($info['rows'] as $c): ?>
                    <tr>
                      <td><?= e(trim(($c['nome'] ?? '') . ' ' . ($c['cognome'] ?? ''))) ?></td>
                      <td class="text-muted"><?= e((string) ($c['azienda'] ?? '')) ?></td>
                      <td class="d-none d-md-table-cell"><?= e((string) ($c['email'] ?? '')) ?></td>
                      <td class="d-none d-md-table-cell"><?= e((string) ($c['telefono'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ((int) $info['totalRows'] > count($info['rows'])): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted small fst-italic">
                        <?= e(t('contacts.import.more_rows', ['n' => (int) $info['totalRows'] - count($info['rows'])])) ?>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Submit -->
      <div class="d-flex gap-2 justify-content-end">
        <a href="<?= e(route('contacts.import.file.upload')) ?>" class="btn btn-outline-secondary">
          <?= e(t('common.action.cancel')) ?>
        </a>
        <button type="submit" class="btn btn-primary"
                data-app-confirm="<?= e(t('contacts.import.confirm', ['count' => (int) $info['totalRows']])) ?>">
          <i class="fa-solid fa-check me-1"></i>
          <?= e((int) $info['totalRows'] === 1 ? t('contacts.import.submit_one') : t('contacts.import.submit_many', ['count' => (int) $info['totalRows']])) ?>
        </button>
      </div>
    </form>

  <?php endif; ?>

</div>

<?php $view->end(); ?>
