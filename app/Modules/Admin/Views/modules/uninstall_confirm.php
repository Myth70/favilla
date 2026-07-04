<?php
/**
 * Admin module uninstall confirmation page.
 * Variables: $view, $preview (array from ModuleUninstallService::preview())
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');

$name = $preview['name'];
$tables = $preview['tables'];
$assets = $preview['assets'];
$uploadsDir = $preview['uploads_directory'];
$uploadsCount = $preview['uploads_count'];
$uploadsSize = $preview['uploads_size'];
$permCount = $preview['permissions_count'];
$migCount = $preview['migrations_count'];
$fileCount = $preview['file_count'];
$inConfig = $preview['in_config'];

$formatSize = function (int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-trash-can',
        'adminTitle'    => t('admin.modules.uninstall.title'),
        'adminSubtitle' => e($name),
    ]); ?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <?= e(t('admin.modules.uninstall.confirm_header')) ?>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong><?= e(t('admin.modules.uninstall.warning_label')) ?></strong> <?= e(t('admin.modules.uninstall.warning_body')) ?>
                </div>

                <?php if ($preview['description'] ?? null): ?>
                <p class="text-muted"><?= e(t_line('modules', $name, $preview['description'])) ?></p>
                <?php endif; ?>

                <h6 class="mt-3 mb-2"><?= e(t('admin.modules.uninstall.summary_heading')) ?></h6>
                <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td><i class="fa-solid fa-folder text-muted me-2"></i><?= e(t('admin.modules.uninstall.row_source')) ?></td>
                            <td class="text-end"><?= e(t('admin.modules.uninstall.file_count', ['count' => $fileCount])) ?> <code>app/Modules/<?= e($name) ?>/</code></td>
                        </tr>
                        <?php if (!empty($tables)): ?>
                        <tr>
                            <td><i class="fa-solid fa-database text-muted me-2"></i><?= e(t('admin.modules.uninstall.row_tables')) ?></td>
                            <td class="text-end">
                                <?php foreach ($tables as $t): ?>
                                    <code class="me-1"><?= e($t) ?></code>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($permCount > 0): ?>
                        <tr>
                            <td><i class="fa-solid fa-key text-muted me-2"></i><?= e(t('admin.modules.uninstall.row_permissions')) ?></td>
                            <td class="text-end"><?= e(t('admin.modules.uninstall.permissions_count', ['count' => $permCount])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($migCount > 0): ?>
                        <tr>
                            <td><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i><?= e(t('admin.modules.uninstall.row_migrations')) ?></td>
                            <td class="text-end"><?= e(t('admin.modules.uninstall.migrations_count', ['count' => $migCount])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($assets['css']) || !empty($assets['js'])): ?>
                        <tr>
                            <td><i class="fa-solid fa-paint-brush text-muted me-2"></i><?= e(t('admin.modules.uninstall.row_assets')) ?></td>
                            <td class="text-end">
                                <?php foreach ($assets['css'] ?? [] as $f): ?>
                                    <code class="me-1"><?= e($f) ?></code>
                                <?php endforeach; ?>
                                <?php foreach ($assets['js'] ?? [] as $f): ?>
                                    <code class="me-1"><?= e($f) ?></code>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($uploadsDir && $uploadsCount > 0): ?>
                        <tr>
                            <td><i class="fa-solid fa-images text-muted me-2"></i><?= e(t('admin.modules.uninstall.row_uploads')) ?></td>
                            <td class="text-end">
                                <?= e(t('admin.modules.uninstall.uploads_in', ['count' => $uploadsCount, 'size' => $formatSize($uploadsSize)])) ?>
                                <code>public/uploads/<?= e($uploadsDir) ?>/</code>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>

                <?php if ($inConfig): ?>
                <div class="alert alert-warning mb-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    <?= t('admin.modules.uninstall.in_config_warning') ?>
                </div>
                <?php endif; ?>

                <form method="post"
                      action="<?= e(route('admin.modules.uninstall.do', ['name' => $name])) ?>">
                    <?= csrf_field() ?>

                    <?php if (!empty($tables)): ?>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="drop_tables"
                               name="drop_tables" value="1" checked>
                        <label class="form-check-label" for="drop_tables">
                            <?= e(t('admin.modules.uninstall.drop_tables')) ?>
                        </label>
                    </div>
                    <?php endif; ?>

                    <?php if ($uploadsDir && $uploadsCount > 0): ?>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="delete_uploads"
                               name="delete_uploads" value="1" checked>
                        <label class="form-check-label" for="delete_uploads">
                            <?= e(t('admin.modules.uninstall.delete_uploads', ['count' => $uploadsCount, 'size' => $formatSize($uploadsSize)])) ?>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="confirm_name" class="form-label">
                            <?= t('admin.modules.uninstall.type_to_confirm', ['name' => e($name)]) ?>
                        </label>
                        <input type="text" class="form-control" id="confirm_name" name="confirm_name"
                               placeholder="<?= e($name) ?>" required autocomplete="off">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger" id="btn-uninstall" disabled>
                            <i class="fa-solid fa-trash-can me-1"></i><?= e(t('admin.modules.uninstall.submit')) ?>
                        </button>
                        <a href="<?= e(route('admin.modules.index')) ?>" class="btn btn-outline-secondary">
                            <?= e(t('admin.modules.uninstall.cancel')) ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function() {
    'use strict';
    var input = document.getElementById('confirm_name');
    var btn   = document.getElementById('btn-uninstall');
    var expected = <?= json_encode($name) ?>;
    input.addEventListener('input', function() {
        btn.disabled = (input.value.trim() !== expected);
    });
})();
</script>

<?php $view->end(); ?>
