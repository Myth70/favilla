<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php $view->include('partials/pf-hero-module', [
            'moduleName'     => t('documenti.admin_sequenze.hero_title'),
            'moduleIcon'     => 'fa-solid fa-hashtag',
            'moduleSubtitle' => t('documenti.admin_sequenze.subtitle'),
            'moduleButtons'  => '',
        ]); ?>
    </div>

    <div class="col-12" id="sequenze-table-container">
        <?php $view->include('Documenti/Views/admin/partials/sequenze_table', ['sequenze' => $sequenze]); ?>
    </div>

</div>
</div>
<?php $view->end(); ?>
