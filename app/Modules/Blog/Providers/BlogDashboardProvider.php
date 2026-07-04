<?php

declare(strict_types=1);

namespace App\Modules\Blog\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Blog\Repositories\BlogArticleRepository;

class BlogDashboardProvider implements DashboardWidgetProvider
{
    public function getWidgets(int $userId): array
    {
        if (!has_permission('blog.view')) {
            return [];
        }

        return [
            [
                'id'         => 'blog.stat',
                'type'       => 'stat',
                'label'      => t('blog.widget.stat_label'),
                'icon'       => 'fa-newspaper',
                'size'       => 3,
                'permission' => 'blog.view',
            ],
            [
                'id'         => 'blog.list',
                'type'       => 'list',
                'label'      => t('blog.widget.list_label'),
                'icon'       => 'fa-newspaper',
                'size'       => 6,
                'permission' => 'blog.view',
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        return match ($widgetId) {
            'blog.stat' => $this->statWidget(),
            'blog.list' => $this->listWidget($userId),
            default     => null,
        };
    }

    private function statWidget(): array
    {
        $repo      = app(BlogArticleRepository::class);
        $counts    = $repo->countByStatus();
        $published = (int) ($counts['published'] ?? 0);

        return [
            'data' => [
                'value'    => $published,
                'subtitle' => tc('blog.widget.stat_subtitle', $published),
                'link'     => route('blog.index'),
                'color'    => 'primary',
            ],
        ];
    }

    private function listWidget(int $userId): array
    {
        $repo      = app(BlogArticleRepository::class);
        $userRoles = $this->getUserRoleSlugs($userId);
        $result    = $repo->listPublished([], 1, 5, $userRoles);

        $rows = [];
        foreach ($result['items'] as $a) {
            $rows[] = [
                $a['title'],
                $a['category_name'] ?? '—',
                format_date($a['published_at'] ?? $a['created_at'], 'relative'),
            ];
        }

        return [
            'data' => [
                'columns'      => [t('blog.widget.col_title'), t('blog.widget.col_category'), t('blog.widget.col_date')],
                'rows'         => $rows,
                'emptyMessage' => t('blog.widget.list_empty'),
                'link'         => route('blog.index'),
                'iconColor'    => 'primary',
            ],
        ];
    }

    private function getUserRoleSlugs(int $userId): array
    {
        $authData = auth();
        if (!$authData || (int) ($authData['id'] ?? 0) !== $userId) {
            return [];
        }
        $roles = $authData['roles'] ?? [];
        return array_values(array_filter(array_map(
            fn ($r) => is_array($r) ? ($r['slug'] ?? '') : (string) $r,
            $roles
        )));
    }
}
