<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php $view->include('partials/pf-hero-module', [
            'moduleName'     => t('documenti.admin_categorie.hero_title'),
            'moduleIcon'     => 'fa-solid fa-folder-tree',
            'moduleSubtitle' => t('documenti.admin_categorie.subtitle'),
            'moduleButtons'  => '<a href="' . e(route('documenti.categorie.index')) . '" class="btn btn-outline-primary btn-sm">'
                              . '<i class="fa-solid fa-pen me-1" aria-hidden="true"></i>' . e(t('documenti.admin_categorie.gestisci_btn')) . '</a>',
        ]); ?>
    </div>

    <div class="col-12">
        <?php if (empty($categorie)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
                'icon'      => 'fa-folder-tree',
                'titolo'    => t('documenti.categorie.empty_titolo'),
                'messaggio' => t('documenti.admin_categorie.empty_messaggio'),
            ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <?php $view->include('Documenti/Views/partials/albero_categorie', [
                    'categorie' => $categorie,
                    'readOnly'  => true,
                ]); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
<?php $view->end(); ?>
