<?php
/**
 * PARTIAL TABELLA — Paginata con sort HTMX.
 *
 * Variabili: $items, $total, $pages, $page, $filters
 *
 * Questo partial viene caricato:
 * - Incluso da index.php al primo caricamento
 * - Via HTMX quando si cambia filtro, ordinamento o pagina
 *
 * hx-target="#items-table" + hx-push-url="true" → aggiorna tabella + URL
 *
 * i18n: ogni stringa user-facing passa da e(t('example.<chiave>')).
 * NOTA: le intestazioni passate a $sh() NON vanno escapate qui (sort_link()
 * applica già e() all'etichetta).
 */

$sh = sort_context(
    $filters['sort'] ?? '',
    $filters['dir']  ?? 'DESC',
    $filters,
    route('example.index'),
    '#items-table'
);
?>

<?php if (empty($items)): ?>
    <div class="text-muted text-center py-5">
        <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-50"></i>
        <?= e(t('example.list.empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= $sh('name', t('example.list.col_name')) ?></th>
                    <th><?= $sh('email', t('example.list.col_email')) ?></th>
                    <th><?= $sh('status', t('example.list.col_status')) ?></th>
                    <th><?= $sh('created_at', t('example.list.col_created')) ?></th>
                    <th class="text-end"><?= e(t('example.list.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item):
                $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'archived' => 'warning'];
                $statusKey    = (string) ($item['status'] ?? 'active');
                $color        = $statusColors[$statusKey] ?? 'secondary';
            ?>
                <tr>
                    <td class="text-muted"><?= e((string) $item['id']) ?></td>
                    <td>
                        <a href="<?= e(route('example.show', ['id' => $item['id']])) ?>">
                            <?= e($item['name']) ?>
                        </a>
                    </td>
                    <td><?= e($item['email']) ?></td>
                    <td>
                        <span class="badge bg-<?= e($color) ?>"><?= e(t('example.status.' . $statusKey)) ?></span>
                    </td>
                    <td class="text-muted small"><?= e(format_date_it($item['created_at'] ?? '', 'compact')) ?></td>
                    <td class="text-end">
                        <a href="<?= e(route('example.show', ['id' => $item['id']])) ?>"
                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="<?= e(t('example.actions.detail')) ?>">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <?php if (has_permission('example.edit')): ?>
                        <a href="<?= e(route('example.edit', ['id' => $item['id']])) ?>"
                           class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('example.actions.edit')) ?>">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (has_permission('example.delete')): ?>
                        <form method="POST"
                              action="<?= e(route('example.destroy', ['id' => $item['id']])) ?>"
                              class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="<?= e(t('example.actions.delete')) ?>"
                                    data-app-confirm="<?= e(t('example.confirm.delete')) ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginazione HTMX -->
    <?php if ($pages > 1): ?>
    <nav class="d-flex justify-content-between align-items-center mt-3 px-2">
        <small class="text-muted"><?= e(t('example.list.results', ['count' => $total, 'page' => $page, 'pages' => $pages])) ?></small>
        <ul class="pagination pagination-sm mb-0">
            <?php
            $baseParams = array_merge($filters, ['sort' => $filters['sort'], 'dir' => $filters['dir']]);
            ?>
            <!-- Precedente -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <?php $qs = http_build_query(array_merge($baseParams, ['page' => $page - 1])); ?>
                <a class="page-link" href="<?= e(route('example.index')) ?>?<?= e($qs) ?>"
                   hx-get="<?= e(route('example.index')) ?>?<?= e($qs) ?>"
                   hx-target="#items-table"
                   hx-push-url="true">&lsaquo;</a>
            </li>
            <!-- Numeri pagina -->
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <?php $qs = http_build_query(array_merge($baseParams, ['page' => $i])); ?>
                <a class="page-link" href="<?= e(route('example.index')) ?>?<?= e($qs) ?>"
                   hx-get="<?= e(route('example.index')) ?>?<?= e($qs) ?>"
                   hx-target="#items-table"
                   hx-push-url="true"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <!-- Successivo -->
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <?php $qs = http_build_query(array_merge($baseParams, ['page' => $page + 1])); ?>
                <a class="page-link" href="<?= e(route('example.index')) ?>?<?= e($qs) ?>"
                   hx-get="<?= e(route('example.index')) ?>?<?= e($qs) ?>"
                   hx-target="#items-table"
                   hx-push-url="true">&rsaquo;</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>
