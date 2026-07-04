<?php
$isEdit = true;
$project = $project ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$statusOptions = \App\Modules\Progetti\Services\ProgettiService::getProjectStatuses();
$statusOptions = array_map(fn ($cfg) => $cfg['label'], $statusOptions);
?>

<form method="POST"
      action="<?= e(route('projects.update', ['id' => (int) $project['id']])) ?>"
      class="prj-edit-form"
      data-prj-edit-form="1">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">

    <div class="modal-header">
        <h5 class="modal-title">
            <i class="fa-solid fa-pen-to-square me-2"></i><?= e(t('progetti.show.edit_project_tip')) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
    </div>
    <div class="modal-body">
        <?php $view->include('Progetti/Views/partials/form_fields', [
            'isEdit' => $isEdit,
            'project' => $project,
            'errors' => $errors,
            'old' => $old,
            'statusOptions' => $statusOptions,
            'isModal' => true,
        ]); ?>
    </div>
</form>
