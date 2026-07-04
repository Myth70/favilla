<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/files.css'); ?>

<?php $view->start('content'); ?>

<?php
// Hero module standardizzato (pagina secondaria user-facing)
$heroBtns  = '<a href="' . e(route('files.download', ['id' => $fileRecord['id']])) . '"';
$heroBtns .= ' class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="' . e(t('files.show.download_tip')) . '">';
$heroBtns .= '<i class="fa-solid fa-download"></i> ' . e(t('files.show.download')) . '</a>';
if (!empty($canEdit)) {
    $heroBtns .= ' <a href="' . e(route('files.edit', ['id' => $fileRecord['id']])) . '"';
    $heroBtns .= ' class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('files.show.edit_tip')) . '">';
    $heroBtns .= '<i class="fa-solid fa-pen"></i></a>';
}
$heroBtns .= ' <a href="' . e(route('files.index')) . '"';
$heroBtns .= ' class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('files.show.back_tip')) . '">';
$heroBtns .= '<i class="fa-solid fa-arrow-left"></i></a>';

$view->include('partials/pf-hero-module', [
    'moduleName'     => $fileRecord['original_name'] ?? t('files.title'),
    'moduleIcon'     => 'fa-solid fa-file',
    'moduleSubtitle' => e(strtoupper($fileRecord['extension'] ?? '')) . ' &middot; ' . e($sizeHr ?? ''),
    'moduleButtons'  => $heroBtns,
]);
?>

<div class="container-fluid">
<div class="row g-4">

  <!-- ── Preview col ─────────────────────────────────────────────── -->
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="app-card-icon"><i class="fa-solid fa-eye"></i></span>
        <span class="fw-semibold"><?= e(t('files.show.preview')) ?></span>
      </div>
      <div class="card-body fm-preview-container">

        <?php if ($previewType === 'image'): ?>
          <img src="<?= e($fileUrl) ?>"
               alt="<?= e($fileRecord['original_name']) ?>"
               class="fm-preview-image img-fluid rounded">

        <?php elseif ($previewType === 'pdf'): ?>
          <iframe src="<?= e($fileUrl) ?>"
                  class="fm-preview-pdf w-100">
          </iframe>

        <?php elseif ($previewType === 'text'): ?>
          <div class="fm-preview-text">
            <?php if ($textPreview !== null): ?>
                <pre class="border rounded p-3 small fm-preview-text-block"><?= e($textPreview) ?></pre>
            <?php else: ?>
              <div class="fm-preview-icon-lg text-center py-5">
                <i class="fa-solid fa-file-lines icon-2xl text-muted mb-3"></i>
                <p class="text-muted"><?= e(t('files.show.text_too_big')) ?></p>
              </div>
            <?php endif; ?>
          </div>

        <?php else: ?>
          <!-- Generic icon preview -->
          <?php
          $iconClass = match(true) {
              $fileRecord['extension'] === 'pdf'                    => 'fa-file-pdf fm-icon-pdf',
              in_array($fileRecord['extension'], ['doc','docx','odt'])  => 'fa-file-word fm-icon-word',
              in_array($fileRecord['extension'], ['xls','xlsx','ods'])  => 'fa-file-excel fm-icon-excel',
              in_array($fileRecord['extension'], ['ppt','pptx'])        => 'fa-file-powerpoint fm-icon-ppt',
              in_array($fileRecord['extension'], ['zip','rar','7z','gz']) => 'fa-file-zipper fm-icon-zip',
              default => 'fa-file fm-icon-other',
          };
          ?>
          <div class="fm-preview-icon-lg text-center py-5">
            <i class="fa-solid <?= $iconClass ?> icon-3xl mb-4"></i>
            <p class="text-muted mb-3"><?= e(strtoupper($fileRecord['extension'])) ?> — <?= e($sizeHr) ?></p>
            <a href="<?= route('files.download', ['id' => $fileRecord['id']]) ?>"
               class="btn btn-primary btn-lg">
              <i class="fa-solid fa-download me-2"></i><?= e(t('files.show.download')) ?>
            </a>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- ── Meta col ────────────────────────────────────────────────── -->
  <div class="col-lg-5">

    <!-- File info card -->
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="app-card-icon"><i class="fa-solid fa-circle-info"></i></span>
        <span class="fw-semibold"><?= e(t('files.show.info')) ?></span>
      </div>
      <div class="card-body">
        <dl class="fm-file-meta-table row g-2 mb-0">

          <dt class="col-5 text-muted small"><?= e(t('files.show.name')) ?></dt>
          <dd class="col-7 mb-0 text-break"><?= e($fileRecord['original_name']) ?></dd>

          <dt class="col-5 text-muted small"><?= e(t('files.show.size')) ?></dt>
          <dd class="col-7 mb-0"><?= e($sizeHr) ?></dd>

          <dt class="col-5 text-muted small"><?= e(t('files.show.type')) ?></dt>
          <dd class="col-7 mb-0"><code><?= e($fileRecord['mime_type']) ?></code></dd>

          <?php if (($fileRecord['folder'] ?? '') !== ''): ?>
          <dt class="col-5 text-muted small"><?= e(t('files.show.folder')) ?></dt>
          <dd class="col-7 mb-0">
            <a href="<?= route('files.index') ?>?folder=<?= urlencode($fileRecord['folder']) ?>"
               class="text-decoration-none">
              <i class="fa-solid fa-folder me-1"></i><?= e($fileRecord['folder']) ?>
            </a>
          </dd>
          <?php endif; ?>

          <dt class="col-5 text-muted small"><?= e(t('files.show.visibility')) ?></dt>
          <dd class="col-7 mb-0">
            <?php if ($fileRecord['visibility'] === 'internal'): ?>
              <span class="badge fm-badge-internal">
                <i class="fa-solid fa-users me-1"></i><?= e(t('files.badge.shared')) ?>
              </span>
            <?php else: ?>
              <span class="badge fm-badge-private">
                <i class="fa-solid fa-lock me-1"></i><?= e(t('files.badge.private')) ?>
              </span>
            <?php endif; ?>
          </dd>

          <dt class="col-5 text-muted small"><?= e(t('files.show.uploaded_by')) ?></dt>
          <dd class="col-7 mb-0"><?= e($fileRecord['uploader_name'] ?? '—') ?></dd>

          <dt class="col-5 text-muted small"><?= e(t('files.show.uploaded_at')) ?></dt>
          <dd class="col-7 mb-0"><?= format_date($fileRecord['created_at'], 'long') ?></dd>

        </dl>
      </div>
    </div>

    <!-- Description -->
    <?php if (!empty($fileRecord['description'])): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="app-card-icon"><i class="fa-solid fa-align-left"></i></span>
        <span class="fw-semibold"><?= e(t('files.show.description')) ?></span>
      </div>
      <div class="card-body"><p class="mb-0 small"><?= nl2br(e($fileRecord['description'])) ?></p></div>
    </div>
    <?php endif; ?>

    <!-- Tags -->
    <?php if (!empty($fileRecord['tags'])): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="app-card-icon"><i class="fa-solid fa-tag"></i></span>
        <span class="fw-semibold"><?= e(t('files.show.tags')) ?></span>
      </div>
      <div class="card-body">
        <?php foreach (explode(',', $fileRecord['tags']) as $tag): ?>
          <?php if (trim($tag) !== ''): ?>
            <span class="badge bg-secondary me-1"><?= e(trim($tag)) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
          <span class="app-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
          <span class="fw-semibold"><?= e(t('files.show.versions')) ?></span>
        </div>
        <form method="POST" action="<?= e(route('files.versions.snapshot', ['id' => $fileRecord['id']])) ?>" class="m-0">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-camera me-1"></i><?= e(t('files.show.snapshot_btn')) ?>
          </button>
        </form>
      </div>
      <div class="card-body">
        <?php if (!empty($versions)): ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th><?= e(t('files.show.v_version')) ?></th>
                  <th><?= e(t('files.show.v_created_at')) ?></th>
                  <th><?= e(t('files.show.v_by')) ?></th>
                  <th class="text-end"><?= e(t('files.show.v_actions')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($versions as $version): ?>
                <tr>
                  <td>#<?= e((string) $version['version_no']) ?></td>
                  <td><?= e(format_date($version['created_at'], 'short')) ?></td>
                  <td><?= e($version['created_by_name'] ?? '—') ?></td>
                  <td class="text-end">
                    <form method="POST" action="<?= e(route('files.versions.restore', ['id' => $fileRecord['id']])) ?>" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="version_no" value="<?= e((string) $version['version_no']) ?>">
                      <button type="submit" class="btn btn-outline-secondary btn-sm"
                              data-app-confirm="<?= e(t('files.confirm.restore_version', ['version' => $version['version_no']])) ?>">
                        <?= e(t('files.show.v_restore')) ?>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0"><?= e(t('files.show.no_snapshots')) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($canShare && !is_single_user()): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="app-card-icon"><i class="fa-solid fa-share-nodes"></i></span>
        <span class="fw-semibold"><?= e(t('files.show.sharing')) ?></span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <form method="POST" action="<?= e(route('files.share.user', ['id' => $fileRecord['id']])) ?>" class="d-flex flex-column gap-2">
              <?= csrf_field() ?>
              <label class="form-label mb-0"><?= e(t('files.show.share_user')) ?></label>
              <select name="user_id" class="form-select form-select-sm" required>
                <option value=""><?= e(t('files.show.select_user')) ?></option>
                <?php foreach ($shareUsers as $u): ?>
                  <option value="<?= e((string) $u['id']) ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="permission" class="form-select form-select-sm">
                <option value="view"><?= e(t('files.show.perm_view')) ?></option>
                <option value="edit"><?= e(t('files.show.perm_edit')) ?></option>
              </select>
              <button type="submit" class="btn btn-outline-primary btn-sm"><?= e(t('files.show.share_user_btn')) ?></button>
            </form>
          </div>
          <div class="col-md-6">
            <form method="POST" action="<?= e(route('files.share.role', ['id' => $fileRecord['id']])) ?>" class="d-flex flex-column gap-2">
              <?= csrf_field() ?>
              <label class="form-label mb-0"><?= e(t('files.show.share_role')) ?></label>
              <select name="role_id" class="form-select form-select-sm" required>
                <option value=""><?= e(t('files.show.select_role')) ?></option>
                <?php foreach ($shareRoles as $r): ?>
                  <option value="<?= e((string) $r['id']) ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="permission" class="form-select form-select-sm">
                <option value="view"><?= e(t('files.show.perm_view')) ?></option>
                <option value="edit"><?= e(t('files.show.perm_edit')) ?></option>
              </select>
              <button type="submit" class="btn btn-outline-primary btn-sm"><?= e(t('files.show.share_role_btn')) ?></button>
            </form>
          </div>
        </div>

        <?php if (!empty($shares)): ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th><?= e(t('files.show.s_type')) ?></th>
                  <th><?= e(t('files.show.s_target')) ?></th>
                  <th><?= e(t('files.show.s_permission')) ?></th>
                  <th class="text-end"><?= e(t('files.show.s_revoke')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($shares as $share): ?>
                <tr>
                  <td><?= e($share['target_type']) ?></td>
                  <td><?= e($share['target_type'] === 'user' ? ($share['user_name'] ?? t('files.show.s_user')) : ($share['role_name'] ?? t('files.show.s_role'))) ?></td>
                  <td><span class="badge bg-secondary"><?= e($share['permission']) ?></span></td>
                  <td class="text-end">
                    <form method="POST" action="<?= e(route('files.share.revoke', ['id' => $fileRecord['id']])) ?>" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="target_type" value="<?= e($share['target_type']) ?>">
                      <input type="hidden" name="target_id" value="<?= e((string) $share['target_id']) ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm"><?= e(t('files.show.s_revoke')) ?></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0"><?= e(t('files.show.no_shares')) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= route('files.download', ['id' => $fileRecord['id']]) ?>"
         class="btn btn-primary">
        <i class="fa-solid fa-download me-1"></i><?= e(t('files.show.download')) ?>
      </a>

      <?php if ($canEdit): ?>
        <a href="<?= route('files.edit', ['id' => $fileRecord['id']]) ?>"
           class="btn btn-outline-secondary">
          <i class="fa-solid fa-pen me-1"></i><?= e(t('files.show.edit')) ?>
        </a>
      <?php endif; ?>

      <?php if ($canDelete): ?>
        <form method="POST"
            action="<?= route('files.destroy', ['id' => $fileRecord['id']]) ?>"
              class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-outline-danger"
                  data-app-confirm="<?= e(t('files.confirm.delete_long')) ?>">
            <i class="fa-solid fa-trash me-1"></i><?= e(t('files.show.delete')) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>

<?php $view->end(); ?>
