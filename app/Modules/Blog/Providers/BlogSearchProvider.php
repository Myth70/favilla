<?php

declare(strict_types=1);

namespace App\Modules\Blog\Providers;

use App\Contracts\SearchableModule;
use PDO;

class BlogSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        $stmt = $pdo->prepare(
            "SELECT id, title, slug, excerpt, status
             FROM blog_articles
             WHERE deleted_at IS NULL
               AND status = 'published'
               AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)
             ORDER BY published_at DESC
             LIMIT ?"
        );
        $stmt->execute([$like, $like, $like, $limit]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[] = [
                'title'    => $row['title'],
                'subtitle' => $row['excerpt'] ? mb_substr($row['excerpt'], 0, 120) : '',
                'url'      => route('blog.show', ['slug' => $row['slug']]),
                'icon'     => 'fa-newspaper',
                'badge'    => null,
            ];
        }
        return $results;
    }

    public function getSearchLabel(): string
    {
        return t('blog.title');
    }

    public function getSearchIcon(): string
    {
        return 'fa-newspaper';
    }
}
