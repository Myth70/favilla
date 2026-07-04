<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Progetti\Services\ProgettiService;

class ProgettiDashboardProvider implements DashboardWidgetProvider
{
    /** Token colore Bootstrap → hex (per le fette ApexCharts del donut). */
    private const COLOR_HEX = [
        'primary'   => '#0d6efd',
        'secondary' => '#6c757d',
        'success'   => '#198754',
        'warning'   => '#ffc107',
        'danger'    => '#dc3545',
        'info'      => '#0dcaf0',
        'dark'      => '#212529',
    ];

    public function getWidgets(int $userId): array
    {
        if (!has_permission('progetti.view')) {
            return [];
        }

        return [
            [
                'id'         => 'progetti.overview',
                'type'       => 'stat',
                'label'      => t('progetti.widget.overview_label'),
                'icon'       => 'fa-diagram-project',
                'size'       => 3,
                'permission' => 'progetti.view',
            ],
            [
                'id'         => 'progetti.my_tasks',
                'type'       => 'list',
                'label'      => t('progetti.widget.my_tasks_label'),
                'icon'       => 'fa-list-check',
                'size'       => 6,
                'permission' => 'progetti.view',
            ],
            [
                'id'         => 'progetti.milestones',
                'type'       => 'list',
                'label'      => t('progetti.widget.milestones_label'),
                'icon'       => 'fa-flag-checkered',
                'size'       => 6,
                'permission' => 'progetti.view',
            ],
            [
                'id'         => 'progetti.status',
                'type'       => 'chart',
                'label'      => t('progetti.widget.status_label'),
                'icon'       => 'fa-chart-pie',
                'size'       => 6,
                'permission' => 'progetti.view',
            ],
            [
                'id'         => 'progetti.budget',
                'type'       => 'stat',
                'label'      => t('progetti.widget.budget_label'),
                'icon'       => 'fa-coins',
                'size'       => 3,
                'permission' => 'progetti.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        $service = app(ProgettiService::class);

        return match ($widgetId) {
            'progetti.overview'   => $this->overviewWidget($service, $userId),
            'progetti.my_tasks'   => $this->myTasksWidget($service, $userId),
            'progetti.milestones' => $this->milestonesWidget($service, $userId),
            'progetti.status'     => $this->statusChartWidget($service, $userId),
            'progetti.budget'     => $this->budgetWidget($service, $userId),
            default               => null,
        };
    }

    private function overviewWidget(ProgettiService $service, int $userId): array
    {
        $stats   = $service->getWidgetStats($userId);
        $active  = (int) ($stats['active_projects'] ?? 0);
        $delayed = (int) ($stats['delayed_projects'] ?? 0);
        $total   = (int) ($stats['total_projects'] ?? 0);

        return [
            'data' => [
                'value'    => $active,
                'subtitle' => $delayed > 0
                    ? t('progetti.widget.overview_subtitle_delayed', ['delayed' => $delayed, 'total' => $total])
                    : t('progetti.widget.overview_subtitle_total', ['total' => $total]),
                'link'     => route('projects.index'),
                'color'    => $delayed > 0 ? 'danger' : ($active > 0 ? 'primary' : 'success'),
            ],
        ];
    }

    private function myTasksWidget(ProgettiService $service, int $userId): array
    {
        $tasks      = $service->getMyTasksDueSoon($userId, 7);
        $priorities = ProgettiService::getPriorityConfig();
        $today      = strtotime('today');
        $rows       = [];

        foreach ($tasks as $t) {
            $pid     = (int) ($t['project_id'] ?? 0);
            $title   = trim((string) ($t['title'] ?? '')) ?: t('progetti.widget.task_fallback');
            $project = trim((string) ($t['project_name'] ?? ''));
            $prio    = (string) ($t['priority'] ?? 'medium');
            $due     = (string) ($t['due_date'] ?? '');

            $prioCfg   = $priorities[$prio] ?? ['label' => $prio, 'color' => 'secondary'];
            $titleHtml = '<a href="' . e(route('projects.show', ['id' => $pid])) . '" class="text-decoration-none">' . e($title) . '</a>'
                . ' <span class="badge bg-' . $prioCfg['color'] . ' bg-opacity-10 text-' . $prioCfg['color'] . '">' . e($prioCfg['label']) . '</span>';

            $rows[] = [
                ['html' => $titleHtml],
                $project !== '' ? $project : '—',
                ['html' => $this->dueBadge($due, $today)],
            ];
        }

        return [
            'data' => [
                'columns'      => [t('progetti.widget.col_task'), t('progetti.widget.col_project'), t('progetti.widget.col_due')],
                'rows'         => $rows,
                'emptyMessage' => t('progetti.widget.my_tasks_empty'),
                'link'         => route('projects.my_tasks'),
                'iconColor'    => 'primary',
            ],
        ];
    }

    private function milestonesWidget(ProgettiService $service, int $userId): array
    {
        $milestones = $service->getMilestonesDueSoon($userId, 30);
        $today      = strtotime('today');
        $rows       = [];

        foreach ($milestones as $m) {
            $pid     = (int) ($m['project_id'] ?? 0);
            $name    = trim((string) ($m['name'] ?? '')) ?: t('progetti.widget.milestone_fallback');
            $project = trim((string) ($m['project_name'] ?? ''));
            $due     = (string) ($m['due_date'] ?? '');

            $nameHtml = '<a href="' . e(route('projects.show', ['id' => $pid])) . '" class="text-decoration-none">' . e($name) . '</a>';

            $rows[] = [
                ['html' => $nameHtml],
                $project !== '' ? $project : '—',
                ['html' => $this->dueBadge($due, $today)],
            ];
        }

        return [
            'data' => [
                'columns'      => [t('progetti.widget.col_milestone'), t('progetti.widget.col_project'), t('progetti.widget.col_due')],
                'rows'         => $rows,
                'emptyMessage' => t('progetti.widget.milestones_empty'),
                'link'         => route('projects.index'),
                'iconColor'    => 'warning',
            ],
        ];
    }

    private function statusChartWidget(ProgettiService $service, int $userId): ?array
    {
        $breakdown = $service->getStatusBreakdown($userId);
        $statuses  = ProgettiService::getProjectStatuses();

        $labels = [];
        $series = [];
        $colors = [];
        $total  = 0;

        foreach ($statuses as $key => $cfg) {
            $count = (int) ($breakdown[$key] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $labels[] = $cfg['label'];
            $series[] = $count;
            $colors[] = self::COLOR_HEX[$cfg['color']] ?? self::COLOR_HEX['secondary'];
            $total   += $count;
        }

        if ($total === 0) {
            return null;
        }

        return [
            'data' => [
                'chartId'   => 'progetti-status',
                'chartType' => 'donut',
                'series'    => $series,
                'iconColor' => 'primary',
                'options'   => [
                    'chart'      => ['height' => 260, 'toolbar' => ['show' => false]],
                    'labels'     => $labels,
                    'colors'     => $colors,
                    'legend'     => ['position' => 'bottom'],
                    'dataLabels' => ['enabled' => true],
                    'stroke'     => ['width' => 0],
                ],
            ],
        ];
    }

    private function budgetWidget(ProgettiService $service, int $userId): ?array
    {
        $budget  = $service->getBudgetAggregate($userId);
        $planned = (float) ($budget['planned'] ?? 0);
        $actual  = (float) ($budget['actual'] ?? 0);

        if ($planned <= 0) {
            return null;
        }

        $burnPct = (int) round(($actual / $planned) * 100);

        return [
            'data' => [
                'value'    => $burnPct . '%',
                'subtitle' => $this->formatEuro($actual) . ' ' . t('progetti.widget.budget_of') . ' ' . $this->formatEuro($planned),
                'link'     => route('projects.index'),
                'color'    => ProgettiService::kpiColor((float) $burnPct, 'burn'),
            ],
        ];
    }

    private function dueBadge(string $date, int $today): string
    {
        $ts = $date !== '' ? strtotime($date) : false;
        if ($ts === false) {
            return '<span class="text-muted">—</span>';
        }

        $days = (int) floor(($ts - $today) / 86400);
        if ($days < 0) {
            $color = 'danger';
            $label = t('progetti.widget.due_overdue');
        } elseif ($days === 0) {
            $color = 'danger';
            $label = t('progetti.widget.due_today');
        } elseif ($days === 1) {
            $color = 'warning';
            $label = t('progetti.widget.due_tomorrow');
        } elseif ($days <= 7) {
            $color = 'warning';
            $label = t('progetti.widget.due_in_days', ['days' => $days]);
        } else {
            $color = 'secondary';
            $label = format_date(date('Y-m-d H:i:s', $ts), 'short');
        }

        return '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . '">' . e($label) . '</span>';
    }

    private function formatEuro(float $amount): string
    {
        return '€ ' . number_format($amount, 0, ',', '.');
    }
}
