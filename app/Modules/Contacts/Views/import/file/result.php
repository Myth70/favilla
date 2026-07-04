<?php
/**
 * Step 3 — Esito dell'importazione.
 *
 * Variabili:
 *  - $summary: ['created' => int, 'skipped' => int, 'rejected' => [['row' => int, 'reason' => string], ...]]
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');

$rejectedCount = count($summary['rejected'] ?? []);
?>
<?php $view->start('content'); ?>

<?php
$ctButtons  = '<a href="' . e(route('contacts.index')) . '" class="btn btn-sm btn-primary">';
$ctButtons .= '<i class="fa-solid fa-address-book"></i> ' . e(t('contacts.import.goto_rubrica_btn')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => t('contacts.import.result_title'),
    'moduleIcon'     => 'fa-solid fa-clipboard-check',
    'moduleSubtitle' => e(t('contacts.import.result_subtitle')),
    'moduleButtons'  => $ctButtons,
]);
?>

<div class="container-fluid">

  <div class="row g-3 mb-3">
    <div class="col-sm-4">
      <div class="card shadow-sm h-100 border-success border-opacity-25">
        <div class="card-body">
          <div class="text-muted small text-uppercase">
            <i class="fa-solid fa-circle-check text-success me-1"></i> <?= e(t('contacts.import.created_label')) ?>
          </div>
          <div class="display-6 text-success mb-0"><?= (int) $summary['created'] ?></div>
          <div class="small text-muted"><?= e(t('contacts.import.created_sub')) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card shadow-sm h-100 border-warning border-opacity-25">
        <div class="card-body">
          <div class="text-muted small text-uppercase">
            <i class="fa-solid fa-circle-pause text-warning me-1"></i> <?= e(t('contacts.import.skipped_label')) ?>
          </div>
          <div class="display-6 text-warning mb-0"><?= (int) $summary['skipped'] ?></div>
          <div class="small text-muted"><?= e(t('contacts.import.skipped_sub')) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card shadow-sm h-100 <?= $rejectedCount > 0 ? 'border-danger border-opacity-25' : '' ?>">
        <div class="card-body">
          <div class="text-muted small text-uppercase">
            <i class="fa-solid fa-circle-xmark <?= $rejectedCount > 0 ? 'text-danger' : 'text-muted' ?> me-1"></i> <?= e(t('contacts.import.rejected_label')) ?>
          </div>
          <div class="display-6 mb-0 <?= $rejectedCount > 0 ? 'text-danger' : 'text-muted' ?>"><?= $rejectedCount ?></div>
          <div class="small text-muted"><?= e(t('contacts.import.rejected_sub')) ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($rejectedCount > 0): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-muted text-uppercase small mb-3">
          <i class="fa-solid fa-triangle-exclamation me-1 text-danger"></i>
          <?= e(t('contacts.import.rejected_table_title')) ?>
        </h2>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" class="small text-muted text-uppercase" style="width: 100px;"><?= e(t('contacts.import.rejected_col_row')) ?></th>
                <th scope="col" class="small text-muted text-uppercase"><?= e(t('contacts.import.rejected_col_reason')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($summary['rejected'] as $r): ?>
                <tr>
                  <td><code>#<?= (int) $r['row'] ?></code></td>
                  <td><?= e((string) $r['reason']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex gap-2">
    <a href="<?= e(route('contacts.index')) ?>" class="btn btn-primary">
      <i class="fa-solid fa-address-book me-1"></i> <?= e(t('contacts.import.goto_contacts_btn')) ?>
    </a>
    <a href="<?= e(route('contacts.import.file.upload')) ?>" class="btn btn-outline-secondary">
      <i class="fa-solid fa-arrow-rotate-left me-1"></i> <?= e(t('contacts.import.import_more_btn')) ?>
    </a>
  </div>

</div>

<?php $view->end(); ?>
