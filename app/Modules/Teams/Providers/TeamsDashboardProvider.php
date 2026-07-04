<?php

declare(strict_types=1);

namespace App\Modules\Teams\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Teams\Services\TeamsService;

class TeamsDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        if (!has_permission('teams.view')) {
            return [];
        }

        return [
            [
                'id'         => 'teams.unread',
                'type'       => 'stat',
                'label'      => t('teams.widget.unread_label'),
                'icon'       => 'fa-comments',
                'size'       => 3,
                'permission' => 'teams.view',
            ],
            [
                'id'         => 'teams.unread_list',
                'type'       => 'list',
                'label'      => t('teams.widget.unread_list_label'),
                'icon'       => 'fa-comments',
                'size'       => 6,
                'permission' => 'teams.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        $service = app(TeamsService::class);

        return match ($widgetId) {
            'teams.unread'      => $this->unreadWidget($service, $userId),
            'teams.unread_list' => $this->unreadListWidget($service, $userId),
            default             => null,
        };
    }

    private function unreadWidget(TeamsService $service, int $userId): array
    {
        $unreadTotal = $service->getUnreadCount($userId);

        return [
            'data' => [
                'value'    => $unreadTotal,
                'subtitle' => tc('teams.widget.unread_subtitle', $unreadTotal),
                'link'     => route('teams.index'),
                'color'    => $unreadTotal > 0 ? 'warning' : 'success',
            ],
        ];
    }

    private function unreadListWidget(TeamsService $service, int $userId): array
    {
        $conversations = $service->getUnreadConversations($userId, 5);

        $rows = [];
        foreach ($conversations as $conversation) {
            $isDirect = ($conversation['type'] ?? '') === 'direct';
            $name = $isDirect
                ? trim((string) ($conversation['other_user_name'] ?? ''))
                : trim((string) ($conversation['name'] ?? ''));

            if ($name === '') {
                $name = $isDirect ? t('teams.widget.default_direct_name') : t('teams.widget.default_group_name');
            }

            $preview = trim((string) ($conversation['last_message_body'] ?? ''));
            if ($preview === '') {
                $preview = t('teams.widget.default_preview');
            }
            if (mb_strlen($preview) > 60) {
                $preview = mb_substr($preview, 0, 57) . '...';
            }

            $conversationId = (int) ($conversation['id'] ?? 0);
            $unread = (int) ($conversation['unread_count'] ?? 0);
            $whenRaw = (string) ($conversation['last_message_at'] ?? $conversation['created_at'] ?? '');
            $when = $whenRaw !== '' ? format_date($whenRaw, 'relative') : '-';

            $rows[] = [
                ['html' => '<a href="' . e(route('teams.show', ['id' => $conversationId])) . '" class="text-decoration-none">' . e($name) . '</a>'
                    . '<div class="text-muted small">' . e($preview) . '</div>'],
                ['html' => '<span class="badge bg-danger bg-opacity-10 text-danger">' . e((string) $unread) . '</span>'],
                $when,
            ];
        }

        return [
            'data' => [
                'columns'      => [t('teams.widget.col_chat'), t('teams.widget.col_unread'), t('teams.widget.col_when')],
                'rows'         => $rows,
                'emptyMessage' => t('teams.widget.empty_unread'),
                'link'         => route('teams.index'),
                'iconColor'    => 'warning',
            ],
        ];
    }
}
