<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Services;

use App\Modules\Progetti\Repositories\ChecklistRepository;
use App\Modules\Progetti\Repositories\ProgettiRepository;
use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;
use App\Modules\Teams\Services\TeamsService;

class ProgettiService
{
    private ProgettiRepository $repo;

    public static function getTaskStatuses(): array
    {
        return [
            'todo' => ['label' => t('progetti.status.task.todo'), 'color' => 'secondary', 'icon' => 'fa-circle'],
            'in_progress' => ['label' => t('progetti.status.task.in_progress'), 'color' => 'primary', 'icon' => 'fa-spinner'],
            'review' => ['label' => t('progetti.status.task.review'), 'color' => 'warning', 'icon' => 'fa-eye'],
            'blocked' => ['label' => t('progetti.status.task.blocked'), 'color' => 'danger', 'icon' => 'fa-lock'],
            'done' => ['label' => t('progetti.status.task.done'), 'color' => 'success', 'icon' => 'fa-circle-check'],
        ];
    }

    public static function getProjectStatuses(): array
    {
        return [
            'planning'  => ['label' => t('progetti.status.project.planning'),  'color' => 'secondary', 'icon' => 'fa-compass-drafting'],
            'active'    => ['label' => t('progetti.status.project.active'),    'color' => 'primary',   'icon' => 'fa-circle-play'],
            'on_hold'   => ['label' => t('progetti.status.project.on_hold'),   'color' => 'warning',   'icon' => 'fa-circle-pause'],
            'completed' => ['label' => t('progetti.status.project.completed'), 'color' => 'success',   'icon' => 'fa-circle-check'],
            'cancelled' => ['label' => t('progetti.status.project.cancelled'), 'color' => 'dark',      'icon' => 'fa-circle-xmark'],
        ];
    }

    public static function validateProjectData(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'][] = t('progetti.validation.name_required');
        }

        $status = (string) ($data['status'] ?? 'planning');
        if (!array_key_exists($status, self::getProjectStatuses())) {
            $errors['status'][] = t('progetti.validation.status_invalid');
        }

        if ((float) ($data['estimated_hours'] ?? 0) < 0) {
            $errors['estimated_hours'][] = t('progetti.validation.hours_negative');
        }

        if ((float) ($data['budget_planned'] ?? 0) < 0) {
            $errors['budget_planned'][] = t('progetti.validation.budget_negative');
        }

        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate   = trim((string) ($data['end_date'] ?? ''));
        if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
            $errors['end_date'][] = t('progetti.validation.end_before_start');
        }

        return $errors;
    }

    public static function getMilestoneStatuses(): array
    {
        return [
            'pending'     => ['label' => t('progetti.status.milestone.pending'),     'color' => 'secondary'],
            'in_progress' => ['label' => t('progetti.status.milestone.in_progress'), 'color' => 'primary'],
            'done'        => ['label' => t('progetti.status.milestone.done'),        'color' => 'success'],
            'missed'      => ['label' => t('progetti.status.milestone.missed'),      'color' => 'danger'],
        ];
    }

    public static function getPriorityConfig(): array
    {
        return [
            'low'    => ['label' => t('progetti.priority.low'),    'color' => 'secondary'],
            'medium' => ['label' => t('progetti.priority.medium'), 'color' => 'info'],
            'high'   => ['label' => t('progetti.priority.high'),   'color' => 'warning'],
            'urgent' => ['label' => t('progetti.priority.urgent'), 'color' => 'danger'],
        ];
    }

    /**
     * Colore KPI unificato per progress e burn rate.
     * @param string $type 'progress' | 'burn'
     */
    public static function kpiColor(float $pct, string $type = 'progress'): string
    {
        if ($type === 'burn') {
            return $pct > 100 ? 'danger' : ($pct > 85 ? 'warning' : 'success');
        }
        // progress
        return $pct < 50 ? 'danger' : ($pct < 80 ? 'warning' : 'success');
    }

    public function __construct()
    {
        $this->repo = app(ProgettiRepository::class);
    }

    /**
     * $viewAll esplicito = chiamante senza sessione (API v1, che risolve i
     * permessi da ApiRequestContext); null = comportamento legacy da sessione.
     */
    public function listForUser(int $userId, array $filters = [], ?bool $viewAll = null): array
    {
        return $this->repo->listForUser($userId, $viewAll ?? $this->canViewAll(), $filters);
    }

    public function create(array $data, int $userId, ?string $userName = null): int
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'code' => $this->normalizeNullable($data['code'] ?? null),
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'client_name' => $this->normalizeNullable($data['client_name'] ?? null),
            'owner_user_id' => $userId,
            'status' => in_array(($data['status'] ?? ''), ['planning', 'active', 'on_hold', 'completed', 'cancelled'], true)
                ? $data['status']
                : 'planning',
            'start_date' => $this->normalizeNullable($data['start_date'] ?? null),
            'end_date' => $this->normalizeNullable($data['end_date'] ?? null),
            'estimated_hours' => (float) ($data['estimated_hours'] ?? 0),
            'budget_planned' => (float) ($data['budget_planned'] ?? 0),
            'created_by' => $userId,
        ];

        $projectId = $this->repo->createProject($payload, $userId);
        $this->bootstrapTeamsConversation($projectId, $payload, $userId, $userName ?? '');

        return $projectId;
    }

    public function updateProject(int $projectId, array $data, int $userId): bool
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'code' => $this->normalizeNullable($data['code'] ?? null),
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'client_name' => $this->normalizeNullable($data['client_name'] ?? null),
            'status' => in_array(($data['status'] ?? ''), ['planning', 'active', 'on_hold', 'completed', 'cancelled'], true)
                ? $data['status']
                : 'planning',
            'start_date' => $this->normalizeNullable($data['start_date'] ?? null),
            'end_date' => $this->normalizeNullable($data['end_date'] ?? null),
            'estimated_hours' => (float) ($data['estimated_hours'] ?? 0),
            'budget_planned' => (float) ($data['budget_planned'] ?? 0),
        ];

        $result = $this->repo->update($projectId, $payload);
        $this->refreshProjectCaches($projectId);

        return $result;
    }

    public function deleteProject(int $projectId, int $userId): bool
    {
        $project = $this->repo->find($projectId);
        if (!$project) {
            return false;
        }

        $this->archiveLinkedTeamsConversation($project, $userId);
        $this->trashLinkedProjectFiles($projectId);
        return $this->repo->delete($projectId);
    }

    /**
     * $viewAll esplicito = chiamante senza sessione (API v1); null = da sessione.
     */
    public function findForUser(int $projectId, int $userId, ?bool $viewAll = null): ?array
    {
        return $this->repo->findForUser($projectId, $userId, $viewAll ?? $this->canViewAll());
    }

    public function getDashboardKpi(int $projectId): array
    {
        $kpi = $this->repo->getDashboardKpi($projectId);

        $totalTasks = (int) ($kpi['total_tasks'] ?? 0);
        $doneTasks = (int) ($kpi['done_tasks'] ?? 0);
        $progress = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 2) : 0.0;

        $estimated = (float) ($kpi['estimated_hours'] ?? 0);
        $consumed = (float) ($kpi['consumed_hours'] ?? 0);
        $budgetPlanned = (float) ($kpi['budget_planned'] ?? 0);
        $actualCost = (float) ($kpi['actual_cost'] ?? 0);

        $kpi['progress_pct'] = $progress;
        $kpi['hours_ratio_pct'] = $estimated > 0 ? round(($consumed / $estimated) * 100, 2) : 0.0;
        $kpi['budget_burn_pct'] = $budgetPlanned > 0 ? round(($actualCost / $budgetPlanned) * 100, 2) : 0.0;

        $kpi['hours_by_user'] = $this->repo->getTimesheetByUser($projectId);
        $kpi['hours_trend']   = $this->repo->getTimesheetTrend($projectId);

        return $kpi;
    }

    public function getKanbanData(int $projectId): array
    {
        $columns = self::getTaskStatuses();

        $rows = $this->repo->getTaskBoard($projectId);

        // Aggiungi conteggi checklist in una sola query (evita N+1)
        if (!empty($rows)) {
            $taskIds = array_column($rows, 'id');
            $clCounts = app(ChecklistRepository::class)->getChecklistCountsForTasks($taskIds);
            foreach ($rows as &$row) {
                $counts = $clCounts[(int) $row['id']] ?? ['total' => 0, 'done' => 0];
                $row['checklist_total'] = $counts['total'];
                $row['checklist_done']  = $counts['done'];
            }
            unset($row);
        }

        $board = [];
        foreach (array_keys($columns) as $key) {
            $board[$key] = [];
        }

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'todo');
            if (!isset($board[$status])) {
                $status = 'todo';
            }
            $board[$status][] = $row;
        }

        return [
            'columns' => $columns,
            'board' => $board,
        ];
    }

    public function getGanttData(int $projectId): array
    {
        $rows = $this->repo->getGanttRows($projectId);

        $datePool = [];
        foreach ($rows as $row) {
            if (!empty($row['start_date'])) {
                $datePool[] = (string) $row['start_date'];
            }
            if (!empty($row['end_date'])) {
                $datePool[] = (string) $row['end_date'];
            }
        }

        if (empty($datePool)) {
            return ['weeks' => [], 'rows' => []];
        }

        sort($datePool);
        $minDate = $this->parseDateValue($datePool[0]);
        $maxDate = $this->parseDateValue($datePool[count($datePool) - 1]);
        if ($minDate === null || $maxDate === null) {
            return ['weeks' => [], 'rows' => []];
        }

        // Allinea sempre a confini di settimana (lun–dom) usando aritmetica deterministica.
        $colStart = $this->toMonday($minDate);
        $colEnd   = $this->toSunday($maxDate);

        // Granularità adattiva: colonna = 1 giorno se il progetto copre ≤ 60 giorni,
        // colonna = 1 settimana (lunedì) altrimenti.
        $spanDays = (int) $colStart->diff($colEnd)->days + 1;
        $useDays  = $spanDays <= 60;

        $cols = [];
        $step = $useDays ? '+1 day' : '+7 days';
        for ($d = $colStart; $d <= $colEnd; $d = $d->modify($step)) {
            $cols[] = [
                'start' => $d->format('Y-m-d'),
                'label' => $d->format('d/m'),
            ];
        }

        $colCount = max(1, count($cols));
        $gridRows = [];

        foreach ($rows as $row) {
            $startDate = !empty($row['start_date']) ? $this->parseDateValue((string) $row['start_date']) : null;
            $endDate   = !empty($row['end_date']) ? $this->parseDateValue((string) $row['end_date']) : null;

            if ($startDate === null && $endDate === null) {
                continue;
            }
            if ($startDate === null) {
                $startDate = $endDate;
            }
            if ($endDate === null) {
                $endDate = $startDate;
            }
            if ($endDate < $startDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            if ($useDays) {
                // offset = giorni dal primo giorno della griglia alla data di inizio del task
                $offset   = (int) $colStart->diff($startDate)->days;
                // duration = giorni dal giorno di inizio al giorno di fine, inclusivi
                $duration = (int) $startDate->diff($endDate)->days + 1;
            } else {
                $offset   = $this->diffInWholeWeeks($colStart, $startDate);
                $duration = $this->diffInWholeWeeks($startDate, $endDate) + 1;
            }

            $offset   = max(0, min($offset, $colCount - 1));
            $duration = max(1, min($duration, $colCount - $offset));

            $gridRows[] = [
                'row_type'       => $row['row_type'],
                'row_id'         => $row['row_id'],
                'row_label'      => $row['row_label'],
                'task_status'    => $row['task_status'],
                'start_date_fmt' => $startDate->format('d/m/Y'),
                'end_date_fmt'   => $endDate->format('d/m/Y'),
                'offset'         => $offset,
                'duration'       => $duration,
            ];
        }

        // Linea "oggi": offset della colonna corrispondente a oggi, null se fuori range
        $today = new \DateTimeImmutable('today');
        $todayOffset = null;
        if ($today >= $colStart && $today <= $colEnd) {
            $todayOffset = $useDays
                ? (int) $colStart->diff($today)->days
                : $this->diffInWholeWeeks($colStart, $today);
        }

        return [
            'weeks'        => $cols,
            'rows'         => $gridRows,
            'today_offset' => $todayOffset,
        ];
    }

    private function parseDateValue(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    /**
     * Ritorna il lunedì della settimana contenente $date (aritmetica deterministica).
     */
    private function toMonday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dow = (int) $date->format('N'); // 1=lun … 7=dom

        return $date->modify('-' . ($dow - 1) . ' days');
    }

    /**
     * Ritorna la domenica della settimana contenente $date.
     */
    private function toSunday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dow = (int) $date->format('N');

        return $date->modify('+' . (7 - $dow) . ' days');
    }

    private function diffInWholeWeeks(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) floor((int) $start->diff($end)->days / 7);
    }

    /**
     * Validates that all non-null dates fall inside the project's start/end range.
     * Silently skips if the project has no defined start or end date.
     *
     * @param array<string, string|null> $datesMap  ['Label' => 'YYYY-MM-DD' | null]
     */
    private function assertDatesInProjectRange(int $projectId, array $datesMap): void
    {
        $toCheck = array_filter($datesMap, fn ($v) => $v !== null && $v !== '');
        if (empty($toCheck)) {
            return;
        }

        $project  = $this->repo->find($projectId);
        $projStart = (!empty($project['start_date']))
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $project['start_date'])
            : null;
        $projEnd   = (!empty($project['end_date']))
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $project['end_date'])
            : null;

        foreach ($toCheck as $label => $value) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
            if ($date === false) {
                throw new \RuntimeException(t('progetti.exception.date_invalid_format', ['label' => $label]));
            }
            if ($projStart instanceof \DateTimeImmutable && $date < $projStart) {
                throw new \RuntimeException(t('progetti.exception.date_before_project_start', [
                    'label'        => $label,
                    'date'         => $date->format('d/m/Y'),
                    'project_date' => $projStart->format('d/m/Y'),
                ]));
            }
            if ($projEnd instanceof \DateTimeImmutable && $date > $projEnd) {
                throw new \RuntimeException(t('progetti.exception.date_after_project_end', [
                    'label'        => $label,
                    'date'         => $date->format('d/m/Y'),
                    'project_date' => $projEnd->format('d/m/Y'),
                ]));
            }
        }
    }

    public function getTimesheetData(int $projectId): array
    {
        $rows = $this->repo->getTimesheetRows($projectId, 150);
        $hours = 0.0;
        $cost  = 0.0;
        foreach ($rows as &$row) {
            $hours += (float) ($row['hours'] ?? 0);
            $rate   = (float) ($row['hourly_rate'] ?? 0);
            $row['cost'] = $rate > 0 ? (float) $row['hours'] * $rate : null;
            if ($row['cost'] !== null) {
                $cost += $row['cost'];
            }
        }
        unset($row);

        return [
            'rows'        => $rows,
            'hours_total' => $hours,
            'cost_total'  => $cost > 0 ? $cost : null,
        ];
    }

    public function getMyTasks(int $userId, array $filters = []): array
    {
        $tasks = $this->repo->getTasksForUser($userId, $filters);

        // Aggiungi conteggi checklist in una sola query (evita N+1)
        if (!empty($tasks)) {
            $taskIds = array_column($tasks, 'id');
            $clCounts = app(ChecklistRepository::class)->getChecklistCountsForTasks($taskIds);
            foreach ($tasks as &$task) {
                $counts = $clCounts[(int) $task['id']] ?? ['total' => 0, 'done' => 0];
                $task['checklist_total'] = $counts['total'];
                $task['checklist_done']  = $counts['done'];
            }
            unset($task);
        }

        return $tasks;
    }

    public function getWidgetStats(int $userId): array
    {
        return $this->repo->getWidgetStats($userId, $this->canViewAll());
    }

    /**
     * @return array<string, int> ['status' => count] per il widget grafico.
     */
    public function getStatusBreakdown(int $userId): array
    {
        return $this->repo->getStatusBreakdown($userId, $this->canViewAll());
    }

    /**
     * @return array{planned: float, actual: float}
     */
    public function getBudgetAggregate(int $userId): array
    {
        return $this->repo->getBudgetAggregate($userId, $this->canViewAll());
    }

    public function getMyTasksDueSoon(int $userId, int $days = 7): array
    {
        return $this->repo->getMyTasksDueSoon($userId, $days);
    }

    public function getMilestonesDueSoon(int $userId, int $days = 30): array
    {
        return $this->repo->getMilestonesDueSoon($userId, $this->canViewAll(), $days);
    }

    public function getManagementData(int $projectId): array
    {
        $tasks = $this->repo->getTasks($projectId);

        // Conteggi checklist per ogni task (evita N+1)
        if (!empty($tasks)) {
            $taskIds = array_column($tasks, 'id');
            $clCounts = app(ChecklistRepository::class)->getChecklistCountsForTasks($taskIds);
            foreach ($tasks as &$task) {
                $counts = $clCounts[(int) $task['id']] ?? ['total' => 0, 'done' => 0];
                $task['checklist_total'] = $counts['total'];
                $task['checklist_done']  = $counts['done'];
            }
            unset($task);
        }

        return [
            'members'        => $this->repo->getMembers($projectId),
            'milestones'     => $this->repo->getMilestones($projectId),
            'tasks'          => $tasks,
            'task_options'   => $this->repo->getTaskOptions($projectId),
            'member_options' => $this->repo->getMemberOptions($projectId),
            'dependency_edges' => $this->repo->getDependencyEdges($projectId),
            'available_member_options' => $this->repo->getAvailableUsersForProject($projectId),
            'project_files'  => $this->repo->getProjectFiles($projectId),
        ];
    }

    public function canManageMembers(int $projectId, int $userId): bool
    {
        if ($this->canManageAll() || has_permission('progetti.manage_members')) {
            return true;
        }

        return $this->repo->isProjectOwner($projectId, $userId);
    }

    public function addMember(int $projectId, int $memberUserId, string $role, mixed $hourlyRate, int $actorUserId): bool
    {
        $this->assertMemberManagementAllowed($projectId, $actorUserId);

        if ($memberUserId <= 0) {
            throw new \RuntimeException(t('progetti.exception.select_valid_user'));
        }

        if (!$this->repo->userExists($memberUserId)) {
            throw new \RuntimeException(t('progetti.exception.user_not_found'));
        }

        if ($this->repo->findProjectMember($projectId, $memberUserId)) {
            throw new \RuntimeException(t('progetti.exception.user_already_member'));
        }

        $result = $this->repo->addProjectMember(
            $projectId,
            $memberUserId,
            $this->normalizeProjectMemberRole($role),
            $this->normalizeHourlyRate($hourlyRate)
        );

        if ($result) {
            $this->syncTeamsMemberAdded($projectId, $memberUserId, $actorUserId);
        }

        return $result;
    }

    public function updateMember(int $projectId, int $memberUserId, string $role, mixed $hourlyRate, int $actorUserId): bool
    {
        $this->assertMemberManagementAllowed($projectId, $actorUserId);

        $member = $this->repo->findProjectMember($projectId, $memberUserId);
        if (!$member) {
            throw new \RuntimeException(t('progetti.exception.member_not_found'));
        }

        if (($member['role'] ?? '') === 'owner') {
            // Per l'owner aggiorna solo la tariffa oraria, il ruolo rimane 'owner'
            $result = $this->repo->updateProjectMember(
                $projectId,
                $memberUserId,
                'owner',
                $this->normalizeHourlyRate($hourlyRate)
            );
        } else {
            $result = $this->repo->updateProjectMember(
                $projectId,
                $memberUserId,
                $this->normalizeProjectMemberRole($role),
                $this->normalizeHourlyRate($hourlyRate)
            );
        }

        if ($result) {
            // La tariffa oraria influenza il budget_actual_cached: ricalcola
            $this->refreshProjectCaches($projectId);
        }

        return $result;
    }

    public function removeMember(int $projectId, int $memberUserId, int $actorUserId): bool
    {
        $this->assertMemberManagementAllowed($projectId, $actorUserId);

        $member = $this->repo->findProjectMember($projectId, $memberUserId);
        if (!$member) {
            throw new \RuntimeException(t('progetti.exception.member_not_found'));
        }

        if (($member['role'] ?? '') === 'owner') {
            throw new \RuntimeException(t('progetti.exception.owner_cannot_be_removed'));
        }

        if ($this->repo->countTasksAssignedToMember($projectId, $memberUserId) > 0) {
            throw new \RuntimeException(t('progetti.exception.member_has_tasks'));
        }

        $result = $this->repo->removeProjectMember($projectId, $memberUserId);

        if ($result) {
            $this->syncTeamsMemberRemoved($projectId, $memberUserId, $actorUserId);
        }

        return $result;
    }

    public function createMilestone(int $projectId, array $data, int $userId): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException(t('progetti.exception.milestone_name_required'));
        }

        $this->assertDatesInProjectRange($projectId, [
            t('progetti.exception.label_milestone_due_date') => $this->normalizeNullable($data['due_date'] ?? null),
        ]);

        $payload = [
            'project_id' => $projectId,
            'name' => $name,
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'due_date' => $this->normalizeNullable($data['due_date'] ?? null),
            'status' => $this->normalizeMilestoneStatus((string) ($data['status'] ?? 'pending')),
            'billable' => !empty($data['billable']) ? 1 : 0,
            'created_by' => $userId,
        ];

        $milestoneId = $this->repo->createMilestone($payload);
        $this->syncMilestoneCalendarEvent($projectId, $milestoneId, $userId);
        return $milestoneId;
    }

    public function updateMilestone(int $projectId, int $milestoneId, array $data, int $userId): bool
    {
        $milestone = $this->repo->findMilestone($projectId, $milestoneId);
        if (!$milestone) {
            throw new \RuntimeException(t('progetti.exception.milestone_not_found'));
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException(t('progetti.exception.milestone_name_required'));
        }

        $this->assertDatesInProjectRange($projectId, [
            t('progetti.exception.label_milestone_due_date') => $this->normalizeNullable($data['due_date'] ?? null),
        ]);

        $result = $this->repo->updateMilestone($projectId, $milestoneId, [
            'name' => $name,
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'due_date' => $this->normalizeNullable($data['due_date'] ?? null),
            'status' => $this->normalizeMilestoneStatus((string) ($data['status'] ?? ($milestone['status'] ?? 'pending'))),
            'billable' => !empty($data['billable']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->syncMilestoneCalendarEvent($projectId, $milestoneId, $userId);
        return $result;
    }

    public function deleteMilestone(int $projectId, int $milestoneId, int $userId): bool
    {
        $milestone = $this->repo->findMilestone($projectId, $milestoneId);
        if (!$milestone) {
            throw new \RuntimeException(t('progetti.exception.milestone_not_found'));
        }

        $calEventId = (int) ($milestone['calendar_event_id'] ?? 0);
        if ($calEventId > 0 && isModuleEnabled('Calendar')) {
            try {
                app(\App\Modules\Calendar\Repositories\CalendarRepository::class)->delete($calEventId);
            } catch (\Throwable $e) {
                error_log('[Progetti] calendar.milestone_delete: ' . $e->getMessage());
            }
        }

        return $this->repo->deleteMilestone($projectId, $milestoneId);
    }

    public function createTask(int $projectId, array $data, int $userId): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException(t('progetti.exception.title_required'));
        }

        $startDate = $this->normalizeNullable($data['start_date'] ?? null);
        $dueDate   = $this->normalizeNullable($data['due_date'] ?? null);
        $this->assertDatesInProjectRange($projectId, [
            t('progetti.exception.label_start_date') => $startDate,
            t('progetti.exception.label_due_date')   => $dueDate,
        ]);
        if ($startDate !== null && $dueDate !== null && $startDate > $dueDate) {
            throw new \RuntimeException(t('progetti.exception.task_start_after_due'));
        }

        $status = $this->normalizeTaskStatus((string) ($data['status'] ?? 'todo'));
        $payload = [
            'project_id' => $projectId,
            'milestone_id' => $this->normalizeNullableInt($data['milestone_id'] ?? null),
            'title' => $title,
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'assigned_user_id' => $this->normalizeNullableInt($data['assigned_user_id'] ?? null),
            'priority' => $this->normalizeTaskPriority((string) ($data['priority'] ?? 'medium')),
            'status' => $status,
            'start_date' => $this->normalizeNullable($data['start_date'] ?? null),
            'due_date' => $this->normalizeNullable($data['due_date'] ?? null),
            'estimated_hours' => max(0, (float) ($data['estimated_hours'] ?? 0)),
            'position' => $this->repo->getNextTaskPosition($projectId, $status),
            'created_by' => $userId,
        ];

        $taskId = $this->repo->createTask($payload);
        if (!empty($payload['assigned_user_id']) && (int) $payload['assigned_user_id'] !== $userId) {
            $this->dispatchTaskAssignedNotification($projectId, $taskId, (int) $payload['assigned_user_id'], $payload['title'], $userId);
        }
        $this->syncTaskCalendarEvent($projectId, $taskId);

        return $taskId;
    }

    public function updateTask(int $projectId, int $taskId, array $data, int $userId): bool
    {
        $task = $this->repo->findTask($projectId, $taskId);
        if (!$task) {
            throw new \RuntimeException(t('progetti.exception.task_not_found'));
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException(t('progetti.exception.title_required'));
        }

        $startDate = $this->normalizeNullable($data['start_date'] ?? null);
        $dueDate   = $this->normalizeNullable($data['due_date'] ?? null);
        $this->assertDatesInProjectRange($projectId, [
            t('progetti.exception.label_start_date') => $startDate,
            t('progetti.exception.label_due_date')   => $dueDate,
        ]);
        if ($startDate !== null && $dueDate !== null && $startDate > $dueDate) {
            throw new \RuntimeException(t('progetti.exception.task_start_after_due'));
        }

        $newStatus = $this->normalizeTaskStatus((string) ($data['status'] ?? $task['status']));
        if (in_array($newStatus, ['in_progress', 'review', 'done'], true)) {
            $openPredecessors = $this->repo->countOpenPredecessors($projectId, $taskId);
            if ($openPredecessors > 0) {
                throw new \RuntimeException(t('progetti.exception.task_blocked_predecessors'));
            }
        }

        $payload = [
            'title' => $title,
            'description' => $this->normalizeNullable($data['description'] ?? null),
            'milestone_id' => $this->normalizeNullableInt($data['milestone_id'] ?? null),
            'assigned_user_id' => $this->normalizeNullableInt($data['assigned_user_id'] ?? null),
            'priority' => $this->normalizeTaskPriority((string) ($data['priority'] ?? $task['priority'])),
            'status' => $newStatus,
            'start_date' => $this->normalizeNullable($data['start_date'] ?? null),
            'due_date' => $this->normalizeNullable($data['due_date'] ?? null),
            'estimated_hours' => max(0, (float) ($data['estimated_hours'] ?? 0)),
            'completed_at' => $newStatus === 'done' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $oldAssigneeId = (int) ($task['assigned_user_id'] ?? 0);
        $result = $this->repo->updateTask($projectId, $taskId, $payload);

        $newAssigneeId = (int) ($payload['assigned_user_id'] ?? 0);
        if ($newAssigneeId > 0 && $newAssigneeId !== $oldAssigneeId && $newAssigneeId !== $userId) {
            $this->dispatchTaskAssignedNotification($projectId, $taskId, $newAssigneeId, $payload['title'], $userId);
        }
        $this->syncTaskCalendarEvent($projectId, $taskId);

        return $result;
    }

    public function deleteTask(int $projectId, int $taskId, int $userId): bool
    {
        $task = $this->repo->findTask($projectId, $taskId);
        if (!$task) {
            throw new \RuntimeException(t('progetti.exception.task_not_found'));
        }

        $calEventId = (int) ($task['calendar_event_id'] ?? 0);
        if ($calEventId > 0 && isModuleEnabled('Calendar')) {
            try {
                app(\App\Modules\Calendar\Repositories\CalendarRepository::class)->delete($calEventId);
            } catch (\Throwable $e) {
                error_log('[Progetti] calendar.task_delete: ' . $e->getMessage());
            }
        }

        return $this->repo->deleteTask($projectId, $taskId);
    }

    public function addTaskDependency(int $projectId, int $successorTaskId, int $predecessorTaskId, int $userId): bool
    {
        if ($successorTaskId <= 0 || $predecessorTaskId <= 0) {
            throw new \RuntimeException(t('progetti.exception.dependency_invalid_tasks'));
        }
        if ($successorTaskId === $predecessorTaskId) {
            throw new \RuntimeException(t('progetti.exception.dependency_self'));
        }

        $successor = $this->repo->findTask($projectId, $successorTaskId);
        $predecessor = $this->repo->findTask($projectId, $predecessorTaskId);
        if (!$successor || !$predecessor) {
            throw new \RuntimeException(t('progetti.exception.dependency_task_not_found'));
        }

        if ($this->repo->dependencyExists($predecessorTaskId, $successorTaskId)) {
            throw new \RuntimeException(t('progetti.exception.dependency_exists'));
        }

        $edges = $this->repo->getDependencyEdges($projectId);
        if ($this->wouldCreateCycle($predecessorTaskId, $successorTaskId, $edges)) {
            throw new \RuntimeException(t('progetti.exception.dependency_cycle'));
        }

        return $this->repo->createDependency($predecessorTaskId, $successorTaskId);
    }

    public function removeTaskDependency(int $projectId, int $successorTaskId, int $predecessorTaskId, int $userId): bool
    {
        // Verifica che entrambi i task appartengano al progetto indicato nell'URL,
        // altrimenti un utente con accesso a QUALSIASI progetto potrebbe cancellare
        // un edge di dipendenza tra due task di un progetto a cui non ha accesso
        // (IDOR: deleteDependency() opera solo sugli ID di task, senza scoping).
        $successor = $this->repo->findTask($projectId, $successorTaskId);
        $predecessor = $this->repo->findTask($projectId, $predecessorTaskId);
        if (!$successor || !$predecessor) {
            throw new \RuntimeException(t('progetti.exception.dependency_task_not_found'));
        }

        return $this->repo->deleteDependency($predecessorTaskId, $successorTaskId);
    }

    private function normalizeNullable(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        return $v === '' ? null : $v;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        $v = (int) ($value ?? 0);
        return $v > 0 ? $v : null;
    }

    private function normalizeMilestoneStatus(string $status): string
    {
        $allowed = ['pending', 'in_progress', 'done', 'missed'];
        return in_array($status, $allowed, true) ? $status : 'pending';
    }

    private function normalizeTaskStatus(string $status): string
    {
        $allowed = ['todo', 'in_progress', 'review', 'blocked', 'done'];
        return in_array($status, $allowed, true) ? $status : 'todo';
    }

    private function normalizeTaskPriority(string $priority): string
    {
        $allowed = ['low', 'medium', 'high', 'urgent'];
        return in_array($priority, $allowed, true) ? $priority : 'medium';
    }

    private function normalizeProjectMemberRole(string $role): string
    {
        $allowed = ['member', 'viewer'];
        return in_array($role, $allowed, true) ? $role : 'member';
    }

    private function normalizeHourlyRate(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (!is_numeric($normalized)) {
            throw new \RuntimeException(t('progetti.exception.hourly_rate_numeric'));
        }

        $amount = round((float) $normalized, 2);
        if ($amount < 0) {
            throw new \RuntimeException(t('progetti.exception.hourly_rate_negative'));
        }

        return $amount;
    }

    private function assertMemberManagementAllowed(int $projectId, int $actorUserId): void
    {
        if (!$this->repo->projectExists($projectId)) {
            throw new \RuntimeException(t('progetti.exception.project_not_found'));
        }

        if (!$this->canManageMembers($projectId, $actorUserId)) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_members'));
        }
    }

    private function wouldCreateCycle(int $predecessorTaskId, int $successorTaskId, array $edges): bool
    {
        $graph = [];
        foreach ($edges as $edge) {
            $pred = (int) ($edge['predecessor_task_id'] ?? 0);
            $succ = (int) ($edge['successor_task_id'] ?? 0);
            if ($pred <= 0 || $succ <= 0) {
                continue;
            }
            $graph[$pred][] = $succ;
        }

        $stack = [$successorTaskId];
        $visited = [];

        while (!empty($stack)) {
            $current = array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            if ($current === $predecessorTaskId) {
                return true;
            }

            foreach ($graph[$current] ?? [] as $next) {
                if (!isset($visited[$next])) {
                    $stack[] = $next;
                }
            }
        }

        return false;
    }

    private function bootstrapTeamsConversation(int $projectId, array $projectPayload, int $userId, string $userName): void
    {
        if (!isModuleEnabled('Teams')) {
            return;
        }

        try {
            $teamsService = app(TeamsService::class);
            $projectName = trim((string) ($projectPayload['name'] ?? t('progetti.exception.default_project_name')));
            $convName = t('progetti.exception.teams_conversation_prefix', ['name' => $projectName]);
            $description = $this->normalizeNullable($projectPayload['description'] ?? null);

            $conversationId = $teamsService->createGroup(
                $userId,
                $userName !== '' ? $userName : t('progetti.exception.default_pm_name'),
                $convName,
                $description,
                []
            );

            if ($conversationId > 0) {
                $this->repo->setTeamsConversation($projectId, $conversationId);
            }
        } catch (\Throwable $e) {
            // Keep project creation resilient if Teams is unavailable.
            error_log('[Progetti] teams.group_create: ' . $e->getMessage());
        }
    }

    private function syncTeamsMemberAdded(int $projectId, int $memberUserId, int $actorUserId): void
    {
        $this->syncTeamsMembership($projectId, $memberUserId, $actorUserId, true);
    }

    private function syncTeamsMemberRemoved(int $projectId, int $memberUserId, int $actorUserId): void
    {
        $this->syncTeamsMembership($projectId, $memberUserId, $actorUserId, false);
    }

    private function syncTeamsMembership(int $projectId, int $memberUserId, int $actorUserId, bool $shouldBePresent): void
    {
        if (!isModuleEnabled('Teams')) {
            return;
        }

        try {
            $project = $this->repo->find($projectId);
            $conversationId = (int) ($project['teams_conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                return;
            }

            $conversationRepo = app(ConversationRepository::class);
            $messageRepo = app(MessageRepository::class);
            $actorName = $this->resolveUserName($actorUserId);
            $memberName = $this->resolveUserName($memberUserId);

            if ($shouldBePresent) {
                $conversationRepo->addMember($conversationId, $memberUserId, 'member');
                $messageRepo->createSystemMessage($conversationId, t('progetti.exception.member_added_message', ['actor' => $actorName, 'member' => $memberName]));
                return;
            }

            $conversationRepo->removeMember($conversationId, $memberUserId);
            $messageRepo->createSystemMessage($conversationId, t('progetti.exception.member_removed_message', ['actor' => $actorName, 'member' => $memberName]));
        } catch (\Throwable $e) {
            // Keep project member management resilient if Teams sync fails.
            error_log('[Progetti] teams.member_sync: ' . $e->getMessage());
        }
    }

    private function archiveLinkedTeamsConversation(array $project, int $actorUserId): void
    {
        if (!isModuleEnabled('Teams')) {
            return;
        }

        $conversationId = (int) ($project['teams_conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            return;
        }

        try {
            $pdo = app(\PDO::class);
            $stmt = $pdo->prepare('SELECT archived_at FROM teams_conversations WHERE id = ? LIMIT 1');
            $stmt->execute([$conversationId]);
            $row = $stmt->fetch();

            if (!$row || !empty($row['archived_at'])) {
                return;
            }

            $conversationRepo = app(ConversationRepository::class);
            $messageRepo = app(MessageRepository::class);
            $actorName = $this->resolveUserName($actorUserId);

            $conversationRepo->archive($conversationId);
            $messageRepo->createSystemMessage(
                $conversationId,
                t('progetti.exception.archived_conversation_message', ['actor' => $actorName])
            );
        } catch (\Throwable $e) {
            // Non bloccare la delete del progetto se Teams non è raggiungibile.
            error_log('[Progetti] teams.archive: ' . $e->getMessage());
        }
    }

    private function trashLinkedProjectFiles(int $projectId): void
    {
        if (!isModuleEnabled('Files')) {
            return;
        }

        $fileIds = $this->repo->getActiveLinkedFileIds($projectId);
        if (empty($fileIds)) {
            return;
        }

        try {
            $filesService = app(\App\Modules\Files\Services\FilesService::class);

            foreach ($fileIds as $fileId) {
                if (!$filesService->softDelete((int) $fileId)) {
                    throw new \RuntimeException(t('progetti.exception.file_soft_delete_failed'));
                }
            }
        } catch (\Throwable $e) {
            // Non bloccare la delete del progetto se il soft-delete dei file fallisce.
            error_log('[Progetti] files.soft_delete: ' . $e->getMessage());
        }
    }

    private function resolveUserName(int $userId): string
    {
        try {
            $stmt = app(\PDO::class)->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            return (string) ($stmt->fetchColumn() ?: t('progetti.exception.default_user_name'));
        } catch (\Throwable) {
            return t('progetti.exception.default_user_name');
        }
    }

    public function getProjectFiles(int $projectId): array
    {
        return $this->repo->getProjectFiles($projectId);
    }

    public function logTime(int $projectId, array $data, int $userId): int
    {
        $taskId = (int) ($data['task_id'] ?? 0);
        if ($taskId <= 0) {
            throw new \RuntimeException(t('progetti.exception.select_task_for_time'));
        }

        $task = $this->repo->findTask($projectId, $taskId);
        if (!$task) {
            throw new \RuntimeException(t('progetti.exception.task_not_found_in_project'));
        }

        $hours = round((float) ($data['hours'] ?? 0), 2);
        if ($hours <= 0 || $hours > 24) {
            throw new \RuntimeException(t('progetti.exception.hours_range'));
        }

        $workDate = trim((string) ($data['work_date'] ?? ''));
        if ($workDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            throw new \RuntimeException(t('progetti.exception.invalid_work_date'));
        }

        $this->assertDatesInProjectRange($projectId, [t('progetti.exception.label_work_date') => $workDate]);

        $note = $this->normalizeNullable($data['note'] ?? null);
        if ($note !== null && mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        $id = $this->repo->createTimesheet([
            'project_id' => $projectId,
            'task_id'    => $taskId,
            'user_id'    => $userId,
            'work_date'  => $workDate,
            'hours'      => $hours,
            'note'       => $note,
        ]);

        $this->refreshProjectCaches($projectId);
        $this->checkBudgetAlert($projectId);
        return $id;
    }

    public function updateTimesheet(int $projectId, int $timesheetId, float $hours, ?string $note, int $userId): bool
    {
        if ($hours <= 0 || $hours > 24) {
            throw new \RuntimeException(t('progetti.exception.hours_range_alt'));
        }
        $note = $note !== null ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }

        $result = $this->canManageAll()
            ? $this->repo->updateTimesheetAdmin($projectId, $timesheetId, $hours, $note)
            : $this->repo->updateTimesheet($projectId, $timesheetId, $userId, $hours, $note);

        if ($result) {
            $this->refreshProjectCaches($projectId);
        }
        return $result;
    }

    public function removeTimesheet(int $projectId, int $timesheetId, int $userId): bool
    {
        // Admin e project manager possono eliminare registrazioni di altri utenti.
        $result = $this->canManageAll()
            ? $this->repo->deleteTimesheetAdmin($projectId, $timesheetId)
            : $this->repo->deleteTimesheet($projectId, $timesheetId, $userId);

        if ($result) {
            $this->refreshProjectCaches($projectId);
        }
        return $result;
    }

    public function quickUpdateTaskStatus(int $projectId, int $taskId, string $newStatus, int $userId): bool
    {
        $task = $this->repo->findTask($projectId, $taskId);
        if (!$task) {
            throw new \RuntimeException(t('progetti.exception.task_not_found'));
        }

        // L'utente deve avere progetti.edit OPPURE essere l'assegnato al task
        if (!has_permission('progetti.edit') && (int) ($task['assigned_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_status'));
        }

        $newStatus = $this->normalizeTaskStatus($newStatus);
        if (in_array($newStatus, ['in_progress', 'review', 'done'], true)) {
            $openPredecessors = $this->repo->countOpenPredecessors($projectId, $taskId);
            if ($openPredecessors > 0) {
                throw new \RuntimeException(t('progetti.exception.task_blocked_predecessors'));
            }
        }

        if ($newStatus === 'done') {
            $clRepo   = app(ChecklistRepository::class);
            $clTotal  = $clRepo->countItems($taskId);
            $clDone   = $clRepo->countDoneItems($taskId);
            if ($clTotal > 0 && $clDone < $clTotal) {
                throw new \RuntimeException(t('progetti.exception.task_blocked_checklist', ['done' => $clDone, 'total' => $clTotal]));
            }
        }

        $completedAt = $newStatus === 'done' ? date('Y-m-d H:i:s') : null;
        $result = $this->repo->updateTaskStatus($projectId, $taskId, $newStatus, $completedAt);

        if ($result) {
            $this->refreshProjectCaches($projectId);
            $this->dispatchTaskStatusChangedNotification($projectId, $task, $newStatus, $userId);
        }

        return $result;
    }

    public function moveTask(int $projectId, int $taskId, string $newStatus, int $newPosition, int $userId): bool
    {
        $task = $this->repo->findTask($projectId, $taskId);
        if (!$task) {
            throw new \RuntimeException(t('progetti.exception.task_not_found'));
        }

        // L'utente deve avere progetti.edit OPPURE essere l'assegnato al task
        if (!has_permission('progetti.edit') && (int) ($task['assigned_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException(t('progetti.exception.not_authorized_move'));
        }

        $newStatus = $this->normalizeTaskStatus($newStatus);
        if (in_array($newStatus, ['in_progress', 'review', 'done'], true)) {
            $openPredecessors = $this->repo->countOpenPredecessors($projectId, $taskId);
            if ($openPredecessors > 0) {
                throw new \RuntimeException(t('progetti.exception.task_blocked_predecessors'));
            }
        }

        if ($newStatus === 'done') {
            $clRepo   = app(ChecklistRepository::class);
            $clTotal  = $clRepo->countItems($taskId);
            $clDone   = $clRepo->countDoneItems($taskId);
            if ($clTotal > 0 && $clDone < $clTotal) {
                throw new \RuntimeException(t('progetti.exception.task_blocked_checklist', ['done' => $clDone, 'total' => $clTotal]));
            }
        }

        $completedAt = $newStatus === 'done' ? date('Y-m-d H:i:s') : null;
        $result = $this->repo->updateTaskPositionAndStatus($projectId, $taskId, $newStatus, max(0, $newPosition), $completedAt);

        if ($result) {
            $this->refreshProjectCaches($projectId);
            $this->dispatchTaskStatusChangedNotification($projectId, $task, $newStatus, $userId);
        }

        return $result;
    }

    public function refreshProjectCaches(int $projectId): void
    {
        try {
            $this->repo->updateProgressCache($projectId);
            $this->repo->updateBudgetCache($projectId);
        } catch (\Throwable $e) {
            // Non bloccare mai l'operazione principale per un errore di cache
            error_log('[Progetti] cache.refresh: ' . $e->getMessage());
        }
    }

    /**
     * Check if a project's budget has crossed an alert threshold (80% or 100%).
     * Sends a notification to the project owner when a new threshold is reached.
     */
    private function checkBudgetAlert(int $projectId): void
    {
        try {
            $kpi = $this->getDashboardKpi($projectId);

            $budgetPlanned = (float) ($kpi['budget_planned'] ?? 0);
            if ($budgetPlanned <= 0) {
                return;
            }

            $burnPct = (float) ($kpi['budget_burn_pct'] ?? 0);

            // Determine which threshold was crossed
            $threshold = null;
            if ($burnPct >= 100) {
                $threshold = 100;
            } elseif ($burnPct >= 80) {
                $threshold = 80;
            }

            if ($threshold === null) {
                return;
            }

            // Look up project owner
            $pdo   = app(\PDO::class);
            $stmt  = $pdo->prepare('SELECT name, created_by FROM projects WHERE id = ?');
            $stmt->execute([$projectId]);
            $proj  = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$proj || empty($proj['created_by'])) {
                return;
            }

            // Avoid duplicate notifications: check if we already sent this threshold today
            $cacheKey = "budget_alert_{$projectId}_{$threshold}";
            $checkStmt = $pdo->prepare(
                "SELECT id FROM audit_logs
                 WHERE entity = 'project' AND entity_id = ? AND action = ?
                   AND created_at >= CURDATE()
                 LIMIT 1"
            );
            $checkStmt->execute([$projectId, "budget_alert_{$threshold}"]);
            if ($checkStmt->fetch()) {
                return; // Already notified today
            }

            \App\Services\AuditService::log(
                "budget_alert_{$threshold}",
                'project',
                $projectId,
                null,
                ['burn_pct' => round($burnPct, 1)]
            );

            \App\Modules\Notifications\Services\NotificationService::dispatchEventToUser(
                'progetti.budget_alert',
                'Progetti',
                (int) $proj['created_by'],
                [
                    'project_id'      => $projectId,
                    'project_name'    => $proj['name'],
                    'budget_burn_pct' => round($burnPct, 1),
                    'threshold'       => $threshold,
                ],
                route('projects.show', ['id' => $projectId])
            );
        } catch (\Throwable $e) {
            // Never break timesheet creation for a failed budget alert
            error_log('[Progetti] notify.budget_alert: ' . $e->getMessage());
        }
    }

    public function uploadProjectFile(int $projectId, array $uploadedFile, array $meta, int $userId): int
    {
        if (!isModuleEnabled('Files')) {
            throw new \RuntimeException(t('progetti.exception.files_module_unavailable'));
        }
        $fileId = app(\App\Modules\Files\Services\FilesService::class)->store($uploadedFile, $meta, $userId);
        $this->repo->linkFile($projectId, $fileId, $userId);
        return $fileId;
    }

    public function unlinkProjectFile(int $projectId, int $fileId, int $userId): bool
    {
        return $this->repo->unlinkFile($projectId, $fileId);
    }

    public function getReportData(int $projectId): array
    {
        $kpi = $this->getDashboardKpi($projectId);
        $data = $this->repo->getReportData($projectId);
        $budgetByMilestone = $this->repo->getBudgetByMilestone($projectId);
        return array_merge(['kpi' => $kpi, 'budget_by_milestone' => $budgetByMilestone], $data);
    }

    private function syncMilestoneCalendarEvent(int $projectId, int $milestoneId, int $userId): void
    {
        if (!isModuleEnabled('Calendar')) {
            return;
        }
        try {
            $milestone = $this->repo->findMilestone($projectId, $milestoneId);
            if (!$milestone) {
                return;
            }

            $hasDueDate = !empty($milestone['due_date']);
            $existingEventId = (int) ($milestone['calendar_event_id'] ?? 0);

            if (!$hasDueDate) {
                if ($existingEventId > 0) {
                    app(\App\Modules\Calendar\Repositories\CalendarRepository::class)->delete($existingEventId);
                    $this->repo->setMilestoneCalendarEvent($milestoneId, null);
                }
                return;
            }

            $eventData = [
                'title'            => '🎯 ' . (string) $milestone['name'],
                'description'      => t('progetti.exception.calendar_milestone_description'),
                'start_datetime'   => $milestone['due_date'] . ' 00:00:00',
                'end_datetime'     => null,
                'all_day'          => 1,
                'color'            => '#6f42c1',
                'location'         => null,
                'visibility'       => 'personal',
                'visible_to_role'  => null,
                'reminder_minutes' => null,
                'created_by'       => $userId,
            ];

            $calRepo = app(\App\Modules\Calendar\Repositories\CalendarRepository::class);
            if ($existingEventId > 0) {
                $calRepo->update($existingEventId, $eventData);
            } else {
                $newId = $calRepo->create($eventData);
                $this->repo->setMilestoneCalendarEvent($milestoneId, $newId);
            }
        } catch (\Throwable $e) {
            // Non bloccare su errore Calendario
            error_log('[Progetti] calendar.milestone_sync: ' . $e->getMessage());
        }
    }

    private function syncTaskCalendarEvent(int $projectId, int $taskId): void
    {
        if (!isModuleEnabled('Calendar')) {
            return;
        }
        try {
            $task = $this->repo->findTask($projectId, $taskId);
            if (!$task) {
                return;
            }

            $hasDueDate    = !empty($task['due_date']);
            $assigneeId    = (int) ($task['assigned_user_id'] ?? 0);
            $existingEventId = (int) ($task['calendar_event_id'] ?? 0);

            if (!$hasDueDate || $assigneeId <= 0) {
                if ($existingEventId > 0) {
                    app(\App\Modules\Calendar\Repositories\CalendarRepository::class)->delete($existingEventId);
                    $this->repo->setTaskCalendarEvent($taskId, null);
                }
                return;
            }

            $eventData = [
                'title'            => '📋 ' . (string) $task['title'],
                'description'      => t('progetti.exception.calendar_task_description'),
                'start_datetime'   => $task['due_date'] . ' 00:00:00',
                'end_datetime'     => null,
                'all_day'          => 1,
                'color'            => '#0d6efd',
                'location'         => null,
                'visibility'       => 'personal',
                'visible_to_role'  => null,
                'reminder_minutes' => null,
                'created_by'       => $assigneeId,
            ];

            $calRepo = app(\App\Modules\Calendar\Repositories\CalendarRepository::class);
            if ($existingEventId > 0) {
                $calRepo->update($existingEventId, $eventData);
            } else {
                $newId = $calRepo->create($eventData);
                $this->repo->setTaskCalendarEvent($taskId, $newId);
            }
        } catch (\Throwable $e) {
            // Non bloccare su errore Calendario
            error_log('[Progetti] calendar.task_sync: ' . $e->getMessage());
        }
    }

    private function dispatchTaskStatusChangedNotification(int $projectId, array $task, string $newStatus, int $actorId): void
    {
        if (!in_array($newStatus, ['review', 'done'], true)) {
            return;
        }
        if (!isModuleEnabled('Notifications')) {
            return;
        }
        try {
            $project = $this->repo->find($projectId);
            $ownerUserId = (int) ($project['owner_user_id'] ?? 0);
            if ($ownerUserId <= 0 || $ownerUserId === $actorId) {
                return;
            }
            $statusLabel = $newStatus === 'done' ? t('progetti.exception.task_status_done') : t('progetti.exception.task_status_review');
            $taskTitle = (string) ($task['title'] ?? t('progetti.exception.default_task_name'));
            $projectName = (string) ($project['name'] ?? t('progetti.exception.default_project_name'));
            \App\Modules\Notifications\Services\NotificationService::dispatchEventToUser(
                'progetti.task_status_changed',
                'Progetti',
                $ownerUserId,
                [
                    'project_id'   => $projectId,
                    'project_name' => $projectName,
                    'task_title'   => $taskTitle,
                    'status_label' => $statusLabel,
                ],
                route('projects.kanban', ['id' => $projectId]),
                $actorId
            );
        } catch (\Throwable $e) {
            // Non bloccare l'operazione principale per un errore di notifica
            error_log('[Progetti] notify.task_status_changed: ' . $e->getMessage());
        }
    }

    private function dispatchTaskAssignedNotification(int $projectId, int $taskId, int $assignedUserId, string $taskTitle, int $fromUserId): void
    {
        if (!isModuleEnabled('Notifications')) {
            return;
        }
        try {
            $project = $this->repo->findForUser($projectId, $fromUserId, true);
            \App\Modules\Notifications\Services\NotificationService::dispatchEventToUser(
                'progetti.task_assigned',
                'Progetti',
                $assignedUserId,
                [
                    'project_id' => $projectId,
                    'project_name' => (string) ($project['name'] ?? t('progetti.exception.default_project_name')),
                    'task_id' => $taskId,
                    'task_title' => $taskTitle,
                ],
                route('projects.show', ['id' => $projectId]),
                $fromUserId
            );
        } catch (\Throwable $e) {
            error_log('[Progetti] notify.task_assigned: ' . $e->getMessage());
        }
    }

    /**
     * Invia notifiche task_due_soon agli assegnatari.
     * Utile per cron job giornaliero.
     */
    public function sendTaskDueReminders(int $days = 7): int
    {
        if (!class_exists(\App\Modules\Notifications\Services\NotificationService::class)) {
            return 0;
        }

        $tasks = $this->repo->getTasksDueSoon();
        $sent = 0;
        $today = new \DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');
        $maxDate = $today->modify('+' . max(1, $days) . ' days');
        $pdo = app(\PDO::class);

        foreach ($tasks as $task) {
            $dueDateRaw = (string) ($task['due_date'] ?? '');
            if ($dueDateRaw === '') {
                continue;
            }

            // Un task viene notificato al massimo una volta al giorno
            if (($task['last_reminded_date'] ?? null) === $todayStr) {
                continue;
            }

            try {
                $dueDate = new \DateTimeImmutable($dueDateRaw);
            } catch (\Throwable) {
                continue;
            }

            $dueDay = new \DateTimeImmutable($dueDate->format('Y-m-d'));
            if ($dueDay < $today || $dueDay > $maxDate) {
                continue;
            }

            $projectId = (int) $task['project_id'];
            $link = '/projects/' . $projectId;
            try {
                $link = route('projects.show', ['id' => $projectId]);
            } catch (\Throwable) {
            }

            $notified = false;
            try {
                \App\Modules\Notifications\Services\NotificationService::dispatchEventToUser(
                    'progetti.task_due_soon',
                    'Progetti',
                    (int) $task['assigned_user_id'],
                    [
                        'project_id' => (int) $task['project_id'],
                        'project_name' => (string) ($task['project_name'] ?? t('progetti.exception.default_project_name')),
                        'task_id' => (int) $task['id'],
                        'task_title' => (string) $task['task_title'],
                        'due_date' => (string) ($task['due_date'] ?? ''),
                    ],
                    $link,
                    null
                );
                $sent++;
                $notified = true;
            } catch (\Throwable) {
                // Fallback legacy per non perdere reminder in ambienti non allineati.
                try {
                    $taskTitle = (string) $task['task_title'];
                    $projectName = (string) ($task['project_name'] ?? t('progetti.exception.default_project_name'));
                    $dueDateLabel = (string) ($task['due_date'] ?? '');
                    \App\Modules\Notifications\Services\NotificationService::send(
                        (int) $task['assigned_user_id'],
                        t('progetti.exception.due_soon_subject', ['task' => $taskTitle]),
                        t('progetti.exception.due_soon_body', ['task' => $taskTitle, 'project' => $projectName, 'date' => $dueDateLabel]),
                        'warning',
                        $link,
                        null
                    );
                    $sent++;
                    $notified = true;
                } catch (\Throwable $e) {
                    // Continua con il prossimo task se anche il fallback fallisce.
                    error_log('[Progetti] notify.task_due_soon: ' . $e->getMessage());
                }
            }

            if ($notified) {
                $pdo->prepare('UPDATE project_tasks SET last_reminded_date = ? WHERE id = ?')
                    ->execute([$todayStr, $task['id']]);
            }
        }

        return $sent;
    }

    private function canViewAll(): bool
    {
        return has_permission('progetti.view_all') || $this->canManageAll();
    }

    private function canManageAll(): bool
    {
        return has_permission('progetti.manage_all');
    }
}
