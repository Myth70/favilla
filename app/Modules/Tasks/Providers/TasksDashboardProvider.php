<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Tasks\Services\TasksService;

class TasksDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'tasks.active',
                'type'       => 'stat',
                'label'      => t('tasks.widget.active_label'),
                'icon'       => 'fa-clipboard-check',
                'size'       => 3,
                'permission' => 'tasks.view',
            ],
            [
                'id'         => 'tasks.upcoming',
                'type'       => 'list',
                'label'      => t('tasks.widget.upcoming_label'),
                'icon'       => 'fa-clipboard-check',
                'size'       => 6,
                'permission' => 'tasks.view',
            ],
            [
                'id'         => 'tasks.trend',
                'type'       => 'chart',
                'label'      => t('tasks.widget.trend_label'),
                'icon'       => 'fa-chart-line',
                'size'       => 6,
                'permission' => 'tasks.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        $service = app(TasksService::class);

        return match ($widgetId) {
            'tasks.active'   => $this->activeData($service, $userId),
            'tasks.upcoming' => $this->upcomingData($service, $userId),
            'tasks.trend'    => $this->trendData($service, $userId),
            default          => null,
        };
    }

    private function activeData(TasksService $service, int $userId): array
    {
        $stats   = $service->getStats($userId);
        $active  = $stats['active'];
        $dueSoon = $service->getDueSoon($userId, 7, 5);

        return ['data' => [
            'value'    => $active,
            'subtitle' => $stats['overdue'] > 0
                ? t('tasks.widget.overdue_sub', ['count' => $stats['overdue']])
                : t('tasks.widget.duesoon_sub', ['count' => count($dueSoon)]),
            'link'     => route('tasks.index'),
            'color'    => $stats['overdue'] > 0 ? 'danger' : ($active > 0 ? 'primary' : 'success'),
        ]];
    }

    private function upcomingData(TasksService $service, int $userId): array
    {
        $dueSoon  = $service->getDueSoon($userId, 7, 5);
        $overdue  = $service->getOverdue($userId, 5);
        $upcoming = array_slice(array_merge($overdue, $dueSoon), 0, 5);

        $rows = [];
        foreach ($upcoming as $task) {
            $priorityMeta = TasksService::getPriorities()[$task['priority']] ?? ['color' => 'secondary', 'label' => '?'];
            $isOverdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d');

            if ($isOverdue) {
                $dueBadge = '<span class="badge bg-danger bg-opacity-10 text-danger">'
                    . '<i class="fa-solid fa-exclamation-triangle me-1"></i>' . e(t('tasks.widget.due_prefix')) . ' '
                    . e(format_date_it($task['due_date'], 'short'))
                    . '</span>';
            } else {
                $dueBadge = '<span class="text-muted small">' . format_date_it($task['due_date'], 'compact') . '</span>';
            }

            $rows[] = [
                ['html' => '<a href="' . e(route('tasks.show', ['id' => $task['id']])) . '" class="text-decoration-none">' . e($task['title']) . '</a>'],
                ['html' => '<span class="badge bg-' . $priorityMeta['color'] . ' bg-opacity-10 text-' . $priorityMeta['color'] . '">' . e($priorityMeta['label']) . '</span>'],
                ['html' => $dueBadge],
            ];
        }

        return ['data' => [
            'columns'      => [t('tasks.widget.col_task'), t('tasks.widget.col_priority'), t('tasks.widget.col_due')],
            'rows'         => $rows,
            'emptyMessage' => t('tasks.widget.upcoming_empty'),
            'link'         => route('tasks.index'),
            'iconColor'    => 'primary',
        ]];
    }

    private function trendData(TasksService $service, int $userId): ?array
    {
        $trend = $service->getWeeklyTrend($userId, 8);

        // Mappa week_start (MIN date DB) → completamenti
        $trendMap = [];
        foreach ($trend as $w) {
            $trendMap[$w['week_start']] = (int) $w['completed'];
        }

        // Genera sempre 8 slot settimanali consecutivi, riempiendo a 0 le settimane vuote
        $categories = [];
        $dataPoints = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekTs   = strtotime("-{$i} week", strtotime('monday this week'));
            $weekDate = date('Y-m-d', $weekTs);
            $categories[] = date('d/m', $weekTs);
            $dataPoints[] = $trendMap[$weekDate] ?? 0;
        }

        // Nasconde il widget quando non ci sono completamenti da mostrare.
        if (array_sum($dataPoints) === 0) {
            return null;
        }

        return ['data' => [
            'chartId'   => 'tasks-trend',
            'chartType' => 'area',
            'series'    => [['name' => t('tasks.widget.series_completed'), 'data' => $dataPoints]],
            'options'   => [
                'chart'  => ['height' => 220, 'toolbar' => ['show' => false]],
                'xaxis'  => ['categories' => $categories],
                'colors' => ['#198754'],
                'stroke' => ['curve' => 'smooth', 'width' => 2],
                'fill'   => ['type' => 'gradient', 'gradient' => ['opacityFrom' => 0.4, 'opacityTo' => 0.05]],
            ],
        ]];
    }
}
