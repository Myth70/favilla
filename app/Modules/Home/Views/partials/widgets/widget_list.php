<?php
/**
 * List widget partial (compact table).
 * Variables: $widget (V2 format with data: {columns, rows, emptyMessage, link, iconColor})
 *
 * Rows can contain:
 *   - string values (auto-escaped)
 *   - ['html' => '<markup>'] arrays for trusted HTML (e.g. badges from providers)
 */
$data = $widget['data'] ?? [];
$columns = $data['columns'] ?? [];
$rows = $data['rows'] ?? [];
$emptyMessage = $data['emptyMessage'] ?? t('home.widget.no_data');
$link = $data['link'] ?? null;
$displayTone = $widget['_displayTone'] ?? ['value' => 'var(--bs-primary)', 'rgb' => 'var(--bs-primary-rgb)'];
?>
<div class="card border-0 shadow-sm h-100 hm-widget-card"
     style="--hm-widget-color: <?= e($displayTone['value']) ?>; --hm-widget-color-rgb: <?= e($displayTone['rgb']) ?>;">
    <div class="hm-list-header">
        <div class="hm-list-header-title">
            <div class="hm-list-header-icon hm-list-header-icon-dynamic">
                <i class="fa-solid <?= e($widget['icon'] ?? 'fa-list') ?>"></i>
            </div>
            <h6 class="hm-list-title"><?= e($widget['label'] ?? '') ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2 hm-list-header-actions">
            <?php if (!empty($data['headerPartial'])): ?>
                <?php $view->include($data['headerPartial'], ['widget' => $widget]); ?>
            <?php endif; ?>
            <?php if ($link): ?>
                <a href="<?= e($link) ?>" class="hm-list-viewall">
                    <?= e($data['linkLabel'] ?? t('home.widget.view_all')) ?> <i class="fa-solid fa-arrow-right hm-list-viewall-icon"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hm-list-body">
        <?php if (empty($rows)): ?>
            <div class="hm-list-empty">
                <div class="hm-list-empty-icon">
                    <i class="fa-solid <?= e($widget['icon'] ?? 'fa-inbox') ?>"></i>
                </div>
                <p class="hm-list-empty-text"><?= e($emptyMessage) ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover hm-list-table">
                    <?php if (!empty($columns)): ?>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?= e($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <?php endif; ?>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td>
                                    <?php if (is_array($cell) && isset($cell['html'])): ?>
                                        <?= $cell['html'] ?>
                                    <?php else: ?>
                                        <?= e($cell) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
