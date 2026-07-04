<?php
/**
 * Reusable export button partial — include from any module view.
 *
 * Variables:
 *   $exportModule    string  Module name (e.g. 'Admin')
 *   $exportSourceKey string  Source key (e.g. 'users')
 *   $exportLabel     string  Optional button label (default 'Esporta')
 */
if (!isModuleEnabled('Reports') || !has_permission('reports.export')) {
    return;
}
$_exportLabel = $exportLabel ?? t('reports.export_btn.default');
$_baseUrl = route('reports.export.quick');
?>
<div class="dropdown d-inline-block">
    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa-solid fa-download me-1"></i><?= e($_exportLabel) ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item" href="<?= e($_baseUrl . '?module=' . urlencode($exportModule) . '&source_key=' . urlencode($exportSourceKey) . '&format=csv') ?>">
                <i class="fa-solid fa-file-csv text-success me-2"></i>CSV
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?= e($_baseUrl . '?module=' . urlencode($exportModule) . '&source_key=' . urlencode($exportSourceKey) . '&format=excel') ?>">
                <i class="fa-solid fa-file-excel text-success me-2"></i>Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?= e($_baseUrl . '?module=' . urlencode($exportModule) . '&source_key=' . urlencode($exportSourceKey) . '&format=pdf') ?>">
                <i class="fa-solid fa-file-pdf text-danger me-2"></i>PDF
            </a>
        </li>
        <?php if (has_permission('reports.create')): ?>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item" href="<?= e(route('reports.templates.create') . '?module=' . urlencode($exportModule) . '&source=' . urlencode($exportSourceKey)) ?>">
                <i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i><?= e(t('reports.export_btn.create_template')) ?>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
