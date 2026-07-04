<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
$view->pushScript('js/reports.js');
$view->start('content');

$totalReports = $stats['total_reports'] ?? 0;
$totalSize = $stats['total_size'] ?? 0;

$formatSize = function (int $bytes): string {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log(max($bytes, 1), 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
};

$rpButtons = '';
if (has_permission('reports.create')) {
    $rpButtons .= '<a href="' . e(route('reports.templates.new')) . '" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus me-1"></i>' . e(t('reports.dashboard.new_template')) . '</a>';
}

$recentHistory = $recentHistory ?? [];
$canDeleteHistory = has_permission('reports.delete') || has_permission('reports.admin');

$formatIcon = function (string $fmt): array {
    return match ($fmt) {
        'csv'   => ['fa-file-csv',   'text-success'],
        'excel' => ['fa-file-excel', 'text-success'],
        'pdf'   => ['fa-file-pdf',   'text-danger'],
        default => ['fa-file',       'text-muted'],
    };
};
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-chart-bar',
    'adminTitle'    => t('reports.title'),
    'adminSubtitle' => t('reports.dashboard.subtitle'),
    'adminButtons'  => $rpButtons,
]); ?>

<?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'dashboard']); ?>

<!-- ================================================================
     Quick Stats
     ================================================================ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rp-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rp-stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <div class="rp-stat-value">
                        <?php
                        $sourceCount = 0;
                        foreach ($sources as $group) {
                            $sourceCount += count($group['sources'] ?? []);
                        }
                        echo (int) $sourceCount;
                        ?>
                    </div>
                    <div class="rp-stat-label"><?= e(t('reports.dashboard.stat_sources')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rp-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rp-stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <div class="rp-stat-value"><?= (int) $templateCount ?></div>
                    <div class="rp-stat-label"><?= e(t('reports.dashboard.stat_templates')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rp-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rp-stat-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-file-export"></i>
                </div>
                <div>
                    <div class="rp-stat-value"><?= (int) $totalReports ?></div>
                    <div class="rp-stat-label"><?= e(t('reports.dashboard.stat_generated')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rp-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rp-stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-hard-drive"></i>
                </div>
                <div>
                    <div class="rp-stat-value"><?= $formatSize($totalSize) ?></div>
                    <div class="rp-stat-label"><?= e(t('reports.dashboard.stat_disk')) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
     Widget — Ultimi report generati
     ================================================================ -->
<div class="card shadow-sm mb-4" id="rp-recent-history">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i><?= e(t('reports.dashboard.recent_title')) ?></h6>
        <a href="<?= e(route('reports.history.index')) ?>" class="small text-decoration-none">
            <?= e(t('reports.dashboard.view_all')) ?> <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentHistory)): ?>
            <div class="text-muted text-center py-4">
                <i class="fa-solid fa-info-circle me-1"></i><?= e(t('reports.dashboard.none_generated')) ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th><?= e(t('reports.dashboard.col_template')) ?></th>
                            <th class="d-none d-md-table-cell"><?= e(t('reports.dashboard.col_module')) ?></th>
                            <th class="d-none d-sm-table-cell"><?= e(t('reports.dashboard.col_format')) ?></th>
                            <th class="d-none d-lg-table-cell"><?= e(t('reports.dashboard.col_date')) ?></th>
                            <th class="text-end"><?= e(t('reports.dashboard.col_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentHistory as $row): ?>
                            <?php [$icon, $color] = $formatIcon($row['output_format'] ?? ''); ?>
                            <tr data-history-row="<?= e($row['id']) ?>">
                                <td>
                                    <i class="fa-solid <?= e($icon) ?> <?= e($color) ?> me-2"></i>
                                    <strong><?= e($row['template_name'] ?? '-') ?></strong>
                                </td>
                                <td class="d-none d-md-table-cell text-muted small"><?= e($row['module'] ?? '') ?></td>
                                <td class="d-none d-sm-table-cell"><span class="badge bg-secondary text-uppercase"><?= e($row['output_format'] ?? '') ?></span></td>
                                <td class="d-none d-lg-table-cell text-muted small"><?= e(format_date($row['generated_at'] ?? '', 'compact')) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?= e(route('reports.history.download', ['id' => $row['id']])) ?>"
                                           class="btn btn-outline-primary"
                                           data-bs-toggle="tooltip" title="<?= e(t('reports.dashboard.download')) ?>">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <?php if ($canDeleteHistory): ?>
                                        <button type="button" class="btn btn-outline-danger rp-history-delete"
                                                data-history-id="<?= e($row['id']) ?>"
                                                data-history-name="<?= e($row['template_name'] ?? '') ?>"
                                                data-bs-toggle="tooltip" title="<?= e(t('reports.dashboard.delete')) ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($recentHistory) && $canDeleteHistory): ?>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';
    var token = <?= json_encode(csrf_token()) ?>;

    function notifyHistory(message, type, options) {
        if (typeof window.notify === 'function') {
            window.notify(Object.assign({
                message: message,
                type: type || 'info',
                source: 'reports-history'
            }, options || {}));
            return;
        }

        console.warn('[reports-history]', message);
    }

    document.querySelectorAll('.rp-history-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-history-id');
            var name = btn.getAttribute('data-history-name') || <?= json_encode(t('reports.dashboard.js_delete_default')) ?>;
            if (!id) return;

            var confirmBody = <?= json_encode(t('reports.dashboard.js_delete_body', ['name' => '%NAME%'])) ?>.replace('%NAME%', name);
            var confirmDeletePromise = typeof window.appConfirm === 'function'
                ? window.appConfirm({
                    title: <?= json_encode(t('reports.dashboard.js_delete_title')) ?>,
                    body: confirmBody,
                    confirmLabel: <?= json_encode(t('reports.dashboard.js_delete_confirm')) ?>,
                    confirmClass: 'btn-danger'
                })
                : Promise.resolve(window.confirm(confirmBody));

            confirmDeletePromise.then(function (ok) {
                if (!ok) return;

                btn.disabled = true;
                var fd = new FormData();
                fd.append('_csrf', token);
                fd.append('_method', 'DELETE');
                fetch(<?= json_encode(str_replace('__ID__', '__ID__', route('reports.history.destroy', ['id' => '__ID__']))) ?>.replace('__ID__', id), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r.statusText); })
                .then(function (json) {
                    if (json && json.ok) {
                        var row = document.querySelector('[data-history-row="' + id + '"]');
                        if (row) row.remove();
                        var tbody = document.querySelector('#rp-recent-history tbody');
                        if (tbody && !tbody.querySelector('tr')) {
                            location.reload();
                        }
                    } else {
                        btn.disabled = false;
                        notifyHistory(<?= json_encode(t('reports.dashboard.js_error_prefix')) ?> + ((json && json.message) || <?= json_encode(t('reports.dashboard.js_error_generic')) ?>), 'danger', {
                            title: <?= json_encode(t('reports.dashboard.js_error_title')) ?>,
                            channel: 'banner',
                            duration: 10000
                        });
                    }
                })
                .catch(function (err) {
                    btn.disabled = false;
                    notifyHistory(<?= json_encode(t('reports.dashboard.js_net_prefix')) ?> + err, 'danger', {
                        title: <?= json_encode(t('reports.dashboard.js_net_title')) ?>,
                        channel: 'banner',
                        duration: 10000
                    });
                });
            });
        });
    });
})();
</script>
<?php endif; ?>

<!-- ================================================================
     Data Sources by Module
     ================================================================ -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="fa-solid fa-database me-2"></i><?= e(t('reports.dashboard.sources_title')) ?></h6>
        <span class="badge bg-primary"><?= e(t('reports.dashboard.sources_count', ['count' => $sourceCount])) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($sources)): ?>
            <div class="text-muted text-center py-4">
                <i class="fa-solid fa-info-circle me-1"></i><?= e(t('reports.dashboard.no_sources')) ?>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($sources as $moduleGroup): ?>
                <div class="col-12">
                    <h6 class="text-muted mb-2">
                        <i class="fa-solid <?= e($moduleGroup['icon'] ?? 'fa-cube') ?> me-1"></i><?= e($moduleGroup['label'] ?? $moduleGroup['module'] ?? '') ?>
                        <span class="badge bg-secondary ms-1"><?= count($moduleGroup['sources'] ?? []) ?></span>
                    </h6>
                    <div class="row g-2">
                        <?php foreach ($moduleGroup['sources'] ?? [] as $source): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card border rp-source-card h-100">
                                <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                                    <div class="text-truncate me-2">
                                        <i class="fa-solid <?= e($source['icon'] ?? 'fa-table') ?> text-muted me-1"></i>
                                        <strong><?= e($source['label'] ?? $source['key'] ?? '') ?></strong>
                                        <small class="text-muted d-block"><?= e(t('reports.dashboard.fields_count', ['count' => count($source['fields'] ?? [])])) ?></small>
                                    </div>
                                    <?php if (has_permission('reports.export')): ?>
                                    <div class="dropdown flex-shrink-0">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fa-solid fa-download me-1"></i><?= e(t('reports.dashboard.export')) ?>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="<?= e(route('reports.export.quick') . '?module=' . urlencode($source['module'] ?? '') . '&source_key=' . urlencode($source['key'] ?? '') . '&format=csv') ?>">
                                                    <i class="fa-solid fa-file-csv text-success me-2"></i>CSV
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?= e(route('reports.export.quick') . '?module=' . urlencode($source['module'] ?? '') . '&source_key=' . urlencode($source['key'] ?? '') . '&format=excel') ?>">
                                                    <i class="fa-solid fa-file-excel text-success me-2"></i>Excel
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?= e(route('reports.export.quick') . '?module=' . urlencode($source['module'] ?? '') . '&source_key=' . urlencode($source['key'] ?? '') . '&format=pdf') ?>">
                                                    <i class="fa-solid fa-file-pdf text-danger me-2"></i>PDF
                                                </a>
                                            </li>
                                            <?php if (has_permission('reports.create')): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="<?= e(route('reports.templates.create') . '?module=' . urlencode($source['module'] ?? '') . '&source=' . urlencode($source['key'] ?? '')) ?>">
                                                    <i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i><?= e(t('reports.dashboard.create_template')) ?>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $view->end(); ?>
