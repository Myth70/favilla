<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Feedback\Services\FeedbackService;

class FeedbackDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'feedback.open',
                'type'       => 'stat',
                'label'      => t('feedback.widget.label'),
                'icon'       => 'fa-bug',
                'size'       => 3,
                'permission' => 'feedback.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        if ($widgetId !== 'feedback.open') {
            return null;
        }

        $service = app(FeedbackService::class);
        $open    = $service->countOpen();
        $new     = $service->countNew();

        return ['data' => [
            'value'    => $open,
            'subtitle' => $new > 0 ? t('feedback.widget.new_sub', ['count' => $new]) : t('feedback.widget.none_new'),
            'link'     => route('feedback.admin.index'),
            'color'    => $open > 0 ? ($new > 0 ? 'warning' : 'primary') : 'success',
        ]];
    }
}
