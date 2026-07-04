<?php

declare(strict_types=1);

namespace App\Modules\Scheduler\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Scheduler\Services\SchedulerService;

class SchedulerDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'scheduler.jobs',
                'type'       => 'stat',
                'label'      => t('scheduler.widget.label'),
                'icon'       => 'fa-clock-rotate-left',
                'size'       => 3,
                'permission' => 'scheduler.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        if ($widgetId !== 'scheduler.jobs') {
            return null;
        }

        try {
            $jobs = app(SchedulerService::class)->getJobs();
        } catch (\Throwable) {
            return null;
        }

        $enabled = 0;
        $failed  = 0;
        foreach ($jobs as $job) {
            if (!empty($job['enabled'])) {
                $enabled++;
            }
            if (($job['last_status'] ?? '') === 'failed') {
                $failed++;
            }
        }

        return ['data' => [
            'value'    => $enabled,
            'subtitle' => $failed > 0
                ? ($failed === 1 ? t('scheduler.widget.failed_one') : t('scheduler.widget.failed_many', ['count' => $failed]))
                : t('scheduler.widget.active_sub', ['count' => count($jobs)]),
            'link'     => route('scheduler.index'),
            'color'    => $failed > 0 ? 'danger' : ($enabled > 0 ? 'primary' : 'secondary'),
        ]];
    }
}
