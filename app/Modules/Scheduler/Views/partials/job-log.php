<?php
/**
 * Scheduler — storico esecuzioni per singolo job (partial HTMX).
 *
 * Variabili: $job (array), $log (array)
 */
?>
<div class="card shadow-sm sch-log-panel-card" tabindex="-1" aria-label="<?= e(t('scheduler.log_panel.history_of', ['name' => $job['display_name'] ?? $job['name']])) ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
            <i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i>
            <?= e(t('scheduler.log_panel.history', ['name' => $job['display_name'] ?? $job['name']])) ?>
            <span class="text-muted fw-normal font-monospace ms-1 small"><?= e($job['slug']) ?></span>
        </span>
        <button type="button" class="btn-close" aria-label="<?= e(t('common.action.close')) ?>"
                data-app-clear-target="job-log-panel"></button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($log)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                <?= e(t('scheduler.no_executions_job')) ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('scheduler.cols.started')) ?></th>
                        <th><?= e(t('scheduler.cols.finished')) ?></th>
                        <th class="text-center"><?= e(t('scheduler.cols.duration')) ?></th>
                        <th class="text-center"><?= e(t('common.label.status')) ?></th>
                        <th><?= e(t('scheduler.cols.output')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($log as $i => $entry): ?>
                    <?php
                    $ok  = $entry['status'] === 'success';
                    $dur = $entry['duration_ms']
                        ? ($entry['duration_ms'] >= 1000
                            ? number_format($entry['duration_ms'] / 1000, 1) . 's'
                            : $entry['duration_ms'] . 'ms')
                        : '—';
                    ?>
                    <tr>
                        <td class="small" title="<?= e($entry['started_at']) ?>">
                            <?= e(format_date($entry['started_at'], 'relative')) ?>
                        </td>
                        <td class="small text-muted">
                            <?= $entry['finished_at'] ? e(format_date($entry['finished_at'], 'time')) : '—' ?>
                        </td>
                        <td class="text-center small text-muted"><?= e($dur) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $ok ? 'bg-success' : 'bg-danger' ?>">
                                <?= $ok ? e(t('scheduler.status.ok')) : e(t('scheduler.status.error')) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($entry['output']): ?>
                                <button class="btn btn-outline-secondary btn-sm py-0"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#jlog-<?= $i ?>"
                                        title="<?= e(t('scheduler.tooltip.expand_output')) ?>">
                                    <i class="fa-solid fa-terminal fa-xs"></i>
                                </button>
                                <?php if (!empty($entry['output_file'])): ?>
                                    <a href="<?= e(route('scheduler.output_file', ['filename' => $entry['output_file']])) ?>"
                                       target="_blank"
                                       class="btn btn-outline-secondary btn-sm py-0 ms-1"
                                       title="<?= e(t('scheduler.tooltip.full_log')) ?>">
                                        <i class="fa-solid fa-file-lines fa-xs"></i>
                                    </a>
                                <?php endif; ?>
                                <div class="collapse mt-1" id="jlog-<?= $i ?>">
                                    <pre class="bg-dark text-light p-2 rounded mb-0 sch-log-output"><?= e($entry['output']) ?></pre>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
