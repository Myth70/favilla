<?php
/**
 * Footer partial.
 */
$_appVersion      = app_version();
$_maintenanceActive = (bool) config('app.maintenance', false);
$_isAdmin           = isset($user) && in_array('admin', $user['roles'] ?? []);
?>
<footer class="app-footer">
    <span class="app-footer-page-title"><?= e($pageTitle ?? '') ?></span>
    <span class="app-footer-copy">
        &copy; <?= date('Y') ?> <?= e(config('app.name', 'Favilla')) ?>
        <?php if ($_appVersion): ?>
            <a href="<?= e(route('home.changelog')) ?>"
               class="app-version-badge"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               title="<?= e(t('common.tooltip.open_changelog')) ?>"
               aria-label="<?= e(t('common.tooltip.open_changelog')) ?>">
                v<?= e($_appVersion) ?>
            </a>
        <?php endif; ?>
        <span id="footer-maint-badge"><?php if ($_maintenanceActive && $_isAdmin): ?><a href="<?= e(route('admin.settings.index')) ?>"
               class="app-footer-maint-badge"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               title="<?= e(t('common.tooltip.maintenance')) ?>"
               aria-label="<?= e(t('common.tooltip.maintenance_aria')) ?>">
                <span class="app-footer-maint-dot" aria-hidden="true"></span>
                Manutenzione attiva
            </a><?php endif; ?></span>
    </span>
    <div class="sg-footer-actions">
        <?php if (isModuleEnabled('Feedback') && !empty($user)): ?>
        <button type="button"
                id="sg-launcher-btn"
                class="app-footer-top-btn sg-footer-btn"
                data-bs-toggle="tooltip"
                data-bs-placement="left"
                title="<?= e(t('common.tooltip.report_bug')) ?>"
                aria-label="<?= e(t('common.tooltip.report_bug')) ?>">
            <i class="fa-solid fa-bug"></i>
        </button>
        <?php endif; ?>
        <button type="button"
                class="app-footer-top-btn"
                data-scroll-top
                data-bs-toggle="tooltip"
                data-bs-placement="left"
                title="<?= e(t('common.tooltip.scroll_top')) ?>"
                aria-label="<?= e(t('common.tooltip.scroll_top')) ?>">
            <i class="fa-solid fa-circle-chevron-up"></i>
        </button>
    </div>
</footer>
