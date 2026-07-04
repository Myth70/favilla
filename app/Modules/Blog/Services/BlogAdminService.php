<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogCategoryRepository;
use App\Modules\Blog\Repositories\BlogCommentRepository;
use App\Modules\Blog\Repositories\BlogTagRepository;
use App\Services\AuditService;

class BlogAdminService
{
    private BlogArticleRepository $articleRepo;
    private BlogCategoryRepository $categoryRepo;
    private BlogCommentRepository $commentRepo;
    private BlogTagRepository $tagRepo;
    private \PDO $pdo;

    public function __construct()
    {
        $this->articleRepo = app(BlogArticleRepository::class);
        $this->categoryRepo = app(BlogCategoryRepository::class);
        $this->commentRepo = app(BlogCommentRepository::class);
        $this->tagRepo = app(BlogTagRepository::class);
        $this->pdo = app(\PDO::class);
    }

    public function getDashboardMetrics(): array
    {
        return [
            'articleCounts' => $this->articleRepo->countByStatus(),
            'commentCount' => $this->commentRepo->countAll(),
            'commentStatusCounts' => $this->commentRepo->countByStatus(),
            'categoryCount' => $this->categoryRepo->count(),
            'tagCount' => $this->tagRepo->count(),
            'pinnedCount' => $this->articleRepo->countPinned(),
        ];
    }

    public function getArticlesPage(array $filters, int $page): array
    {
        return $this->articleRepo->listForAdmin($filters, $page);
    }

    public function findArticleForEdit(int $id): ?array
    {
        return $this->articleRepo->findForEdit($id);
    }

    public function setArticleStatus(int $id, string $status, ?string $publishedAt): void
    {
        $this->articleRepo->update($id, [
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    public function getTrashPage(int $page): array
    {
        return $this->articleRepo->listTrashed($page);
    }

    public function getCommentsPage(array $filters, int $page): array
    {
        return $this->commentRepo->listForAdmin($filters, $page);
    }

    public function deleteComment(int $id): ?array
    {
        $comment = $this->commentRepo->find($id);
        if (!$comment) {
            return null;
        }

        $this->commentRepo->softDelete($id);
        AuditService::log('blog_comment_deleted', 'blog_comment', $id, ['body' => mb_substr($comment['body'], 0, 100)], null);

        return $comment;
    }

    /**
     * Approve a pending comment and dispatch the deferred notification.
     */
    public function approveComment(int $id, int $moderatorId): ?array
    {
        $comment = $this->commentRepo->find($id);
        if (!$comment) {
            return null;
        }

        if (!$this->commentRepo->setStatus($id, 'approved', $moderatorId)) {
            return null;
        }

        AuditService::log('blog_comment_approved', 'blog_comment', $id, ['status' => $comment['status']], ['status' => 'approved']);

        try {
            $articleStmt = $this->pdo->prepare(
                'SELECT id, title, slug, created_by FROM blog_articles WHERE id = ? AND deleted_at IS NULL'
            );
            $articleStmt->execute([(int) $comment['article_id']]);
            $article = $articleStmt->fetch();
            if ($article && (int) $article['created_by'] !== (int) $comment['user_id']) {
                $eventSlug = $comment['parent_id'] ? 'blog.comment_replied' : 'blog.comment_created';
                \App\Modules\Notifications\Services\NotificationService::dispatchEventToUser(
                    $eventSlug,
                    'Blog',
                    (int) $article['created_by'],
                    [
                        'author_id' => (int) $article['created_by'],
                        'article_title' => (string) $article['title'],
                        'comment_id' => $id,
                    ],
                    route('blog.show', ['slug' => $article['slug']]) . '#comments',
                    $moderatorId
                );
            }
        } catch (\Throwable $e) {
            error_log('[Blog] approveComment notification failed for comment ' . $id . ': ' . $e->getMessage());
        }

        return $comment;
    }

    /**
     * Reject (hide) a comment without deleting it.
     */
    public function rejectComment(int $id, int $moderatorId): ?array
    {
        $comment = $this->commentRepo->find($id);
        if (!$comment) {
            return null;
        }
        if (!$this->commentRepo->setStatus($id, 'rejected', $moderatorId)) {
            return null;
        }
        AuditService::log('blog_comment_rejected', 'blog_comment', $id, ['status' => $comment['status']], ['status' => 'rejected']);
        return $comment;
    }

    public function getCategories(): array
    {
        return $this->categoryRepo->allWithCount();
    }

    public function createCategory(string $name, ?string $description, int $userId): int
    {
        $categoryId = $this->categoryRepo->create([
            'name' => $name,
            'slug' => BlogSlugService::categorySlug($name),
            'description' => $description,
            'created_by' => $userId,
        ]);

        AuditService::log('blog_category_created', 'blog_category', $categoryId, null, ['name' => $name]);

        return $categoryId;
    }

    public function updateCategory(int $id, string $name, ?string $description): ?array
    {
        $category = $this->categoryRepo->find($id);
        if (!$category) {
            return null;
        }

        $this->categoryRepo->update($id, [
            'name' => $name,
            'slug' => BlogSlugService::categorySlug($name, $id),
            'description' => $description,
        ]);

        AuditService::log('blog_category_updated', 'blog_category', $id, ['name' => $category['name']], ['name' => $name]);

        return $category;
    }

    public function deleteCategory(int $id): ?array
    {
        $category = $this->categoryRepo->find($id);
        if (!$category) {
            return null;
        }

        $this->categoryRepo->delete($id);
        AuditService::log('blog_category_deleted', 'blog_category', $id, ['name' => $category['name']], null);

        return $category;
    }

    public function getTags(): array
    {
        return $this->tagRepo->allWithCount();
    }

    public function createTag(string $name): void
    {
        $this->tagRepo->findOrCreate($name);
        AuditService::log('blog_tag_created', 'blog_tag', 0, null, ['name' => $name]);
    }

    public function deleteTag(int $id): ?array
    {
        $tag = $this->tagRepo->find($id);
        if (!$tag) {
            return null;
        }

        $this->tagRepo->delete($id);
        AuditService::log('blog_tag_deleted', 'blog_tag', $id, ['name' => $tag['name']], null);

        return $tag;
    }

    public function getBannedUsers(): array
    {
        return $this->commentRepo->listBanned();
    }

    public function getActiveUsersForSelection(int $limit = 300): array
    {
        $limit = max(1, min($limit, 1000));
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email
             FROM users
             WHERE deleted_at IS NULL
             ORDER BY name ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function banUser(int $userId, string $reason, int $bannedBy): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            return false;
        }

        $this->commentRepo->banUser($userId, $reason, $bannedBy);
        AuditService::log('blog_user_banned', 'user', $userId, null, ['reason' => $reason]);

        return true;
    }

    public function unbanUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM blog_comment_blacklist WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            return false;
        }

        $this->commentRepo->unbanUser($userId);
        AuditService::log('blog_user_unbanned', 'user', $userId, null, null);

        return true;
    }
}
