<?php
/**
 * Step 1 — Upload del file (CSV o vCard).
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');
?>
<?php $view->start('content'); ?>

<?php
$ctButtons  = '<a href="' . e(route('contacts.import.index')) . '" class="btn btn-sm btn-outline-secondary">';
$ctButtons .= '<i class="fa-solid fa-arrow-left"></i> ' . e(t('contacts.import.title_main')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => t('contacts.import.upload_title_main'),
    'moduleIcon'     => 'fa-solid fa-file-arrow-up',
    'moduleSubtitle' => e(t('contacts.import.upload_subtitle')),
    'moduleButtons'  => $ctButtons,
]);
?>

<div class="container-fluid">

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h5 mb-3">
            <i class="fa-solid fa-upload me-2 text-primary"></i>
            <?= e(t('contacts.import.upload_section')) ?>
          </h2>

          <form method="POST"
                action="<?= e(route('contacts.import.file.store')) ?>"
                enctype="multipart/form-data"
                class="needs-validation"
                novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
              <label for="ct-import-file" class="form-label fw-semibold"><?= e(t('contacts.import.file_label')) ?></label>
              <input type="file"
                     class="form-control"
                     id="ct-import-file"
                     name="file"
                     accept=".csv,.vcf,text/csv,text/vcard,text/x-vcard"
                     required>
              <div class="form-text">
                <?= e(t('contacts.import.formats_help')) ?>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-arrow-right me-1"></i> <?= e(t('contacts.import.upload_btn')) ?>
              </button>
              <a href="<?= e(route('contacts.import.index')) ?>" class="btn btn-outline-secondary">
                <?= e(t('common.action.cancel')) ?>
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h3 class="h6 text-muted text-uppercase small mb-3"><?= e(t('contacts.import.howto_title')) ?></h3>

          <ul class="list-unstyled small mb-3">
            <li class="mb-2">
              <i class="fa-solid fa-1 fa-fw text-primary me-1"></i>
              <?= t('contacts.import.howto_step_1') ?>
            </li>
            <li class="mb-2">
              <i class="fa-solid fa-2 fa-fw text-primary me-1"></i>
              <?= t('contacts.import.howto_step_2') ?>
            </li>
            <li class="mb-2">
              <i class="fa-solid fa-3 fa-fw text-primary me-1"></i>
              <?= t('contacts.import.howto_step_3') ?>
            </li>
          </ul>

          <hr>

          <h3 class="h6 text-muted text-uppercase small mb-2"><?= e(t('contacts.import.template_title')) ?></h3>
          <p class="small text-muted mb-2">
            <?= e(t('contacts.import.template_help')) ?>
          </p>
          <a href="<?= e(route('contacts.import.file.template')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-download me-1"></i> <?= e(t('contacts.import.template_btn')) ?>
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<?php $view->end(); ?>
