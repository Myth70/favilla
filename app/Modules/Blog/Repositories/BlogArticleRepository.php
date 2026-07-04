<?php

declare(strict_types=1);

namespace App\Modules\Blog\Repositories;

use App\Repositories\BaseRepository;

class BlogArticleRepository extends BaseRepository
{
    protected string $table = 'blog_articles';
    protected bool $softDelete = true;
    protected bool $timestamps = true;
    protected array $fillable = [
        'title', 'slug', 'excerpt', 'meta_description', 'meta_keywords', 'og_image',
        'content', 'cover_image',
        'category_id', 'status', 'is_pinned', 'visibility',
        'reading_time', 'view_count', 'published_at', 'publish_at',
        'created_by',
    ];

    private const SORTABLE_COLUMNS = [
        'id', 'title', 'status', 'published_at', 'created_at', 'is_pinned', 'reading_time', 'view_count', 'likes_count',
    ];

    /**
     * List published articles with pagination, category/tag/search filters.
     * Pinned articles appear first, then sorted by published_at.
     * Optionally filters by visibility based on user roles.
     */
    public function listPublished(array $filters = [], int $page = 1, int $perPage = 12, array $userRoles = []): array
    {
        $where  = ['a.status = ?', 'a.deleted_at IS NULL'];
        $params = ['published'];
        $joins  = 'LEFT JOIN users u ON u.id = a.created_by
                   LEFT JOIN blog_categories c ON c.id = a.category_id';

        if (!empty($filters['search'])) {
            $where[]  = '(a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)';
            $q        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$q, $q, $q]);
        }

        if (!empty($filters['category_id'])) {
            $where[]  = 'a.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['tag_id'])) {
            $joins   .= ' INNER JOIN blog_article_tags bat ON bat.article_id = a.id';
            $where[]  = 'bat.tag_id = ?';
            $params[] = (int) $filters['tag_id'];
        }

        if (!empty($filters['author_id'])) {
            $where[]  = 'a.created_by = ?';
            $params[] = (int) $filters['author_id'];
        }

        if (!empty($filters['from'])) {
            $where[]  = 'a.published_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[]  = 'a.published_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        // Visibility filter (skip for admins — handled at controller level)
        // "visibility" è una lista CSV senza spazi (es. "admin,manager"): niente
        // FIND_IN_SET (MySQL-only, non esiste su SQLite) — controllo di appartenenza
        // portabile via LIKE ancorato sui bordi/virgole del valore.
        if (!empty($userRoles) && !has_permission('blog.admin')) {
            $visConditions = ['a.visibility = ?'];
            $params[]      = 'all';
            foreach ($userRoles as $role) {
                $roleSlug = is_array($role) ? ($role['slug'] ?? '') : (string) $role;
                if ($roleSlug !== '') {
                    $visConditions[] = '(a.visibility = ? OR a.visibility LIKE ? OR a.visibility LIKE ? OR a.visibility LIKE ?)';
                    $params[]        = $roleSlug;
                    $params[]        = $roleSlug . ',%';
                    $params[]        = '%,' . $roleSlug;
                    $params[]        = '%,' . $roleSlug . ',%';
                }
            }
            $where[] = '(' . implode(' OR ', $visConditions) . ')';
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        // Sort:
        //  - default: pinned first, then most recent
        //  - 'popular': by view_count desc (last 30 days bias via published_at filter handled at controller)
        $sort = $filters['sort'] ?? 'recent';
        $orderBy = match ($sort) {
            'popular' => 'a.view_count DESC, a.published_at DESC',
            default   => 'a.is_pinned DESC, a.published_at DESC',
        };

        $sql = "
            SELECT a.*, u.name AS author_name, u.avatar_path AS author_avatar,
                   c.name AS category_name, c.slug AS category_slug,
                   (SELECT COUNT(*) FROM blog_comments bc WHERE bc.article_id = a.id AND bc.deleted_at IS NULL AND bc.status = 'approved') AS comment_count,
                   (SELECT COUNT(*) FROM blog_article_likes bal WHERE bal.article_id = a.id) AS likes_count
            FROM {$this->table} a
            {$joins}
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(DISTINCT a.id) FROM {$this->table} a {$joins} WHERE {$whereClause}";
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
     * Find a published article by slug (public view).
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.name AS author_name, u.avatar_path AS author_avatar,
                   c.name AS category_name, c.slug AS category_slug
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.created_by
            LEFT JOIN blog_categories c ON c.id = a.category_id
            WHERE a.slug = ? AND a.status = 'published' AND a.deleted_at IS NULL
        ");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find article by ID without status filter (for admin/author).
     */
    public function findForEdit(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.name AS author_name
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.created_by
            WHERE a.id = ? AND a.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List articles for a specific author with pagination.
     */
    public function listForAuthor(int $userId, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where  = ['a.created_by = ?', 'a.deleted_at IS NULL'];
        $params = [$userId];

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(a.title LIKE ? OR a.excerpt LIKE ?)';
            $q        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$q, $q]);
        }

        $sortBy = $filters['sort'] ?? 'created_at';
        if (!in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        // likes_count è un alias di subquery: non può avere prefisso "a."
        $orderExpr = $sortBy === 'likes_count' ? 'likes_count' : "a.{$sortBy}";

        $sql = "
            SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                   (SELECT COUNT(*) FROM blog_comments bc WHERE bc.article_id = a.id AND bc.deleted_at IS NULL) AS comment_count,
                   (SELECT COUNT(*) FROM blog_article_likes bal WHERE bal.article_id = a.id) AS likes_count
            FROM {$this->table} a
            LEFT JOIN blog_categories c ON c.id = a.category_id
            WHERE {$whereClause}
            ORDER BY {$orderExpr} {$sortDir}
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM {$this->table} a WHERE {$whereClause}";
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
     * List ALL articles for admin panel with pagination.
     */
    public function listForAdmin(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(a.title LIKE ? OR u.name LIKE ?)';
            $q        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$q, $q]);
        }

        $sortBy = $filters['sort'] ?? 'created_at';
        if (!in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        // likes_count è un alias di subquery: non può avere prefisso "a."
        $orderExpr = $sortBy === 'likes_count' ? 'likes_count' : "a.{$sortBy}";

        $sql = "
            SELECT a.*, u.name AS author_name, c.name AS category_name,
                   (SELECT COUNT(*) FROM blog_comments bc WHERE bc.article_id = a.id AND bc.deleted_at IS NULL) AS comment_count,
                   (SELECT COUNT(*) FROM blog_article_likes bal WHERE bal.article_id = a.id) AS likes_count
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.created_by
            LEFT JOIN blog_categories c ON c.id = a.category_id
            WHERE {$whereClause}
            ORDER BY {$orderExpr} {$sortDir}
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM {$this->table} a LEFT JOIN users u ON u.id = a.created_by WHERE {$whereClause}";
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
     * Check if a slug already exists.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE slug = ? AND id != ? AND deleted_at IS NULL");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE slug = ? AND deleted_at IS NULL");
            $stmt->execute([$slug]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get tags for an article.
     */
    public function getArticleTags(int $articleId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.*
            FROM blog_tags t
            INNER JOIN blog_article_tags bat ON bat.tag_id = t.id
            WHERE bat.article_id = ?
            ORDER BY t.name
        ');
        $stmt->execute([$articleId]);
        return $stmt->fetchAll();
    }

    /**
     * Sync article tags (delete + re-insert).
     */
    public function syncTags(int $articleId, array $tagIds): void
    {
        $this->pdo->prepare('DELETE FROM blog_article_tags WHERE article_id = ?')->execute([$articleId]);

        // Deduplicated up front: la DELETE precedente garantisce che non ci siano
        // righe residue per questo articolo, quindi una INSERT semplice basta
        // (niente INSERT IGNORE, sintassi MySQL non portabile su SQLite).
        $uniqueTagIds = array_unique(array_map('intval', $tagIds));
        if (empty($uniqueTagIds)) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO blog_article_tags (article_id, tag_id) VALUES (?, ?)');
        foreach ($uniqueTagIds as $tagId) {
            $stmt->execute([$articleId, $tagId]);
        }
    }

    /**
     * Increment the view count for an article.
     */
    public function incrementViewCount(int $id): void
    {
        $this->pdo->prepare("UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = ?")
            ->execute([$id]);
    }

    /**
     * Track a view by (article, user, day) and increment view_count only
     * if it's a new entry. Returns true when the counter was incremented.
     * Dedups against logout/login refresh inflation by persisting the
     * 1-view-per-user-per-day rule in blog_article_views.
     */
    public function trackUniqueView(int $articleId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        // CURDATE() non esiste su SQLite: data calcolata lato PHP e passata come
        // parametro. INSERT IGNORE è sintassi MySQL-only (SQLite vuole
        // "INSERT OR IGNORE"): qui serve la vera semantica di ignore (il dedup
        // 1/utente/giorno è il punto della query), quindi si sceglie il dialetto.
        $insertVerb = $this->isSqlite() ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $stmt = $this->pdo->prepare(
            "{$insertVerb} INTO blog_article_views (article_id, user_id, viewed_on)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$articleId, $userId, date('Y-m-d')]);
        if ($stmt->rowCount() > 0) {
            $this->incrementViewCount($articleId);
            return true;
        }
        return false;
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    /**
     * Related articles to a given one — same tags or same category — most recent first.
     * Excludes the article itself and any soft-deleted/non-published rows.
     */
    public function listRelated(int $articleId, ?int $categoryId, array $tagIds, int $limit = 4): array
    {
        $where   = ['a.id <> ?', "a.status = 'published'", 'a.deleted_at IS NULL'];
        $params  = [$articleId];
        $joins   = 'LEFT JOIN users u ON u.id = a.created_by
                    LEFT JOIN blog_categories c ON c.id = a.category_id';
        $select  = 'SELECT a.id, a.title, a.slug, a.excerpt, a.cover_image, a.published_at, a.reading_time, a.view_count,
                           u.name AS author_name,
                           c.name AS category_name, c.slug AS category_slug, 0 AS overlap';

        if (!empty($tagIds)) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $joins .= " LEFT JOIN blog_article_tags bat2 ON bat2.article_id = a.id AND bat2.tag_id IN ($placeholders)";
            $select = str_replace('0 AS overlap', 'COUNT(bat2.tag_id) AS overlap', $select);
            $params = array_merge($params, $tagIds);
        }

        $whereClause = implode(' AND ', $where);

        // Categoria come secondo criterio: gli articoli stessa categoria pesano un po' di più
        $sameCatExpr = $categoryId ? '(a.category_id = ?)' : '0';
        if ($categoryId) {
            $params[] = (int) $categoryId;
        }

        $sql = "
            {$select}, {$sameCatExpr} AS same_cat
            FROM {$this->table} a
            {$joins}
            WHERE {$whereClause}
            GROUP BY a.id
            ORDER BY overlap DESC, same_cat DESC, a.published_at DESC
            LIMIT ?
        ";
        $params[] = max(1, $limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Articles scheduled for publication whose publish_at is now in the past.
     * Used by the blog:publish-scheduled CLI command.
     */
    public function getScheduledDue(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, slug, created_by
            FROM {$this->table}
            WHERE status = 'scheduled'
              AND publish_at IS NOT NULL
              AND publish_at <= NOW()
              AND deleted_at IS NULL
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Publish an article.
     */
    public function publish(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET status = 'published', published_at = COALESCE(published_at, NOW())
            WHERE id = ? AND deleted_at IS NULL
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Unpublish (revert to draft).
     */
    public function unpublish(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'draft' WHERE id = ? AND deleted_at IS NULL");
        return $stmt->execute([$id]);
    }

    /**
     * Toggle pin status.
     */
    public function togglePin(int $id, bool $pinned): bool
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET is_pinned = ? WHERE id = ? AND deleted_at IS NULL");
        return $stmt->execute([(int) $pinned, $id]);
    }

    /**
     * Count articles by status (for admin dashboard).
     */
    public function countByStatus(): array
    {
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) AS cnt
            FROM {$this->table}
            WHERE deleted_at IS NULL
            GROUP BY status
        ");
        $rows   = $stmt->fetchAll();
        $result = ['draft' => 0, 'scheduled' => 0, 'published' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Count pinned articles.
     */
    public function countPinned(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE is_pinned = 1 AND status = 'published' AND deleted_at IS NULL"
        )->fetchColumn();
    }

    /**
     * List soft-deleted articles for the trash view.
     */
    public function listTrashed(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT a.id, a.title, a.slug, a.status, a.deleted_at, a.created_at,
                   u.name AS author_name
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.created_by
            WHERE a.deleted_at IS NOT NULL
            ORDER BY a.deleted_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $items = $stmt->fetchAll();

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE deleted_at IS NOT NULL");
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
        ];
    }
}
