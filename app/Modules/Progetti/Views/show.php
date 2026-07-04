<?php

use App\Modules\Progetti\Services\ProgettiService;

$view->layout('main');
$view->pushStyle('css/progetti.css');
$view->pushScript('js/apexcharts.min.js');
$view->pushScript('js/vendor/Sortable.min.js');
$view->pushScript('js/progetti.js');

$pid = (int) $project['id'];
$statusConfig      = ProgettiService::getProjectStatuses();
$taskStatuses      = ProgettiService::getTaskStatuses();
$priorityConfig    = ProgettiService::getPriorityConfig();
$milestoneStatuses = ProgettiService::getMilestoneStatuses();

$st = $statusConfig[$project['status']] ?? $statusConfig['planning'];
$members    = $management['members'] ?? [];
$milestones = $management['milestones'] ?? [];
$tasks      = $management['tasks'] ?? [];
$projFiles  = $management['project_files'] ?? [];
$progress   = (float) ($project['progress_cached'] ?? 0);

$totalTasks   = count($tasks);
$doneTasks    = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'done'));
$overdueTasks = count(array_filter($tasks, fn($t) =>
    !empty($t['due_date']) && $t['due_date'] < date('Y-m-d') && ($t['status'] ?? '') !== 'done'
));

// KPI per il tab Dashboard
$kpi         = $kpi ?? [];
$kpiProgress = (float) ($kpi['progress_pct'] ?? $progress);
$consumed    = (float) ($kpi['consumed_hours'] ?? 0);
$estimated   = (float) ($kpi['estimated_hours'] ?? ($project['estimated_hours'] ?? 0));
$hoursRatio  = (float) ($kpi['hours_ratio_pct'] ?? 0);
$budgetBurn  = (float) ($kpi['budget_burn_pct'] ?? 0);
$actualCost  = (float) ($kpi['actual_cost'] ?? ($project['budget_actual_cached'] ?? 0));
$budgetPlan  = (float) ($kpi['budget_planned'] ?? ($project['budget_planned'] ?? 0));
$kpiOverdue  = (int) ($kpi['overdue_tasks'] ?? $overdueTasks);
$missedMs    = (int) ($kpi['missed_milestones'] ?? 0);
$kpiTotal    = (int) ($kpi['total_tasks'] ?? $totalTasks);
$kpiDone     = (int) ($kpi['done_tasks'] ?? $doneTasks);
$billableMs  = (int) ($kpi['billable_milestones'] ?? 0);
$billableDone = (int) ($kpi['billable_done'] ?? 0);

// Dipendenze per popolare il modal task edit
$depEdges = $management['dependency_edges'] ?? [];
$depsBySuccessor = [];
foreach ($depEdges as $de) {
    $depsBySuccessor[(int) $de['successor_task_id']][] = (int) $de['predecessor_task_id'];
}
$taskOptionsById = [];
foreach (($management['task_options'] ?? []) as $to) {
    $taskOptionsById[(int) $to['id']] = (string) $to['title'];
}

// Dati per grafici
$hoursTrend   = $kpi['hours_trend'] ?? [];
$userBarData  = $kpi['hours_by_user'] ?? [];

// Distribuzione task per stato (da $tasks già disponibile)
$tasksByStatus = [];
foreach ($tasks as $t) {
    $s = $t['status'] ?? 'todo';
    $tasksByStatus[$s] = ($tasksByStatus[$s] ?? 0) + 1;
}
$taskStatusOrder  = ['todo', 'in_progress', 'review', 'blocked', 'done'];
$taskStatusLabels = array_map(fn ($k) => $taskStatuses[$k]['label'], $taskStatusOrder);
// Token semantici Bootstrap risolti runtime via CSS custom properties (theme-aware)
$taskStatusColors = ['secondary', 'primary', 'warning', 'danger', 'success'];
$taskStatusCounts = array_map(fn($s) => $tasksByStatus[$s] ?? 0, $taskStatusOrder);

// JSON per grafici ApexCharts (serializzati in PHP, letti dal JS)
$chartTaskStatus = json_encode([
    'series' => $taskStatusCounts,
    'labels' => $taskStatusLabels,
    'colors' => $taskStatusColors,
]);

$chartUserBar = json_encode([
    'names'  => array_column($userBarData, 'user_name'),
    'hours'  => array_map(fn($r) => (float) $r['hours'], $userBarData),
    'costs'  => array_map(fn($r) => (float) $r['cost'], $userBarData),
]);

$chartTrend = json_encode([
    'dates' => array_column($hoursTrend, 'week_start'),
    'hours' => array_map(fn($r) => (float) $r['hours'], $hoursTrend),
]);
?>

<?php $view->start('content'); ?>
<div class="container-fluid prj-page" data-project-id="<?= $pid ?>"
     data-prj-start="<?= e((string) ($project['start_date'] ?? '')) ?>"
     data-prj-end="<?= e((string) ($project['end_date'] ?? '')) ?>"
     data-prj-deps="<?= e(json_encode($depsBySuccessor)) ?>"
     data-prj-task-titles="<?= e(json_encode($taskOptionsById)) ?>">

    <!-- ═══ PROJECT HERO ═══ -->
    <?php
    $progressColor = ProgettiService::kpiColor($progress, 'progress');
    $daysLeft = null;
    if (!empty($project['end_date'])) {
        $endDt    = \DateTime::createFromFormat('Y-m-d', $project['end_date']);
        $today    = new \DateTime('today');
        $diff     = $today->diff($endDt);
        $daysLeft = $diff->invert ? -(int)$diff->days : (int)$diff->days;
    }
    ?>
    <div class="prj-hero prj-hero-<?= e($project['status']) ?> mb-3">
        <!-- Top bar: titolo + azioni -->
        <div class="prj-hero-top">
            <div class="prj-hero-title-group">
                <div class="prj-hero-icon prj-hero-icon-<?= e($st['color']) ?>">
                    <i class="fa-solid <?= e($st['icon']) ?>"></i>
                </div>
                <div class="prj-hero-title-block">
                    <h4 class="prj-hero-title"><?= e((string) $project['name']) ?></h4>
                    <div class="prj-hero-badges">
                        <span class="badge bg-<?= e($st['color']) ?>"><?= e($st['label']) ?></span>
                        <?php if (!empty($project['code'])): ?>
                        <span class="badge prj-badge-code"><?= e($project['code']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="prj-hero-actions">
                <?php if (!empty($project['teams_conversation_id'])): ?>
                <a href="<?= e(route('teams.show', ['id' => (int) $project['teams_conversation_id']])) ?>"
                         class="btn btn-sm prj-btn-ghost prj-btn-ghost-info" data-bs-toggle="tooltip" title="<?= e(t('progetti.show.conversation')) ?>">
                    <i class="fa-solid fa-comments"></i><span class="prj-btn-label"><?= e(t('progetti.show.conversation_short')) ?></span>
                </a>
                <?php endif; ?>
                <a href="<?= e(route('projects.report', ['id' => $pid])) ?>"
                         class="btn btn-sm prj-btn-ghost prj-btn-ghost-secondary" data-bs-toggle="tooltip" title="<?= e(t('progetti.show.report_tip')) ?>">
                    <i class="fa-solid fa-file-lines"></i><span class="prj-btn-label"><?= e(t('progetti.show.report_short')) ?></span>
                </a>
                <?php if (has_permission('progetti.edit')): ?>
                <a href="<?= e(route('projects.edit', ['id' => $pid])) ?>"
                   class="btn btn-sm prj-btn-ghost prj-btn-ghost-warning" data-bs-toggle="tooltip" title="<?= e(t('progetti.show.edit_project_tip')) ?>">
                    <i class="fa-solid fa-pen-to-square"></i><span class="prj-btn-label"><?= e(t('progetti.show.edit_short')) ?></span>
                </a>
                <?php endif; ?>
                <?php if (has_permission('progetti.delete')): ?>
                <button type="button"
                        class="btn btn-sm prj-btn-ghost prj-btn-ghost-danger"
                        data-prj-confirm-action="1"
                        data-prj-confirm-title="<?= e(t('progetti.show.delete_title')) ?>"
                        data-prj-confirm-message="<?= e(t('progetti.show.delete_message', ['name' => (string) $project['name']])) ?>"
                        data-prj-confirm-action-url="<?= e(route('projects.destroy', ['id' => $pid])) ?>"
                        data-prj-confirm-submit="<?= e(t('progetti.show.delete_submit')) ?>"
                        data-prj-confirm-icon="fa-diagram-project"
                        data-bs-toggle="tooltip"
                        title="<?= e(t('progetti.show.delete_project_tip')) ?>">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Progress bar prominente -->
        <div class="prj-hero-progress-wrap">
            <div class="prj-hero-progress-header">
                <div class="prj-hero-progress-label">
                    <i class="fa-solid fa-bars-progress me-1"></i><?= e(t('progetti.show.progress')) ?>
                </div>
                <div class="prj-hero-progress-stats">
                    <span class="prj-hero-progress-pct"><?= e(number_format($progress, 0)) ?>%</span>
                    <span class="prj-hero-progress-detail"><?= e(t('progetti.show.tasks_count', ['done' => $doneTasks, 'total' => $totalTasks])) ?></span>
                </div>
            </div>
            <div class="prj-hero-progress-track">
                <div class="prj-hero-progress-fill prj-hero-progress-<?= e($progressColor) ?>"
                     data-prj-pct="<?= e((string) min(100, $progress)) ?>"></div>
            </div>
            <?php if ($overdueTasks > 0): ?>
            <div class="prj-hero-progress-alert">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e(t('progetti.show.tasks_overdue', ['count' => $overdueTasks])) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info pills -->
        <div class="prj-hero-meta">
            <div class="prj-hero-pill">
                <i class="fa-solid fa-building"></i>
                <span><?= e((string) ($project['client_name'] ?: t('progetti.show.no_client'))) ?></span>
            </div>
            <div class="prj-hero-pill">
                <i class="fa-solid fa-user-tie"></i>
                <span><?= e((string) ($project['owner_name'] ?: t('progetti.show.no_owner'))) ?></span>
            </div>
            <?php if (!empty($project['start_date']) || !empty($project['end_date'])): ?>
            <div class="prj-hero-pill">
                <i class="fa-regular fa-calendar"></i>
                <span>
                    <?= ($project['start_date'] ?? '') !== '' ? e(format_date((string)$project['start_date'], 'short')) : '?' ?>
                    <i class="fa-solid fa-arrow-right-long prj-hero-pill-arrow"></i>
                    <?= ($project['end_date'] ?? '') !== '' ? e(format_date((string)$project['end_date'], 'short')) : '?' ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ((float) ($project['budget_planned'] ?? 0) > 0): ?>
            <div class="prj-hero-pill">
                <i class="fa-solid fa-coins"></i>
                <span>€ <?= e(number_format((float) $project['budget_planned'], 0, ',', '.')) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($daysLeft !== null): ?>
            <div class="prj-hero-pill <?= $daysLeft < 0 ? 'prj-hero-pill-danger' : ($daysLeft <= 7 ? 'prj-hero-pill-warning' : '') ?>">
                <i class="fa-solid fa-hourglass-half"></i>
                <span><?php if ($daysLeft < 0): ?><?= e(t('progetti.show.overdue_by', ['days' => abs($daysLeft)])) ?><?php elseif ($daysLeft === 0): ?><?= e(t('progetti.show.due_today')) ?><?php else: ?><?= e(t('progetti.show.due_in_days', ['days' => $daysLeft])) ?><?php endif; ?></span>
            </div>
            <?php endif; ?>
            <?php if (count($members) > 0): ?>
            <div class="prj-hero-pill">
                <i class="fa-solid fa-users"></i>
                <span><?= e(tc('progetti.show.members_count', count($members))) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($project['description'])): ?>
        <div class="prj-hero-description mt-2">
            <p class="small text-muted mb-0"><?= e((string) $project['description']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB NAVIGATION ═══ -->
    <ul class="nav prj-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link active" data-prj-tab="overview">
                <i class="fa-solid fa-layer-group me-1"></i><?= e(t('progetti.show.tab_overview')) ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-prj-tab="dashboard">
                <i class="fa-solid fa-gauge-high me-1"></i><?= e(t('progetti.show.tab_dashboard')) ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-prj-tab="kanban"
               data-prj-tab-url="<?= e(route('projects.kanban', ['id' => $pid])) ?>">
                <i class="fa-solid fa-columns me-1"></i><?= e(t('progetti.show.tab_kanban')) ?>
                <span class="badge bg-secondary bg-opacity-25 ms-1"><?= $totalTasks ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-prj-tab="gantt"
               data-prj-tab-url="<?= e(route('projects.gantt', ['id' => $pid])) ?>">
                <i class="fa-solid fa-bars-staggered me-1"></i><?= e(t('progetti.show.tab_gantt')) ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-prj-tab="timesheet"
               data-prj-tab-url="<?= e(route('projects.timesheet', ['id' => $pid])) ?>">
                <i class="fa-solid fa-clock me-1"></i><?= e(t('progetti.show.tab_timesheet')) ?>
            </a>
        </li>
    </ul>

    <!-- ═══ TAB CONTENT ═══ -->
    <div class="prj-tab-content">

        <!-- ── OVERVIEW PANE ── -->
        <div id="prj-pane-overview" class="prj-tab-pane prj-tab-active">

            <!-- Quick Stats -->
            <?php if ($overdueTasks > 0): ?>
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="prj-stat-mini">
                        <div class="prj-stat-mini-icon bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-exclamation-triangle"></i></div>
                        <div><div class="prj-stat-mini-value text-danger"><?= $overdueTasks ?></div><div class="prj-stat-mini-label"><?= e(t('progetti.show.overdue_tasks_label')) ?></div></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3">
                <!-- ── MEMBERS ── -->
                <div class="col-lg-6">
                    <div class="card prj-section-card prj-accent-info h-100">
                        <div class="card-header">
                            <span><i class="fa-solid fa-users me-1"></i><?= e(t('progetti.show.members_title')) ?></span>
                            <span class="badge bg-secondary"><?= count($members) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($members)): ?>
                            <p class="text-muted small mb-0"><?= e(t('progetti.show.no_members')) ?></p>
                            <?php else: ?>
                            <?php
                            // Avviso tariffe mancanti quando il budget è impostato
                            $budgetPlanned = (float) ($project['budget_planned'] ?? 0);
                            $membersWithoutRate = array_filter($members, fn($m) => empty($m['hourly_rate_override']));
                            if (!empty($canManageMembers) && $budgetPlanned > 0 && count($membersWithoutRate) > 0):
                            ?>
                            <div class="alert alert-warning alert-sm py-1 px-2 mb-2 d-flex align-items-center gap-2 small">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span><?= e(tc('progetti.show.missing_rate_warning', count($membersWithoutRate))) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php
                            $roleLabels = ['owner' => t('progetti.show.role_owner'), 'member' => t('progetti.show.role_member'), 'viewer' => t('progetti.show.role_viewer')];
                            foreach ($members as $member):
                                $isOwnerM = (($member['role'] ?? '') === 'owner');
                                $mAvatarUrl = \App\Modules\Auth\Helpers\AvatarHelper::url($member['avatar_path'] ?? null);
                                $mInitials  = \App\Modules\Auth\Helpers\AvatarHelper::initials($member['name'] ?? '?');
                                $mEditId = 'prj-member-edit-' . (int) $member['user_id'];
                            ?>
                            <div class="prj-member-item flex-wrap">
                                <?php if ($mAvatarUrl): ?>
                                <img src="<?= e($mAvatarUrl) ?>" alt="" class="rounded-circle prj-member-avatar-img">
                                <?php else: ?>
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white prj-member-avatar prj-member-avatar-fallback">
                                    <?= e($mInitials) ?>
                                </span>
                                <?php endif; ?>
                                <div class="prj-member-info flex-grow-1">
                                    <div class="prj-member-name"><?= e((string) $member['name']) ?></div>
                                    <div class="prj-member-role">
                                        <?php if ($isOwnerM): ?>
                                        <span class="badge bg-dark badge-sm"><?= e(t('progetti.show.role_owner')) ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary badge-sm"><?= e($roleLabels[$member['role']] ?? $member['role']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($member['hourly_rate_override'])): ?>
                                        · <?= e(number_format((float) $member['hourly_rate_override'], 2, ',', '.')) ?> €/h
                                        <?php else: ?>
                                        <?php if (!empty($canManageMembers)): ?>
                                        · <span class="text-warning small"><i class="fa-solid fa-triangle-exclamation"></i> <?= e(t('progetti.show.no_rate')) ?></span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($canManageMembers)): ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary border-0 opacity-50"
                                        onclick="document.getElementById('<?= e($mEditId) ?>').classList.toggle('d-none')"
                                        data-bs-toggle="tooltip"
                                        title="<?= e(t('progetti.show.edit_rate_tip')) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <?php if (!$isOwnerM): ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger border-0 opacity-50"
                                        data-prj-confirm-action="1"
                                        data-prj-confirm-title="<?= e(t('progetti.show.remove_member_title')) ?>"
                                        data-prj-confirm-message="<?= e(t('progetti.show.remove_member_message', ['name' => (string) ($member['name'] ?? '')])) ?>"
                                        data-prj-confirm-action-url="<?= e(route('projects.members.destroy', ['id' => $pid, 'memberId' => (int) $member['user_id']])) ?>"
                                        data-prj-confirm-submit="<?= e(t('progetti.show.remove_member_submit')) ?>"
                                        data-prj-confirm-icon="fa-user-minus"
                                        data-bs-toggle="tooltip"
                                        title="<?= e(t('progetti.show.remove_member_tip')) ?>">
                                    <i class="fa-solid fa-user-minus"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($canManageMembers)): ?>
                                <div id="<?= e($mEditId) ?>" class="d-none w-100 mt-1">
                                    <form method="POST" action="<?= e(route('projects.members.update', ['id' => $pid, 'memberId' => (int) $member['user_id']])) ?>" class="row g-1 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_method" value="PUT">
                                        <?php if (!$isOwnerM): ?>
                                        <div class="col-auto">
                                            <select class="form-select form-select-sm" name="role">
                                                <option value="member" <?= ($member['role'] ?? '') === 'member' ? 'selected' : '' ?>><?= e(t('progetti.show.role_member')) ?></option>
                                                <option value="viewer" <?= ($member['role'] ?? '') === 'viewer' ? 'selected' : '' ?>><?= e(t('progetti.show.role_viewer')) ?></option>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-auto">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm prj-show-input-budget" name="hourly_rate_override"
                                                   placeholder="€/h"
                                                   value="<?= !empty($member['hourly_rate_override']) ? e((string) (float) $member['hourly_rate_override']) : '' ?>">
                                        </div>
                                        <div class="col-auto">
                                            <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-check"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('<?= e($mEditId) ?>').classList.add('d-none')"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($canManageMembers) && !empty($management['available_member_options'] ?? [])): ?>
                        <div class="prj-add-form">
                            <form method="POST" action="<?= e(route('projects.members.store', ['id' => $pid])) ?>" class="row g-2">
                                <?= csrf_field() ?>
                                <div class="col-5">
                                    <select class="form-select form-select-sm" name="user_id" required>
                                        <option value=""><?= e(t('progetti.show.add_user_placeholder')) ?></option>
                                        <?php foreach ($management['available_member_options'] as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <select class="form-select form-select-sm" name="role">
                                        <option value="member"><?= e(t('progetti.show.role_member')) ?></option>
                                        <option value="viewer"><?= e(t('progetti.show.role_viewer')) ?></option>
                                    </select>
                                </div>
                                <div class="col-2">
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="hourly_rate_override" placeholder="€/h">
                                </div>
                                <div class="col-2">
                                    <button class="btn btn-sm btn-primary w-100" type="submit"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── MILESTONES ── -->
                <div class="col-lg-6">
                    <div class="card prj-section-card prj-accent-warning h-100">
                        <div class="card-header">
                            <span><i class="fa-solid fa-flag me-1"></i><?= e(t('progetti.show.milestones_title')) ?></span>
                            <span class="badge bg-secondary"><?= count($milestones) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($milestones)): ?>
                            <p class="text-muted small mb-0"><?= e(t('progetti.show.no_milestones')) ?></p>
                            <?php else: ?>
                            <div class="ps-2">
                                <?php foreach ($milestones as $ms): ?>
                                <div class="prj-milestone-item">
                                    <div class="prj-milestone-dot prj-ms-<?= e($ms['status']) ?>"></div>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="prj-milestone-title"><?= e($ms['name']) ?></div>
                                            <div class="prj-milestone-meta">
                                                <span class="badge bg-<?= e($milestoneStatuses[$ms['status']]['color'] ?? 'secondary') ?> me-1"><?= e($milestoneStatuses[$ms['status']]['label'] ?? $ms['status']) ?></span>
                                                <?php if (!empty($ms['due_date'])): ?>
                                                <i class="fa-regular fa-calendar me-1"></i><?= e(format_date((string)$ms['due_date'], 'short')) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (has_permission('progetti.edit')): ?>
                                        <div class="d-flex gap-1">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-warning border-0 opacity-75"
                                                    data-prj-ms-edit="1"
                                                    data-prj-ms-id="<?= (int) $ms['id'] ?>"
                                                    data-prj-ms-name="<?= e((string) ($ms['name'] ?? '')) ?>"
                                                    data-prj-ms-description="<?= e((string) ($ms['description'] ?? '')) ?>"
                                                    data-prj-ms-due-date="<?= e((string) ($ms['due_date'] ?? '')) ?>"
                                                    data-prj-ms-status="<?= e((string) ($ms['status'] ?? 'pending')) ?>"
                                                    data-prj-ms-billable="<?= !empty($ms['billable']) ? '1' : '0' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= e(t('progetti.show.edit_milestone_tip')) ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger border-0 opacity-50"
                                                    data-prj-confirm-action="1"
                                                    data-prj-confirm-title="<?= e(t('progetti.show.delete_milestone_title')) ?>"
                                                    data-prj-confirm-message="<?= e(t('progetti.show.delete_milestone_message', ['name' => (string) ($ms['name'] ?? '')])) ?>"
                                                    data-prj-confirm-action-url="<?= e(route('projects.milestones.destroy', ['id' => $pid, 'milestoneId' => (int) $ms['id']])) ?>"
                                                    data-prj-confirm-submit="<?= e(t('progetti.show.delete_milestone_submit')) ?>"
                                                    data-prj-confirm-icon="fa-flag"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= e(t('progetti.show.delete_milestone_tip')) ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (has_permission('progetti.edit')): ?>
                        <div class="prj-add-form">
                            <form method="POST" action="<?= e(route('projects.milestones.store', ['id' => $pid])) ?>" class="row g-2">
                                <?= csrf_field() ?>
                                <div class="col-4"><input type="text" class="form-control form-control-sm" name="name" placeholder="<?= e(t('progetti.show.milestone_name_placeholder')) ?>" required></div>
                                <div class="col-3"><input type="date" class="form-control form-control-sm" name="due_date"
                                    <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                                    <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>
                                ></div>
                                <div class="col-2">
                                    <select class="form-select form-select-sm" name="status">
                                        <?php foreach (['pending', 'in_progress', 'done', 'missed'] as $msKey): ?>
                                        <option value="<?= e($msKey) ?>"><?= e($milestoneStatuses[$msKey]['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-2">
                                    <div class="form-check form-check-sm pt-1">
                                        <input class="form-check-input" type="checkbox" name="billable" value="1" id="ms-billable">
                                        <label class="form-check-label small" for="ms-billable"><?= e(t('progetti.show.billable')) ?></label>
                                    </div>
                                </div>
                                <div class="col-1"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="fa-solid fa-plus"></i></button></div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── TASKS ── -->
            <div class="row g-3 mt-0">
                <div class="col-12">
                    <div class="card prj-section-card prj-accent-success">
                        <div class="card-header">
                            <span><i class="fa-solid fa-list-check me-1"></i><?= e(t('progetti.show.tasks_title')) ?></span>
                            <span class="badge bg-secondary"><?= $totalTasks ?></span>
                        </div>
                        <?php if (empty($tasks)): ?>
                        <div class="card-body"><p class="text-muted small mb-0"><?= e(t('progetti.show.no_tasks')) ?></p></div>
                        <?php else: ?>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th><?= e(t('progetti.show.col_task')) ?></th>
                                        <th class="d-none d-md-table-cell text-center"><?= e(t('progetti.show.col_checklist')) ?></th>
                                        <th><?= e(t('progetti.show.col_status')) ?></th>
                                        <th><?= e(t('progetti.show.col_priority')) ?></th>
                                        <th><?= e(t('progetti.show.col_assigned')) ?></th>
                                        <th><?= e(t('progetti.show.col_due')) ?></th>
                                        <th class="text-end"><?= e(t('progetti.show.col_hours')) ?></th>
                                        <th class="text-end"><?= e(t('progetti.show.col_actions')) ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($tasks as $task):
                                        $tIsOverdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && ($task['status'] ?? '') !== 'done';
                                        $tIsDone    = ($task['status'] ?? '') === 'done';
                                    ?>
                                    <tr class="<?= $tIsOverdue ? 'prj-row-late' : '' ?> <?= $tIsDone ? 'prj-row-completed' : '' ?>">
                                        <td>
                                            <div class="fw-semibold small"><?= e($task['title']) ?></div>
                                            <?php if (!empty($task['milestone_name'] ?? '')): ?>
                                            <div class="text-muted small"><i class="fa-solid fa-flag me-1"></i><?= e($task['milestone_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-none d-md-table-cell text-center">
                                            <?php
                                            $clT = (int) ($task['checklist_total'] ?? 0);
                                            $clD = (int) ($task['checklist_done'] ?? 0);
                                            $clP = $clT > 0 ? round($clD / $clT * 100) : 0;
                                            ?>
                                            <?php if ($clT > 0): ?>
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <div class="progress prj-progress-thin prj-progress-w-50">
                                                    <div class="progress-bar bg-<?= $clD === $clT ? 'success' : 'primary' ?>" style="--prj-pct:<?= $clP ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?= $clD ?>/<?= $clT ?></small>
                                            </div>
                                            <?php else: ?>
                                            <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?= e($taskStatuses[$task['status']]['color'] ?? 'secondary') ?>"><?= e($taskStatuses[$task['status']]['label'] ?? $task['status']) ?></span></td>
                                        <td><span class="badge bg-<?= e($priorityConfig[$task['priority']]['color'] ?? 'secondary') ?> bg-opacity-75"><?= e($priorityConfig[$task['priority']]['label'] ?? $task['priority']) ?></span></td>
                                        <td class="small"><?= e((string) ($task['assigned_user_name'] ?? '—')) ?></td>
                                        <td class="small <?= $tIsOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                            <?= ($task['due_date'] ?? '') !== '' ? e(format_date((string)$task['due_date'], 'short')) : '—' ?>
                                        </td>
                                        <td class="text-end small text-muted"><?= e(number_format((float) ($task['estimated_hours'] ?? 0), 1)) ?></td>
                                        <td class="text-end">
                                            <?php if (has_permission('progetti.edit')): ?>
                                            <div class="d-flex justify-content-end gap-1">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-warning border-0"
                                                        data-prj-task-edit="1"
                                                        data-prj-task-id="<?= (int) $task['id'] ?>"
                                                        data-prj-task-title="<?= e((string) ($task['title'] ?? '')) ?>"
                                                        data-prj-task-description="<?= e((string) ($task['description'] ?? '')) ?>"
                                                        data-prj-task-milestone-id="<?= e((string) ($task['milestone_id'] ?? '')) ?>"
                                                        data-prj-task-assigned-user-id="<?= e((string) ($task['assigned_user_id'] ?? '')) ?>"
                                                        data-prj-task-priority="<?= e((string) ($task['priority'] ?? 'medium')) ?>"
                                                        data-prj-task-status="<?= e((string) ($task['status'] ?? 'todo')) ?>"
                                                        data-prj-task-start-date="<?= e((string) ($task['start_date'] ?? '')) ?>"
                                                        data-prj-task-due-date="<?= e((string) ($task['due_date'] ?? '')) ?>"
                                                        data-prj-task-estimated-hours="<?= e((string) ($task['estimated_hours'] ?? '0')) ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="<?= e(t('progetti.show.edit_task_tip')) ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger border-0"
                                                        data-prj-confirm-action="1"
                                                        data-prj-confirm-title="<?= e(t('progetti.show.delete_task_title')) ?>"
                                                        data-prj-confirm-message="<?= e(t('progetti.show.delete_task_message', ['name' => (string) ($task['title'] ?? '')])) ?>"
                                                        data-prj-confirm-action-url="<?= e(route('projects.tasks.destroy', ['id' => $pid, 'taskId' => (int) $task['id']])) ?>"
                                                        data-prj-confirm-submit="<?= e(t('progetti.show.delete_task_submit')) ?>"
                                                        data-prj-confirm-icon="fa-list-check"
                                                        data-bs-toggle="tooltip"
                                                        title="<?= e(t('progetti.show.delete_task_tip')) ?>">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (has_permission('progetti.edit')): ?>
                        <div class="prj-add-form">
                            <form method="POST" action="<?= e(route('projects.tasks.store', ['id' => $pid])) ?>">
                                <?= csrf_field() ?>
                                <!-- Riga principale -->
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" name="title" placeholder="<?= e(t('progetti.show.task_title_placeholder')) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" name="milestone_id">
                                            <option value=""><?= e(t('progetti.show.milestone_placeholder')) ?></option>
                                            <?php foreach ($milestones as $ms): ?>
                                            <option value="<?= (int) $ms['id'] ?>"><?= e((string) $ms['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select form-select-sm" name="assigned_user_id">
                                            <option value=""><?= e(t('progetti.show.assign_placeholder')) ?></option>
                                            <?php foreach (($management['member_options'] ?? []) as $mo): ?>
                                            <option value="<?= (int) $mo['id'] ?>"><?= e($mo['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#prj-task-extra-fields" title="<?= e(t('progetti.show.more_options_tip')) ?>">
                                            <i class="fa-solid fa-sliders"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" type="submit" data-bs-toggle="tooltip" title="<?= e(t('progetti.show.add_task_tip')) ?>"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                </div>
                                <!-- Riga opzioni extra (collassabile) -->
                                <div class="collapse mt-2" id="prj-task-extra-fields">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-0"><?= e(t('progetti.show.priority_label')) ?></label>
                                            <select class="form-select form-select-sm" name="priority">
                                                <option value="low"><?= e(t('progetti.priority.low')) ?></option>
                                                <option value="medium" selected><?= e(t('progetti.priority.medium')) ?></option>
                                                <option value="high"><?= e(t('progetti.priority.high')) ?></option>
                                                <option value="urgent"><?= e(t('progetti.priority.urgent')) ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted mb-0"><?= e(t('progetti.show.start_date_label')) ?></label>
                                            <input type="date" class="form-control form-control-sm" name="start_date"
                                                <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                                                <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>
                                            >
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted mb-0"><?= e(t('progetti.show.due_date_label')) ?></label>
                                            <input type="date" class="form-control form-control-sm" name="due_date"
                                                <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                                                <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>
                                            >
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-0"><?= e(t('progetti.show.estimated_hours_label')) ?></label>
                                            <input type="number" min="0" step="0.25" class="form-control form-control-sm" name="estimated_hours" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── FILES ── -->
            <div class="row g-3 mt-0">
                <div class="col-12">
                    <div class="card prj-section-card prj-accent-danger">
                        <div class="card-header">
                            <span><i class="fa-solid fa-paperclip me-1"></i><?= e(t('progetti.show.files_title')) ?></span>
                            <span class="badge bg-secondary"><?= count($projFiles) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projFiles)): ?>
                            <p class="text-muted small mb-0"><?= e(t('progetti.show.no_files')) ?></p>
                            <?php else: ?>
                            <?php
                            $extIcons = ['pdf' => 'fa-file-pdf text-danger', 'doc' => 'fa-file-word text-primary', 'docx' => 'fa-file-word text-primary',
                                'xls' => 'fa-file-excel text-success', 'xlsx' => 'fa-file-excel text-success', 'png' => 'fa-file-image text-info',
                                'jpg' => 'fa-file-image text-info', 'jpeg' => 'fa-file-image text-info', 'zip' => 'fa-file-zipper text-warning'];
                            ?>
                            <?php foreach ($projFiles as $pf): ?>
                            <div class="prj-file-item">
                                <div class="prj-file-icon">
                                    <i class="fa-solid <?= e($extIcons[strtolower($pf['extension'] ?? '')] ?? 'fa-file text-muted') ?>"></i>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <?php if (isModuleEnabled('Files')): ?>
                                    <a href="<?= e(route('files.show', ['id' => (int) $pf['file_id']])) ?>" class="text-decoration-none small fw-semibold"><?= e($pf['original_name']) ?></a>
                                    <?php else: ?>
                                    <span class="small fw-semibold"><?= e($pf['original_name']) ?></span>
                                    <?php endif; ?>
                                    <div class="text-muted small">
                                        <?= e(number_format((int) $pf['size_bytes'] / 1024, 1)) ?> KB
                                        · <?= e((string) ($pf['linked_by_name'] ?? t('progetti.report.na'))) ?>
                                        · <?= e(format_date((string)($pf['linked_at'] ?? ''), 'short')) ?>
                                    </div>
                                </div>
                                <?php if (has_permission('progetti.edit')): ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger border-0 opacity-50"
                                        data-prj-confirm-action="1"
                                        data-prj-confirm-title="<?= e(t('progetti.show.remove_file_title')) ?>"
                                        data-prj-confirm-message="<?= e(t('progetti.show.remove_file_message', ['name' => (string) ($pf['original_name'] ?? '')])) ?>"
                                        data-prj-confirm-action-url="<?= e(route('projects.files.destroy', ['id' => $pid, 'fileId' => (int) $pf['file_id']])) ?>"
                                        data-prj-confirm-submit="<?= e(t('progetti.show.remove_file_submit')) ?>"
                                        data-prj-confirm-icon="fa-paperclip"
                                        data-bs-toggle="tooltip"
                                        title="<?= e(t('progetti.show.remove_file_tip')) ?>">
                                        <i class="fa-solid fa-link-slash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (has_permission('progetti.edit') && isModuleEnabled('Files')): ?>
                        <div class="prj-add-form">
                            <form method="POST" action="<?= e(route('projects.files.store', ['id' => $pid])) ?>" enctype="multipart/form-data" class="row g-2">
                                <?= csrf_field() ?>
                                <div class="col-md-5"><input type="file" class="form-control form-control-sm" name="file" required></div>
                                <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="description" placeholder="<?= e(t('progetti.show.file_description_placeholder')) ?>" maxlength="500"></div>
                                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="fa-solid fa-paperclip me-1"></i><?= e(t('progetti.show.attach_file')) ?></button></div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /overview -->

        <!-- ── DASHBOARD KPI PANE ── -->
        <div id="prj-pane-dashboard" class="prj-tab-pane">

            <!-- Dati serializzati per ApexCharts -->
            <script id="prj-chart-task-status" type="application/json"><?= $chartTaskStatus ?></script>
            <script id="prj-chart-user-bar"    type="application/json"><?= $chartUserBar ?></script>
            <script id="prj-chart-trend"       type="application/json"><?= $chartTrend ?></script>

            <!-- Risolve i token Bootstrap dei colori in valori hex computati (theme-aware) -->
            <script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
            (function () {
                var styles = getComputedStyle(document.documentElement);
                var resolve = function (token) {
                    var v = styles.getPropertyValue('--bs-' + token);
                    return v ? v.trim() : token;
                };
                document.querySelectorAll('script[id^="prj-chart-"][type="application/json"]').forEach(function (node) {
                    try {
                        var data = JSON.parse(node.textContent);
                        if (Array.isArray(data.colors)) {
                            data.colors = data.colors.map(resolve);
                            node.textContent = JSON.stringify(data);
                        }
                    } catch (e) { /* swallow: chart inizializzato senza colori custom */ }
                });
            })();
            </script>

            <!-- ── Riga 1: card KPI fluid ── -->
            <div class="row g-3 mb-3">
                <!-- Avanzamento -->
                <div class="col-sm">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="prj-kpi-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fa-solid fa-chart-pie"></i>
                                </div>
                                <div>
                                    <div class="prj-kpi-value"><?= e(number_format($kpiProgress, 1, ',', '.')) ?>%</div>
                                    <div class="prj-kpi-label"><?= e(t('progetti.show.kpi_progress')) ?></div>
                                </div>
                            </div>
                            <div class="prj-progress-track mb-1">
                                <div class="prj-progress-fill bg-primary" data-prj-pct="<?= e((string) min(100, $kpiProgress)) ?>"></div>
                            </div>
                            <div class="prj-kpi-sub"><?= e(t('progetti.show.kpi_remaining', ['done' => $kpiDone, 'total' => $kpiTotal, 'remaining' => $kpiTotal - $kpiDone])) ?></div>
                        </div>
                    </div>
                </div>
                <!-- Ore -->
                <?php
                $hoursColor     = ProgettiService::kpiColor($hoursRatio, 'burn');
                $hoursIconClass = 'bg-' . $hoursColor . ' bg-opacity-10 text-' . $hoursColor;
                $hoursBarClass  = 'bg-' . $hoursColor;
                ?>
                <div class="col-sm">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="prj-kpi-icon <?= $hoursIconClass ?>">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <div>
                                    <div class="prj-kpi-value"><?= e(number_format($consumed, 1, ',', '.')) ?> h</div>
                                    <div class="prj-kpi-label"><?= e(t('progetti.show.kpi_hours')) ?></div>
                                </div>
                            </div>
                            <div class="prj-progress-track mb-1">
                                <div class="prj-progress-fill <?= $hoursBarClass ?>" data-prj-pct="<?= e((string) min(100, $hoursRatio)) ?>"></div>
                            </div>
                            <div class="prj-kpi-sub">
                                <?php if ($estimated > 0): ?>
                                <?= e(t('progetti.show.kpi_hours_estimated', ['hours' => number_format($estimated, 1, ',', '.'), 'pct' => number_format($hoursRatio, 1, ',', '.')])) ?>
                                <?php else: ?>
                                <?= e(t('progetti.show.kpi_no_estimate')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Budget -->
                <?php
                $budgetColor     = ProgettiService::kpiColor($budgetBurn, 'burn');
                $budgetIconClass = 'bg-' . $budgetColor . ' bg-opacity-10 text-' . $budgetColor;
                $budgetBarClass  = 'bg-' . $budgetColor;
                ?>
                <div class="col-sm">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="prj-kpi-icon <?= $budgetIconClass ?>">
                                    <i class="fa-solid fa-euro-sign"></i>
                                </div>
                                <div>
                                    <div class="prj-kpi-value">€ <?= e(number_format($actualCost, 0, ',', '.')) ?></div>
                                    <div class="prj-kpi-label"><?= e(t('progetti.show.kpi_budget')) ?></div>
                                </div>
                            </div>
                            <div class="prj-progress-track mb-1">
                                <div class="prj-progress-fill <?= $budgetBarClass ?>" data-prj-pct="<?= e((string) min(100, $budgetBurn)) ?>"></div>
                            </div>
                            <div class="prj-kpi-sub">
                                <?php if ($budgetPlan > 0): ?>
                                <?= e(t('progetti.show.kpi_budget_planned', ['amount' => number_format($budgetPlan, 0, ',', '.'), 'pct' => number_format($budgetBurn, 1, ',', '.')])) ?>
                                <?php else: ?>
                                <?= e(t('progetti.show.kpi_no_budget')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Rischi -->
                <?php
                $riskIconClass = ($kpiOverdue > 0 || $missedMs > 0)
                    ? 'bg-danger bg-opacity-10 text-danger'
                    : 'bg-success bg-opacity-10 text-success';
                $riskValueClass = $kpiOverdue > 0 ? 'text-danger' : '';
                ?>
                <div class="col-sm">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="prj-kpi-icon <?= $riskIconClass ?>">
                                    <i class="fa-solid fa-<?= ($kpiOverdue > 0 || $missedMs > 0) ? 'triangle-exclamation' : 'circle-check' ?>"></i>
                                </div>
                                <div>
                                    <div class="prj-kpi-value <?= $riskValueClass ?>"><?= $kpiOverdue ?></div>
                                    <div class="prj-kpi-label"><?= e(t('progetti.show.kpi_risks')) ?></div>
                                </div>
                            </div>
                            <div class="prj-kpi-sub">
                                <?php if ($kpiOverdue === 0 && $missedMs === 0): ?>
                                <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i><?= e(t('progetti.show.kpi_no_risk')) ?></span>
                                <?php else: ?>
                                <?= e(t('progetti.show.kpi_risk_summary', ['missed' => $missedMs, 'overdue' => $kpiOverdue])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($billableMs > 0): ?>
                <!-- Fatturabile -->
                <div class="col-sm">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="prj-kpi-icon bg-info bg-opacity-10 text-info">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </div>
                                <div>
                                    <div class="prj-kpi-value"><?= $billableDone ?>/<?= $billableMs ?></div>
                                    <div class="prj-kpi-label"><?= e(t('progetti.show.kpi_billable')) ?></div>
                                </div>
                            </div>
                            <?php $billablePct = $billableMs > 0 ? round(($billableDone / $billableMs) * 100, 1) : 0; ?>
                            <div class="prj-progress-track mb-1">
                                <div class="prj-progress-fill bg-info" data-prj-pct="<?= e((string) min(100, $billablePct)) ?>"></div>
                            </div>
                            <div class="prj-kpi-sub"><?= e(t('progetti.show.kpi_billable_pct', ['pct' => number_format($billablePct, 1, ',', '.')])) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Riga 2: grafici ── -->
            <div class="row g-3">
                <!-- Distribuzione attivita per stato (donut) -->
                <?php if ($kpiTotal > 0): ?>
                <div class="col-lg-4">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="prj-kpi-chart-title"><i class="fa-solid fa-layer-group me-1 text-primary"></i><?= e(t('progetti.show.chart_tasks_by_status')) ?></div>
                            <div id="prj-chart-donut-tasks" class="prj-chart-container"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Ore per membro (bar orizzontale) -->
                <?php if (!empty($userBarData)): ?>
                <div class="col-lg-<?= $kpiTotal > 0 ? '8' : '12' ?>">
                    <div class="card shadow-sm prj-kpi-card h-100">
                        <div class="card-body">
                            <div class="prj-kpi-chart-title"><i class="fa-solid fa-users me-1 text-info"></i><?= e(t('progetti.show.chart_hours_by_member')) ?></div>
                            <div id="prj-chart-bar-users" class="prj-chart-container"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Trend ore settimanale (area) -->
                <?php if (count($hoursTrend) > 1): ?>
                <div class="col-12">
                    <div class="card shadow-sm prj-kpi-card">
                        <div class="card-body">
                            <div class="prj-kpi-chart-title"><i class="fa-solid fa-chart-area me-1 text-success"></i><?= e(t('progetti.show.chart_hours_trend')) ?></div>
                            <div id="prj-chart-area-trend" class="prj-chart-container prj-chart-container-lg"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ── KANBAN PANE ── -->
        <div id="prj-pane-kanban" class="prj-tab-pane"></div>

        <!-- ── GANTT PANE ── -->
        <div id="prj-pane-gantt" class="prj-tab-pane"></div>

        <!-- ── TIMESHEET PANE ── -->
        <div id="prj-pane-timesheet" class="prj-tab-pane"></div>

    </div>

</div>

<?php if (has_permission('progetti.edit')): ?>
<div class="modal fade" id="prjMilestoneEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="prj-milestone-edit-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-flag me-2"></i><?= e(t('progetti.show.milestone_edit_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label"><?= e(t('progetti.form.name')) ?></label>
                            <input type="text" class="form-control" name="name" id="prj-ms-edit-name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= e(t('progetti.show.field_due')) ?></label>
                            <input type="date" class="form-control" name="due_date" id="prj-ms-edit-due-date"
                                   <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                                   <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('progetti.show.field_status')) ?></label>
                            <select class="form-select" name="status" id="prj-ms-edit-status">
                                <?php foreach ($milestoneStatuses as $msKey => $msCfg): ?>
                                <option value="<?= e($msKey) ?>"><?= e($msCfg['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="billable" value="1" id="prj-ms-edit-billable">
                                <label class="form-check-label" for="prj-ms-edit-billable"><?= e(t('progetti.show.billable')) ?></label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('progetti.form.description')) ?></label>
                            <textarea class="form-control" name="description" id="prj-ms-edit-description" rows="4" placeholder="<?= e(t('progetti.show.milestone_description_placeholder')) ?>"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.show.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i><?= e(t('progetti.show.save_milestone')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (has_permission('progetti.edit')): ?>
<div class="modal fade" id="prjTaskEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="prj-task-edit-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-list-check me-2"></i><?= e(t('progetti.show.task_edit_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
                </div>
                <!-- Tab nav -->
                <div class="px-3 pt-2 border-bottom">
                    <ul class="nav nav-tabs border-0" id="prjTaskModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="prjTaskTabDetailsBtn" type="button"
                                    data-bs-toggle="tab" data-bs-target="#prjTaskTabDetails"
                                    role="tab" aria-selected="true">
                                <i class="fa-solid fa-pen-to-square me-1"></i><?= e(t('progetti.show.tab_details')) ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="prjTaskChecklistTabBtn" type="button"
                                    data-bs-toggle="tab" data-bs-target="#prjTaskTabChecklist"
                                    role="tab" aria-selected="false">
                                <i class="fa-solid fa-list-check me-1"></i><?= e(t('progetti.show.tab_checklist')) ?>
                                <span id="prj-checklist-badge" class="badge bg-secondary ms-1 d-none">0/0</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="modal-body">
                <div class="tab-content">
                <!-- ── Tab Dettagli ──────────────────────────────────── -->
                <div class="tab-pane fade show active" id="prjTaskTabDetails" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('progetti.show.field_title')) ?></label>
                            <input type="text" class="form-control" name="title" id="prj-task-edit-title" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_milestone')) ?></label>
                            <select class="form-select" name="milestone_id" id="prj-task-edit-milestone-id">
                                <option value=""><?= e(t('progetti.show.no_milestone')) ?></option>
                                <?php foreach ($milestones as $ms): ?>
                                <option value="<?= (int) $ms['id'] ?>"><?= e((string) $ms['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_assigned')) ?></label>
                            <select class="form-select" name="assigned_user_id" id="prj-task-edit-assigned-user-id">
                                <option value=""><?= e(t('progetti.show.not_assigned')) ?></option>
                                <?php foreach (($management['member_options'] ?? []) as $mo): ?>
                                <option value="<?= (int) $mo['id'] ?>"><?= e((string) $mo['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_priority')) ?></label>
                            <select class="form-select" name="priority" id="prj-task-edit-priority">
                                <option value="low"><?= e(t('progetti.priority.low')) ?></option>
                                <option value="medium"><?= e(t('progetti.priority.medium')) ?></option>
                                <option value="high"><?= e(t('progetti.priority.high')) ?></option>
                                <option value="urgent"><?= e(t('progetti.priority.urgent')) ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_status')) ?></label>
                            <select class="form-select" name="status" id="prj-task-edit-status">
                                <option value="todo"><?= e(t('progetti.status.task.todo')) ?></option>
                                <option value="in_progress"><?= e(t('progetti.status.task.in_progress')) ?></option>
                                <option value="review"><?= e(t('progetti.status.task.review')) ?></option>
                                <option value="blocked"><?= e(t('progetti.status.task.blocked')) ?></option>
                                <option value="done"><?= e(t('progetti.status.task.done')) ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_start')) ?></label>
                            <input type="date" class="form-control" name="start_date" id="prj-task-edit-start-date"
                                   <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                                   <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_due')) ?></label>
                            <input type="date" class="form-control" name="due_date" id="prj-task-edit-due-date"
                                   <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                                   <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= e(t('progetti.show.field_estimated_hours')) ?></label>
                            <input type="number" min="0" step="0.25" class="form-control" name="estimated_hours" id="prj-task-edit-estimated-hours">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('progetti.show.field_description')) ?></label>
                            <textarea class="form-control" name="description" id="prj-task-edit-description" rows="4" placeholder="<?= e(t('progetti.show.task_description_placeholder')) ?>"></textarea>
                        </div>
                        <!-- Dipendenze -->
                        <div class="col-12">
                            <label class="form-label"><i class="fa-solid fa-link me-1"></i><?= e(t('progetti.show.dependencies_label')) ?></label>
                            <div id="prj-dep-list" class="mb-2"></div>
                            <div class="input-group input-group-sm">
                                <select class="form-select form-select-sm" id="prj-dep-add-select">
                                    <option value=""><?= e(t('progetti.show.add_predecessor')) ?></option>
                                    <?php foreach (($management['task_options'] ?? []) as $to): ?>
                                    <option value="<?= (int) $to['id'] ?>"><?= e((string) $to['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" id="prj-dep-add-btn" title="<?= e(t('progetti.show.add_dependency_tip')) ?>">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            <div class="form-text"><?= e(t('progetti.show.dependency_hint')) ?></div>
                        </div>
                    </div>
                </div><!-- /.tab-pane#prjTaskTabDetails -->

                <!-- ── Tab Checklist ──────────────────────────────────── -->
                <div class="tab-pane fade" id="prjTaskTabChecklist" role="tabpanel">
                    <div id="prj-checklist-container"
                         data-project-id="<?= $pid ?>"
                         data-checklist-url="<?= e(route('projects.tasks.checklist.index', ['id' => $pid, 'taskId' => '__TID__'])) ?>"
                         data-quick-status-url="<?= e(route('projects.tasks.quick_status', ['id' => $pid, 'taskId' => '__TID__'])) ?>"
                         data-csrf="<?= e(csrf_token()) ?>">
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-spinner fa-spin fa-lg"></i>
                        </div>
                    </div>
                </div><!-- /.tab-pane#prjTaskTabChecklist -->

                </div><!-- /.tab-content -->
                </div><!-- /.modal-body -->
                <div class="modal-footer">
                    <button type="button" id="prj-task-mark-done-btn"
                            class="btn btn-success d-none me-auto"
                            data-task-id=""
                            data-bs-toggle="tooltip"
                            title="<?= e(t('progetti.show.mark_done_tip')) ?>">
                        <i class="fa-solid fa-circle-check me-1"></i><?= e(t('progetti.show.mark_done_btn')) ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.show.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i><?= e(t('progetti.show.save_task')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (has_permission('progetti.edit') || has_permission('progetti.delete') || !empty($canManageMembers)): ?>
<div class="modal fade" id="prjActionConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i id="prj-confirm-modal-icon" class="fa-solid fa-triangle-exclamation text-danger me-2"></i><span id="prj-confirm-modal-title"><?= e(t('progetti.show.confirm_action')) ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="prj-confirm-modal-message"><?= e(t('progetti.show.confirm_message')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.show.cancel')) ?></button>
                <form method="POST" id="prj-confirm-modal-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger" id="prj-confirm-modal-submit"><?= e(t('progetti.show.confirm_submit')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php $view->end(); ?>
