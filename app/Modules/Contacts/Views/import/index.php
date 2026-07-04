<?php
/**
 * Variabili: $modules (raggruppate per modulo, da ContactSourceService::getSourcesForUser)
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');
?>
<?php $view->start('content'); ?>

<?php
$ctButtons  = '<a href="' . e(route('contacts.index')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('contacts.import.back_tip')) . '">';
$ctButtons .= '<i class="fa-solid fa-xmark"></i> ' . e(t('common.action.close')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => t('contacts.import.title_main'),
    'moduleIcon'     => 'fa-solid fa-file-import',
    'moduleSubtitle' => e(t('contacts.import.title_subtitle')),
    'moduleButtons'  => $ctButtons,
]);
?>

<div class="container-fluid">

  <!-- Importa da file (CSV / vCard) -->
  <section class="card shadow-sm mb-3">
    <div class="card-body">
      <header class="d-flex align-items-center gap-2 mb-3">
        <span class="ct-source-mod-icon">
          <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
        </span>
        <h2 class="h5 mb-0"><?= e(t('contacts.import.file_source_title')) ?></h2>
        <small class="text-muted ms-auto">CSV, vCard</small>
      </header>

      <div class="row g-3">
        <div class="col-md-6 col-lg-4">
          <a href="<?= e(route('contacts.import.file.upload')) ?>"
             class="card h-100 ct-source-card text-decoration-none text-body">
            <div class="card-body d-flex gap-3">
              <div class="ct-source-icon flex-shrink-0">
                <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
              </div>
              <div class="flex-grow-1">
                <div class="fw-semibold">CSV</div>
                <div class="text-muted small mt-1">
                  Carica un file CSV esportato da Excel, Google Contacts o un fornitore.
                  Mappa le colonne ai campi della rubrica prima dell'import.
                </div>
                <div class="mt-2">
                  <span class="badge bg-light text-muted border">
                    <i class="fa-solid fa-upload me-1"></i> <?= e(t('contacts.import.upload_badge')) ?>
                  </span>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-md-6 col-lg-4">
          <a href="<?= e(route('contacts.import.file.upload')) ?>"
             class="card h-100 ct-source-card text-decoration-none text-body">
            <div class="card-body d-flex gap-3">
              <div class="ct-source-icon flex-shrink-0">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
              </div>
              <div class="flex-grow-1">
                <div class="fw-semibold">vCard (.vcf)</div>
                <div class="text-muted small mt-1">
                  Importa la rubrica del telefono (iPhone, Android) o di Outlook
                  esportata come <code>.vcf</code>. Singolo o multiplo, autodetect.
                </div>
                <div class="mt-2">
                  <span class="badge bg-light text-muted border">
                    <i class="fa-solid fa-upload me-1"></i> <?= e(t('contacts.import.upload_badge')) ?>
                  </span>
                </div>
              </div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </section>

  <?php if (empty($modules)): ?>
    <div class="card shadow-sm">
      <div class="card-body text-center py-4">
        <i class="fa-solid fa-inbox fa-2x text-muted mb-2" aria-hidden="true"></i>
        <h2 class="h6 mb-2"><?= e(t('contacts.import.no_modules')) ?></h2>
        <p class="text-muted small mb-0">
          <?= e(t('contacts.import.no_modules_help_a')) ?>
          <code>App\Contracts\ContactSourceProvider</code>.<br>
          <?= e(t('contacts.import.no_modules_help_b')) ?>
        </p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($modules as $modGroup): ?>
      <section class="card shadow-sm mb-3">
        <div class="card-body">
          <header class="d-flex align-items-center gap-2 mb-3">
            <span class="ct-source-mod-icon">
              <i class="fa-solid <?= e($modGroup['icon'] ?? 'fa-cube') ?>" aria-hidden="true"></i>
            </span>
            <h2 class="h5 mb-0"><?= e($modGroup['label']) ?></h2>
            <small class="text-muted ms-auto"><?= e(count($modGroup['sources']) === 1 ? t('contacts.import.source_count_one') : t('contacts.import.source_count_many', ['count' => count($modGroup['sources'])])) ?></small>
          </header>

          <div class="row g-3">
            <?php foreach ($modGroup['sources'] as $src): ?>
              <div class="col-md-6 col-lg-4">
                <a href="<?= e(route('contacts.import.browse', ['module' => $modGroup['module'], 'source' => $src['key']])) ?>"
                   class="card h-100 ct-source-card text-decoration-none text-body">
                  <div class="card-body d-flex gap-3">
                    <div class="ct-source-icon flex-shrink-0">
                      <i class="fa-solid <?= e($src['icon'] ?? 'fa-table') ?>" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                      <div class="fw-semibold"><?= e($src['label']) ?></div>
                      <?php if (!empty($src['description'])): ?>
                        <div class="text-muted small mt-1"><?= e($src['description']) ?></div>
                      <?php endif; ?>
                      <div class="mt-2">
                        <span class="badge bg-light text-muted border">
                          <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>
                          <?= e(t('contacts.import.browse_badge')) ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php $view->end(); ?>
