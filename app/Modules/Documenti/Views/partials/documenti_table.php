<?php
/**
 * Tabella elenco documenti.
 * Variabili:
 *   $result    array  ['items'|'data','total','pages','page']
 *   $filters   array  Filtri attivi (per preservarli nella paginazione/sort)
 *   $showBulk  bool   Mostra checkbox selezione (default false)
 *   $routeName string Nome route per paginazione (default 'documenti.index')
 *   $hxTarget  string Target HTMX (default '#dc-list')
 */
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Helpers\UiHelper;

$items     = $result['items'] ?? $result['data'] ?? [];
$total     = (int) ($result['total'] ?? 0);
$page      = (int) ($result['page']  ?? 1);
$pages     = (int) ($result['lastPage'] ?? 1);
$showBulk  = $showBulk  ?? false;
$routeName = $routeName ?? 'documenti.index';
$hxTarget  = $hxTarget  ?? '#dc-list';
$filters   = $filters   ?? [];

$currentSort = $filters['sort'] ?? 'created_at';
$currentDir  = strtoupper($filters['dir'] ?? 'DESC');

$sortCell = function (string $field, string $label) use ($currentSort, $currentDir): void {
    $isActive = $currentSort === $field;
    $icon = !$isActive ? 'fa-sort'
        : ($currentDir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');
    $attrs = 'class="dc-sort" data-sort="' . e($field) . '"' . ($isActive ? ' data-sort-active="1"' : '');
    echo '<th ' . $attrs . '>'
       . e($label)
       . ' <i class="fa-solid ' . e($icon) . ' dc-sort-icon" aria-hidden="true"></i>'
       . '</th>';
};
?>
<?php if (empty($items)): ?>
    <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-file-circle-xmark',
        'titolo'    => t('documenti.table.empty_titolo'),
        'messaggio' => t('documenti.table.empty_messaggio'),
    ]); ?>
<?php else: ?>
<div class="card" id="dc-list" data-bulk-scope data-bulk-bar="#dc-bulk-bar">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">
            <?= e(tc('documenti.table.count', $total)) ?>
        </small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <?php if ($showBulk): ?>
                    <th style="width:2.5rem">
                        <input type="checkbox" class="form-check-input" data-bulk-toggle-all aria-label="<?= e(t('documenti.table.seleziona_tutti')) ?>">
                    </th>
                    <?php endif; ?>
                    <?php $sortCell('titolo', t('documenti.create.titolo_label')); ?>
                    <th><?= e(t('documenti.create.categoria_label')) ?></th>
                    <?php $sortCell('stato', t('documenti.widget.col_stato')); ?>
                    <?php $sortCell('scade_il', t('documenti.create.scadenza_label')); ?>
                    <?php $sortCell('created_at', t('documenti.show.aggiornato')); ?>
                    <th class="text-end dc-no-print" style="width:5rem"><?= e(t('documenti.scadenze.col_azioni')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <?php if ($showBulk): ?>
                    <td>
                        <input type="checkbox" class="form-check-input" data-bulk-item value="<?= (int)$item['id'] ?>" aria-label="<?= e(t('documenti.table.seleziona_documento', ['id' => (int) $item['id']])) ?>">
                    </td>
                    <?php endif; ?>
                    <td>
                        <a href="<?= e(route('documenti.show', ['id' => $item['id']])) ?>" class="fw-semibold text-decoration-none">
                            <?= e($item['titolo']) ?>
                        </a>
                        <?php if (!empty($item['protocollo'])): ?>
                            <small class="d-block"><code class="dc-code"><?= e($item['protocollo']) ?></code></small>
                        <?php endif; ?>
                        <?php if (!empty($item['tag'])): ?>
                            <span class="badge bg-light text-secondary mt-1">
                                <i class="fa-solid fa-tag me-1" aria-hidden="true"></i><?= e($item['tag']) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= e($item['categoria_nome'] ?? '—') ?></small></td>
                    <td><?= StatoHelper::badge($item['stato']) ?></td>
                    <td>
                        <?php if (!empty($item['scade_il'])): ?>
                            <?php
                            $giorni = (int) ceil((strtotime($item['scade_il']) - time()) / 86400);
                            $urgCls = StatoHelper::urgencyClass($giorni);
                            $urgLab = StatoHelper::urgencyLabel($giorni);
                            ?>
                            <span class="dc-urgent-chip <?= e($urgCls) ?>"
                                  data-bs-toggle="tooltip"
                                  title="<?= e(format_date($item['scade_il'], 'relative')) ?>">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                <?= e($urgLab) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted"
                               data-bs-toggle="tooltip"
                               title="<?= e(format_date($item['updated_at'] ?? $item['created_at'] ?? '', 'long')) ?>">
                            <?= e(format_date($item['updated_at'] ?? $item['created_at'] ?? '', 'compact')) ?>
                        </small>
                    </td>
                    <td class="text-end dc-no-print">
                        <?= UiHelper::ariaButton(
                            t('documenti.table.visualizza_documento'),
                            'fa-eye',
                            [
                                'href'  => route('documenti.show', ['id' => $item['id']]),
                                'class' => 'btn btn-sm btn-outline-primary',
                            ]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <?php $view->include('partials/pagination', [
            'page'        => $page,
            'total_pages' => $pages,
            'total'       => $total,
            'routeName'   => $routeName,
            'hxTarget'    => $hxTarget,
            'filters'     => array_filter($filters, static fn ($v) => $v !== '' && $v !== [] && $v !== null),
            'label'       => t('documenti.table.pagination_label'),
        ]); ?>
    <?php endif; ?>

    <?php if ($showBulk): ?>
    <?php /* Slot esteso dalle view: passa $bulkBar via include */ ?>
    <?= $bulkBar ?? '' ?>
    <?php endif; ?>
</div>
<?php endif; ?>
