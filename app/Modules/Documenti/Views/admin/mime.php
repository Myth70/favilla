<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php $view->include('partials/pf-hero-module', [
            'moduleName'     => t('documenti.admin_mime.hero_title'),
            'moduleIcon'     => 'fa-solid fa-file-code',
            'moduleSubtitle' => t('documenti.admin_mime.subtitle'),
            'moduleButtons'  => '',
        ]); ?>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body py-2">
                <label class="form-label small mb-1" for="dc-mime-search"><?= e(t('documenti.admin_mime.cerca_label')) ?></label>
                <input type="search" id="dc-mime-search" class="form-control form-control-sm"
                       placeholder="<?= e(t('documenti.admin_mime.cerca_placeholder')) ?>"
                       data-filter-rows="#mime-table-container tr[data-mime]"
                       data-filter-attr="data-mime">
            </div>
        </div>
    </div>

    <div class="col-12" id="mime-table-container">
        <?php $view->include('Documenti/Views/admin/partials/mime_table', ['mimeTypes' => $mimeTypes]); ?>
    </div>

</div>
</div>
<?php $view->end(); ?>
