<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers;

use App\Core\Controller;
use App\Modules\Blog\Services\BlogInteractionService;
use App\Traits\ControllerHelpers;

class BlogInteractionController extends Controller
{
    use ControllerHelpers;

    private BlogInteractionService $service;

    public function __construct()
    {
        $this->service = app(BlogInteractionService::class);
    }

    // ── Like toggle ───────────────────────────────────────────────────────

    public function toggleLike(string $slug): void
    {
        $result = $this->service->toggleLikeBySlug($slug, (int) (auth()['id'] ?? 0));
        if (!$result) {
            http_response_code(404);
            return;
        }

        $this->renderPartial('Blog/Views/public/partials/like_button', [
            'article' => $result['article'],
            'isLiked' => $result['isLiked'],
            'count'   => $result['count'],
        ]);
    }

    // ── Bookmark toggle ───────────────────────────────────────────────────

    public function toggleBookmark(string $slug): void
    {
        $result = $this->service->toggleBookmarkBySlug($slug, (int) (auth()['id'] ?? 0));
        if (!$result) {
            http_response_code(404);
            return;
        }

        $this->renderPartial('Blog/Views/public/partials/bookmark_button', [
            'article'     => $result['article'],
            'isBookmarked' => $result['isBookmarked'],
        ]);
    }

    // ── Saved articles (blog.my — tab Salvati) ────────────────────────────

    public function saved(): void
    {
        $userId = (int) (auth()['id'] ?? 0);
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->service->getSavedArticles($userId, $page, 12);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Blog/Views/public/partials/article_list', array_merge($result, [
                'total_pages' => $result['lastPage'],
                'categories' => [],
                'paginationUrl' => route('blog.saved'),
            ]));
            return;
        }

        $this->render('Blog/Views/author/saved', array_merge($result, [
            'total_pages' => $result['lastPage'],
            'pageTitle'  => t('blog.saved.title'),
            'breadcrumbs' => [
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.saved.title')],
            ],
        ]));
    }
}
