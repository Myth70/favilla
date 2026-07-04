<?php
use App\Modules\Files\Services\FilesService;

$sortLink = function(string $col, string $label) use ($filters): string {
    $dir = ($filters['sort'] === $col && $filters['dir'] === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($filters['sort'] === $col) {
        $icon = ' <i class="fa-solid fa-sort-' . ($filters['dir'] === 'ASC' ? 'up' : 'down') . ' fa-xs"></i>';
    }
    $q = http_build_query(array_merge(array_filter($filters), ['sort' => $col, 'dir' => $dir, 'view' => 'list']));
        return '<a href="' . e(route('files.index')) . '?' . $q . '" '
          . 'hx-get="' . e(route('files.index')) . '?' . $q . '" '
         . 'hx-target="#files-container" hx-push-url="true" '
         . 'class="text-decoration-none text-body">'
         . e($label) . $icon . '</a>';
};
?>

<?php if (empty($items)): ?>
  <div class="text-center py-5 text-muted">
    <i class="fa-solid fa-folder-open fa-3x mb-3"></i>
    <p><?= e(t('files.list.empty')) ?></p>
    <?php if (isModuleEnabled('Files')): ?>
      <a href="<?= e(route('files.upload')) ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-cloud-arrow-up me-1"></i><?= e(t('files.list.upload_first')) ?>
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>

  <!-- Bulk form wrapper -->
  <form id="fm-bulk-form" method="POST" action="<?= e(route('files.bulk_destroy')) ?>">
    <?= csrf_field() ?>

    <!-- Bulk action bar (shown by BulkSelect when count > 0 via .active class) -->
    <div id="fm-bulk-bar" class="alert alert-warning align-items-center gap-3 py-2 mb-3">
      <span class="small"><strong id="fm-selected-count">0</strong> <?= e(t('files.list.selected_word')) ?></span>
      <a id="fm-zip-btn" href="#" class="btn btn-sm btn-outline-primary ms-auto"
         data-fm-bulk-zip="1"
         data-fm-zip-url="<?= e(route('files.download_zip')) ?>">
        <i class="fa-solid fa-file-zipper me-1"></i><?= e(t('files.list.download_zip')) ?>
      </a>
      <button type="submit" class="btn btn-sm btn-warning">
        <i class="fa-solid fa-trash me-1"></i><?= e(t('files.list.delete_selected')) ?>
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle fm-table">
        <thead class="table-light">
          <tr>
            <th class="fm-col-check">
              <input type="checkbox" class="form-check-input" id="fm-check-all">
            </th>
            <th><?= $sortLink('original_name', t('files.list.col_name')) ?></th>
            <th><?= $sortLink('extension', t('files.list.col_type')) ?></th>
            <th><?= $sortLink('size_bytes', t('files.list.col_size')) ?></th>
            <th><?= $sortLink('folder', t('files.list.col_folder')) ?></th>
            <th><?= $sortLink('visibility', t('files.list.col_visibility')) ?></th>
            <th><?= $sortLink('created_at', t('files.list.col_date')) ?></th>
            <th class="text-end"><?= e(t('files.list.col_actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $file): ?>
            <?php
            $currentUser = auth();
            $isOwner = (int)$file['created_by'] === (int)$currentUser['id'];
            $canDel  = $isOwner || has_permission('files.admin');
            ?>
            <tr>
              <td>
                <input type="checkbox"
                       name="ids[]"
                       value="<?= $file['id'] ?>"
                       class="form-check-input fm-row-check"
                       <?= !$canDel ? 'disabled' : '' ?>>
              </td>
              <td>
                <a href="<?= e(route('files.show', ['id' => $file['id']])) ?>"
                   class="text-decoration-none fw-semibold text-truncate d-inline-block fm-name-link"
                   title="<?= e($file['original_name']) ?>">
                  <?= e($file['original_name']) ?>
                </a>
                <?php if ((int) ($file['version_count'] ?? 0) > 0 || (int) ($file['share_count'] ?? 0) > 0): ?>
                <div class="d-flex gap-1 mt-1 flex-wrap">
                  <?php if ((int) ($file['version_count'] ?? 0) > 0): ?>
                  <span class="badge text-bg-light border text-body-secondary" data-bs-toggle="tooltip" title="<?= e(t('files.badge.versions_available')) ?>">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i><?= (int) $file['version_count'] ?>
                  </span>
                  <?php endif; ?>
                  <?php if ((int) ($file['share_count'] ?? 0) > 0): ?>
                  <span class="badge text-bg-light border text-body-secondary" data-bs-toggle="tooltip" title="<?= e(t('files.badge.shares_active')) ?>">
                    <i class="fa-solid fa-share-nodes me-1"></i><?= (int) $file['share_count'] ?>
                  </span>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?= e(strtoupper($file['extension'])) ?></span></td>
              <td class="text-nowrap text-muted small"><?= FilesService::humanSize((int)$file['size_bytes']) ?></td>
              <td class="text-muted small text-truncate fm-folder-cell">
                <?= ($file['folder'] ?? '') !== '' ? e($file['folder']) : '<span class="text-muted">—</span>' ?>
              </td>
              <td>
                <?php if ($file['visibility'] === 'internal'): ?>
                  <span class="badge fm-badge-internal"><i class="fa-solid fa-users fa-xs me-1"></i><?= e(t('files.badge.shared')) ?></span>
                <?php else: ?>
                  <span class="badge fm-badge-private"><i class="fa-solid fa-lock fa-xs me-1"></i><?= e(t('files.badge.private')) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap text-muted small">
                <?= format_date($file['created_at'], 'short') ?>
              </td>
              <td class="text-end text-nowrap">
                <?php if (str_starts_with($file['mime_type'], 'image/') || $file['mime_type'] === 'application/pdf'): ?>
                <button type="button"
                        class="btn btn-xs btn-outline-info"
                        data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.preview')) ?>"
                        hx-get="<?= e(route('files.preview_modal', ['id' => $file['id']])) ?>"
                        hx-target="#fm-preview-modal-content">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                <?php endif; ?>
                <a href="<?= e(route('files.download', ['id' => $file['id']])) ?>"
                   class="btn btn-xs btn-outline-secondary"
                   data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.download')) ?>">
                  <i class="fa-solid fa-download"></i>
                </a>
                <?php if ($isOwner || has_permission('files.admin')): ?>
                  <a href="<?= e(route('files.edit', ['id' => $file['id']])) ?>"
                     class="btn btn-xs btn-outline-secondary"
                     data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.edit')) ?>">
                    <i class="fa-solid fa-pen"></i>
                  </a>
                <?php endif; ?>
                <?php if ($canDel): ?>
                  <form method="POST"
                        action="<?= e(route('files.destroy', ['id' => $file['id']])) ?>"
                        class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit"
                            class="btn btn-xs btn-outline-danger"
                            data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.delete')) ?>"
                            data-app-confirm="<?= e(t('files.confirm.delete')) ?>">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
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
      'routeName'   => 'files.index',
      'hxTarget'    => '#files-container',
      'filters'     => array_filter($filters),
      'extraParams' => ['view' => 'list'],
      'label'       => t('files.list.pagination_label'),
  ]); ?>

<?php endif; ?>
