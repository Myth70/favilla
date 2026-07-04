<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Contracts\DashboardWidgetProvider;
use PDO;

class AuthDashboardProvider implements DashboardWidgetProvider
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'auth.profile',
                'type'       => 'stat',
                'label'      => t('auth.widget.profile_label'),
                'icon'       => 'fa-user',
                'size'       => 3,
                'permission' => null,
            ],
            [
                'id'         => 'auth.activity',
                'type'       => 'list',
                'label'      => t('auth.widget.activity_label'),
                'icon'       => 'fa-clock-rotate-left',
                'size'       => 6,
                'permission' => null,
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        return match ($widgetId) {
            'auth.profile'  => $this->profileData($userId),
            'auth.activity' => $this->activityData($userId),
            default         => null,
        };
    }

    private function profileData(int $userId): array
    {
        $lastLogin = $this->getLastLogin($userId);

        return ['data' => [
            'value'    => $lastLogin ? format_date_it($lastLogin, 'compact') : t('auth.widget.na'),
            'subtitle' => t('auth.widget.last_access'),
            'link'     => route('profile'),
            'color'    => 'primary',
        ]];
    }

    private function activityData(int $userId): array
    {
        $activity = $this->getRecentActivity($userId, 8);
        $rows = [];

        // Human-readable labels for actions and entities
        $actionMap = [
            'login' => t('auth.widget.action.login'),
            'logout' => t('auth.widget.action.logout'),
            'password changed' => t('auth.widget.action.password_changed'),
            'profile updated' => t('auth.widget.action.profile_updated'),
            'calendar event created' => t('auth.widget.action.event_created'),
            'calendar event updated' => t('auth.widget.action.event_updated'),
            'calendar event deleted' => t('auth.widget.action.event_deleted'),
            'file uploaded' => t('auth.widget.action.file_uploaded'),
            'file deleted' => t('auth.widget.action.file_deleted'),
        ];
        $entityMap = [
            'user' => t('auth.widget.entity.user'),
            'calendar_event' => 'calendar',
            'file' => 'file',
            'notification' => t('auth.widget.entity.notification'),
        ];

        foreach ($activity as $log) {
            $rawAction = strtolower(str_replace('_', ' ', $log['action']));
            $rawEntity = strtolower($log['entity'] ?? '');

            // Try full match first (action + entity), then action only
            $fullKey = trim($rawAction . ' ' . str_replace('_', ' ', $rawEntity));
            if (isset($actionMap[$fullKey])) {
                $label = $actionMap[$fullKey];
            } elseif (isset($actionMap[$rawAction])) {
                $label = $actionMap[$rawAction];
            } else {
                $label = ucfirst($rawAction);
            }

            // Add entity context if not already in label and action is not self-explanatory
            $selfExplanatory = ['login', 'logout', 'password changed', 'profile updated'];
            if ($rawEntity && !isset($actionMap[$fullKey]) && !in_array($rawAction, $selfExplanatory, true)) {
                $entityLabel = $entityMap[$rawEntity] ?? ucfirst(str_replace('_', ' ', $rawEntity));
                $label .= ' · ' . $entityLabel;
            }

            $rows[] = [
                $label,
                format_date_it($log['created_at'], 'relative'),
            ];
        }

        return ['data' => [
            'columns'      => [t('auth.widget.col_activity'), t('auth.widget.col_when')],
            'rows'         => $rows,
            'emptyMessage' => t('auth.widget.activity_empty'),
            'link'         => null,
            'iconColor'    => 'secondary',
        ]];
    }

    private function getLastLogin(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT created_at FROM audit_logs
             WHERE user_id = ? AND action LIKE '%login%'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['created_at'] : null;
    }

    private function getRecentActivity(int $userId, int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT action, entity, entity_id, created_at
             FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
