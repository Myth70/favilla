<?php $view->layout('main'); ?>

<?php $view->start('content'); ?>

<?php
$linkedTask = $linkedContext['task'] ?? null;
$linkedRecurrence = $linkedContext['ricorrenza'] ?? null;
$deleteConfirm = ($linkedTask || $linkedRecurrence)
    ? t('calendar.detail.delete_confirm_linked')
    : t('calendar.detail.delete_confirm');
?>

<div class="container-fluid">

<?php
$calHeroButtons = '<a href="' . e(route('calendar.index')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('calendar.back_to_calendar')) . '"><i class="fa-solid fa-arrow-left"></i> ' . e(t('calendar.title')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => $event['title'] ?? t('calendar.fallback_event'),
    'moduleIcon'     => 'fa-solid fa-calendar-day',
    'moduleSubtitle' => t('calendar.detail_subtitle'),
    'moduleButtons'  => $calHeroButtons,
]);
?>

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card">
                <div class="card-header">
                    <?php if ($event['color']): ?>
                    <span class="d-inline-block rounded-circle me-2 cal-color-dot-lg cal-color-dot-dynamic" style="--cal-dot-color:<?= e($event['color']) ?>;"></span>
                    <?php endif; ?>
                    <h5 class="mb-0"><?= e($event['title']) ?></h5>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <!-- Data/Ora -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-0"><?= e(t('calendar.detail.start_date')) ?></label>
                            <div class="fw-semibold">
                                <i class="fa-regular fa-clock me-1"></i>
                                <?= e(format_date($event['start_datetime'], $event['all_day'] ? 'compact' : 'long')) ?>
                            </div>
                        </div>

                        <?php if ($event['end_datetime']): ?>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-0"><?= e(t('calendar.detail.end_date')) ?></label>
                            <div class="fw-semibold">
                                <i class="fa-regular fa-clock me-1"></i>
                                <?= e(format_date($event['end_datetime'], $event['all_day'] ? 'compact' : 'long')) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($event['all_day']): ?>
                        <div class="col-12">
                            <span class="badge bg-info"><i class="fa-solid fa-sun me-1"></i><?= e(t('calendar.detail.all_day')) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($event['location']): ?>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-0"><?= e(t('calendar.detail.location')) ?></label>
                            <div><i class="fa-solid fa-location-dot me-1"></i><?= e($event['location']) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($event['description']): ?>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-0"><?= e(t('calendar.detail.description')) ?></label>
                            <div class="border rounded p-2 bg-body-tertiary"><?= nl2br(e($event['description'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-0"><?= e(t('calendar.detail.visibility')) ?></label>
                            <div>
                                <?php if ($event['visibility'] === 'personal'): ?>
                                    <span class="badge bg-secondary"><i class="fa-solid fa-lock me-1"></i><?= e(t('calendar.detail.personal')) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-primary"><i class="fa-solid fa-users me-1"></i><?= e(t('calendar.detail.role')) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-0"><?= e(t('calendar.detail.created_by')) ?></label>
                            <div><?= e($event['creator_name'] ?? '—') ?></div>
                        </div>

                        <?php if ($linkedTask || $linkedRecurrence): ?>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-2"><?= e(t('calendar.detail.links')) ?></label>
                            <div class="d-flex flex-column gap-2">
                                <?php if ($linkedTask): ?>
                                <a href="<?= e(route('tasks.show', ['id' => $linkedTask['id']])) ?>"
                                   class="text-decoration-none border rounded p-3 d-flex justify-content-between align-items-center gap-2">
                                    <span>
                                        <span class="badge text-bg-info me-2"><i class="fa-solid fa-list-check me-1"></i><?= e(t('calendar.detail.link_task')) ?></span>
                                        <strong><?= e($linkedTask['title']) ?></strong>
                                    </span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-muted"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($linkedRecurrence): ?>
                                <?php $contactName = trim(($linkedRecurrence['nome'] ?? '') . ' ' . ($linkedRecurrence['cognome'] ?? '')); ?>
                                <a href="<?= e(route('contacts.show', ['id' => $linkedRecurrence['contatto_id']]) . '#ct-ric-' . (int) $linkedRecurrence['id']) ?>"
                                   class="text-decoration-none border rounded p-3 d-flex justify-content-between align-items-center gap-2">
                                    <span>
                                        <span class="badge text-bg-primary me-2"><i class="fa-solid fa-address-book me-1"></i><?= e(t('calendar.detail.link_contact')) ?></span>
                                        <strong><?= e($linkedRecurrence['titolo']) ?></strong>
                                        <span class="text-muted">· <?= e($contactName) ?></span>
                                    </span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-muted"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <?php if ($canEdit || $canDelete): ?>
                <div class="card-footer text-end">
                    <?php if ($linkedTask): ?>
                    <a href="<?= e(route('tasks.show', ['id' => $linkedTask['id']])) ?>"
                       class="btn btn-outline-info btn-sm">
                        <i class="fa-solid fa-list-check me-1"></i><?= e(t('calendar.detail.open_task')) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($linkedRecurrence): ?>
                    <a href="<?= e(route('contacts.show', ['id' => $linkedRecurrence['contatto_id']]) . '#ct-ric-' . (int) $linkedRecurrence['id']) ?>"
                       class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-address-book me-1"></i><?= e(t('calendar.detail.open_contact')) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <a href="<?= e(route('calendar.index') . '?edit=' . (int) $event['id']) ?>"
                       class="btn btn-warning btn-sm"
                       data-bs-toggle="tooltip" data-bs-title="<?= e(t('calendar.detail.edit_calendar_tooltip')) ?>">
                        <i class="fa-solid fa-pen me-1"></i><?= e(t('common.action.edit')) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <form method="POST" action="<?= e(route('calendar.destroy', ['id' => $event['id']])) ?>"
                          class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-danger btn-sm"
                                data-app-confirm="<?= e($deleteConfirm) ?>"
                                data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
                                data-bs-toggle="tooltip" data-bs-title="<?= e(t('calendar.detail.delete_tooltip')) ?>">
                            <i class="fa-solid fa-trash me-1"></i><?= e(t('common.action.delete')) ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<?php $view->end(); ?>

