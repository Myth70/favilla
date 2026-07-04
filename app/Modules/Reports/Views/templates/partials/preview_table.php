<?php
/**
 * PARTIAL — Data preview table.
 *
 * Variables: $rows, $columns
 * Shows at most 25 rows with formatting by column type.
 */

$maxPreviewRows = 25;
?>

<?php if (empty($rows) || empty($columns)): ?>
    <div class="text-muted text-center py-4">
        <i class="fa-solid fa-table fa-2x mb-2 d-block opacity-50"></i>
        <?= e(t('reports.preview.empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="text-muted rp-col-preview-index">#</th>
                    <?php foreach ($columns as $col): ?>
                    <th><?= e($col['label'] ?? $col['key'] ?? '') ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($rows, 0, $maxPreviewRows) as $idx => $row): ?>
                <tr>
                    <td class="text-muted small"><?= $idx + 1 ?></td>
                    <?php foreach ($columns as $col):
                        $key = $col['key'] ?? '';
                        $type = $col['type'] ?? 'string';
                        $val = $row[$key] ?? '';
                    ?>
                    <td>
                        <?php if ($type === 'boolean'): ?>
                            <?php if ($val): ?>
                                <i class="fa-solid fa-check text-success"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-xmark text-danger"></i>
                            <?php endif; ?>

                        <?php elseif ($type === 'currency'): ?>
                            <span class="text-end d-block"><?= e(number_format((float) $val, 2, ',', '.')) ?> &euro;</span>

                        <?php elseif ($type === 'date' && $val): ?>
                            <?= e(format_date($val, 'compact')) ?>

                        <?php elseif ($type === 'datetime' && $val): ?>
                            <?= e(format_date($val, 'short')) ?>

                        <?php elseif ($type === 'number'): ?>
                            <span class="text-end d-block"><?= e(number_format((float) $val, 0, ',', '.')) ?></span>

                        <?php else: ?>
                            <?= e((string) $val) ?>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($rows) > $maxPreviewRows): ?>
    <div class="text-muted small text-center py-2">
        <?= e(t('reports.preview.shown', ['shown' => $maxPreviewRows, 'total' => count($rows)])) ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
