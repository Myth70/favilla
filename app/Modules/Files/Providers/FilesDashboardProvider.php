<?php

declare(strict_types=1);

namespace App\Modules\Files\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Files\Services\FilesService;

class FilesDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'files.stat',
                'type'       => 'stat',
                'label'      => t('files.widget.stat_label'),
                'icon'       => 'fa-folder-open',
                'size'       => 3,
                'permission' => null,
            ],
            [
                'id'         => 'files.recent',
                'type'       => 'list',
                'label'      => t('files.widget.recent_label'),
                'icon'       => 'fa-clock',
                'size'       => 6,
                'permission' => null,
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        $service = app(FilesService::class);

        return match ($widgetId) {
            'files.stat'   => $this->statData($service, $userId),
            'files.recent' => $this->recentData($service, $userId),
            default        => null,
        };
    }

    private function statData(FilesService $service, int $userId): array
    {
        $stats = $service->getUserStats($userId);

        return ['data' => [
            'value'    => $stats['total_files'],
            'subtitle' => t('files.widget.used_sub', ['size' => $stats['total_size_hr']]),
            'link'     => route('files.index'),
            'color'    => 'info',
        ]];
    }

    private function recentData(FilesService $service, int $userId): array
    {
        $recent = $service->getRecentByUser($userId, 5);
        $rows = [];
        foreach ($recent as $f) {
            $rows[] = [
                $f['original_name'],
                FilesService::humanSize((int) $f['size_bytes']),
                format_date_it($f['created_at'], 'relative'),
            ];
        }

        return ['data' => [
            'columns'      => [t('files.widget.col_name'), t('files.widget.col_size'), t('files.widget.col_date')],
            'rows'         => $rows,
            'emptyMessage' => t('files.widget.empty'),
            'link'         => route('files.index'),
            'iconColor'    => 'info',
        ]];
    }
}
