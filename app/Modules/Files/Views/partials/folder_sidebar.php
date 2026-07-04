<nav class="fm-folder-tree card shadow-sm p-0" id="fm-folder-nav">
  <header class="app-section-subhead d-flex align-items-center fm-folder-head">
    <i class="fa-solid fa-folder-tree"></i>
    <span><?= e(t('files.folder.title')) ?></span>
    <?php if (isModuleEnabled('Files')): ?>
    <button type="button" id="fm-new-folder-btn"
            class="btn btn-link btn-sm p-0 ms-auto fm-folder-icon-btn"
            data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('files.folder.new')) ?>">
      <i class="fa-solid fa-folder-plus fa-sm"></i>
    </button>
    <?php endif; ?>
  </header>

  <!-- Inline create form (hidden by default) -->
  <?php if (isModuleEnabled('Files')): ?>
  <div id="fm-new-folder-form" class="px-2 pt-2 pb-1 border-bottom d-none">
    <form hx-post="<?= route('files.folders.store') ?>"
          hx-target="#fm-folder-nav"
          hx-swap="outerHTML">
      <?= csrf_field() ?>
      <div class="input-group input-group-sm">
        <input type="text" name="folder" id="fm-new-folder-input"
               class="form-control form-control-sm"
               placeholder="<?= e(t('files.folder.name_ph')) ?>" required maxlength="200" autocomplete="off">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-check"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="fm-cancel-folder">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <ul class="list-unstyled mb-0 p-2">

    <!-- Root / All files -->
    <li class="fm-folder-item <?= ($filters['folder'] ?? '') === '' ? 'active' : '' ?>">
      <a href="<?= route('files.index') ?>"
         class="d-flex align-items-center gap-2 rounded px-2 py-1 text-decoration-none text-body">
        <i class="fa-solid fa-house-chimney fa-fw fm-folder-icon text-primary"></i>
        <span class="small"><?= e(t('files.folder.all_files')) ?></span>
      </a>
    </li>

    <?php if (!empty($folders)): ?>
      <li class="py-1"><hr class="my-1"></li>
      <?php foreach ($folders as $folder): ?>
        <?php $active = ($filters['folder'] ?? '') === $folder; ?>
        <li class="fm-folder-item <?= $active ? 'active' : '' ?>" data-folder="<?= e($folder) ?>">

          <!-- Normal view row -->
          <div class="fm-folder-row d-flex align-items-center gap-1 rounded px-2 py-1 <?= $active ? 'fm-folder-row-active' : '' ?>">
            <a href="<?= route('files.index') ?>?folder=<?= urlencode($folder) ?>"
               class="d-flex align-items-center gap-2 flex-grow-1 text-decoration-none text-body min-w-0"
               title="<?= e($folder) ?>">
              <i class="fa-solid fa-folder fa-fw fm-folder-icon <?= $active ? 'text-warning' : 'text-muted' ?>"></i>
              <span class="small text-truncate fm-folder-name">
                <?= e(basename($folder) ?: $folder) ?>
              </span>
              <?php $cnt = ($folderCounts ?? [])[$folder] ?? 0; ?>
              <?php if ($cnt > 0): ?>
                <span class="badge bg-secondary rounded-pill ms-1 fm-folder-count"><?= $cnt ?></span>
              <?php endif; ?>
            </a>
            <span class="fm-folder-actions d-flex gap-1 ms-auto flex-shrink-0">
              <button type="button"
                      class="btn btn-link btn-sm p-0 text-muted fm-rename-btn"
                      data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('files.folder.rename')) ?>"
                      data-folder="<?= e($folder) ?>">
                <i class="fa-solid fa-pen fa-xs"></i>
              </button>
              <form hx-post="<?= route('files.folders.destroy') ?>"
                    hx-swap="none"
                  class="fm-inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <input type="hidden" name="folder" value="<?= e($folder) ?>">
                <button type="button"
                        class="btn btn-link btn-sm p-0 text-danger fm-delete-folder-btn"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('files.folder.delete')) ?>"
                        data-folder-label="<?= e(basename($folder) ?: $folder) ?>">
                  <i class="fa-solid fa-trash fa-xs"></i>
                </button>
              </form>
            </span>
          </div>

          <!-- Inline rename form (hidden by default) -->
          <div class="fm-rename-form d-none px-1 pb-1">
            <form hx-post="<?= route('files.folders.rename') ?>"
                  hx-swap="none">
              <?= csrf_field() ?>
              <input type="hidden" name="_method" value="PUT">
              <input type="hidden" name="old_folder" value="<?= e($folder) ?>">
              <div class="input-group input-group-sm">
                <input type="text" name="new_folder"
                       class="form-control form-control-sm"
                       value="<?= e($folder) ?>" required maxlength="200" autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="fa-solid fa-check"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm fm-rename-cancel">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </form>
          </div>

        </li>
      <?php endforeach; ?>
    <?php endif; ?>

  </ul>
</nav>
