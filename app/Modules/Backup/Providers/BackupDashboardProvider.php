<?php

declare(strict_types=1);

namespace App\Modules\Backup\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Backup\Services\BackupService;

class BackupDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'backup.last',
                'type'       => 'stat',
                'label'      => t('backup.widget.label'),
                'icon'       => 'fa-database',
                'size'       => 3,
                'permission' => 'backup.manage',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        if ($widgetId !== 'backup.last') {
            return null;
        }

        try {
            $service = app(BackupService::class);
            $history = $service->listHistory(1);
            $last    = $history[0] ?? null;
            $running = $service->isBackupRunning();
        } catch (\Throwable) {
            return null;
        }

        if ($running) {
            $data = [
                'value'    => '…',
                'subtitle' => t('backup.widget.running'),
                'link'     => route('backup.index'),
                'color'    => 'info',
            ];
        } elseif ($last === null) {
            $data = [
                'value'    => '—',
                'subtitle' => t('backup.widget.none'),
                'link'     => route('backup.index'),
                'color'    => 'warning',
            ];
        } else {
            $when = format_date_it((string) ($last['created_at'] ?? ''), 'compact');
            $size = $this->formatBytes((int) ($last['size_bytes'] ?? 0));
            $data = [
                'value'    => $when !== '' ? $when : 'OK',
                'subtitle' => t('backup.widget.last', ['size' => $size]),
                'link'     => route('backup.index'),
                'color'    => 'success',
            ];
        }

        return ['data' => $data];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));
        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
