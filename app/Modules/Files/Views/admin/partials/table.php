<?php
use App\Modules\Files\Services\FilesService;

$sortLink = function(string $col, string $label) use ($filters): string {
    $dir = ($filters['sort'] === $col && $filters['dir'] === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($filters['sort'] === $col) {
        $icon = ' <i class="fa-solid fa-sort-' . ($filters['dir'] === 'ASC' ? 'up' : 'down') . ' fa-xs"></i>';
    }
    $q = http_build_query(array_merge(array_filter($filters), ['sort' => $col, 'dir' => $dir]));
    return '<a href="' . route('files.admin.index') . '?' . $q . '" '
         . 'hx-get="' . route('files.admin.index') . '?' . $q . '" '
         . 'hx-target="#fm-admin-table" hx-push-url="true" '
         . 'class="text-decoration-none text-body">'
         . e($label) . $icon . '</a>';
};
?>

<?php if (empty($items)): ?>
  <p class="text-center text-muted py-4"><?= e(t('files.admin.empty')) ?></p>
<?php else: ?>

  <form id="fm-admin-bulk-form" method="POST" action="<?= route('files.admin.bulk_delete') ?>">
    <?= csrf_field() ?>

    <div id="fm-admin-bulk-bar" class="alert alert-warning align-items-center gap-3 py-2 mb-2">
      <span class="small"><strong id="fm-admin-selected-count">0</strong> <?= e(t('files.admin.selected_word')) ?></span>
      <button type="submit"
              class="btn btn-sm btn-warning ms-auto"
              data-app-confirm="<?= e(t('files.confirm.trash_selected')) ?>" data-app-confirm-label="<?= e(t('files.admin.move_to_trash')) ?>" data-app-confirm-class="btn-warning">
        <i class="fa-solid fa-trash me-1"></i><?= e(t('files.admin.move_to_trash')) ?>
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle table-sm fm-table">
        <thead class="table-light">
          <tr>
            <th class="fm-col-check">
              <input type="checkbox" class="form-check-input" id="fm-admin-check-all">
            </th>
            <th><?= $sortLink('original_name', t('files.admin.col_name')) ?></th>
            <th><?= $sortLink('extension', t('files.admin.col_type')) ?></th>
            <th><?= $sortLink('size_bytes', t('files.admin.col_size')) ?></th>
            <th><?= e(t('files.admin.col_uploaded_by')) ?></th>
            <th><?= $sortLink('visibility', t('files.admin.col_visibility')) ?></th>
            <th><?= $sortLink('created_at', t('files.admin.col_date')) ?></th>
            <th class="text-end"><?= e(t('files.admin.col_actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $file): ?>
            <tr>
              <td>
                <input type="checkbox"
                       name="ids[]"
                       value="<?= $file['id'] ?>"
                       class="form-check-input fm-admin-row-check">
              </td>
              <td>
                <a href="<?= route('files.show', ['id' => $file['id']]) ?>"
                   class="text-decoration-none fw-semibold text-truncate d-inline-block fm-name-link-admin"
                   title="<?= e($file['original_name']) ?>">
                  <?= e($file['original_name']) ?>
                </a>
                <?php if (!empty($file['folder'])): ?>
                  <div class="text-muted small">
                    <i class="fa-solid fa-folder fa-xs me-1"></i><?= e($file['folder']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?= e(strtoupper($file['extension'])) ?></span></td>
              <td class="text-nowrap text-muted small"><?= FilesService::humanSize((int)$file['size_bytes']) ?></td>
              <td class="text-muted small"><?= e($file['uploader_name'] ?? '—') ?></td>
              <td>
                <?php if ($file['visibility'] === 'internal'): ?>
                  <span class="badge fm-badge-internal"><i class="fa-solid fa-users fa-xs"></i></span>
                <?php else: ?>
                  <span class="badge fm-badge-private"><i class="fa-solid fa-lock fa-xs"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap text-muted small">
                <?= format_date($file['created_at'], 'short') ?>
              </td>
              <td class="text-end text-nowrap">
                <a href="<?= route('files.download', ['id' => $file['id']]) ?>"
                   class="btn btn-xs btn-outline-secondary"
                   title="<?= e(t('files.admin.download')) ?>" data-bs-toggle="tooltip">
                  <i class="fa-solid fa-download"></i>
                </a>
                <form method="POST"
                      action="<?= route('files.admin.bulk_delete') ?>"
                      class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="ids[]" value="<?= $file['id'] ?>">
                  <button type="submit"
                          class="btn btn-xs btn-outline-warning"
                          title="<?= e(t('files.admin.trash_tip')) ?>" data-bs-toggle="tooltip"
                          data-app-confirm="<?= e(t('files.confirm.trash_single')) ?>" data-app-confirm-label="<?= e(t('files.admin.move_to_trash')) ?>" data-app-confirm-class="btn-warning">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>

  <?php $view->include('partials/pagination', [
      'page'        => $page,
      'total_pages' => $total_pages,
      'total'       => $total,
      'routeName'   => 'files.admin.index',
      'hxTarget'    => '#fm-admin-table',
      'filters'     => array_filter($filters),
      'label'       => t('files.list.pagination_label'),
  ]); ?>

<?php endif; ?>
