<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogCategoryRepository;
use App\Modules\Blog\Repositories\BlogTagRepository;

class BlogAuthorService
{
    private BlogArticleRepository $articleRepo;
    private BlogCategoryRepository $categoryRepo;
    private BlogTagRepository $tagRepo;
    private \PDO $pdo;

    public function __construct()
    {
        $this->articleRepo = app(BlogArticleRepository::class);
        $this->categoryRepo = app(BlogCategoryRepository::class);
        $this->tagRepo = app(BlogTagRepository::class);
        $this->pdo = app(\PDO::class);
    }

    public function getAuthorArticles(int $userId, array $filters, int $page): array
    {
        return $this->articleRepo->listForAuthor($userId, $filters, $page);
    }

    /**
     * Get aggregate stats for an author's articles.
     */
    public function getAuthorStats(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_articles,
                SUM(CASE WHEN a.status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) AS drafts,
                COALESCE(SUM(a.view_count), 0) AS total_views,
                (SELECT COUNT(*) FROM blog_article_likes bal
                 INNER JOIN blog_articles ba ON ba.id = bal.article_id
                 WHERE ba.created_by = ? AND ba.deleted_at IS NULL) AS total_likes,
                (SELECT COUNT(*) FROM blog_comments bc
                 INNER JOIN blog_articles ba2 ON ba2.id = bc.article_id
                 WHERE ba2.created_by = ? AND bc.deleted_at IS NULL AND ba2.deleted_at IS NULL) AS total_comments
            FROM blog_articles a
            WHERE a.created_by = ? AND a.deleted_at IS NULL
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_articles' => 0, 'published' => 0, 'drafts' => 0,
            'total_views' => 0, 'total_likes' => 0, 'total_comments' => 0,
        ];
    }

    public function getCreateFormData(): array
    {
        return [
            'categories' => $this->categoryRepo->allOrdered(),
            'allTags' => $this->tagRepo->allOrdered(),
            'roles' => $this->getRoles(),
        ];
    }

    public function findArticleForEdit(int $articleId): ?array
    {
        return $this->articleRepo->findForEdit($articleId);
    }

    public function getEditFormData(int $articleId): ?array
    {
        $article = $this->articleRepo->findForEdit($articleId);
        if (!$article) {
            return null;
        }

        return [
            'article' => $article,
            'categories' => $this->categoryRepo->allOrdered(),
            'allTags' => $this->tagRepo->allOrdered(),
            'articleTags' => $this->articleRepo->getArticleTags($articleId),
            'roles' => $this->getRoles(),
        ];
    }

    private function getRoles(): array
    {
        $stmt = $this->pdo->query('SELECT id, slug, name FROM roles ORDER BY name');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
