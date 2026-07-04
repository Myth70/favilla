<?php

declare(strict_types=1);

namespace App\Modules\Blog\Repositories;

use App\Repositories\BaseRepository;

class BlogTagRepository extends BaseRepository
{
    protected string $table = 'blog_tags';
    protected array $fillable = ['name', 'slug'];

    /**
     * All tags ordered by name.
     */
    public function allOrdered(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY name ASC");
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
     * Tags with article count.
     */
    public function allWithCount(): array
    {
        $stmt = $this->pdo->query("
            SELECT t.*, COUNT(a.id) AS article_count
            FROM {$this->table} t
            LEFT JOIN blog_article_tags bat ON bat.tag_id = t.id
            LEFT JOIN blog_articles a ON a.id = bat.article_id
                AND a.status = 'published' AND a.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Find or create a tag by name, return the tag row.
     */
    public function findOrCreate(string $name): array
    {
        $trimmedName = trim($name);

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$trimmedName]);
        $byName = $stmt->fetch();
        if ($byName) {
            return $byName;
        }

        $slug = \App\Modules\Blog\Services\BlogSlugService::tagSlug($trimmedName);
        $bySlug = $this->findBySlug($slug);
        if ($bySlug) {
            return $bySlug;
        }

        $id = $this->create([
            'name' => $trimmedName,
            'slug' => $slug,
        ]);

        return $this->find($id);
    }
}
