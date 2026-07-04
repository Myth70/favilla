<?php use App\Modules\Files\Services\FilesService; ?>

<?php if (empty($items)): ?>
  <div class="text-center py-5 text-muted">
    <i class="fa-solid fa-trash-can fa-3x mb-3"></i>
    <p><?= e(t('files.admin.trash_empty')) ?></p>
  </div>
<?php else: ?>

  <!-- Bulk purge form -->
  <form id="fm-trash-bulk-form" method="POST" action="<?= route('files.admin.bulk_purge') ?>">
    <?= csrf_field() ?>

    <div id="fm-trash-bulk-bar" class="alert alert-danger align-items-center gap-3 py-2 mb-2">
      <span class="small"><strong id="fm-trash-selected-count">0</strong> <?= e(t('files.admin.selected_word')) ?></span>
      <button type="submit"
              class="btn btn-sm btn-danger ms-auto fm-purge-btn"
              data-app-confirm="<?= e(t('files.confirm.purge')) ?>" data-app-confirm-label="<?= e(t('files.admin.purge')) ?>">
        <i class="fa-solid fa-trash me-1"></i><?= e(t('files.admin.purge_selected')) ?>
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle table-sm fm-table">
        <thead class="table-light">
          <tr>
            <th class="fm-col-check">
              <input type="checkbox" class="form-check-input" id="fm-trash-check-all">
            </th>
            <th><?= e(t('files.admin.tcol_name')) ?></th>
            <th><?= e(t('files.admin.tcol_type')) ?></th>
            <th><?= e(t('files.admin.tcol_size')) ?></th>
            <th><?= e(t('files.admin.tcol_uploaded_by')) ?></th>
            <th><?= e(t('files.admin.tcol_deleted_at')) ?></th>
            <th class="text-end"><?= e(t('files.admin.tcol_actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $file): ?>
            <tr class="table-warning">
              <td>
                <input type="checkbox"
                       name="ids[]"
                       value="<?= $file['id'] ?>"
                       class="form-check-input fm-trash-row-check">
              </td>
              <td>
                <span class="fw-semibold"><?= e($file['original_name']) ?></span>
                <?php if (!empty($file['folder'])): ?>
                  <div class="text-muted small"><i class="fa-solid fa-folder fa-xs me-1"></i><?= e($file['folder']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?= e(strtoupper($file['extension'])) ?></span></td>
              <td class="text-muted small text-nowrap"><?= FilesService::humanSize((int)$file['size_bytes']) ?></td>
              <td class="text-muted small"><?= e($file['uploader_name'] ?? '—') ?></td>
              <td class="text-muted small text-nowrap"><?= format_date($file['deleted_at'], 'short') ?></td>
              <td class="text-end text-nowrap">

                <!-- Restore -->
                <form method="POST"
                      action="<?= route('files.admin.restore', ['id' => $file['id']]) ?>"
                      class="d-inline">
                  <?= csrf_field() ?>
                  <button type="submit"
                          class="btn btn-xs btn-outline-success"
                          data-bs-toggle="tooltip" title="<?= e(t('files.admin.restore')) ?>">
                    <i class="fa-solid fa-rotate-left"></i>
                  </button>
                </form>

                <!-- Hard delete -->
                <form method="POST"
                      action="<?= route('files.admin.purge', ['id' => $file['id']]) ?>"
                      class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit"
                          class="btn btn-xs btn-danger fm-purge-btn"
                          data-bs-toggle="tooltip" title="<?= e(t('files.admin.purge')) ?>"
                          data-app-confirm="<?= e(t('files.confirm.purge')) ?>" data-app-confirm-label="<?= e(t('files.admin.purge')) ?>">
                    <i class="fa-solid fa-fire"></i>
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
      'routeName'   => 'files.admin.trash',
      'hxTarget'    => '#fm-trash-table',
      'filters'     => array_filter($filters),
      'label'       => t('files.admin.pagination_trash_label'),
  ]); ?>

<?php endif; ?>
