<?php
/**
 * Checklist partial — usato in show.php e aggiornato via HTMX.
 *
 * Variabili: $checklist, $taskId, $canEdit
 */
?>
<?php if (!empty($errorMessage ?? null)): ?>
<div class="alert alert-danger alert-sm m-2 mb-0" role="alert">
    <?= e($errorMessage) ?>
</div>
<?php endif; ?>

<ul class="list-group list-group-flush att-checklist">
    <?php foreach ($checklist as $item): ?>
    <li class="list-group-item d-flex align-items-center gap-2 py-2 att-checklist-item <?= $item['is_done'] ? 'att-checklist-done' : '' ?>">
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-sm p-0 border-0 bg-transparent"
                hx-put="<?= e(route('tasks.checklist.toggle', ['id' => $taskId, 'cid' => $item['id']])) ?>"
                hx-target="#att-checklist-container"
                hx-swap="innerHTML"
            hx-headers='{"X-CSRF-Token": "<?= e(csrf_token()) ?>"}'>
            <i class="fa-<?= $item['is_done'] ? 'solid' : 'regular' ?> fa-<?= $item['is_done'] ? 'square-check text-success' : 'square text-muted' ?> fa-lg"></i>
        </button>
        <?php else: ?>
        <i class="fa-<?= $item['is_done'] ? 'solid' : 'regular' ?> fa-<?= $item['is_done'] ? 'square-check text-success' : 'square text-muted' ?> fa-lg"></i>
        <?php endif; ?>

        <span class="flex-grow-1 <?= $item['is_done'] ? 'text-decoration-line-through text-muted' : '' ?>">
            <?= e($item['text']) ?>
        </span>

        <?php if ($canEdit): ?>
        <form method="POST"
              action="<?= e(route('tasks.checklist.destroy', ['id' => $taskId, 'cid' => $item['id']])) ?>"
              class="d-inline"
              hx-delete="<?= e(route('tasks.checklist.destroy', ['id' => $taskId, 'cid' => $item['id']])) ?>"
              hx-target="#att-checklist-container"
              hx-swap="innerHTML"
              hx-headers='{"X-CSRF-Token": "<?= e(csrf_token()) ?>"}'>
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-sm text-danger p-0 border-0 bg-transparent opacity-50"
                    data-app-confirm="<?= e(t('tasks.checklist.remove_item')) ?>"
                    data-app-confirm-label="<?= e(t('common.action.remove')) ?>"
                    data-app-confirm-class="btn-danger"
                    title="<?= e(t('common.action.remove')) ?>" data-bs-toggle="tooltip">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </form>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>

    <?php if (empty($checklist)): ?>
    <li class="list-group-item text-muted text-center py-3 small">
        <i class="fa-solid fa-list-check me-1 opacity-50"></i><?= e(t('tasks.checklist.empty')) ?>
    </li>
    <?php endif; ?>
</ul>

<?php if ($canEdit): ?>
<div class="p-2 border-top">
        <form method="POST" action="<?= e(route('tasks.checklist.store', ['id' => $taskId])) ?>" class="d-flex gap-2"
          hx-post="<?= e(route('tasks.checklist.store', ['id' => $taskId])) ?>"
          hx-target="#att-checklist-container"
          hx-swap="innerHTML"
                    hx-vals='{"_token":"<?= e(csrf_token()) ?>"}'>
        <?= csrf_field() ?>
        <input type="text" name="text" class="form-control form-control-sm"
               placeholder="<?= e(t('tasks.checklist.add_placeholder')) ?>" required maxlength="500">
        <button type="submit" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-plus"></i>
        </button>
    </form>
</div>
<?php endif; ?>
