<?php

declare(strict_types=1);

namespace App\Modules\Blog\Repositories;

use App\Repositories\BaseRepository;

class BlogInteractionRepository extends BaseRepository
{
    protected string $table = 'blog_articles'; // non usata direttamente

    // ── Like ──────────────────────────────────────────────────────────────

    public function isLiked(int $articleId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM blog_article_likes WHERE article_id = ? AND user_id = ?'
        );
        $stmt->execute([$articleId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function addLike(int $articleId, int $userId): void
    {
        // INSERT IGNORE è sintassi MySQL-only: SQLite vuole "INSERT OR IGNORE".
        $insertVerb = $this->isSqlite() ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->pdo->prepare(
            "{$insertVerb} INTO blog_article_likes (article_id, user_id) VALUES (?, ?)"
        )->execute([$articleId, $userId]);
    }

    public function removeLike(int $articleId, int $userId): void
    {
        $this->pdo->prepare(
            'DELETE FROM blog_article_likes WHERE article_id = ? AND user_id = ?'
        )->execute([$articleId, $userId]);
    }

    public function countLikes(int $articleId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM blog_article_likes WHERE article_id = ?'
        );
        $stmt->execute([$articleId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Bookmark ──────────────────────────────────────────────────────────

    public function isBookmarked(int $articleId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM blog_article_bookmarks WHERE article_id = ? AND user_id = ?'
        );
        $stmt->execute([$articleId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function addBookmark(int $articleId, int $userId): void
    {
        $insertVerb = $this->isSqlite() ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->pdo->prepare(
            "{$insertVerb} INTO blog_article_bookmarks (article_id, user_id) VALUES (?, ?)"
        )->execute([$articleId, $userId]);
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public function removeBookmark(int $articleId, int $userId): void
    {
        $this->pdo->prepare(
            'DELETE FROM blog_article_bookmarks WHERE article_id = ? AND user_id = ?'
        )->execute([$articleId, $userId]);
    }

    /**
     * Articoli salvati (bookmark) dall'utente con paginazione.
     */
    public function listBookmarkedByUser(int $userId, int $page = 1, int $perPage = 12): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT a.*, u.name AS author_name, c.name AS category_name, c.slug AS category_slug,
                   b.created_at AS bookmarked_at
            FROM blog_article_bookmarks b
            INNER JOIN blog_articles a ON a.id = b.article_id
            LEFT JOIN users u ON u.id = a.created_by
            LEFT JOIN blog_categories c ON c.id = a.category_id
            WHERE b.user_id = ?
              AND a.deleted_at IS NULL
              AND a.status = 'published'
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $perPage, $offset]);
        $items = $stmt->fetchAll();

        $cntStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM blog_article_bookmarks b
            INNER JOIN blog_articles a ON a.id = b.article_id
            WHERE b.user_id = ? AND a.deleted_at IS NULL AND a.status = 'published'
        ");
        $cntStmt->execute([$userId]);
        $total = (int) $cntStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * Numero di like per articolo — usato dalla lista admin.
     * Restituisce mappa article_id => likes_count.
     */
    public function likesCountForArticles(array $articleIds): array
    {
        if (empty($articleIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT article_id, COUNT(*) AS cnt
             FROM blog_article_likes
             WHERE article_id IN ($placeholders)
             GROUP BY article_id"
        );
        $stmt->execute($articleIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['article_id']] = (int) $row['cnt'];
        }
        return $map;
    }
}
