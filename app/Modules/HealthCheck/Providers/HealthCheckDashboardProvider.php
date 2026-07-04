<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\HealthCheck\Services\HealthCheckService;

class HealthCheckDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'healthcheck.status',
                'type'       => 'stat',
                'label'      => t('healthcheck.widget.label'),
                'icon'       => 'fa-heart-pulse',
                'size'       => 3,
                'permission' => 'healthcheck.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        if ($widgetId !== 'healthcheck.status') {
            return null;
        }

        // Legge solo l'ULTIMO run salvato: non esegue i controlli a runtime.
        try {
            $history = app(HealthCheckService::class)->getHistory(1, 1);
            $run     = $history['items'][0] ?? null;
        } catch (\Throwable) {
            return null;
        }

        if ($run === null) {
            return ['data' => [
                'value'    => '—',
                'subtitle' => t('healthcheck.widget.never'),
                'link'     => route('healthcheck.index'),
                'color'    => 'secondary',
            ]];
        }

        $fail = (int) ($run['total_fail'] ?? 0);
        $warn = (int) ($run['total_warn'] ?? 0);
        $ok   = (int) ($run['total_ok'] ?? 0);
        $when = format_date_it((string) ($run['created_at'] ?? ''), 'compact');

        if ($fail > 0) {
            $value = $fail;
            $color = 'danger';
            $label = $fail === 1 ? t('healthcheck.widget.fail_one') : t('healthcheck.widget.fail_many', ['count' => $fail]);
        } elseif ($warn > 0) {
            $value = $warn;
            $color = 'warning';
            $label = $warn === 1 ? t('healthcheck.widget.warn_one') : t('healthcheck.widget.warn_many', ['count' => $warn]);
        } else {
            $value = 'OK';
            $color = 'success';
            $label = t('healthcheck.widget.passed', ['count' => $ok]);
        }

        return ['data' => [
            'value'    => $value,
            'subtitle' => $when !== '' ? $label . ' · ' . $when : $label,
            'link'     => route('healthcheck.index'),
            'color'    => $color,
        ]];
    }
}
