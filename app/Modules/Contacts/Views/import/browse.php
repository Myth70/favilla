<?php
/**
 * Variabili: $module, $source, $sourceMeta, $rows, $total, $page, $perPage, $filters
 */
$view->layout('main');
$view->pushStyle('css/contacts.css');
?>
<?php $view->start('content'); ?>

<?php
$ctButtons  = '<a href="' . e(route('contacts.import.index')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('contacts.import.sources_back_tip')) . '">';
$ctButtons .= '<i class="fa-solid fa-arrow-left"></i> ' . e(t('contacts.import.title_main')) . '</a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => 'Importa da ' . e($sourceMeta['label'] ?? $source),
    'moduleIcon'     => 'fa-solid ' . ($sourceMeta['icon'] ?? 'fa-file-import'),
    'moduleSubtitle' => e(($sourceMeta['module_label'] ?? $module) . ' — seleziona un record da importare nella rubrica'),
    'moduleButtons'  => $ctButtons,
]);
?>

<div class="container-fluid">

  <div class="card shadow-sm mb-3">
    <div class="card-body">

      <!-- Filtro ricerca HTMX -->
      <div class="input-group input-group-sm mb-3">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="text" class="form-control"
               name="q" value="<?= e($filters['q'] ?? '') ?>"
               placeholder="<?= e(t('contacts.import.search_ph')) ?>"
               autocomplete="off"
               hx-get="<?= e(route('contacts.import.list', ['module' => $module, 'source' => $source])) ?>"
               hx-trigger="keyup changed delay:350ms, search"
               hx-target="#ct-import-tbody"
               hx-push-url="false">
        <?php if (!empty($filters['q'])): ?>
          <a href="<?= e(route('contacts.import.browse', ['module' => $module, 'source' => $source])) ?>"
             class="btn btn-sm btn-outline-secondary"
             data-bs-toggle="tooltip" title="<?= e(t('contacts.import.search_clear_tip')) ?>">
            <i class="fa-solid fa-xmark"></i>
          </a>
        <?php endif; ?>
      </div>

      <!-- Tabella record -->
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" class="small text-muted text-uppercase"><?= e(t('contacts.import.record_col')) ?></th>
              <th scope="col" class="small text-muted text-uppercase d-none d-md-table-cell"><?= e(t('contacts.fields.email')) ?></th>
              <th scope="col" class="small text-muted text-uppercase d-none d-md-table-cell"><?= e(t('contacts.fields.telefono')) ?></th>
              <th scope="col" class="text-end small text-muted text-uppercase"><?= e(t('contacts.import.action_col')) ?></th>
            </tr>
          </thead>
          <tbody id="ct-import-tbody">
            <?php $view->include('Contacts/Views/import/partials/import_rows', [
              'module'  => $module,
              'source'  => $source,
              'rows'    => $rows,
              'total'   => $total,
              'page'    => $page,
              'perPage' => $perPage,
              'filters' => $filters,
            ]); ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>

</div>

<?php $view->end(); ?>
