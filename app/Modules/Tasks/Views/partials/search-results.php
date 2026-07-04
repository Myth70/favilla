<?php
/**
 * Risultati ricerca HTMX
 *
 * Variabili: $results, $q, $statuses, $priorities
 */
?>
<?php if (empty($results)): ?>
    <div class="text-muted text-center py-3">
        <?php if ($q !== ''): ?>
            <?= t('tasks.search.no_results', ['q' => '<strong>' . e($q) . '</strong>']) ?>
        <?php else: ?>
            <?= e(t('tasks.search.type_to_search')) ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th><?= e(t('tasks.table.col_task')) ?></th>
                    <th><?= e(t('tasks.fields.status')) ?></th>
                    <th><?= e(t('tasks.fields.due_date')) ?></th>
                    <th class="text-end"><?= e(t('common.label.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $item):
                $sMeta = $statuses[$item['status']] ?? ['label' => '?', 'color' => 'secondary', 'icon' => 'fa-question'];
            ?>
                <tr>
                    <td><?= e($item['title']) ?></td>
                    <td><span class="badge bg-<?= $sMeta['color'] ?>"><?= e($sMeta['label']) ?></span></td>
                    <td><?= !empty($item['due_date']) ? e(date('d/m/Y', strtotime($item['due_date']))) : '—' ?></td>
                    <td class="text-end">
                        <a href="<?= e(route('tasks.show', ['id' => $item['id']])) ?>"
                           class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <small class="text-muted"><?= e(t('tasks.list.results', ['count' => count($results)])) ?></small>
<?php endif; ?>
