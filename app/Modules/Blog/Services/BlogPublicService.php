<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogCategoryRepository;
use App\Modules\Blog\Repositories\BlogCommentRepository;
use App\Modules\Blog\Repositories\BlogInteractionRepository;
use App\Modules\Blog\Repositories\BlogTagRepository;

class BlogPublicService
{
    private BlogArticleRepository $articleRepo;
    private BlogArticleService $articleService;
    private BlogCategoryRepository $categoryRepo;
    private BlogCommentRepository $commentRepo;
    private BlogInteractionRepository $interactionRepo;
    private BlogTagRepository $tagRepo;

    public function __construct()
    {
        $this->articleRepo = app(BlogArticleRepository::class);
        $this->articleService = app(BlogArticleService::class);
        $this->categoryRepo = app(BlogCategoryRepository::class);
        $this->commentRepo = app(BlogCommentRepository::class);
        $this->interactionRepo = app(BlogInteractionRepository::class);
        $this->tagRepo = app(BlogTagRepository::class);
    }

    public function getHomePageData(array $filters, int $page, array $userRoles): array
    {
        return array_merge($this->articleRepo->listPublished($filters, $page, 12, $userRoles), [
            'categories' => $this->categoryRepo->allWithCount(),
            'tags' => $this->tagRepo->allWithCount(),
        ]);
    }

    public function getArticlePageData(string $slug, array $userRoles, int $currentUserId): ?array
    {
        $article = $this->findVisibleArticle($slug, $userRoles);
        if (!$article) {
            return null;
        }

        $articleId = (int) $article['id'];
        // Dedup viste: 1 incremento/utente/giorno via INSERT IGNORE su blog_article_views.
        // L'autore non incrementa mai le proprie viste.
        if ($currentUserId > 0 && (int) ($article['created_by'] ?? 0) !== $currentUserId) {
            $this->articleRepo->trackUniqueView($articleId, $currentUserId);
        }

        return [
            'article' => $article,
            'tags' => $this->articleRepo->getArticleTags($articleId),
            'comments' => $this->commentRepo->listForArticle($articleId),
            'canComment' => has_permission('blog.comment') && !$this->commentRepo->isUserBanned($currentUserId),
            'isLiked' => $this->interactionRepo->isLiked($articleId, $currentUserId),
            'likesCount' => $this->interactionRepo->countLikes($articleId),
            'isBookmarked' => $this->interactionRepo->isBookmarked($articleId, $currentUserId),
        ];
    }

    public function findVisibleArticle(string $slug, array $userRoles): ?array
    {
        return $this->articleService->findForPublicView($slug, $userRoles);
    }

    public function getCategoryPageData(string $slug, string $search, int $page, array $userRoles): ?array
    {
        $category = $this->categoryRepo->findBySlug($slug);
        if (!$category) {
            return null;
        }

        $filters = [
            'category_id' => $category['id'],
            'search' => $search,
        ];

        return array_merge($this->getHomePageData($filters, $page, $userRoles), [
            'filters' => $filters,
            'currentCategory' => $category,
        ]);
    }

    public function getTagPageData(string $slug, string $search, int $page, array $userRoles): ?array
    {
        $tag = $this->tagRepo->findBySlug($slug);
        if (!$tag) {
            return null;
        }

        $filters = [
            'tag_id' => $tag['id'],
            'search' => $search,
        ];

        return array_merge($this->getHomePageData($filters, $page, $userRoles), [
            'filters' => $filters,
            'currentTag' => $tag,
        ]);
    }

    public function getSearchPageData(string $search, int $page, array $userRoles): array
    {
        return array_merge($this->getHomePageData(['search' => $search], $page, $userRoles), [
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Published articles for a specific author (by user id).
     * Returns null when the user does not exist.
     */
    public function getAuthorPageData(int $userId, array $filters, int $page, array $userRoles): ?array
    {
        $userRepo = app(\App\Repositories\UserRepository::class);
        $author = $userRepo->find($userId);
        if (!$author || !empty($author['deleted_at'])) {
            return null;
        }

        $filters['author_id'] = $userId;

        return array_merge($this->articleRepo->listPublished($filters, $page, 12, $userRoles), [
            'author' => $author,
            'categories' => $this->categoryRepo->allWithCount(),
            'tags' => $this->tagRepo->allWithCount(),
            'filters' => $filters,
        ]);
    }

    /**
     * Augment article page data with related articles.
     */
    public function getArticlePageDataWithRelated(string $slug, array $userRoles, int $currentUserId, int $relatedLimit = 4): ?array
    {
        $data = $this->getArticlePageData($slug, $userRoles, $currentUserId);
        if (!$data) {
            return null;
        }
        $article = $data['article'];
        $tagIds = array_map(fn ($t) => (int) $t['id'], $data['tags']);
        $data['related'] = $this->articleRepo->listRelated(
            (int) $article['id'],
            $article['category_id'] ? (int) $article['category_id'] : null,
            $tagIds,
            $relatedLimit
        );
        return $data;
    }
}
