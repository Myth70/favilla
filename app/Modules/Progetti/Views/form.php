<?php
$view->layout('main');
$view->pushStyle('css/progetti.css');

$isEdit   = !empty($isEdit);
$project  = $project ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];

$statusOptions = \App\Modules\Progetti\Services\ProgettiService::getProjectStatuses();
$statusOptions = array_map(fn ($cfg) => $cfg['label'], $statusOptions);
?>

<?php $view->start('content'); ?>
<div class="container-fluid prj-page">

    <?php
    $formHeroButtons = '<a href="' . e(route('projects.index')) . '" class="btn btn-outline-secondary btn-sm">'
                     . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('progetti.form.back_to_list')) . '</a>';
    $view->include('partials/pf-hero-module', [
        'moduleName'     => $isEdit ? t('progetti.form.edit_title') : t('progetti.form.new_title'),
        'moduleIcon'     => 'fa-solid fa-' . ($isEdit ? 'pen-to-square' : 'folder-plus'),
        'moduleSubtitle' => $isEdit ? e((string) ($project['name'] ?? '')) : t('progetti.form.new_subtitle'),
        'moduleButtons'  => $formHeroButtons,
    ]);
    ?>

    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST"
                          action="<?= $isEdit
                              ? e(route('projects.update', ['id' => (int) $project['id']]))
                              : e(route('projects.store')) ?>">
                        <?= csrf_field() ?>
                        <?php if ($isEdit): ?>
                        <input type="hidden" name="_method" value="PUT">
                        <?php endif; ?>
                        <?php $view->include('Progetti/Views/partials/form_fields', [
                            'isEdit' => $isEdit,
                            'project' => $project,
                            'errors' => $errors,
                            'old' => $old,
                            'statusOptions' => $statusOptions,
                            'isModal' => false,
                        ]); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->end(); ?>
