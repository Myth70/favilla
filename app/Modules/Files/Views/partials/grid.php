<?php
use App\Modules\Files\Services\FilesService;
?>

<?php if (empty($items)): ?>
  <div class="text-center py-5 text-muted">
    <i class="fa-solid fa-folder-open fa-3x mb-3"></i>
    <p><?= e(t('files.list.empty')) ?></p>
    <?php if (isModuleEnabled('Files')): ?>
      <a href="<?= route('files.upload') ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-cloud-arrow-up me-1"></i><?= e(t('files.list.upload_first')) ?>
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>

  <div class="fm-grid row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 mb-3">
    <?php foreach ($items as $file): ?>
      <?php $iconClass = FilesService::iconClass($file['extension'], $file['mime_type']); ?>
      <div class="col">
        <div class="fm-card card h-100 shadow-sm">

          <!-- Icon area -->
          <div class="card-body text-center pb-2 pt-3">
            <i class="fa-solid <?= $iconClass ?> fm-card-icon fa-3x mb-2"></i>

            <!-- Preview thumbnail for images -->
            <?php if (str_starts_with($file['mime_type'], 'image/')): ?>
              <?php $thumbUrl = route('files.preview', ['id' => (int) $file['id']]); ?>
              <div class="fm-card-thumb mt-1 mb-2">
                <img src="<?= e($thumbUrl) ?>"
                     alt="<?= e($file['original_name']) ?>"
                class="img-fluid rounded fm-card-thumb-img"
                     loading="lazy">
              </div>
            <?php endif; ?>

            <p class="fm-card-name mb-1 small fw-semibold text-truncate"
               title="<?= e($file['original_name']) ?>">
              <?= e($file['original_name']) ?>
            </p>
          </div>

          <!-- Meta -->
          <div class="card-footer bg-transparent border-top-0 pt-0">
            <div class="fm-card-meta d-flex justify-content-between align-items-center mb-2">
              <small class="text-muted"><?= FilesService::humanSize((int)$file['size_bytes']) ?></small>
              <?php if ($file['visibility'] === 'internal'): ?>
                <span class="badge fm-badge-internal"><i class="fa-solid fa-users fa-xs"></i></span>
              <?php else: ?>
                <span class="badge fm-badge-private"><i class="fa-solid fa-lock fa-xs"></i></span>
              <?php endif; ?>
            </div>

            <?php if ((int) ($file['version_count'] ?? 0) > 0 || (int) ($file['share_count'] ?? 0) > 0): ?>
            <div class="d-flex gap-1 flex-wrap mb-2">
              <?php if ((int) ($file['version_count'] ?? 0) > 0): ?>
              <span class="badge text-bg-light border text-body-secondary" data-bs-toggle="tooltip" title="<?= e(t('files.badge.versions_available')) ?>">
                <i class="fa-solid fa-clock-rotate-left me-1"></i><?= e(tc('files.badge.versions_count', (int) $file['version_count'])) ?>
              </span>
              <?php endif; ?>
              <?php if ((int) ($file['share_count'] ?? 0) > 0): ?>
              <span class="badge text-bg-light border text-body-secondary" data-bs-toggle="tooltip" title="<?= e(t('files.badge.shares_active')) ?>">
                <i class="fa-solid fa-share-nodes me-1"></i><?= e(tc('files.badge.shares_count', (int) $file['share_count'])) ?>
              </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="fm-card-actions d-flex gap-1">
              <a href="<?= route('files.show', ['id' => $file['id']]) ?>"
                 class="btn btn-sm btn-outline-primary flex-grow-1"
                 data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.view')) ?>">
                <i class="fa-solid fa-eye"></i>
              </a>
              <?php if (str_starts_with($file['mime_type'], 'image/') || $file['mime_type'] === 'application/pdf'): ?>
              <button type="button"
                      class="btn btn-sm btn-outline-info"
                      data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.preview')) ?>"
                      hx-get="<?= e(route('files.preview_modal', ['id' => $file['id']])) ?>"
                      hx-target="#fm-preview-modal-content">
                <i class="fa-solid fa-magnifying-glass"></i>
              </button>
              <?php endif; ?>
              <a href="<?= route('files.download', ['id' => $file['id']]) ?>"
                 class="btn btn-sm btn-outline-secondary"
                 data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.download')) ?>">
                <i class="fa-solid fa-download"></i>
              </a>
              <?php
              $currentUser = auth();
              $isOwner = (int)$file['created_by'] === (int)$currentUser['id'];
              $canDel  = $isOwner || has_permission('files.admin');
              if ($canDel): ?>
                <form method="POST"
                      action="<?= route('files.destroy', ['id' => $file['id']]) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit"
                          class="btn btn-sm btn-outline-danger"
                          data-bs-toggle="tooltip" title="<?= e(t('files.tooltip.delete')) ?>"
                          data-app-confirm="<?= e(t('files.confirm.delete')) ?>">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php $view->include('partials/pagination', [
      'page'        => $page,
      'total_pages' => $total_pages,
      'total'       => $total,
      'routeName'   => 'files.index',
      'hxTarget'    => '#files-container',
      'filters'     => array_filter($filters),
      'extraParams' => ['view' => 'grid'],
      'label'       => t('files.list.pagination_label'),
  ]); ?>

<?php endif; ?>
