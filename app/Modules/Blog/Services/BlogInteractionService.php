<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogInteractionRepository;

class BlogInteractionService
{
    private BlogArticleRepository $articleRepo;
    private BlogInteractionRepository $interactionRepo;

    public function __construct()
    {
        $this->articleRepo = app(BlogArticleRepository::class);
        $this->interactionRepo = app(BlogInteractionRepository::class);
    }

    public function toggleLikeBySlug(string $slug, int $userId): ?array
    {
        $article = $this->articleRepo->findBySlug($slug);
        if (!$article) {
            return null;
        }

        $articleId = (int) $article['id'];
        $liked = $this->interactionRepo->isLiked($articleId, $userId);

        if ($liked) {
            $this->interactionRepo->removeLike($articleId, $userId);
            $isLiked = false;
        } else {
            $this->interactionRepo->addLike($articleId, $userId);
            $isLiked = true;
        }

        return [
            'article' => $article,
            'isLiked' => $isLiked,
            'count' => $this->interactionRepo->countLikes($articleId),
        ];
    }

    public function toggleBookmarkBySlug(string $slug, int $userId): ?array
    {
        $article = $this->articleRepo->findBySlug($slug);
        if (!$article) {
            return null;
        }

        $articleId = (int) $article['id'];
        $bookmarked = $this->interactionRepo->isBookmarked($articleId, $userId);

        if ($bookmarked) {
            $this->interactionRepo->removeBookmark($articleId, $userId);
            $isBookmarked = false;
        } else {
            $this->interactionRepo->addBookmark($articleId, $userId);
            $isBookmarked = true;
        }

        return [
            'article' => $article,
            'isBookmarked' => $isBookmarked,
        ];
    }

    public function getSavedArticles(int $userId, int $page, int $perPage = 12): array
    {
        return $this->interactionRepo->listBookmarkedByUser($userId, $page, $perPage);
    }
}
