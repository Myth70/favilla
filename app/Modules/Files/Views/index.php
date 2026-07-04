<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/files.css'); ?>
<?php $view->pushScript('js/bulk-select.js'); ?>
<?php $view->pushScript('js/files.js'); ?>

<?php $view->start('content'); ?>

<div class="container-fluid">
<div class="row g-4">

    <!-- ================================================================
         Hero strip — profilo + stats file
         ================================================================ -->
    <?php
    use App\Modules\Auth\Helpers\AvatarHelper;
    $fProfile   = $userProfile ?? [];
    $fStats     = $fileStats ?? ['total_files' => 0, 'total_size' => 0, 'total_size_hr' => '0 B'];
    $fAvatarUrl = AvatarHelper::url($fProfile['avatar'] ?? null);
    $fInitials  = AvatarHelper::initials($fProfile['name'] ?? 'U');
    $fFolderCnt = count($folders ?? []);
    $fHeroStats = [
        ['value' => (int) ($fStats['total_files'] ?? 0), 'label' => t('files.hero.files'),      'icon' => 'fa-solid fa-file',       'color' => 'primary'],
        ['value' => (int) $fFolderCnt,                   'label' => t('files.hero.folders'),    'icon' => 'fa-solid fa-folder',     'color' => 'warning'],
        ['value' => (string) ($fStats['total_size_hr'] ?? '0 B'), 'label' => t('files.hero.space_used'), 'icon' => 'fa-solid fa-hard-drive', 'color' => 'info'],
    ];
    $fScope = $filters['scope'] ?? 'recent';
    $fScopeLabels = [
        'recent' => t('files.scope.recent_label'),
        'mine'   => t('files.scope.mine_label'),
        'shared' => t('files.scope.shared_label'),
    ];
    $fScopeBase = array_diff_key($filters, ['scope' => '', 'page' => '']);
    $fScopeLink = static function (string $scope, string $label, string $icon, string $activeScope, array $params): string {
        $query = array_merge($params, ['scope' => $scope]);
        $qs = http_build_query($query);

        return '<a href="' . e(route('files.index')) . '?' . e($qs) . '" class="btn btn-sm ' . ($activeScope === $scope ? 'btn-primary' : 'btn-outline-secondary') . '">'
            . '<i class="fa-solid ' . e($icon) . ' me-1"></i>' . e($label)
            . '</a>';
    };
    ?>
    <div class="col-12">
        <?php
        $fSubtitle = !empty($filters['folder'])
            ? t('files.hero.folder_prefix', ['name' => $filters['folder']])
            : ($fProfile['name'] ?? '') . ' — ' . ($fScopeLabels[$fScope] ?? t('files.hero.visible_files'));
        $fButtons = isModuleEnabled('Files')
            ? '<a href="' . e(route('files.upload')) . '" class="btn btn-primary btn-sm text-nowrap"><i class="fa-solid fa-cloud-arrow-up me-1"></i>' . e(t('files.hero.upload_btn')) . '</a>'
            : '';
        $view->include('partials/pf-hero-user', [
            'userName'    => t('files.my_files'),
            'userSubtitle' => $fSubtitle,
            'userAvatar'  => $fAvatarUrl ?? null,
            'userInitials' => $fInitials,
            'userStats'   => $fHeroStats,
            'userButtons' => $fButtons,
        ]);
        ?>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2">
                    <?= $fScopeLink('recent', t('files.scope.recent'), 'clock', $fScope, $fScopeBase) ?>
                    <?= $fScopeLink('mine', t('files.scope.mine'), 'user', $fScope, $fScopeBase) ?>
                    <?= $fScopeLink('shared', t('files.scope.shared'), 'share-nodes', $fScope, $fScopeBase) ?>
                </div>
                <small class="text-muted"><?= e(t('files.scope.hint')) ?></small>
            </div>
        </div>
    </div>

    <!-- ================================================================
         Folder sidebar (desktop) — col-md-3
         ================================================================ -->
    <div class="col-md-3 d-none d-md-block">
        <?php $view->include('Files/Views/partials/folder_sidebar', get_defined_vars()); ?>
    </div>

    <!-- ================================================================
         Folder selector (mobile only)
         ================================================================ -->
    <div class="col-12 d-md-none">
        <select class="form-select form-select-sm"
                id="fm-folder-select-mobile"
                data-fm-folder-mobile="1">
            <option value="" <?= empty($filters['folder']) ? 'selected' : '' ?>>
                <?= e(t('files.list.all_folders')) ?>
            </option>
            <?php foreach ($folders ?? [] as $f): ?>
            <option value="<?= e($f) ?>" <?= ($filters['folder'] ?? '') === $f ? 'selected' : '' ?>>
                <?= e($f) ?>
                <?php if (($folderCounts[$f] ?? 0) > 0): ?>
                    (<?= (int) $folderCounts[$f] ?>)
                <?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- ================================================================
         File list card — col-md-9
         ================================================================ -->
    <div class="col-12 col-md-9">
        <div class="card shadow-sm">

            <!-- Toolbar nel card-header -->
            <div class="card-header">
                <div class="fm-toolbar d-flex flex-wrap gap-2 align-items-center">

                    <!-- Search -->
                    <div class="flex-grow-1 fm-toolbar-search">
                        <input type="search"
                               name="search"
                               class="form-control form-control-sm"
                               placeholder="<?= e(t('files.list.search_ph')) ?>"
                               value="<?= e($filters['search']) ?>"
                               data-filter
                               hx-get="<?= route('files.index') ?>"
                               hx-trigger="keyup changed delay:400ms, search"
                               hx-target="#files-container"
                               hx-push-url="true"
                               hx-include="[data-filter]">
                    </div>

                    <!-- MIME group filter -->
                    <select name="mime_group"
                            class="form-select form-select-sm fm-filter-auto"
                            data-filter
                            hx-get="<?= route('files.index') ?>"
                            hx-trigger="change"
                            hx-target="#files-container"
                            hx-push-url="true"
                            hx-include="[data-filter]">
                        <option value=""><?= e(t('files.list.all_types')) ?></option>
                        <option value="image"    <?= $filters['mime_group'] === 'image'    ? 'selected' : '' ?>><?= e(t('files.list.images')) ?></option>
                        <option value="document" <?= $filters['mime_group'] === 'document' ? 'selected' : '' ?>><?= e(t('files.list.documents')) ?></option>
                        <option value="archive"  <?= $filters['mime_group'] === 'archive'  ? 'selected' : '' ?>><?= e(t('files.list.archives')) ?></option>
                        <option value="text"     <?= $filters['mime_group'] === 'text'     ? 'selected' : '' ?>><?= e(t('files.list.text')) ?></option>
                    </select>

                    <!-- Visibility filter -->
                    <select name="visibility"
                            class="form-select form-select-sm fm-filter-auto"
                            data-filter
                            hx-get="<?= route('files.index') ?>"
                            hx-trigger="change"
                            hx-target="#files-container"
                            hx-push-url="true"
                            hx-include="[data-filter]">
                        <option value=""><?= e(t('files.list.all')) ?></option>
                        <option value="private"  <?= $filters['visibility'] === 'private'  ? 'selected' : '' ?>><?= e(t('files.list.private_plural')) ?></option>
                        <option value="internal" <?= $filters['visibility'] === 'internal' ? 'selected' : '' ?>><?= e(t('files.list.shared_plural')) ?></option>
                    </select>

                    <!-- Hidden inputs per filtri persistenti -->
                    <input type="hidden" name="sort"   value="<?= e($filters['sort']) ?>"   data-filter>
                    <input type="hidden" name="dir"    value="<?= e($filters['dir']) ?>"    data-filter>
                    <input type="hidden" name="scope"  value="<?= e($filters['scope']) ?>"  data-filter>
                    <input type="hidden" name="folder" value="<?= e($filters['folder']) ?>" data-filter id="fm-folder-input">

                    <!-- View toggle -->
                    <div class="ms-auto d-flex gap-2">
                        <div class="fm-view-toggle btn-group btn-group-sm" role="group">
                            <button type="button"
                                    class="btn btn-outline-secondary <?= $viewMode === 'grid' ? 'active' : '' ?>"
                                    data-fm-view="grid"
                                    data-bs-toggle="tooltip"
                                    title="<?= e(t('files.list.view_grid')) ?>"
                                    aria-label="<?= e(t('files.list.view_grid')) ?>">
                                <i class="fa-solid fa-grip"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary <?= $viewMode === 'list' ? 'active' : '' ?>"
                                    data-fm-view="list"
                                    data-bs-toggle="tooltip"
                                    title="<?= e(t('files.list.view_list')) ?>"
                                    aria-label="<?= e(t('files.list.view_list')) ?>">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Card body: file list -->
            <div class="card-body p-3">
                <input type="hidden" name="view" id="fm-view-input" value="<?= e($viewMode) ?>" data-filter>
                <div id="files-container">
                    <?php if ($viewMode === 'list'): ?>
                        <?php $view->include('Files/Views/partials/list_table', get_defined_vars()); ?>
                    <?php else: ?>
                        <?php $view->include('Files/Views/partials/grid', get_defined_vars()); ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>
</div>

<!-- ── Preview modal (contenuto caricato via HTMX) ──────────────────────── -->
<div class="modal fade" id="fm-preview-modal" tabindex="-1"
     aria-labelledby="fm-preview-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" id="fm-preview-modal-content">
      <!-- Caricato dinamicamente via hx-get="/files/{id}/preview-modal" -->
    </div>
  </div>
</div>

<!-- Delete folder confirmation modal (persiste anche dopo HTMX swap della sidebar) -->
<div class="modal fade" id="fm-delete-confirm-modal" tabindex="-1"
     aria-labelledby="fm-delete-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fm-delete-modal-label">
                    <i class="fa-solid fa-triangle-exclamation text-danger"></i><?= e(t('files.folder.delete_title')) ?>
                </h5>
        <button type="button" class="btn-close btn-sm"
                data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
      </div>
            <div class="modal-body">
        <?= e(t('files.folder.delete_q_pre')) ?> <strong id="fm-delete-folder-name"></strong><?= e(t('files.folder.delete_q_post')) ?><br>
        <?= e(t('files.folder.delete_files_moved')) ?>
      </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
                <button type="button" class="btn btn-danger"
                id="fm-delete-confirm-btn">
          <i class="fa-solid fa-trash me-1"></i><?= e(t('files.folder.delete')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<?php $view->end(); ?>
