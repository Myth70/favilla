<?php

declare(strict_types=1);

namespace App\Modules\Blog\Repositories;

use App\Repositories\BaseRepository;

class BlogCategoryRepository extends BaseRepository
{
    protected string $table = 'blog_categories';
    protected array $fillable = ['name', 'slug', 'description', 'sort_order', 'created_by'];

    /**
     * All categories ordered by sort_order.
     */
    public function allOrdered(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY sort_order ASC, name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Find by slug.
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Check if slug exists.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Categories with article count (for sidebar/widget).
     */
    public function allWithCount(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.*, COUNT(a.id) AS article_count
            FROM {$this->table} c
            LEFT JOIN blog_articles a ON a.category_id = c.id
                AND a.status = 'published' AND a.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        return $stmt->fetchAll();
    }
}
