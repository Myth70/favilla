<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Calendar\Services\CalendarService;

class CalendarDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'calendar.stat',
                'type'       => 'stat',
                'label'      => t('calendar.widget.stat_label'),
                'icon'       => 'fa-calendar',
                'size'       => 3,
                'permission' => 'calendar.view',
            ],
            [
                'id'         => 'calendar.upcoming',
                'type'       => 'list',
                'label'      => t('calendar.widget.agenda_label'),
                'icon'       => 'fa-calendar',
                'size'       => 6,
                'permission' => 'calendar.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        $service = app(CalendarService::class);

        return match ($widgetId) {
            'calendar.stat'     => $this->statData($service, $userId),
            'calendar.upcoming' => $this->upcomingData($service, $userId),
            default             => null,
        };
    }

    private function statData(CalendarService $service, int $userId): array
    {
        $count = $service->countUpcomingEvents($userId);

        return ['data' => [
            'value'    => $count,
            'subtitle' => $count === 1 ? t('calendar.widget.events_one') : t('calendar.widget.events_many'),
            'link'     => route('calendar.index'),
            'color'    => $count > 0 ? 'info' : 'secondary',
        ]];
    }

    private function upcomingData(CalendarService $service, int $userId): array
    {
        $upcoming = $service->getUpcomingEvents($userId, 10);
        $cutoff   = strtotime('+30 days');
        $upcoming = array_filter($upcoming, fn ($ev) => strtotime($ev['start_datetime']) <= $cutoff);
        $upcoming = array_slice(array_values($upcoming), 0, 5);

        $todayTs = strtotime(date('Y-m-d') . ' 00:00:00');
        $rows    = [];
        foreach ($upcoming as $ev) {
            $ts = strtotime($ev['start_datetime']);
            if ($ts === false) {
                continue;
            }

            $days = (int) floor(($ts - $todayTs) / 86400);
            if ($days === 0) {
                $whenBadge = '<span class="badge bg-danger bg-opacity-10 text-danger">' . e(t('calendar.widget.today')) . '</span>';
            } elseif ($days === 1) {
                $whenBadge = '<span class="badge bg-warning bg-opacity-10 text-warning">' . e(t('calendar.widget.tomorrow')) . '</span>';
            } else {
                $whenBadge = '<span class="badge bg-secondary bg-opacity-10 text-secondary">' . e(t('calendar.widget.in_days', ['count' => $days])) . '</span>';
            }

            $dateStr = format_date_it($ev['start_datetime'], 'short');
            if (date('H:i', $ts) !== '00:00') {
                $dateStr .= ' ' . date('H:i', $ts);
            }

            $rows[] = [
                $ev['title'],
                $dateStr,
                ['html' => $whenBadge],
            ];
        }

        return ['data' => [
            'columns'      => [t('calendar.widget.col_event'), t('calendar.widget.col_date'), t('calendar.widget.col_when')],
            'rows'         => $rows,
            'emptyMessage' => t('calendar.widget.empty'),
            'link'         => route('calendar.index'),
            'iconColor'    => 'info',
        ]];
    }
}
