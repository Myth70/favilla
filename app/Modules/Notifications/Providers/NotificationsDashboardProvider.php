<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Notifications\Services\NotificationService;

class NotificationsDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'notifications.stat',
                'type'       => 'stat',
                'label'      => t('notifications.widget.stat_label'),
                'icon'       => 'fa-bell',
                'size'       => 3,
                'permission' => null,
            ],
            [
                'id'         => 'notifications.list',
                'type'       => 'list',
                'label'      => t('notifications.widget.list_label'),
                'icon'       => 'fa-bell',
                'size'       => 6,
                'permission' => null,
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        return match ($widgetId) {
            'notifications.stat' => $this->statData($userId),
            'notifications.list' => $this->listData($userId),
            default              => null,
        };
    }

    private function statData(int $userId): array
    {
        $unread = NotificationService::getUnreadCount($userId);

        return ['data' => [
            'value'    => $unread,
            'subtitle' => $unread === 1 ? t('notifications.widget.unread_one') : t('notifications.widget.unread_many'),
            'link'     => route('notifications.index'),
            'color'    => $unread > 0 ? 'warning' : 'secondary',
        ]];
    }

    private function listData(int $userId): array
    {
        $notifications = NotificationService::getUnread($userId, 5);
        $typeBadges = [
            'info'    => ['color' => 'info',    'label' => t('notifications.widget.type_info')],
            'success' => ['color' => 'success', 'label' => t('notifications.widget.type_success')],
            'warning' => ['color' => 'warning', 'label' => t('notifications.widget.type_warning')],
            'danger'  => ['color' => 'danger',  'label' => t('notifications.widget.type_danger')],
        ];
        $rows = [];
        foreach ($notifications as $n) {
            $tm = $typeBadges[$n['type'] ?? 'info'] ?? $typeBadges['info'];
            $rows[] = [
                $n['title'] ?? '',
                ['html' => '<span class="badge bg-' . $tm['color'] . ' bg-opacity-10 text-' . $tm['color'] . '">' . e($tm['label']) . '</span>'],
                format_date_it($n['created_at'] ?? '', 'relative'),
            ];
        }

        return ['data' => [
            'columns'      => [t('notifications.widget.col_title'), t('notifications.widget.col_type'), t('notifications.widget.col_date')],
            'rows'         => $rows,
            'emptyMessage' => t('notifications.widget.empty'),
            'link'         => route('notifications.index'),
            'iconColor'    => 'warning',
        ]];
    }
}
