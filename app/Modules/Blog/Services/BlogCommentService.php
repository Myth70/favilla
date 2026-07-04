<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogCommentRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\SettingsService;
use PDO;

class BlogCommentService
{
    private BlogArticleRepository $articleRepo;
    private BlogArticleService $articleService;
    private BlogCommentRepository $commentRepo;
    private PDO $pdo;

    public function __construct()
    {
        $this->articleRepo = app(BlogArticleRepository::class);
        $this->articleService = app(BlogArticleService::class);
        $this->commentRepo = app(BlogCommentRepository::class);
        $this->pdo = app(PDO::class);
    }

    /**
     * @param array $userRoles Role slugs of the acting user, used to enforce the
     *                         article's visibility restriction (defense against
     *                         commenting on a role-restricted article the user
     *                         cannot otherwise see, e.g. by guessing its slug).
     */
    public function storeComment(string $slug, int $userId, string $body, array $userRoles = []): array
    {
        $article = $this->articleService->findForPublicView($slug, $userRoles);
        if (!$article) {
            return ['error' => t('blog.exception.article_not_found')];
        }

        if ($this->commentRepo->isUserBanned($userId)) {
            return ['error' => t('blog.exception.comment_banned')];
        }

        $status = $this->isModerationEnabled() ? 'pending' : 'approved';

        $commentId = $this->commentRepo->create([
            'article_id' => (int) $article['id'],
            'user_id' => $userId,
            'parent_id' => null,
            'body' => $body,
            'status' => $status,
        ]);

        // Notifica l'autore solo se il commento è già visibile, altrimenti
        // partirà al momento dell'approvazione lato admin.
        if ($status === 'approved') {
            $this->notifyAuthor(
                (int) ($article['created_by'] ?? 0),
                $userId,
                'blog.comment_created',
                (string) $article['title'],
                route('blog.show', ['slug' => $slug]) . '#comments'
            );
        } else {
            $this->notifyModerators(
                (int) $article['id'],
                (string) $article['title'],
                (int) $commentId,
                $userId
            );
        }

        return ['article' => $article, 'status' => $status];
    }

    /**
     * @param array $userRoles Role slugs of the acting user — see storeComment() for why.
     */
    public function replyToComment(string $slug, int $commentId, int $userId, string $body, array $userRoles = []): array
    {
        $article = $this->articleService->findForPublicView($slug, $userRoles);
        if (!$article) {
            return ['error' => t('blog.exception.article_not_found')];
        }

        if ($this->commentRepo->isUserBanned($userId)) {
            return ['error' => t('blog.exception.comment_banned')];
        }

        $parent = $this->commentRepo->find($commentId);
        if (!$parent || (int) $parent['article_id'] !== (int) $article['id'] || $parent['deleted_at'] !== null) {
            return ['error' => t('blog.exception.parent_comment_not_found')];
        }

        if ($parent['parent_id'] !== null) {
            return ['error' => t('blog.exception.no_reply_to_reply')];
        }

        $status = $this->isModerationEnabled() ? 'pending' : 'approved';

        $replyId = $this->commentRepo->create([
            'article_id' => (int) $article['id'],
            'user_id' => $userId,
            'parent_id' => $commentId,
            'body' => $body,
            'status' => $status,
        ]);

        if ($status === 'approved') {
            $this->notifyAuthor(
                (int) ($article['created_by'] ?? 0),
                $userId,
                'blog.comment_replied',
                (string) $article['title'],
                route('blog.show', ['slug' => $slug]) . '#comments'
            );
        } else {
            $this->notifyModerators(
                (int) $article['id'],
                (string) $article['title'],
                (int) $replyId,
                $userId
            );
        }

        return ['article' => $article, 'parent' => $parent, 'status' => $status];
    }

    /**
     * Read the per-module moderation flag (app_settings: blog.comment_moderation).
     */
    private function isModerationEnabled(): bool
    {
        return (bool) SettingsService::get('blog.comment_moderation', false);
    }

    public function findArticleBySlug(string $slug): ?array
    {
        return $this->articleRepo->findBySlug($slug);
    }

    /**
     * Notifica tutti gli utenti attivi con permesso blog.comment.moderate
     * dell'arrivo di un nuovo commento in coda di moderazione.
     * L'autore del commento è escluso (no self-notify).
     */
    private function notifyModerators(int $articleId, string $articleTitle, int $commentId, int $authorUserId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT u.id, u.name
                 FROM users u
                 INNER JOIN user_role ur ON ur.user_id = u.id
                 INNER JOIN role_permission rp ON rp.role_id = ur.role_id
                 INNER JOIN permissions p ON p.id = rp.permission_id
                 WHERE u.is_active = 1
                   AND u.deleted_at IS NULL
                   AND p.slug = 'blog.comment.moderate'
                   AND u.id <> ?"
            );
            $stmt->execute([$authorUserId]);
            $moderators = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($moderators)) {
                return;
            }

            $authorName = $this->getUserName($authorUserId);
            $link = route('blog.admin.comments') . '?status=pending';

            foreach ($moderators as $mod) {
                NotificationService::dispatchEventToUser(
                    'blog.comment_pending',
                    'Blog',
                    (int) $mod['id'],
                    [
                        'article_id'    => $articleId,
                        'article_title' => $articleTitle,
                        'comment_id'    => $commentId,
                        'author_name'   => $authorName,
                        'link'          => $link,
                    ],
                    $link,
                    $authorUserId
                );
            }
        } catch (\Throwable $e) {
            error_log('[Blog] notifyModerators failed for comment ' . $commentId . ': ' . $e->getMessage());
        }
    }

    private function getUserName(int $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return is_string($name) && $name !== '' ? $name : t('blog.admin.blacklist.user_id_fallback', ['id' => $userId]);
    }

    private function notifyAuthor(int $authorId, int $userId, string $eventSlug, string $articleTitle, string $link): void
    {
        if ($authorId <= 0 || $authorId === $userId) {
            return;
        }

        try {
            NotificationService::dispatchEventToUser(
                $eventSlug,
                'Blog',
                $authorId,
                [
                    'author_id' => $authorId,
                    'article_title' => $articleTitle,
                    'link'      => $link,
                ],
                $link,
                $userId
            );
        } catch (\Throwable $e) {
            error_log('[Blog] notifyAuthor failed for event ' . $eventSlug . ' (author=' . $authorId . '): ' . $e->getMessage());
        }
    }
}
