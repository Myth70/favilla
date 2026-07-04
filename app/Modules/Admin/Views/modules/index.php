<?php
/**
 * Admin modules management.
 * Variables: $view, $modules (from getAllModulesWithStatus()), $coreModules
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'    => 'fa-solid fa-puzzle-piece',
    'adminTitle'   => t('admin.modules.title'),
    'adminButtons' => '<a href="' . e(route('admin.modules.import')) . '" class="btn btn-primary"><i class="fa-solid fa-file-import me-1"></i> ' . e(t('admin.modules.import_btn')) . '</a>',
]); ?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<?php if (!empty($coreModules)): ?>
<?php
$coreModuleRoutes = [
    'Admin'        => ['route' => 'admin.dashboard',               'permission' => 'admin.users.view'],
    'Notifications'=> ['route' => 'admin.notifications.settings',  'permission' => 'notifications.admin.manage'],
    'Backup'       => ['route' => 'backup.index',                  'permission' => 'backup.manage'],
    'Reports'      => ['route' => 'reports.index',                 'permission' => 'reports.view'],
    'HealthCheck'  => ['route' => 'healthcheck.index',             'permission' => 'healthcheck.view'],
    'HelpOnline'   => ['route' => 'helponline.admin.index',        'permission' => 'helponline.admin'],
    'Scheduler'    => ['route' => 'scheduler.index',               'permission' => 'scheduler.view'],
    'Files'        => ['route' => 'files.admin.index',             'permission' => 'files.admin'],
    'Calendar'   => ['route' => 'calendar.index',              'permission' => 'calendar.view'],
    'Tasks'        => ['route' => 'tasks.index',                'permission' => 'tasks.view'],
    'Contatti'     => ['route' => 'contacts.index',                'permission' => 'contacts.view'],
    'Segnalazioni' => ['route' => 'feedback.admin.index',      'permission' => 'feedback.view'],
];

$sortedCoreModules = $coreModules;
usort($sortedCoreModules, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});
?>
<div class="card">
    <div class="card-body p-3">
        <div class="mb-3">
    <h6 class="text-muted mb-2">
        <i class="fa-solid fa-shield-halved me-1"></i><?= e(t('admin.modules.core_heading')) ?>
        <small class="fw-normal"><?= e(t('admin.modules.core_active')) ?></small>
    </h6>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($sortedCoreModules as $cm): ?>
        <?php
        $cmName   = (string) ($cm['name'] ?? '');
        $routeDef = $coreModuleRoutes[$cmName] ?? null;
        $linkUrl  = null;

        if ($routeDef && has_permission($routeDef['permission'])) {
            try {
                $linkUrl = route($routeDef['route']);
            } catch (\Throwable $e) {
                $linkUrl = null;
            }
        }
        ?>

        <?php if ($linkUrl !== null): ?>
        <a href="<?= e($linkUrl) ?>"
           class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 text-decoration-none"
           data-bs-toggle="tooltip"
           title="<?= e(t('admin.modules.core_open', ['name' => $cmName])) ?>">
            <i class="fa-solid fa-circle-check me-1"></i><?= e($cmName) ?>
        </a>
        <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-3 py-2">
            <i class="fa-solid fa-circle-check me-1"></i><?= e($cmName) ?>
        </span>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
    </div>
</div>

<?php endif; ?>

<div class="card">
    <div class="card-body p-2">
        <?php if (empty($modules)): ?>
        <div class="text-center text-muted py-5">
            <i class="fa-solid fa-puzzle-piece fa-2x mb-2 d-block"></i>
            <?= e(t('admin.modules.empty')) ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('admin.modules.col_module')) ?></th>
                        <th class="text-center"><?= e(t('admin.modules.col_status')) ?></th>
                        <th class="text-center"><?= e(t('admin.modules.col_testing')) ?></th>
                        <th><?= e(t('admin.modules.col_database')) ?></th>
                        <th><?= e(t('admin.modules.col_permissions')) ?></th>
                        <th class="text-end"><?= e(t('admin.modules.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $mod):
                        $adminUrl = null;
                        if (!empty($mod['admin_route']) && $mod['enabled']) {
                            $permOk = empty($mod['admin_permission']) || has_permission($mod['admin_permission']);
                            if ($permOk) {
                                try {
                                    $adminUrl = route($mod['admin_route']);
                                } catch (\Throwable $e) {
                                    $adminUrl = null;
                                }
                            }
                        }
                    ?>
                    <tr<?php if ($adminUrl !== null): ?> class="adm-clickable-row" data-href="<?= e($adminUrl) ?>"<?php endif; ?>>
                        <td>
                            <strong><?= e($mod['name']) ?></strong>
                            <?php if ($mod['version'] ?? null): ?>
                                <span class="badge bg-info-subtle text-info-emphasis ms-1"><?= e($mod['version']) ?></span>
                            <?php endif; ?>
                            <?php if ($mod['auto_discovered'] ?? false): ?>
                                <span class="badge bg-purple-subtle text-purple-emphasis border border-purple-subtle ms-1 adm-badge-auto"
                                      data-bs-toggle="tooltip" title="<?= e(t('admin.modules.auto_tip')) ?>">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>auto
                                </span>
                            <?php elseif (!$mod['in_config']): ?>
                                <span class="badge bg-secondary ms-1 small"><?= e(t('admin.modules.not_in_config')) ?></span>
                            <?php endif; ?>
                            <?php if (($mod['database_mode'] ?? 'shared') === 'independent'): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis ms-1"
                                      data-bs-toggle="tooltip" title="<?= e(t('admin.modules.db_independent_tip')) ?>">
                                    <i class="fa-solid fa-database me-1"></i><?= e(t('admin.modules.db_independent')) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($mod['description'] ?? null): ?>
                                <div class="text-muted small"><?= e(t_line('modules', $mod['name'], $mod['description'])) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Enabled toggle -->
                        <td class="text-center">
                            <form method="post"
                                  action="<?= e(route('admin.modules.toggle', ['name' => $mod['name']])) ?>"
                                  class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"
                                       value="<?= $mod['enabled'] ? 'disable' : 'enable' ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $mod['enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                        data-bs-toggle="tooltip" title="<?= e($mod['enabled'] ? t('admin.modules.disable') : t('admin.modules.enable')) ?>">
                                    <i class="fa-solid <?= $mod['enabled'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                </button>
                            </form>
                        </td>

                        <!-- Testing toggle -->
                        <td class="text-center">
                            <form method="post"
                                  action="<?= e(route('admin.modules.toggle', ['name' => $mod['name']])) ?>"
                                  class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"
                                       value="<?= $mod['testing'] ? 'testing-off' : 'testing-on' ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $mod['testing'] ? 'btn-warning' : 'btn-outline-secondary' ?>"
                                        data-bs-toggle="tooltip" title="<?= e($mod['testing'] ? t('admin.modules.testing_off') : t('admin.modules.testing_on')) ?>">
                                    <i class="fa-solid fa-flask"></i>
                                </button>
                            </form>
                        </td>

                        <!-- Database -->
                        <td>
                            <?php
                            $dbMapping = $mod['db_mapping'] ?? null;
                            $dbModeRow = $mod['database_mode'] ?? 'shared';
                            ?>
                            <?php if ($dbModeRow !== 'independent'): ?>
                                <span class="badge bg-light text-muted border" data-bs-toggle="tooltip" title="<?= e(t('admin.modules.db_shared_tip')) ?>">
                                    <i class="fa-solid fa-database me-1"></i><?= e(t('admin.modules.db_shared')) ?>
                                </span>
                            <?php else: ?>
                                <?php
                                $status   = $dbMapping['provisioning_status'] ?? 'pending';
                                $dbName   = $dbMapping['database_name'] ?? '—';
                                $lastErr  = $dbMapping['last_error'] ?? null;
                                $badgeMap = [
                                    'ready'   => 'bg-success',
                                    'manual'  => 'bg-warning text-dark',
                                    'pending' => 'bg-secondary',
                                    'error'   => 'bg-danger',
                                    'removed' => 'bg-dark',
                                ];
                                $badgeCls = $badgeMap[$status] ?? 'bg-secondary';
                                $tooltip  = $lastErr ? t('admin.modules.db_last_error', ['error' => $lastErr]) : ucfirst($status);
                                ?>
                                <div class="small">
                                    <code class="me-1"><?= e($dbName) ?></code>
                                    <span class="badge <?= $badgeCls ?>"
                                          data-bs-toggle="tooltip"
                                          title="<?= e($tooltip) ?>">
                                        <?= e($status) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Permissions status -->
                        <td>
                            <?php if (empty($mod['permissions'])): ?>
                                <span class="text-muted small"><?= e(t('admin.modules.perm_none')) ?></span>
                            <?php else: ?>
                                <?php $new = $mod['new_count']; ?>
                                <?php foreach ($mod['permissions'] as $p): ?>
                                <span class="badge <?= $p['imported'] ? 'bg-success' : 'bg-warning text-dark' ?> me-1 mb-1"
                                      data-bs-toggle="tooltip" title="<?= e($p['slug']) ?>">
                                    <?= e($p['name']) ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if ($new > 0): ?>
                                <small class="text-warning d-block mt-1">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    <?= e(t('admin.modules.perm_not_imported', ['count' => $new])) ?>
                                </small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end text-nowrap">
                            <?php if (!empty($mod['permissions'])): ?>
                            <form method="post"
                                  action="<?= e(route('admin.modules.import-permissions', ['name' => $mod['name']])) ?>"
                                  class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm <?= $mod['new_count'] > 0 ? 'btn-outline-primary' : 'btn-outline-secondary' ?>"
                                        data-bs-toggle="tooltip"
                                        title="<?= e($mod['new_count'] > 0 ? t('admin.modules.import_perms_new', ['count' => $mod['new_count']]) : t('admin.modules.sync_perms')) ?>"
                                        aria-label="<?= e($mod['new_count'] > 0 ? t('admin.modules.import_perms') : t('admin.modules.sync_perms')) ?>">
                                    <i class="fa-solid fa-<?= $mod['new_count'] > 0 ? 'file-import' : 'arrows-rotate' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- Export dropdown -->
                            <div class="btn-group ms-1">
                                <a href="<?= e(route('admin.modules.export', ['name' => $mod['name']])) ?>"
                                   class="btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="tooltip" title="<?= e(t('admin.modules.export_code_only')) ?>" aria-label="<?= e(t('admin.modules.export_code_only')) ?>">
                                    <i class="fa-solid fa-file-export"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden"><?= e(t('admin.modules.export_options')) ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="<?= e(route('admin.modules.export', ['name' => $mod['name']])) ?>">
                                            <i class="fa-solid fa-code me-2 text-muted"></i><?= e(t('admin.modules.export_code')) ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= e(route('admin.modules.export', ['name' => $mod['name']])) ?>?data=1">
                                            <i class="fa-solid fa-database me-2 text-muted"></i><?= e(t('admin.modules.export_with_data')) ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= e(route('admin.modules.export', ['name' => $mod['name']])) ?>?data=1&amp;uploads=1">
                                            <i class="fa-solid fa-box-archive me-2 text-muted"></i><?= e(t('admin.modules.export_with_data_files')) ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <!-- Uninstall -->
                            <a href="<?= e(route('admin.modules.uninstall', ['name' => $mod['name']])) ?>"
                               class="btn btn-sm btn-outline-danger ms-1"
                               data-bs-toggle="tooltip" title="<?= e(t('admin.modules.uninstall_tip')) ?>" aria-label="<?= e(t('admin.modules.uninstall_tip')) ?>">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</div>


<?php $view->end(); ?>
