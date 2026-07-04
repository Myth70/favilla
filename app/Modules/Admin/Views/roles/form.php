<?php
/**
 * Admin role create/edit form.
 * Variables: $view, $role (null = create), $errors, $old, $grouped, $assignedIds, $userCount
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');

$isEdit      = $role !== null;
$grouped     = $grouped ?? [];
$assignedIds = $assignedIds ?? [];
$userCount   = $userCount ?? 0;
$action      = $isEdit
    ? route('admin.roles.update', ['id' => $role['id']])
    : route('admin.roles.store');

$isSystemRole = $isEdit && ($role['slug'] ?? '') === 'admin';

// Pre-compute per-module selected counts + totals
$moduleCounts    = [];
$totalPerms      = 0;
$totalAssigned   = 0;
$modulesCovered  = 0;
if ($isEdit) {
    foreach ($grouped as $module => $perms) {
        $sel = 0;
        foreach ($perms as $perm) {
            if (isset($assignedIds[$perm['id']])) $sel++;
        }
        $moduleCounts[$module] = ['selected' => $sel, 'total' => count($perms)];
        $totalPerms    += count($perms);
        $totalAssigned += $sel;
        if ($sel > 0) $modulesCovered++;
    }
}

// Module icon mapping for richer visual cues
$moduleIconMap = [
    'admin'       => 'fa-shield-halved',
    'auth'        => 'fa-key',
    'utenti'      => 'fa-users',
    'users'       => 'fa-users',
    'ruoli'       => 'fa-user-tag',
    'roles'       => 'fa-user-tag',
    'clienti'     => 'fa-address-book',
    'contacts'    => 'fa-address-book',
    'tasks'    => 'fa-list-check',
    'calendar'  => 'fa-calendar-days',
    'files'       => 'fa-folder-open',
    'home'        => 'fa-house',
    'profile'     => 'fa-user',
    'settings'    => 'fa-gear',
    'mail'        => 'fa-envelope',
    'backup'      => 'fa-database',
    'audit'       => 'fa-clipboard-list',
    'scheduler'   => 'fa-clock',
    'notifications' => 'fa-bell',
];
function admin_role_module_icon(string $module, array $map): string {
    $key = strtolower(trim($module));
    if (isset($map[$key])) return $map[$key];
    foreach ($map as $k => $icon) {
        if (str_contains($key, $k)) return $icon;
    }
    return 'fa-cube';
}

// Hero buttons
$heroButtons = '<a href="' . e(route('admin.roles.index')) . '" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('admin.roles.back_tip')) . '"><i class="fa-solid fa-arrow-left me-1"></i> ' . e(t('admin.roles.back')) . '</a>';

if ($isEdit) {
    $heroSubtitleParts = [
        '<code class="adm-role-slug-inline">' . e($role['slug']) . '</code>',
    ];
    if ($isSystemRole) {
        $heroSubtitleParts[] = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-lock me-1"></i>' . e(t('admin.roles.system_role')) . '</span>';
    }
    if ($totalPerms > 0) {
        $heroSubtitleParts[] = '<span class="adm-hero-stat"><i class="fa-solid fa-shield-halved"></i> ' . e(t('admin.roles.perms_count', ['assigned' => $totalAssigned, 'total' => $totalPerms])) . '</span>';
    }
    $heroSubtitleParts[] = '<span class="adm-hero-stat"><i class="fa-solid fa-users"></i> ' . e(tc('admin.roles.users_count', (int) $userCount)) . '</span>';
    $heroSubtitle = implode(' ', $heroSubtitleParts);
} else {
    $heroSubtitle = null;
}
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => $isEdit ? 'fa-solid fa-user-tag' : 'fa-solid fa-plus-circle',
        'adminTitle'    => $isEdit ? e($role['name']) : t('admin.roles.title_new'),
        'adminSubtitle' => $heroSubtitle,
        'adminButtons'  => $heroButtons,
    ]); ?>

    <div class="row g-3 align-items-start">

        <!-- Dati ruolo -->
        <div class="<?= $isEdit ? 'col-xl-4' : 'col-xl-6 col-xxl-5' ?> adm-sticky-col">

            <?php if ($isEdit): ?>
            <div class="card adm-card mb-3 adm-role-meta-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-uppercase fw-semibold adm-meta-label"><?= e(t('admin.roles.overview')) ?></span>
                        <?php if ($isSystemRole): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" data-bs-toggle="tooltip" title="<?= e(t('admin.roles.system_tip')) ?>">
                                <i class="fa-solid fa-lock me-1"></i><?= e(t('admin.roles.system')) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-body-secondary border border-secondary-subtle"><?= e(t('admin.roles.custom')) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 adm-role-stats">
                        <div class="col-6">
                            <div class="adm-stat-box">
                                <div class="adm-stat-value"><?= $totalAssigned ?><span class="adm-stat-sep">/<?= $totalPerms ?></span></div>
                                <div class="adm-stat-label"><i class="fa-solid fa-shield-halved me-1"></i><?= e(t('admin.roles.stat_perms')) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="adm-stat-box">
                                <div class="adm-stat-value"><?= (int) $userCount ?></div>
                                <div class="adm-stat-label"><i class="fa-solid fa-users me-1"></i><?= $userCount === 1 ? e(t('admin.roles.stat_user_one')) : e(t('admin.roles.stat_user_many')) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="adm-stat-box">
                                <div class="adm-stat-value"><?= $modulesCovered ?><span class="adm-stat-sep">/<?= count($grouped) ?></span></div>
                                <div class="adm-stat-label"><i class="fa-solid fa-cubes me-1"></i><?= e(t('admin.roles.stat_modules')) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="adm-stat-box">
                                <div class="adm-stat-value adm-stat-date">
                                    <?= !empty($role['updated_at']) ? e(format_date($role['updated_at'], 'compact')) : '—' ?>
                                </div>
                                <div class="adm-stat-label"><i class="fa-solid fa-clock-rotate-left me-1"></i><?= e(t('admin.roles.stat_updated')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card adm-card">
                <div class="card-header adm-card-header py-2">
                    <strong><i class="fa-solid fa-pen-ruler me-1"></i> <?= e(t('admin.roles.data_card')) ?></strong>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e($action) ?>">
                        <?= csrf_field() ?>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="_method" value="PUT">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label"><?= e(t('admin.roles.name')) ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name"
                                   value="<?= e($old['name'] ?? $role['name'] ?? '') ?>"
                                   class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                   <?= $isSystemRole ? 'readonly' : '' ?>
                                   autocomplete="off">
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isEdit): ?>
                        <div class="mb-3">
                            <label class="form-label"><?= e(t('admin.roles.slug')) ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text app-input-icon"><i class="fa-solid fa-hashtag"></i></span>
                                <input type="text" name="slug"
                                       value="<?= e($old['slug'] ?? '') ?>"
                                       class="form-control font-monospace <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                                       placeholder="<?= e(t('admin.roles.slug_ph')) ?>"
                                       autocomplete="off">
                                <?php if (isset($errors['slug'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['slug']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-text"><?= e(t('admin.roles.slug_help')) ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label"><?= e(t('admin.roles.description')) ?></label>
                            <textarea name="description" rows="3"
                                      class="form-control"
                                      placeholder="<?= e(t('admin.roles.description_ph')) ?>"><?= e($old['description'] ?? $role['description'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <button type="submit" class="btn btn-primary"
                                    data-bs-toggle="tooltip" title="<?= e(t('admin.roles.save_data_tip')) ?>">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                <?= $isEdit ? e(t('admin.roles.save_data')) : e(t('admin.roles.create')) ?>
                            </button>
                            <a href="<?= e(route('admin.roles.index')) ?>" class="btn btn-outline-secondary"><?= e(t('admin.roles.cancel')) ?></a>

                            <?php if ($isEdit && !$isSystemRole && $userCount === 0): ?>
                                <form method="post" action="<?= e(route('admin.roles.destroy', ['id' => $role['id']])) ?>"
                                    class="ms-auto">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-outline-danger"
                                    data-app-confirm="<?= e(t('admin.roles.delete_full_confirm', ['name' => $role['name']])) ?>"
                                        data-app-confirm-label="<?= e(t('admin.roles.delete_full_label')) ?>"
                                        data-app-confirm-class="btn-danger"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.roles.delete_role_tip')) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <!-- Permessi -->
        <div class="col-xl-8" id="permissions">
            <div class="card adm-card">

                <!-- Sticky header -->
                <div class="card-header adm-card-header adm-perm-sticky-header p-0">
                    <div class="adm-perm-header-row">
                        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-wrap">
                            <i class="fa-solid fa-shield-halved text-secondary icon-sm" aria-hidden="true"></i>
                            <span class="adm-module-name"><?= e(t('admin.roles.perms')) ?></span>
                            <span class="badge adm-module-badge text-bg-secondary" id="perm-global-counter">0 / 0</span>
                            <span id="perm-unsaved-badge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-none"
                                  data-bs-toggle="tooltip" title="<?= e(t('admin.roles.unsaved_tip')) ?>">
                                <i class="fa-solid fa-circle-exclamation me-1"></i><?= e(t('admin.roles.unsaved')) ?>
                            </span>

                            <div class="btn-group btn-group-sm ms-1" role="group" aria-label="<?= e(t('admin.roles.expand_aria')) ?>">
                                <button type="button" class="btn btn-outline-secondary" id="perm-expand-all"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.roles.expand_tip')) ?>">
                                    <i class="fa-solid fa-angles-down"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="perm-collapse-all"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.roles.collapse_tip')) ?>">
                                    <i class="fa-solid fa-angles-up"></i>
                                </button>
                            </div>

                            <div class="adm-perm-filter-wrap ms-auto">
                                <span class="adm-perm-filter-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="search"
                                       id="perm-filter"
                                       class="form-control form-control-sm adm-perm-filter"
                                       placeholder="<?= e(t('admin.roles.filter_ph')) ?>"
                                       autocomplete="off"
                                       aria-label="<?= e(t('admin.roles.filter_aria')) ?>">
                                <kbd class="adm-perm-filter-kbd">/</kbd>
                            </div>
                        </div>

                        <div class="d-flex gap-1 flex-shrink-0 adm-perm-header-actions">
                            <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(t('admin.roles.actions_aria')) ?>">
                                <button type="button" class="btn btn-outline-secondary" id="perm-select-all"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.roles.select_all_tip')) ?>">
                                    <i class="fa-solid fa-square-check"></i><span class="d-none d-xl-inline ms-1"><?= e(t('admin.roles.select_all')) ?></span>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="perm-preset-readonly"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.roles.readonly_tip')) ?>">
                                    <i class="fa-solid fa-eye"></i><span class="d-none d-xl-inline ms-1"><?= e(t('admin.roles.readonly')) ?></span>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="perm-clear-all"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.roles.clear_tip')) ?>">
                                    <i class="fa-regular fa-square"></i><span class="d-none d-xl-inline ms-1"><?= e(t('admin.roles.clear')) ?></span>
                                </button>
                            </div>
                            <button type="submit" form="perm-form" id="perm-save-top" class="btn btn-sm btn-primary"
                                    data-bs-toggle="tooltip" title="<?= e(t('admin.roles.save_perms_tip')) ?>">
                                <i class="fa-solid fa-floppy-disk"></i><span class="d-none d-lg-inline ms-1"><?= e(t('admin.roles.save')) ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body adm-perm-scroll-body p-0">
                    <?php if (empty($grouped)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-lock-open icon-3xl mb-2 d-block"></i>
                        <?= e(t('admin.roles.no_perms')) ?>
                    </div>
                    <?php else: ?>

                    <form id="perm-form"
                          hx-post="<?= e(route('admin.roles.permissions.update', ['id' => $role['id']])) ?>"
                          hx-swap="none">
                        <?= csrf_field() ?>

                        <div class="accordion accordion-flush" id="perm-accordion">
                            <?php foreach ($grouped as $module => $perms):
                                $modKey     = 'mod-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $module));
                                $totalCount = count($perms);
                                $selCount   = $moduleCounts[$module]['selected'] ?? 0;
                                $moduleIcon = admin_role_module_icon((string) $module, $moduleIconMap);
                            ?>
                            <div class="accordion-item border-0 border-bottom adm-perm-group"
                                 data-module="<?= e(strtolower((string) $module)) ?>">

                                <h2 class="accordion-header m-0">
                                    <div class="d-flex align-items-center">
                                        <button class="accordion-button adm-module-header flex-grow-1 collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#<?= e($modKey) ?>"
                                                aria-expanded="false"
                                                aria-controls="<?= e($modKey) ?>">
                                            <span class="adm-module-icon"><i class="fa-solid <?= e($moduleIcon) ?>" aria-hidden="true"></i></span>
                                            <span class="adm-module-name me-2"><?= e($module) ?></span>
                                            <span class="badge adm-module-badge me-auto <?= $selCount === 0 ? 'text-bg-secondary' : ($selCount === $totalCount ? 'adm-badge-accent' : 'text-bg-warning') ?>"
                                                  data-mod-badge="<?= e($modKey) ?>">
                                                <?= $selCount ?> / <?= $totalCount ?>
                                            </span>
                                            <span class="adm-module-progress" aria-hidden="true">
                                                <span class="adm-module-progress-bar" data-mod-progress="<?= e($modKey) ?>"
                                                      style="--pct: <?= $totalCount > 0 ? round(($selCount / $totalCount) * 100) : 0 ?>%"></span>
                                            </span>
                                            <i class="fa-solid fa-chevron-down adm-module-chevron ms-2" aria-hidden="true"></i>
                                        </button>
                                        <div class="d-flex gap-1 px-2 flex-shrink-0">
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-secondary adm-mod-all"
                                                    data-mod-target="<?= e($modKey) ?>"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="<?= e(t('admin.roles.mod_all_tip', ['module' => $module])) ?>"><?= e(t('admin.roles.mod_all')) ?></button>
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-secondary adm-mod-none"
                                                    data-mod-target="<?= e($modKey) ?>"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="<?= e(t('admin.roles.mod_none_tip', ['module' => $module])) ?>"><?= e(t('admin.roles.mod_none')) ?></button>
                                        </div>
                                    </div>
                                </h2>

                                <div id="<?= e($modKey) ?>"
                                     class="accordion-collapse collapse">
                                    <div class="accordion-body py-2 px-3">
                                        <div class="row row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-2">
                                            <?php foreach ($perms as $perm): ?>
                                            <?php $permLabel = t_line('permissions', $perm['slug'] ?? '', $perm['name'] ?? ''); ?>
                                            <div class="col">
                                                <label class="form-check adm-perm-item adm-perm-compact mb-0 w-100"
                                                       data-label="<?= e(strtolower($permLabel . ' ' . ($perm['slug'] ?? ''))) ?>"
                                                       data-slug="<?= e(strtolower($perm['slug'] ?? '')) ?>">
                                                    <input class="form-check-input flex-shrink-0"
                                                           type="checkbox"
                                                           name="permission_ids[]"
                                                           value="<?= e($perm['id']) ?>"
                                                           <?= isset($assignedIds[$perm['id']]) ? 'checked' : '' ?>>
                                                    <span class="form-check-label text-truncate"
                                                          data-bs-toggle="tooltip"
                                                          data-bs-placement="top"
                                                          title="<?= e($perm['slug']) ?>">
                                                        <?= e($permLabel) ?>
                                                    </span>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="perm-no-results" class="text-center text-muted py-5 d-none">
                            <i class="fa-solid fa-face-meh icon-2xl mb-2 d-block"></i>
                            <?= e(t('admin.roles.no_filter_results')) ?>
                        </div>

                        <div class="d-flex gap-2 p-3 border-top align-items-center flex-wrap">
                            <button type="submit" class="btn btn-primary" id="perm-save-bottom"
                                    data-bs-toggle="tooltip" title="<?= e(t('admin.roles.save_perms_tip')) ?>">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?= e(t('admin.roles.save_perms')) ?>
                            </button>
                            <span class="text-body-secondary small">
                                <?= t('admin.roles.kbd_hint') ?>
                            </span>
                        </div>
                    </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($isEdit): ?>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';

    var form = document.getElementById('perm-form');
    if (!form) return;

    var filterInput    = document.getElementById('perm-filter');
    var globalBadge    = document.getElementById('perm-global-counter');
    var unsavedBadge   = document.getElementById('perm-unsaved-badge');
    var selectAllBtn   = document.getElementById('perm-select-all');
    var clearAllBtn    = document.getElementById('perm-clear-all');
    var readonlyBtn    = document.getElementById('perm-preset-readonly');
    var expandAllBtn   = document.getElementById('perm-expand-all');
    var collapseAllBtn = document.getElementById('perm-collapse-all');
    var saveTopBtn     = document.getElementById('perm-save-top');
    var saveBottomBtn  = document.getElementById('perm-save-bottom');
    var noResultsEl    = document.getElementById('perm-no-results');

    // Snapshot initial state for dirty tracking
    var initialState = {};
    form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        initialState[cb.value] = cb.checked;
    });

    function isDirty() {
        var dirty = false;
        form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            if (initialState[cb.value] !== cb.checked) dirty = true;
        });
        return dirty;
    }

    function updateDirtyUi() {
        var dirty = isDirty();
        unsavedBadge.classList.toggle('d-none', !dirty);
        [saveTopBtn, saveBottomBtn].forEach(function (b) {
            if (!b) return;
            b.classList.toggle('adm-btn-pulse', dirty);
        });
    }

    function updateModuleBadge(groupEl) {
        var collapseEl = groupEl.querySelector('.accordion-collapse');
        if (!collapseEl) return;
        var badge   = document.querySelector('[data-mod-badge="' + collapseEl.id + '"]');
        var progEl  = document.querySelector('[data-mod-progress="' + collapseEl.id + '"]');

        var checked = 0, total = 0;
        groupEl.querySelectorAll('.adm-perm-item').forEach(function (item) {
            if (item.classList.contains('d-none')) return;
            var cb = item.querySelector('input[type="checkbox"]');
            if (!cb) return;
            total++;
            if (cb.checked) checked++;
        });

        if (badge) {
            badge.textContent = checked + ' / ' + total;
            badge.className = 'badge adm-module-badge me-auto ' + (checked === 0 ? 'text-bg-secondary' : (checked === total ? 'adm-badge-accent' : 'text-bg-warning'));
        }
        if (progEl) {
            progEl.style.setProperty('--pct', total > 0 ? Math.round((checked / total) * 100) + '%' : '0%');
        }
    }

    function updateGlobalCounter() {
        if (!globalBadge) return;
        var total = 0, checked = 0;
        form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            total++;
            if (cb.checked) checked++;
        });
        globalBadge.textContent = checked + ' / ' + total;
        globalBadge.className = 'badge adm-module-badge ' + (checked === 0 ? 'text-bg-secondary' : (checked === total ? 'adm-badge-accent' : 'text-bg-warning'));
    }

    function updateAllCounters() {
        form.querySelectorAll('.adm-perm-group').forEach(updateModuleBadge);
        updateGlobalCounter();
        updateDirtyUi();
    }

    updateAllCounters();

    form.addEventListener('change', function (e) {
        if (e.target.type !== 'checkbox') return;
        var groupEl = e.target.closest('.adm-perm-group');
        if (groupEl) updateModuleBadge(groupEl);
        updateGlobalCounter();
        updateDirtyUi();
    });

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = true; });
            updateAllCounters();
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
            updateAllCounters();
        });
    }

    // Read-only preset: matches common "view" verbs in the permission slug
    var readonlyRegex = /(^|\.)(index|show|view|list|read|export|search|download)(\.|$)/;
    if (readonlyBtn) {
        readonlyBtn.addEventListener('click', function () {
            form.querySelectorAll('.adm-perm-item').forEach(function (item) {
                var cb = item.querySelector('input[type="checkbox"]');
                if (!cb) return;
                var slug = item.getAttribute('data-slug') || '';
                cb.checked = readonlyRegex.test(slug);
            });
            updateAllCounters();
        });
    }

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            document.querySelectorAll('#perm-accordion .accordion-collapse').forEach(function (el) {
                bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
            });
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function () {
            document.querySelectorAll('#perm-accordion .accordion-collapse').forEach(function (el) {
                bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
            });
        });
    }

    form.addEventListener('click', function (e) {
        var btn = e.target.closest('.adm-mod-all, .adm-mod-none');
        if (!btn) return;
        var bodyEl = document.getElementById(btn.getAttribute('data-mod-target'));
        if (!bodyEl) return;
        var wantChecked = btn.classList.contains('adm-mod-all');
        bodyEl.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = wantChecked; });
        var groupEl = btn.closest('.adm-perm-group');
        if (groupEl) updateModuleBadge(groupEl);
        updateGlobalCounter();
        updateDirtyUi();
    });

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            var q = filterInput.value.trim().toLowerCase();
            var anyVisible = false;

            form.querySelectorAll('.adm-perm-group').forEach(function (groupEl) {
                var visible = 0;
                groupEl.querySelectorAll('.adm-perm-item').forEach(function (itemEl) {
                    var show = q === '' || (itemEl.getAttribute('data-label') || '').indexOf(q) !== -1;
                    itemEl.classList.toggle('d-none', !show);
                    if (show) visible++;
                });

                groupEl.classList.toggle('d-none', visible === 0);
                if (visible > 0) anyVisible = true;

                if (q !== '' && visible > 0) {
                    var collapseEl = groupEl.querySelector('.accordion-collapse');
                    if (collapseEl && !collapseEl.classList.contains('show')) {
                        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                    }
                }

                updateModuleBadge(groupEl);
            });

            if (noResultsEl) {
                noResultsEl.classList.toggle('d-none', anyVisible || q === '');
            }

            updateGlobalCounter();
        });
    }

    // Keyboard shortcuts: Ctrl+S save, "/" focus filter, Esc clear filter
    document.addEventListener('keydown', function (e) {
        var tgt = e.target;
        var isTyping = tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable);
        var typingInFilter = tgt === filterInput;

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            if (typeof htmx !== 'undefined') {
                htmx.trigger(form, 'submit');
            } else {
                form.requestSubmit();
            }
            return;
        }

        if (e.key === '/' && !isTyping && filterInput) {
            e.preventDefault();
            filterInput.focus();
            filterInput.select();
            return;
        }

        if (e.key === 'Escape' && typingInFilter && filterInput.value !== '') {
            filterInput.value = '';
            filterInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // After successful HTMX save: reset dirty baseline
    form.addEventListener('htmx:afterRequest', function (evt) {
        if (evt.detail && evt.detail.successful) {
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                initialState[cb.value] = cb.checked;
            });
            updateDirtyUi();
        }
    });

    // Warn before leaving page with unsaved changes
    window.addEventListener('beforeunload', function (e) {
        if (isDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>
<?php endif; ?>

<?php $view->end(); ?>
