<?php

declare(strict_types=1);

namespace App\Modules\Blog\Repositories;

use App\Repositories\BaseRepository;

class BlogCommentRepository extends BaseRepository
{
    protected string $table = 'blog_comments';
    protected array $fillable = ['article_id', 'user_id', 'parent_id', 'body', 'status'];

    /**
     * Defense-in-depth: enforce 1-level nesting at the data layer.
     * A reply (parent_id != null) is only valid if the parent comment
     * itself has no parent — otherwise we'd create reply-to-reply chains
     * that the UI cannot render.
     */
    protected function beforeCreate(array &$data): void
    {
        if (!empty($data['parent_id'])) {
            $stmt = $this->pdo->prepare(
                "SELECT parent_id FROM {$this->table} WHERE id = ? AND deleted_at IS NULL"
            );
            $stmt->execute([(int) $data['parent_id']]);
            $parent = $stmt->fetch();
            if (!$parent) {
                throw new \RuntimeException(t('blog.exception.parent_comment_missing'));
            }
            if ($parent['parent_id'] !== null) {
                throw new \RuntimeException(t('blog.exception.no_reply_to_reply'));
            }
        }
    }

    /**
     * Get comments for an article, nested 1-level deep.
     * Returns root comments with a 'replies' key.
     * Only approved comments are returned to the public view.
     */
    public function listForArticle(int $articleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bc.*, u.name AS user_name, u.avatar_path AS user_avatar
            FROM {$this->table} bc
            LEFT JOIN users u ON u.id = bc.user_id
            WHERE bc.article_id = ?
              AND bc.deleted_at IS NULL
              AND bc.status = 'approved'
            ORDER BY bc.created_at ASC
        ");
        $stmt->execute([$articleId]);
        $all = $stmt->fetchAll();

        // Build tree (1-level)
        $roots   = [];
        $byId    = [];
        foreach ($all as $c) {
            $c['replies'] = [];
            $byId[$c['id']] = $c;
        }
        foreach ($byId as $c) {
            if ($c['parent_id'] && isset($byId[$c['parent_id']])) {
                $byId[$c['parent_id']]['replies'][] = $c;
            } else {
                $roots[] = &$byId[$c['id']];
            }
        }

        return $roots;
    }

    /**
     * List comments for admin (flat, with article title).
     */
    public function listForAdmin(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['bc.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = '(bc.body LIKE ? OR u.name LIKE ?)';
            $q        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$q, $q]);
        }

        if (!empty($filters['article_id'])) {
            $where[]  = 'bc.article_id = ?';
            $params[] = (int) $filters['article_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'rejected'], true)) {
            $where[]  = 'bc.status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $sql = "
            SELECT bc.*, u.name AS user_name, a.title AS article_title, a.slug AS article_slug
            FROM {$this->table} bc
            LEFT JOIN users u ON u.id = bc.user_id
            LEFT JOIN blog_articles a ON a.id = bc.article_id
            WHERE {$whereClause}
            ORDER BY bc.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM {$this->table} bc LEFT JOIN users u ON u.id = bc.user_id WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * Soft-delete a comment.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Check if a user is banned from commenting.
     */
    public function isUserBanned(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM blog_comment_blacklist WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Ban a user from commenting.
     */
    public function banUser(int $userId, string $reason, int $bannedBy): void
    {
        // ON DUPLICATE KEY UPDATE è sintassi MySQL-only (nessun equivalente
        // SQLite): SELECT-poi-branch INSERT/UPDATE, portabile su entrambi.
        $exists = $this->pdo->prepare('SELECT 1 FROM blog_comment_blacklist WHERE user_id = ?');
        $exists->execute([$userId]);

        if ($exists->fetchColumn() !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE blog_comment_blacklist SET reason = ?, banned_by = ?, created_at = NOW() WHERE user_id = ?'
            );
            $stmt->execute([$reason, $bannedBy, $userId]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO blog_comment_blacklist (user_id, reason, banned_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $reason, $bannedBy]);
    }

    /**
     * Unban a user.
     */
    public function unbanUser(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM blog_comment_blacklist WHERE user_id = ?')->execute([$userId]);
    }

    /**
     * List banned users.
     */
    public function listBanned(): array
    {
        $stmt = $this->pdo->query('
            SELECT bl.*, u.name AS user_name, u.email AS user_email,
                   b.name AS banned_by_name
            FROM blog_comment_blacklist bl
            LEFT JOIN users u ON u.id = bl.user_id
            LEFT JOIN users b ON b.id = bl.banned_by
            ORDER BY bl.created_at DESC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Count total comments (for admin stats).
     */
    public function countAll(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table} WHERE deleted_at IS NULL")->fetchColumn();
    }

    /**
     * Count comments by moderation status (for admin dashboard).
     */
    public function countByStatus(): array
    {
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM {$this->table} WHERE deleted_at IS NULL GROUP BY status"
        );
        $result = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Set moderation status for a comment.
     */
    public function setStatus(int $id, string $status, int $moderatorId): bool
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table}
             SET status = ?, moderated_by = ?, moderated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$status, $moderatorId, $id]);
        return $stmt->rowCount() > 0;
    }
}
