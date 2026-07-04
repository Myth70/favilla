<?php
$view->layout('main');
$view->pushStyle('css/progetti.css');
$view->pushScript('js/apexcharts.min.js');
$view->pushScript('js/progetti-report.js');

$statusConfig      = \App\Modules\Progetti\Services\ProgettiService::getProjectStatuses();
$milestoneStatuses = \App\Modules\Progetti\Services\ProgettiService::getMilestoneStatuses();
$priorityConfig    = \App\Modules\Progetti\Services\ProgettiService::getPriorityConfig();
$taskStatuses      = \App\Modules\Progetti\Services\ProgettiService::getTaskStatuses();
$sc = $statusConfig[$project['status']] ?? $statusConfig['planning'];

$kpi          = $report['kpi'] ?? [];
$progressPct  = (int) ($kpi['progress_pct'] ?? 0);
$estimatedH   = (float) ($kpi['estimated_hours'] ?? 0);
$consumedH    = (float) ($kpi['consumed_hours'] ?? 0);
$hoursRatioPct= (float) ($kpi['hours_ratio_pct'] ?? 0);
$budgetPlanned= (float) ($kpi['budget_planned'] ?? 0);
$actualCost   = (float) ($kpi['actual_cost'] ?? 0);
$budgetBurnPct= (float) ($kpi['budget_burn_pct'] ?? 0);
$totalTasks   = (int)   ($kpi['total_tasks'] ?? 0);
$doneTasks    = (int)   ($kpi['done_tasks'] ?? 0);
$overdueTasks = (int)   ($kpi['overdue_tasks'] ?? 0);

$isActive = in_array($project['status'] ?? '', ['active', 'in_progress', 'planning'], true);

// Dynamic color helpers (centralizzati in \App\Modules\Progetti\Services\ProgettiService::kpiColor)
$progressColor = \App\Modules\Progetti\Services\ProgettiService::kpiColor((float) $progressPct, 'progress');
$hoursColor    = \App\Modules\Progetti\Services\ProgettiService::kpiColor($hoursRatioPct, 'burn');
$budgetColor   = \App\Modules\Progetti\Services\ProgettiService::kpiColor($budgetBurnPct, 'burn');
$taskColor     = $overdueTasks > 0 ? 'danger' : ($doneTasks === $totalTasks && $totalTasks > 0 ? 'success' : 'primary');

$heroButtons  = '<a href="' . e(route('projects.show', ['id' => (int) $project['id']])) . '" '
              . 'class="btn btn-outline-light btn-sm d-print-none">'
              . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('progetti.report.back_to_project')) . '</a> '
              . '<button onclick="window.print()" class="btn btn-light btn-sm d-print-none">'
              . '<i class="fa-solid fa-print me-1"></i>' . e(t('progetti.report.print')) . '</button>';

$heroSubtitle = '';
if (!empty($project['code']))       $heroSubtitle .= '<span class="me-2 opacity-75">' . e($project['code']) . '</span>';
if (!empty($project['client_name'])) $heroSubtitle .= '<i class="fa-solid fa-building me-1 opacity-50"></i>' . e($project['client_name']) . ' &nbsp;';
$heroSubtitle .= '<span class="badge bg-' . e($sc['color']) . '">' . e($sc['label']) . '</span>';
$heroSubtitle .= ' &middot; ' . e(t('progetti.report.generated_on', ['date' => date('d/m/Y H:i')]));
?>

<?php $view->start('content'); ?>

<?php $view->include('partials/pf-hero-module', [
    'moduleName'     => (string) $project['name'],
    'moduleIcon'     => 'fa-solid fa-chart-line',
    'moduleSubtitle' => $heroSubtitle,
    'moduleButtons'  => $heroButtons,
]); ?>

<div class="container-fluid prj-report-page pb-5">

    <!-- ── KPI Cards ──────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Avanzamento -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase"><?= e(t('progetti.report.progress')) ?></span>
                        <i class="fa-solid fa-gauge-high text-<?= e($progressColor) ?> opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <span class="fs-3 fw-bold text-<?= e($progressColor) ?>"><?= $progressPct ?>%</span>
                        <span class="text-muted small mb-1"><?= e(t('progetti.report.of_planned')) ?></span>
                    </div>
                    <div class="progress prj-progress-xs" data-bs-toggle="tooltip" title="<?= $progressPct ?>%">
                        <div class="progress-bar bg-<?= e($progressColor) ?>"
                             style="--prj-pct:<?= min($progressPct, 100) ?>%"></div>
                    </div>
                    <div class="text-muted small mt-1"><?= e(t('progetti.report.tasks_done', ['done' => $doneTasks, 'total' => $totalTasks])) ?></div>
                </div>
            </div>
        </div>

        <!-- Ore -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase"><?= e(t('progetti.report.hours')) ?></span>
                        <i class="fa-solid fa-clock text-<?= e($hoursColor) ?> opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <span class="fs-3 fw-bold text-<?= e($hoursColor) ?>"><?= number_format($consumedH, 1) ?> h</span>
                        <span class="text-muted small mb-1"><?= e(t('progetti.report.hours_of', ['hours' => number_format($estimatedH, 1)])) ?></span>
                    </div>
                    <div class="progress prj-progress-xs" data-bs-toggle="tooltip"
                         title="<?= $hoursRatioPct ?>%">
                        <div class="progress-bar bg-<?= e($hoursColor) ?>"
                             style="--prj-pct:<?= min($hoursRatioPct, 100) ?>%"></div>
                    </div>
                    <div class="text-muted small mt-1"><?= e(t('progetti.report.hours_pct', ['pct' => number_format($hoursRatioPct, 1)])) ?></div>
                </div>
            </div>
        </div>

        <!-- Budget -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase"><?= e(t('progetti.report.budget')) ?></span>
                        <i class="fa-solid fa-euro-sign text-<?= e($budgetColor) ?> opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <span class="fs-3 fw-bold text-<?= e($budgetColor) ?>">€ <?= number_format($actualCost, 0, ',', '.') ?></span>
                        <span class="text-muted small mb-1"><?= e(t('progetti.report.budget_of', ['amount' => number_format($budgetPlanned, 0, ',', '.')])) ?></span>
                    </div>
                    <div class="progress prj-progress-xs" data-bs-toggle="tooltip"
                         title="<?= $budgetBurnPct ?>%">
                        <div class="progress-bar bg-<?= e($budgetColor) ?>"
                             style="--prj-pct:<?= min($budgetBurnPct, 100) ?>%"></div>
                    </div>
                    <div class="text-muted small mt-1"><?= e(t('progetti.report.budget_pct', ['pct' => number_format($budgetBurnPct, 1)])) ?></div>
                </div>
            </div>
        </div>

        <!-- Attivita -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase"><?= e(t('progetti.report.tasks')) ?></span>
                        <i class="fa-solid fa-list-check text-<?= e($taskColor) ?> opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <span class="fs-3 fw-bold text-<?= e($taskColor) ?>"><?= $totalTasks ?></span>
                        <span class="text-muted small mb-1"><?= e(t('progetti.report.tasks_total')) ?></span>
                    </div>
                    <div class="d-flex gap-3 mt-1">
                        <span class="small" data-bs-toggle="tooltip" title="<?= e(t('progetti.report.tasks_done_tip')) ?>">
                            <i class="fa-solid fa-circle-check text-success me-1"></i><?= e(t('progetti.report.tasks_done_label', ['count' => $doneTasks])) ?>
                        </span>
                        <?php if ($overdueTasks > 0): ?>
                        <span class="small text-danger fw-semibold" data-bs-toggle="tooltip" title="<?= e(t('progetti.report.tasks_overdue_tip')) ?>">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?= e(t('progetti.report.tasks_overdue_label', ['count' => $overdueTasks])) ?>
                        </span>
                        <?php else: ?>
                        <span class="small text-muted">
                            <i class="fa-solid fa-circle-check text-muted me-1"></i><?= e(t('progetti.report.no_overdue')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Grafici ApexCharts ──────────────────────────────────────────────── -->
    <div class="row g-3 mb-4 d-print-none">

        <!-- Donut: Attivita per stato -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-chart-pie me-2 opacity-75"></i><?= e(t('progetti.report.chart_tasks')) ?></h2>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div id="prj-chart-donut" class="prj-report-chart"></div>
                </div>
            </div>
        </div>

        <!-- Bar: Ore per membro -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-users me-2 opacity-75"></i><?= e(t('progetti.report.chart_hours_user')) ?></h2>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div id="prj-chart-hours-user" class="prj-report-chart"></div>
                </div>
            </div>
        </div>

        <!-- Area: Trend settimanale -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-chart-area me-2 opacity-75"></i><?= e(t('progetti.report.chart_trend')) ?></h2>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div id="prj-chart-trend" class="prj-report-chart"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Dettagli + Milestone ──────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Dettagli progetto -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-circle-info me-2 opacity-75"></i><?= e(t('progetti.report.details_title')) ?></h2>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_code')) ?></dt>
                                <dd class="col-7"><?= e((string) ($project['code'] ?? t('progetti.report.na'))) ?></dd>
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_client')) ?></dt>
                                <dd class="col-7"><?= e((string) ($project['client_name'] ?? t('progetti.report.na'))) ?></dd>
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_owner')) ?></dt>
                                <dd class="col-7"><?= e((string) ($project['owner_name'] ?? t('progetti.report.na'))) ?></dd>
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_status')) ?></dt>
                                <dd class="col-7"><span class="badge bg-<?= e($sc['color']) ?>"><?= e($sc['label']) ?></span></dd>
                            </dl>
                        </div>
                        <div class="col-sm-6">
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_start')) ?></dt>
                                <dd class="col-7"><?= ($project['start_date'] ?? '') !== '' ? e(format_date((string)$project['start_date'], 'short')) : '—' ?></dd>
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_end')) ?></dt>
                                <dd class="col-7"><?= ($project['end_date'] ?? '') !== '' ? e(format_date((string)$project['end_date'], 'short')) : '—' ?></dd>
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_estimated')) ?></dt>
                                <dd class="col-7"><?= e(number_format($estimatedH, 1)) ?> h</dd>
                                <dt class="col-5 text-muted"><?= e(t('progetti.report.field_budget')) ?></dt>
                                <dd class="col-7">€ <?= e(number_format($budgetPlanned, 2, ',', '.')) ?></dd>
                            </dl>
                        </div>
                    </div>
                    <?php if (!empty($project['description'])): ?>
                    <hr class="my-3">
                    <p class="small text-muted mb-0"><?= e((string) $project['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Milestone list -->
        <?php if (!empty($report['milestones_list'])): ?>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-flag me-2 opacity-75"></i><?= e(t('progetti.report.milestones_status_title')) ?></h2>
                    <span class="badge bg-secondary"><?= count($report['milestones_list']) ?></span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($report['milestones_list'] as $ms):
                            $msCfg = $milestoneStatuses[$ms['status']] ?? ['label' => $ms['status'], 'color' => 'secondary'];
                            $msOver = ($ms['due_date'] ?? '') !== '' && $ms['status'] !== 'done' && strtotime((string) $ms['due_date']) < time();
                        ?>
                        <li class="list-group-item px-3 py-2 d-flex align-items-center gap-2">
                            <span class="badge bg-<?= e($msCfg['color']) ?> flex-shrink-0"><?= e($msCfg['label']) ?></span>
                            <span class="small fw-semibold flex-grow-1"><?= e($ms['name']) ?></span>
                            <?php if ($ms['billable']): ?>
                            <i class="fa-solid fa-file-invoice text-info flex-shrink-0"
                               data-bs-toggle="tooltip" title="<?= e(t('progetti.report.billable_tip')) ?>"></i>
                            <?php endif; ?>
                            <?php if (($ms['due_date'] ?? '') !== ''): ?>
                            <span class="small flex-shrink-0 <?= $msOver ? 'text-danger fw-semibold' : 'text-muted' ?>"
                                  data-bs-toggle="tooltip" title="<?= $msOver ? e(t('progetti.report.overdue_tip')) : e(t('progetti.report.due_tip')) ?>">
                                <?= e(format_date((string) $ms['due_date'], 'short')) ?>
                            </span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Budget per Milestone ──────────────────────────────────────────────── -->
    <?php if (!empty($report['budget_by_milestone'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h2 class="h6 mb-0"><i class="fa-solid fa-layer-group me-2 opacity-75"></i><?= e(t('progetti.report.budget_by_milestone_title')) ?></h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th><?= e(t('progetti.report.col_milestone')) ?></th>
                        <th class="text-center"><?= e(t('progetti.report.col_status')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_tasks')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_hours_planned')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_hours_actual')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_hours_delta')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_cost')) ?></th>
                        <th class="prj-col-util"><?= e(t('progetti.report.col_usage_pct')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $totEst = 0; $totCons = 0; $totCost = 0; $totTasks2 = 0;
                    foreach ($report['budget_by_milestone'] as $bm):
                        $est   = (float) $bm['estimated_hours'];
                        $cons  = (float) $bm['consumed_hours'];
                        $cost  = (float) $bm['consumed_cost'];
                        $delta = $cons - $est;
                        $pct   = $est > 0 ? round($cons / $est * 100, 1) : null;
                        $barColor = ($pct !== null && $pct > 100) ? 'danger' : (($pct !== null && $pct > 85) ? 'warning' : 'success');
                        $msStatus = $bm['milestone_status'] ?? '';
                        $msCfg2   = $milestoneStatuses[$msStatus] ?? ['label' => $msStatus, 'color' => 'secondary'];
                        $totEst += $est; $totCons += $cons; $totCost += $cost; $totTasks2 += (int) $bm['task_count'];
                    ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($bm['milestone_name']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= e($msCfg2['color']) ?> small"><?= e($msCfg2['label']) ?></span>
                        </td>
                        <td class="text-end small"><?= (int) $bm['task_count'] ?></td>
                        <td class="text-end small"><?= $est > 0 ? e(number_format($est, 1)) . ' h' : '—' ?></td>
                        <td class="text-end small"><?= $cons > 0 ? e(number_format($cons, 1)) . ' h' : '—' ?></td>
                        <td class="text-end small <?= $delta > 0 ? 'text-danger' : ($delta < 0 ? 'text-success' : 'text-muted') ?>">
                            <?php if ($est > 0): ?>
                                <?= $delta > 0 ? '+' : '' ?><?= e(number_format($delta, 1)) ?> h
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-end small">
                            <?= $cost > 0 ? '€ ' . e(number_format($cost, 2, ',', '.')) : '—' ?>
                        </td>
                        <td class="small">
                            <?php if ($pct !== null): ?>
                            <div class="d-flex align-items-center gap-2">
                                  <div class="progress flex-grow-1 prj-progress-xs"
                                     data-bs-toggle="tooltip" title="<?= $pct ?>%">
                                    <div class="progress-bar bg-<?= e($barColor) ?>"
                                         style="--prj-pct:<?= min($pct, 100) ?>%"></div>
                                </div>
                                <span class="text-<?= e($barColor) ?> fw-semibold prj-pct-label-md"><?= $pct ?>%</span>
                            </div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                    <?php
                    $totDelta = $totCons - $totEst;
                    $totPct   = $totEst > 0 ? round($totCons / $totEst * 100, 1) : null;
                    $totBar   = ($totPct !== null && $totPct > 100) ? 'danger' : (($totPct !== null && $totPct > 85) ? 'warning' : 'success');
                    ?>
                    <tr>
                        <td colspan="2"><?= e(t('progetti.report.total_row')) ?></td>
                        <td class="text-end"><?= $totTasks2 ?></td>
                        <td class="text-end"><?= $totEst > 0 ? e(number_format($totEst, 1)) . ' h' : '—' ?></td>
                        <td class="text-end"><?= $totCons > 0 ? e(number_format($totCons, 1)) . ' h' : '—' ?></td>
                        <td class="text-end <?= $totDelta > 0 ? 'text-danger' : ($totDelta < 0 ? 'text-success' : '') ?>">
                            <?= $totEst > 0 ? ($totDelta > 0 ? '+' : '') . e(number_format($totDelta, 1)) . ' h' : '—' ?>
                        </td>
                        <td class="text-end"><?= $totCost > 0 ? '€ ' . e(number_format($totCost, 2, ',', '.')) : '—' ?></td>
                        <td class="small">
                            <?php if ($totPct !== null): ?>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1 prj-progress-xs">
                                    <div class="progress-bar bg-<?= e($totBar) ?>"
                                         style="--prj-pct:<?= min($totPct, 100) ?>%"></div>
                                </div>
                                <span class="text-<?= e($totBar) ?> fw-bold prj-pct-label-md"><?= $totPct ?>%</span>
                            </div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Ore per Membro ──────────────────────────────────────────────── -->
    <?php if (!empty($report['timesheet_by_user'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h2 class="h6 mb-0"><i class="fa-solid fa-user-clock me-2 opacity-75"></i><?= e(t('progetti.report.hours_by_member_title')) ?></h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th><?= e(t('progetti.report.col_member')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_hours_total')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_cost_attributed')) ?></th>
                        <th data-bs-toggle="tooltip" title="<?= e(t('progetti.report.avg_rate_tip')) ?>"
                            class="text-end"><?= e(t('progetti.report.col_avg_rate')) ?></th>
                        <th class="prj-col-total-pct"><?= e(t('progetti.report.col_pct_total')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $totalHoursU = array_sum(array_column($report['timesheet_by_user'], 'total_hours'));
                    $totalCostU  = array_sum(array_column($report['timesheet_by_user'], 'total_cost'));
                    foreach ($report['timesheet_by_user'] as $row):
                        $rowH   = (float) $row['total_hours'];
                        $rowC   = (float) $row['total_cost'];
                        $rate   = $rowH > 0 ? $rowC / $rowH : 0;
                        $rowPct = $totalHoursU > 0 ? round($rowH / $totalHoursU * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?= e((string) $row['user_name']) ?></td>
                        <td class="text-end"><?= e(number_format($rowH, 1)) ?> h</td>
                        <td class="text-end">€ <?= e(number_format($rowC, 2, ',', '.')) ?></td>
                        <td class="text-end text-muted small">
                            <?= $rate > 0 ? '€ ' . e(number_format($rate, 2, ',', '.')) . '/h' : '—' ?>
                        </td>
                        <td class="small">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1 prj-progress-xs"
                                     data-bs-toggle="tooltip" title="<?= $rowPct ?>%">
                                    <div class="progress-bar prj-progress-accent" style="--prj-pct:<?= $rowPct ?>%"></div>
                                </div>
                                <span class="prj-pct-label-sm"><?= $rowPct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                    <tr>
                        <td><?= e(t('progetti.report.total_row')) ?></td>
                        <td class="text-end"><?= e(number_format((float) $totalHoursU, 1)) ?> h</td>
                        <td class="text-end">€ <?= e(number_format((float) $totalCostU, 2, ',', '.')) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Elenco Milestone ──────────────────────────────────────────────── -->
    <?php if (!empty($report['milestones_list'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <h2 class="h6 mb-0"><i class="fa-solid fa-flag me-2 opacity-75"></i><?= e(t('progetti.report.milestones_list_title')) ?></h2>
            <span class="badge bg-secondary"><?= count($report['milestones_list']) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th><?= e(t('progetti.report.col_name')) ?></th>
                        <th class="text-center"><?= e(t('progetti.report.col_status')) ?></th>
                        <th class="text-center"><?= e(t('progetti.report.col_billable')) ?></th>
                        <th><?= e(t('progetti.report.col_due')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_tasks')) ?></th>
                        <th class="prj-col-adv"><?= e(t('progetti.report.col_progress')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($report['milestones_list'] as $ms):
                        $msCfg    = $milestoneStatuses[$ms['status']] ?? ['label' => $ms['status'], 'color' => 'secondary'];
                        $msTotal  = (int) $ms['task_count'];
                        $msDone   = (int) $ms['done_tasks'];
                        $msPct    = $msTotal > 0 ? round($msDone / $msTotal * 100) : (int) $ms['progress_cached'];
                        $msColor  = $ms['status'] === 'done' ? 'success' : ($ms['status'] === 'missed' ? 'danger' : ($msPct >= 80 ? 'warning' : 'primary'));
                        $msOver   = ($ms['due_date'] ?? '') !== '' && $ms['status'] !== 'done' && strtotime((string) $ms['due_date']) < time();
                    ?>
                    <tr>
                        <td class="small fw-semibold">
                            <?= e((string) $ms['name']) ?>
                            <?php if (!empty($ms['description'])): ?>
                            <i class="fa-solid fa-circle-info text-muted ms-1 small"
                               data-bs-toggle="tooltip"
                               title="<?= e((string) $ms['description']) ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= e($msCfg['color']) ?>"><?= e($msCfg['label']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($ms['billable']): ?>
                                <i class="fa-solid fa-file-invoice text-info"
                                   data-bs-toggle="tooltip" title="<?= e(t('progetti.report.billable_tip')) ?>"></i>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small <?= $msOver ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?= ($ms['due_date'] ?? '') !== '' ? e(format_date((string) $ms['due_date'], 'short')) : '—' ?>
                            <?php if ($msOver): ?>
                                <i class="fa-solid fa-triangle-exclamation ms-1"
                                   data-bs-toggle="tooltip" title="<?= e(t('progetti.report.milestone_overdue_tip')) ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small"><?= $msDone ?> / <?= $msTotal ?></td>
                        <td class="small">
                            <div class="d-flex align-items-center gap-2">
                                  <div class="progress flex-grow-1 prj-progress-xs"
                                     data-bs-toggle="tooltip" title="<?= $msPct ?>%">
                                    <div class="progress-bar bg-<?= e($msColor) ?>"
                                         style="--prj-pct:<?= $msPct ?>%"></div>
                                </div>
                                  <span class="prj-pct-label-xs"><?= $msPct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Elenco Attivita ──────────────────────────────────────────────── -->
    <?php if (!empty($report['tasks_list'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <h2 class="h6 mb-0"><i class="fa-solid fa-list-check me-2 opacity-75"></i><?= e(t('progetti.report.tasks_list_title')) ?></h2>
            <span class="badge bg-secondary"><?= count($report['tasks_list']) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th><?= e(t('progetti.report.col_task')) ?></th>
                        <th><?= e(t('progetti.report.col_milestone')) ?></th>
                        <th><?= e(t('progetti.report.col_assigned')) ?></th>
                        <th class="text-center"><?= e(t('progetti.report.col_status')) ?></th>
                        <th class="text-center"><?= e(t('progetti.report.col_priority')) ?></th>
                        <th><?= e(t('progetti.report.col_due')) ?></th>
                        <th><?= e(t('progetti.report.col_completed_on')) ?></th>
                        <th class="text-end"><?= e(t('progetti.report.col_hours_estimated')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $statusOrder = ['todo', 'in_progress', 'review', 'done', 'cancelled'];
                    usort($report['tasks_list'], function($a, $b) use ($statusOrder) {
                        $ai = array_search($a['status'], $statusOrder, true);
                        $bi = array_search($b['status'], $statusOrder, true);
                        if ($ai !== $bi) return $ai - $bi;
                        return strcmp((string)($a['due_date'] ?? ''), (string)($b['due_date'] ?? ''));
                    });
                    foreach ($report['tasks_list'] as $t):
                        $stCfg = $taskStatuses[$t['status']] ?? ['label' => $t['status'], 'color' => 'secondary'];
                        $prCfg = $priorityConfig[$t['priority']] ?? ['label' => $t['priority'], 'color' => 'secondary'];
                        $isOverdue = ($t['due_date'] ?? '') !== ''
                            && ($t['completed_at'] ?? '') === ''
                            && strtotime((string) $t['due_date']) < time();
                    ?>
                    <tr>
                        <td class="small fw-semibold"><?= e((string) $t['title']) ?></td>
                        <td class="small text-muted"><?= e((string) ($t['milestone_name'] ?? '—')) ?></td>
                        <td class="small"><?= e((string) ($t['assigned_user_name'] ?? '—')) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= e($stCfg['color']) ?>"><?= e($stCfg['label']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= e($prCfg['color']) ?>"><?= e($prCfg['label']) ?></span>
                        </td>
                        <td class="small <?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?= ($t['due_date'] ?? '') !== '' ? e(format_date((string)$t['due_date'], 'short')) : '—' ?>
                            <?php if ($isOverdue): ?>
                            <i class="fa-solid fa-triangle-exclamation ms-1"
                               data-bs-toggle="tooltip" title="<?= e(t('progetti.report.task_overdue_tip')) ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= ($t['completed_at'] ?? '') !== '' ? e(format_date((string)$t['completed_at'], 'short')) : '—' ?>
                        </td>
                        <td class="text-end small"><?= e(number_format((float) ($t['estimated_hours'] ?? 0), 1)) ?> h</td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-muted small text-end mt-2 d-print-block">
        <?= e(t('progetti.report.footer', ['date' => date('d/m/Y H:i')])) ?>
    </div>

</div>

<!-- ── Dati per ApexCharts (letti dal JS dopo DOMContentLoaded) ── -->
<script id="prj-report-task-status" type="application/json"><?= json_encode(array_values($report['tasks_by_status'] ?? [])) ?></script>
<script id="prj-report-hours-user"  type="application/json"><?= json_encode(array_values($kpi['hours_by_user'] ?? [])) ?></script>
<script id="prj-report-hours-trend" type="application/json"><?= json_encode(array_values($kpi['hours_trend'] ?? [])) ?></script>
<script id="prj-report-status-labels" type="application/json"><?= json_encode(array_map(fn($v) => $v['label'], $taskStatuses)) ?></script>

<!-- ApexCharts init in public/assets/js/progetti-report.js (caricato via pushScript dopo apexcharts.min.js) -->
<?php $view->end(); ?>
