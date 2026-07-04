<?php
use App\Modules\Feedback\Services\FeedbackService;

$tipiMeta     = FeedbackService::tipiMeta();
$severitaMeta = FeedbackService::severitaMeta();
$statiMeta    = FeedbackService::statiMeta();

$items   = $items ?? [];
$f       = $filters ?? [];
$sortBy  = $sortBy ?? 'created_at';
$sortDir = $sortDir ?? 'DESC';

// Filtri da preservare nei link (sort + paginazione)
$linkFilters = array_filter([
    'q'        => $f['q'] ?? '',
    'stato'    => $f['stato'] ?? '',
    'tipo'     => $f['tipo'] ?? '',
    'severita' => $f['severita'] ?? '',
    'modulo'   => $f['modulo'] ?? '',
], static fn($v) => $v !== '');

$sh = sort_context($sortBy, $sortDir, $linkFilters, route('feedback.admin.index'), '#sg-table');
?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(t('feedback.table.col_code')) ?></th>
                    <th><?= $sh('tipo', t('feedback.table.col_tipo')) ?></th>
                    <th><?= $sh('severita', t('feedback.table.col_severita')) ?></th>
                    <th><?= $sh('stato', t('feedback.table.col_stato')) ?></th>
                    <th><?= $sh('modulo', t('feedback.table.col_modulo')) ?></th>
                    <th><?= e(t('feedback.table.col_titolo')) ?></th>
                    <th><?= e(t('feedback.table.col_autore')) ?></th>
                    <th><?= $sh('created_at', t('feedback.table.col_data')) ?></th>
                    <th class="text-end"><?= e(t('common.label.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="fa-solid fa-inbox me-1"></i><?= e(t('feedback.table.empty')) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $tipo = $tipiMeta[$row['tipo']] ?? ['label' => $row['tipo'], 'color' => 'secondary', 'icon' => 'fa-circle'];
                        $sev  = $severitaMeta[$row['severita']] ?? ['label' => $row['severita'], 'color' => 'secondary'];
                        $stt  = $statiMeta[$row['stato']] ?? ['label' => $row['stato'], 'color' => 'secondary'];
                        $showUrl = route('feedback.admin.show', ['id' => (int) $row['id']]);
                        ?>
                        <tr>
                            <td><a href="<?= e($showUrl) ?>" class="text-decoration-none fw-semibold"><?= e((string) $row['ref_code']) ?></a></td>
                            <td><span class="badge text-bg-<?= e($tipo['color']) ?>"><i class="fa-solid <?= e($tipo['icon']) ?> me-1"></i><?= e($tipo['label']) ?></span></td>
                            <td><span class="badge text-bg-<?= e($sev['color']) ?>"><?= e($sev['label']) ?></span></td>
                            <td><span class="badge text-bg-<?= e($stt['color']) ?>"><?= e($stt['label']) ?></span></td>
                            <td><?= e((string) ($row['modulo'] ?? '—')) ?></td>
                            <td class="text-truncate" style="max-width: 280px;">
                                <a href="<?= e($showUrl) ?>" class="text-decoration-none text-body"><?= e((string) ($row['titolo'] ?? '')) ?></a>
                            </td>
                            <td><?= e((string) ($row['creatore_nome'] ?? '—')) ?></td>
                            <td class="text-nowrap"><span class="text-muted small"><?= e(format_date($row['created_at'] ?? null, 'compact')) ?></span></td>
                            <td class="text-end">
                                <a href="<?= e($showUrl) ?>" class="btn btn-sm btn-outline-secondary"
                                   data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('feedback.table.open_detail')) ?>">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $view->include('partials/pagination', [
        'page'        => $page ?? 1,
        'total_pages' => $total_pages ?? 1,
        'total'       => $total ?? 0,
        'routeName'   => 'feedback.admin.index',
        'hxTarget'    => '#sg-table',
        'filters'     => $linkFilters,
        'extraParams' => ['sort' => $sortBy, 'dir' => $sortDir],
        'label'       => t('feedback.table.label'),
    ]); ?>
</div>
