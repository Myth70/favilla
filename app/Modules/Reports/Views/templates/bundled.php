<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
$view->start('content');
?>

<?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'templates']); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i><?= e(t('reports.bundled.title')) ?></h5>
    <div class="d-flex gap-2">
        <?php if (has_permission('reports.admin') && !empty($available)): ?>
        <form method="POST" action="<?= e(route('reports.templates.import_bundled')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="module_name" value="">
            <button type="submit" class="btn btn-sm btn-primary"
                    data-app-confirm="<?= e(t('reports.bundled.import_all_confirm')) ?>"
                    data-app-confirm-class="btn-primary">
                <i class="fa-solid fa-download me-1"></i><?= e(t('reports.bundled.import_all')) ?>
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= e(route('reports.templates.index')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('reports.bundled.back_templates')) ?>
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-circle-info me-2"></i><?= e(t('reports.bundled.how_title')) ?></h6>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2">
            <?= t('reports.bundled.how_body') ?>
        </p>
        <pre class="bg-light p-3 rounded small mb-0"><code>app/Modules/NomeModulo/
&boxvr;&boxh;&boxh; report_templates/
&boxv;   &boxvr;&boxh;&boxh; lista_clienti.json
&boxv;   &boxur;&boxh;&boxh; dettaglio_ordine.json
&boxur;&boxh;&boxh; ...</code></pre>
    </div>
</div>

<?php if (empty($available)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-box-open fa-2x d-block mb-2 opacity-50"></i>
            <?= e(t('reports.bundled.none_title')) ?>
            <div class="mt-2 small">
                <?= t('reports.bundled.none_hint') ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($available as $modName => $info): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0">
                            <i class="fa-solid fa-puzzle-piece text-primary me-1"></i>
                            <?= e($modName) ?>
                        </h6>
                        <span class="badge bg-primary rounded-pill"><?= e(t('reports.bundled.files_count', ['count' => (int) $info['files']])) ?></span>
                    </div>
                    <p class="small text-muted mb-3">
                        <?php if ($info['imported'] > 0): ?>
                            <i class="fa-solid fa-check-circle text-success me-1"></i>
                            <?= e(t('reports.bundled.imported_count', ['count' => (int) $info['imported']])) ?>
                        <?php else: ?>
                            <i class="fa-solid fa-clock text-warning me-1"></i>
                            <?= e(t('reports.bundled.none_imported')) ?>
                        <?php endif; ?>
                    </p>

                    <?php if (has_permission('reports.admin')): ?>
                    <div class="d-flex gap-2">
                        <form method="POST" action="<?= e(route('reports.templates.import_bundled')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="module_name" value="<?= e($modName) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip"
                                    title="<?= e(t('reports.bundled.import_tip')) ?>">
                                <i class="fa-solid fa-download me-1"></i><?= e(t('reports.bundled.import')) ?>
                            </button>
                        </form>
                        <form method="POST" action="<?= e(route('reports.templates.import_bundled')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="module_name" value="<?= e($modName) ?>">
                            <input type="hidden" name="overwrite" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-warning"
                                    data-app-confirm="<?= e(t('reports.bundled.overwrite_confirm', ['module' => $modName])) ?>"
                                    data-app-confirm-class="btn-warning"
                                    data-bs-toggle="tooltip" title="<?= e(t('reports.bundled.overwrite_tip')) ?>">
                                <i class="fa-solid fa-rotate me-1"></i><?= e(t('reports.bundled.update')) ?>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- JSON format reference -->
<div class="card shadow-sm mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-code me-2"></i><?= e(t('reports.bundled.json_title')) ?></h6>
    </div>
    <div class="card-body">
        <pre class="bg-light p-3 rounded small mb-0"><code>{
    "name": "Report Clienti Attivi",
    "description": "Lista clienti con stato attivo",
    "source_key": "clienti",
    "source_type": "list",
    "output_format": "pdf",
    "visibility": "global",
    "max_rows": 10000,
    "template_html": "&lt;h1&gt;Clienti&lt;/h1&gt;&lt;table data-prm-type=\"data_table\" data-prm-config='{\"columns\":[\"name\",\"email\"]}'&gt;&lt;/table&gt;",
    "filters_config": { "status": "active" },
    "sorting_config": { "field": "name", "dir": "ASC" }
}</code></pre>
        <p class="small text-muted mt-2 mb-0">
            <?= t('reports.bundled.json_note') ?>
        </p>
    </div>
</div>

<?php $view->end(); ?>
