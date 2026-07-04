<?php
// Dati: $event, $canEdit, $canDelete
$linkedTask = $linkedContext['task'] ?? null;
$linkedRecurrence = $linkedContext['ricorrenza'] ?? null;
$deleteConfirm = ($linkedTask || $linkedRecurrence)
    ? t('calendar.detail.delete_confirm_linked')
    : t('calendar.detail.delete_confirm');
?>

<div class="modal-header">
    <h5 class="modal-title" id="cal-modal-label">
        <?php if ($event['color']): ?>
        <span class="d-inline-block rounded-circle cal-color-dot-sm cal-color-dot-dynamic" style="--cal-dot-color:<?= e($event['color']) ?>;"></span>
        <?php endif; ?>
        <?= e($event['title']) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
</div>

<div class="modal-body">
    <div class="row g-3">

        <!-- Data/Ora -->
        <div class="col-sm-6">
            <div class="text-muted small"><?= e(t('calendar.detail.start')) ?></div>
            <div class="fw-semibold">
                <i class="fa-regular fa-clock me-1"></i>
                <?= e(format_date($event['start_datetime'], $event['all_day'] ? 'compact' : 'long')) ?>
            </div>
        </div>

        <?php if ($event['end_datetime']): ?>
        <div class="col-sm-6">
            <div class="text-muted small"><?= e(t('calendar.detail.end')) ?></div>
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
            <div class="text-muted small"><?= e(t('calendar.detail.location')) ?></div>
            <div><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= e($event['location']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($event['description']): ?>
        <div class="col-12">
            <div class="text-muted small"><?= e(t('calendar.detail.description')) ?></div>
            <div class="border rounded p-2 bg-body-tertiary small"><?= nl2br(e($event['description'])) ?></div>
        </div>
        <?php endif; ?>

        <div class="col-sm-6">
            <div class="text-muted small"><?= e(t('calendar.detail.visibility')) ?></div>
            <div>
                <?php if ($event['visibility'] === 'personal'): ?>
                    <span class="badge bg-secondary"><i class="fa-solid fa-lock me-1"></i><?= e(t('calendar.detail.personal')) ?></span>
                <?php elseif ($event['visibility'] === 'public'): ?>
                    <span class="badge bg-success"><i class="fa-solid fa-globe me-1"></i><?= e(t('calendar.detail.public')) ?></span>
                <?php else: ?>
                    <span class="badge bg-primary"><i class="fa-solid fa-users me-1"></i><?= e(t('calendar.detail.shared')) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="text-muted small"><?= e(t('calendar.detail.created_by')) ?></div>
            <div><i class="fa-regular fa-user me-1"></i><?= e($event['creator_name'] ?? '—') ?></div>
        </div>

        <?php if ($linkedTask || $linkedRecurrence): ?>
        <div class="col-12">
            <div class="text-muted small mb-2"><?= e(t('calendar.detail.links')) ?></div>
            <div class="d-flex flex-column gap-2">
                <?php if ($linkedTask): ?>
                <a href="<?= e(route('tasks.show', ['id' => $linkedTask['id']])) ?>"
                   class="text-decoration-none border rounded p-2 d-flex justify-content-between align-items-center gap-2">
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
                   class="text-decoration-none border rounded p-2 d-flex justify-content-between align-items-center gap-2">
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
<div class="modal-footer">
        <?php if ($canDelete): ?>
        <form method="POST"
              action="<?= e(route('calendar.destroy', ['id' => $event['id']])) ?>"
              class="me-auto"
              hx-delete="<?= e(route('calendar.destroy', ['id' => $event['id']])) ?>"
              hx-swap="none"
              hx-headers='{"X-CSRF-Token": "<?= e(csrf_token()) ?>"}'>
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-outline-danger"
                    data-app-confirm="<?= e($deleteConfirm) ?>"
                    data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
                    data-app-confirm-class="btn-danger"
                    data-bs-toggle="tooltip" data-bs-title="<?= e(t('calendar.detail.delete_tooltip')) ?>">
                <i class="fa-solid fa-trash me-1"></i><?= e(t('common.action.delete')) ?>
            </button>
        </form>
        <?php endif; ?>
        <?php if ($linkedTask): ?>
        <a href="<?= e(route('tasks.show', ['id' => $linkedTask['id']])) ?>" class="btn btn-outline-info">
            <i class="fa-solid fa-list-check me-1"></i><?= e(t('calendar.detail.open_task')) ?>
        </a>
        <?php endif; ?>
        <?php if ($linkedRecurrence): ?>
        <a href="<?= e(route('contacts.show', ['id' => $linkedRecurrence['contatto_id']]) . '#ct-ric-' . (int) $linkedRecurrence['id']) ?>" class="btn btn-outline-primary">
            <i class="fa-solid fa-address-book me-1"></i><?= e(t('calendar.detail.open_contact')) ?>
        </a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-warning"
                hx-get="<?= e(route('calendar.edit', ['id' => $event['id']])) ?>"
                hx-target="#cal-modal-content"
                hx-swap="innerHTML"
                data-bs-toggle="tooltip" data-bs-title="<?= e(t('calendar.detail.edit_tooltip')) ?>">
            <i class="fa-solid fa-pen me-1"></i><?= e(t('common.action.edit')) ?>
        </button>
        <?php endif; ?>
</div>
<?php endif; ?>

