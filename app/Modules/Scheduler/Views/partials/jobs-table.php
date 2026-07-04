<?php
/**
 * Scheduler — tabella job.
 *
 * Variabili: $jobs (array)
 */

$statusBadge = [
    'success' => ['bg-success',           t('scheduler.status.success')],
    'failed'  => ['bg-danger',            t('scheduler.status.failed')],
    'running' => ['bg-warning text-dark', t('scheduler.status.running')],
];

$pollIntervalSeconds = 4;
$pollIntervalMs = $pollIntervalSeconds * 1000;
$hasRunning = !empty(array_filter($jobs, static fn($j) => ($j['last_status'] ?? '') === 'running'));
$now = new \DateTimeImmutable();
?>
<div class="table-responsive" id="jobs-table"
    data-poll-interval-ms="<?= $pollIntervalMs ?>"
    <?php if ($hasRunning): ?>
        hx-get="<?= e(route('scheduler.poll')) ?>"
        hx-trigger="every <?= $pollIntervalSeconds ?>s"
        hx-target="#jobs-table"
        hx-swap="outerHTML"
    <?php endif; ?>
>
<?php if (empty($jobs)): ?>
    <div class="p-5 text-center text-muted">
        <i class="fa-solid fa-clock fa-2x mb-3 d-block opacity-25"></i>
        <?= e(t('scheduler.table.no_jobs')) ?>
        <?php if (has_permission('scheduler.manage')): ?>
            <div class="mt-2">
                <a href="<?= e(route('scheduler.create')) ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i><?= e(t('scheduler.table.create_first')) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th><?= e(t('scheduler.cols.job')) ?></th>
                <th class="text-center"><?= e(t('scheduler.cols.interval')) ?></th>
                <th><?= e(t('scheduler.cols.schedule')) ?></th>
                <th class="text-center"><?= e(t('common.label.status')) ?></th>
                <th class="text-center"><?= e(t('common.label.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $job): ?>
            <?php
            $isRunning = ($job['last_status'] ?? '') === 'running';
            $isEnabled = (bool) $job['enabled'];
            $badge     = $statusBadge[$job['last_status'] ?? ''] ?? ['bg-secondary', t('scheduler.never_run')];

            $nextRun = null;
            $isDue = false;
            if ($isEnabled && !$isRunning) {
                if (!empty($job['next_retry_at'])) {
                    try {
                        $retryAt = new \DateTimeImmutable((string) $job['next_retry_at']);
                        $isDue = $retryAt <= $now;
                        $nextRun = $isDue
                            ? t('scheduler.table.retry_now')
                            : t('scheduler.table.retry_at', ['time' => $retryAt->format('H:i'), 'rel' => format_date($retryAt->format('Y-m-d H:i:s'), 'relative')]);
                    } catch (\Throwable $e) {
                        $nextRun = null;
                    }
                } elseif ($job['last_run_at']) {
                    $next = new \DateTimeImmutable((string) $job['last_run_at']);
                    $next = $next->modify('+' . $job['interval_minutes'] . ' minutes');
                    $isDue = $next <= $now;
                    $nextRun = $isDue
                        ? t('scheduler.table.next_now')
                        : t('scheduler.table.next_at', ['time' => $next->format('H:i'), 'rel' => format_date($next->format('Y-m-d H:i:s'), 'relative')]);
                } else {
                    $isDue = true;
                    $nextRun = t('scheduler.table.next_now');
                }
            }

            $duration = $job['last_duration_ms']
                ? ($job['last_duration_ms'] >= 1000
                    ? number_format($job['last_duration_ms'] / 1000, 1) . 's'
                    : $job['last_duration_ms'] . 'ms')
                : null;

            $intervalLabel = $job['interval_minutes'] >= 1440
                ? ($job['interval_minutes'] / 1440) . 'g'
                : ($job['interval_minutes'] >= 60
                    ? ($job['interval_minutes'] / 60) . 'h'
                    : $job['interval_minutes'] . 'min');
            $intervalBadgeClass = $isDue ? 'bg-danger' : 'bg-secondary';

            $args = $job['args_json'] ? implode(' ', json_decode($job['args_json'], true) ?? []) : '';
            $rowClass = $isEnabled ? '' : 'sch-row-disabled';
            ?>
            <tr class="<?= $rowClass ?>"
                data-scheduler-job-row
                data-job-id="<?= (int) $job['id'] ?>"
                data-job-name="<?= e($job['display_name'] ?? $job['name']) ?>"
                data-job-enabled="<?= $isEnabled ? '1' : '0' ?>"
                data-job-due="<?= $isDue ? '1' : '0' ?>"
                data-job-running="<?= $isRunning ? '1' : '0' ?>">

                <!-- Job: nome + comando + statistiche -->
                <td>
                    <div class="fw-semibold"><?= e($job['display_name'] ?? $job['name']) ?></div>
                    <div class="sch-cmd font-monospace mt-1">
                        <?= e($job['command']) ?><?= $args !== '' ? ' ' . e($args) : '' ?>
                    </div>
                    <?php if (($job['total_runs'] ?? 0) > 0): ?>
                        <div class="sch-next mt-1">
                            <span class="text-success"><?= (int)$job['success_runs'] ?> ok</span>
                            <span class="text-muted mx-1">·</span>
                            <span class="<?= (int)$job['failed_runs'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)$job['failed_runs'] ?> err</span>
                        </div>
                    <?php endif; ?>
                </td>

                <!-- Intervallo -->
                <td class="text-center">
                    <span class="badge <?= $intervalBadgeClass ?>"><?= e($intervalLabel) ?></span>
                </td>

                <!-- Pianificazione: ultimo run + prossimo -->
                <td>
                    <?php if ($isRunning): ?>
                        <span class="text-warning fw-semibold small">
                            <i class="fa-solid fa-circle-notch fa-spin me-1"></i><?= e(t('scheduler.running_dots')) ?>
                        </span>
                    <?php elseif ($job['last_run_at']): ?>
                        <span class="small" title="<?= e($job['last_run_at']) ?>">
                            <?= e(format_date($job['last_run_at'], 'relative')) ?>
                        </span>
                        <?php if ($nextRun): ?>
                            <div class="sch-next"><?= e($nextRun) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted small"><?= e(t('scheduler.never_run')) ?></span>
                    <?php endif; ?>
                </td>

                <!-- Stato + durata -->
                <td class="text-center">
                    <span class="badge <?= $badge[0] ?> <?= $isRunning ? 'sch-badge-running' : '' ?>">
                        <?= $isRunning ? '<i class="fa-solid fa-circle-notch fa-spin me-1"></i>' : '' ?>
                        <?= $badge[1] ?>
                    </span>
                    <?php if ($duration): ?>
                        <div class="sch-next"><?= e($duration) ?></div>
                    <?php endif; ?>
                </td>

                <!-- Azioni -->
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center align-items-center flex-wrap">

                        <!-- Abilita/Disabilita -->
                        <?php if (has_permission('scheduler.manage')): ?>
                        <button type="button"
                                class="btn btn-sm <?= $isEnabled ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                                data-bs-toggle="tooltip"
                                hx-post="<?= e(route('scheduler.toggle')) ?>"
                                hx-vals='{"id":"<?= (int)$job['id'] ?>","enabled":"<?= $isEnabled ? '0' : '1' ?>","_token":"<?= csrf_token() ?>"}'
                                hx-target="#jobs-table"
                                hx-swap="outerHTML"
                                title="<?= $isEnabled ? e(t('scheduler.tooltip.disable')) : e(t('scheduler.tooltip.enable')) ?>"
                                aria-label="<?= $isEnabled ? e(t('scheduler.tooltip.disable')) : e(t('scheduler.tooltip.enable')) ?>">
                            <i class="fa-solid <?= $isEnabled ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                        </button>
                        <?php endif; ?>

                        <!-- Storico esecuzioni -->
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="tooltip"
                                title="<?= e(t('scheduler.tooltip.history')) ?>"
                            aria-label="<?= e(t('scheduler.tooltip.history')) ?>"
                                aria-controls="job-log-panel"
                                hx-get="<?= e(route('scheduler.job_log', ['id' => (int)$job['id']])) ?>"
                                hx-target="#job-log-panel"
                                hx-swap="innerHTML">
                            <i class="fa-solid fa-clock-rotate-left fa-xs"></i>
                        </button>

                        <?php if (has_permission('scheduler.manage')): ?>

                        <!-- Reset job bloccato (solo se running) -->
                        <?php if ($isRunning): ?>
                        <button type="button"
                                class="btn btn-outline-warning btn-sm"
                            data-bs-toggle="tooltip"
                                title="<?= e(t('scheduler.tooltip.reset_stuck')) ?>"
                            aria-label="<?= e(t('scheduler.tooltip.reset_stuck')) ?>"
                                hx-post="<?= e(route('scheduler.reset', ['id' => (int)$job['id']])) ?>"
                                hx-vals='{"_token":"<?= csrf_token() ?>"}'
                                hx-target="#jobs-table"
                                hx-swap="outerHTML"
                                hx-confirm="<?= e(t('scheduler.table.reset_confirm')) ?>">
                            <i class="fa-solid fa-rotate-left fa-xs"></i>
                        </button>
                        <?php endif; ?>

                        <!-- Esegui ora -->
                        <button type="button"
                                class="btn btn-outline-primary btn-sm sch-run-job-btn"
                            data-bs-toggle="tooltip"
                                title="<?= e(t('scheduler.tooltip.run_now')) ?>"
                            aria-label="<?= e(t('scheduler.tooltip.run_now')) ?>"
                                data-job-id="<?= (int) $job['id'] ?>"
                                data-job-name="<?= e($job['display_name'] ?? $job['name']) ?>"
                                data-job-enabled="<?= $isEnabled ? '1' : '0' ?>"
                                data-job-due="<?= $isDue ? '1' : '0' ?>"
                                data-job-running="<?= $isRunning ? '1' : '0' ?>"
                                hx-post="<?= e(route('scheduler.run', ['id' => (int)$job['id']])) ?>"
                                hx-vals='{"_token":"<?= csrf_token() ?>"}'
                                hx-target="#jobs-table"
                                hx-swap="outerHTML"
                                hx-indicator="#jobs-table">
                            <i class="fa-solid fa-play fa-xs"></i>
                        </button>

                        <!-- Modifica -->
                        <a href="<?= e(route('scheduler.edit', ['id' => (int)$job['id']])) ?>"
                           class="btn btn-outline-secondary btn-sm"
                           data-bs-toggle="tooltip"
                           title="<?= e(t('common.action.edit')) ?>"
                           aria-label="<?= e(t('common.action.edit')) ?>">
                            <i class="fa-solid fa-pen fa-xs"></i>
                        </a>

                        <!-- Elimina -->
                        <span class="d-inline-flex" data-bs-toggle="tooltip" title="<?= e(t('common.action.delete')) ?>">
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    aria-label="<?= e(t('common.action.delete')) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal-delete-job"
                                    data-job-id="<?= (int)$job['id'] ?>"
                                    data-job-name="<?= e($job['display_name'] ?? $job['name']) ?>"
                                    data-job-url="<?= e(route('scheduler.destroy', ['id' => (int)$job['id']])) ?>">
                                <i class="fa-solid fa-trash-can fa-xs"></i>
                            </button>
                        </span>

                        <?php endif; ?>
                    </div>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
