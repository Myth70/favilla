<?php

declare(strict_types=1);

namespace App\Modules\Reports\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Reports\Services\HistoryService;

class ReportsDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'reports.generated',
                'type'       => 'stat',
                'label'      => t('reports.widget.label'),
                'icon'       => 'fa-file-export',
                'size'       => 3,
                'permission' => 'reports.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        if ($widgetId !== 'reports.generated') {
            return null;
        }

        try {
            $stats = app(HistoryService::class)->getStats();
        } catch (\Throwable) {
            return null;
        }

        $total = (int) ($stats['total_reports'] ?? 0);
        $last  = (string) ($stats['last_generated_at'] ?? '');
        $when  = $last !== '' ? format_date_it($last, 'compact') : '';

        return ['data' => [
            'value'    => $total,
            'subtitle' => ($total > 0 && $when !== '') ? t('reports.widget.last_sub', ['when' => $when]) : t('reports.widget.total_sub'),
            'link'     => route('reports.history.index'),
            'color'    => 'orange',
        ]];
    }
}
