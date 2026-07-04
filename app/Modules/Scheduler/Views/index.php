<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/scheduler.css'); ?>
<?php $view->pushScript('js/scheduler.js'); ?>
<?php $view->start('content'); ?>

<?php
$canManage = has_permission('scheduler.manage');
$heroButtons = '';
if ($canManage) {
    $heroButtons = '<a href="' . e(route('scheduler.create')) . '" class="btn btn-primary btn-sm">'
                 . '<i class="fa-solid fa-plus me-1"></i>' . e(t('scheduler.new_job')) . '</a>';
}

$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('scheduler.title'),
    'adminIcon'     => 'fa-solid fa-clock',
    'adminSubtitle' => t('scheduler.hero_subtitle'),
    'adminButtons'  => $heroButtons,
]);

// Statistiche aggregate
$totalJobs    = count($jobs);
$enabledJobs  = count(array_filter($jobs, static fn($j) => $j['enabled']));
$totalRuns    = (int) array_sum(array_column($jobs, 'total_runs'));
$totalSuccess = (int) array_sum(array_column($jobs, 'success_runs'));
$successRate  = $totalRuns > 0 ? round($totalSuccess / $totalRuns * 100) : null;
$anyRunning   = !empty(array_filter($jobs, static fn($j) => ($j['last_status'] ?? '') === 'running'));
?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 sch-stat">
            <div class="card-body py-3">
                <div class="sch-stat-value"><?= $totalJobs ?></div>
                <div class="sch-stat-label text-muted"><?= e(t('scheduler.stats.total')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 sch-stat <?= $enabledJobs > 0 ? 'sch-stat-success' : '' ?>">
            <div class="card-body py-3">
                <div class="sch-stat-value"><?= $enabledJobs ?></div>
                <div class="sch-stat-label text-muted"><?= e(t('scheduler.stats.enabled')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 sch-stat">
            <div class="card-body py-3">
                <div class="sch-stat-value"><?= $totalRuns ?></div>
                <div class="sch-stat-label text-muted"><?= e(t('scheduler.stats.total_runs')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <?php
        $rateClass = '';
        if ($successRate !== null) {
            $rateClass = $successRate >= 90 ? 'sch-stat-success' : ($successRate >= 70 ? 'sch-stat-warning' : 'sch-stat-danger');
        }
        ?>
        <div class="card shadow-sm h-100 sch-stat <?= $rateClass ?>">
            <div class="card-body py-3">
                <div class="sch-stat-value"><?= $successRate !== null ? $successRate . '%' : '—' ?></div>
                <div class="sch-stat-label text-muted"><?= e(t('scheduler.stats.success_rate')) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Job table -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
        <span class="fw-semibold">
            <i class="fa-solid fa-list-check me-2 text-primary"></i><?= e(t('scheduler.configured_jobs')) ?>
            <?php if ($anyRunning): ?>
                <span class="badge bg-warning text-dark ms-2 sch-badge-running">
                    <i class="fa-solid fa-circle-notch fa-spin me-1"></i><?= e(t('scheduler.running')) ?>
                </span>
            <?php endif; ?>
        </span>
        <?php if ($canManage && !empty($jobs)): ?>
            <div class="sch-batch-toolbar">
                <div id="scheduler-run-all-status" class="sch-batch-status text-muted small" aria-live="polite"></div>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm sch-batch-run-btn"
                            id="scheduler-run-due-btn"
                            data-scheduler-batch-mode="due"
                            data-idle-label="<?= e(t('scheduler.batch.run_due')) ?>"
                            data-running-label="<?= e(t('scheduler.batch.run_due_running')) ?>"
                            data-empty-message="<?= e(t('scheduler.batch.run_due_empty')) ?>"
                            data-confirm-title="<?= e(t('scheduler.batch.run_due_confirm_title')) ?>"
                            data-confirm-body="<?= e(t('scheduler.batch.run_due_confirm_body')) ?>"
                            data-confirm-label="<?= e(t('scheduler.batch.run_due_confirm_label')) ?>"
                            data-bs-toggle="tooltip"
                            title="<?= e(t('scheduler.batch.run_due_tooltip')) ?>"
                            aria-label="<?= e(t('scheduler.batch.run_due')) ?>">
                        <span class="spinner-border spinner-border-sm sch-run-all-spinner d-none" aria-hidden="true"></span>
                        <i class="fa-solid fa-hourglass-end sch-run-all-icon"></i>
                        <span class="sch-run-all-label ms-1"><?= e(t('scheduler.batch.run_due')) ?></span>
                    </button>
                    <button type="button"
                            class="btn btn-outline-primary btn-sm sch-batch-run-btn"
                            id="scheduler-run-all-btn"
                            data-scheduler-batch-mode="all"
                            data-idle-label="<?= e(t('scheduler.batch.run_all')) ?>"
                            data-running-label="<?= e(t('scheduler.batch.run_all_running')) ?>"
                            data-empty-message="<?= e(t('scheduler.batch.run_all_empty')) ?>"
                            data-confirm-title="<?= e(t('scheduler.batch.run_all_confirm_title')) ?>"
                            data-confirm-body="<?= e(t('scheduler.batch.run_all_confirm_body')) ?>"
                            data-confirm-label="<?= e(t('scheduler.batch.run_all_confirm_label')) ?>"
                            data-bs-toggle="tooltip"
                            title="<?= e(t('scheduler.batch.run_all_tooltip')) ?>"
                            aria-label="<?= e(t('scheduler.batch.run_all')) ?>">
                        <span class="spinner-border spinner-border-sm sch-run-all-spinner d-none" aria-hidden="true"></span>
                        <i class="fa-solid fa-forward-step sch-run-all-icon"></i>
                        <span class="sch-run-all-label ms-1"><?= e(t('scheduler.batch.run_all')) ?></span>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php $view->include('Scheduler/Views/partials/jobs-table', ['jobs' => $jobs]); ?>
    </div>
</div>

<!-- Panel log per singolo job (caricato via HTMX) -->
<div id="job-log-panel" class="mb-4"></div>

<!-- Log recente -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
            <i class="fa-solid fa-terminal me-2 text-secondary"></i><?= e(t('scheduler.recent_log')) ?>
        </span>
        <?php if (has_permission('scheduler.manage') && !empty($recentLog)): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal" data-bs-target="#modal-prune-log"
                title="<?= e(t('scheduler.prune_tooltip')) ?>">
            <i class="fa-solid fa-trash-can me-1"></i><?= e(t('scheduler.prune_btn')) ?>
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentLog)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                <?= e(t('scheduler.no_executions')) ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('scheduler.cols.job')) ?></th>
                        <th><?= e(t('scheduler.cols.started')) ?></th>
                        <th class="text-center"><?= e(t('scheduler.cols.duration')) ?></th>
                        <th class="text-center"><?= e(t('common.label.status')) ?></th>
                        <th><?= e(t('scheduler.cols.output')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLog as $i => $log): ?>
                    <?php
                    $ok  = $log['status'] === 'success';
                    $dur = $log['duration_ms']
                        ? ($log['duration_ms'] >= 1000
                            ? number_format($log['duration_ms'] / 1000, 1) . 's'
                            : $log['duration_ms'] . 'ms')
                        : '—';
                    ?>
                    <tr>
                        <td>
                            <span class="fw-semibold small"><?= e($log['display_name'] ?? $log['job_name'] ?? $log['job_slug']) ?></span>
                        </td>
                        <td class="small text-muted" title="<?= e($log['started_at']) ?>">
                            <?= e(format_date($log['started_at'], 'relative')) ?>
                        </td>
                        <td class="text-center small text-muted"><?= e($dur) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $ok ? 'bg-success' : 'bg-danger' ?>">
                                <?= $ok ? e(t('scheduler.status.ok')) : e(t('scheduler.status.error')) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['output']): ?>
                                <button class="btn btn-outline-secondary btn-sm py-0"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#log-<?= $i ?>"
                                        title="<?= e(t('scheduler.tooltip.expand_output')) ?>">
                                    <i class="fa-solid fa-terminal fa-xs"></i>
                                </button>
                                <?php if (!empty($log['output_file'])): ?>
                                    <a href="<?= e(route('scheduler.output_file', ['filename' => $log['output_file']])) ?>"
                                       target="_blank"
                                       class="btn btn-outline-secondary btn-sm py-0 ms-1"
                                       title="<?= e(t('scheduler.tooltip.full_log')) ?>">
                                        <i class="fa-solid fa-file-lines fa-xs"></i>
                                    </a>
                                <?php endif; ?>
                                <div class="collapse mt-1" id="log-<?= $i ?>">
                                    <pre class="bg-dark text-light p-2 rounded mb-0 sch-log-output"><?= e($log['output']) ?></pre>
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

<!-- Modale: elimina job -->
<?php if ($canManage): ?>
<div class="modal fade" id="modal-delete-job" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-trash-can text-danger"></i><?= e(t('scheduler.delete_modal.title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <?= e(t('scheduler.delete_modal.body')) ?> <strong id="modal-job-name"></strong>?
                <br><span class="text-muted small"><?= e(t('scheduler.delete_modal.note')) ?></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
                <form id="form-delete-job" method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-trash-can me-1"></i><?= e(t('common.action.delete')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modale: svuota log -->
<div class="modal fade" id="modal-prune-log" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-trash-can text-warning"></i><?= e(t('scheduler.prune_modal.title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
            </div>
            <form method="POST" action="<?= e(route('scheduler.prune_log')) ?>">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <p class="mb-3"><?= e(t('scheduler.prune_modal.older_than')) ?></p>
                    <div class="input-group sch-prune-input">
                        <input type="number" name="days" class="form-control" value="30" min="1" max="3650">
                        <span class="input-group-text"><?= e(t('scheduler.prune_modal.days')) ?></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fa-solid fa-trash-can me-1"></i><?= e(t('scheduler.prune_modal.submit')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';
    var modalEl = document.getElementById('modal-delete-job');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            document.getElementById('modal-job-name').textContent = btn.getAttribute('data-job-name');
            document.getElementById('form-delete-job').action = btn.getAttribute('data-job-url');
        });
    }
})();
</script>
<?php endif; ?>

<?php $view->end(); ?>
