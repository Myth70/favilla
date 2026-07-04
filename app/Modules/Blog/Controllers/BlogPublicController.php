<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers;

use App\Core\Controller;
use App\Modules\Blog\Services\BlogPublicService;
use App\Modules\Reports\Services\DocumentService;
use App\Traits\ControllerHelpers;

class BlogPublicController extends Controller
{
    use ControllerHelpers;
    private BlogPublicService $service;

    public function __construct()
    {
        $this->service = app(BlogPublicService::class);
    }

    /**
     * Blog home — list published articles.
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['q'] ?? $_GET['search'] ?? '',
            'from'   => $this->normalizeDate($_GET['from'] ?? ''),
            'to'     => $this->normalizeDate($_GET['to'] ?? ''),
            'sort'   => in_array($_GET['sort'] ?? '', ['recent', 'popular'], true) ? $_GET['sort'] : 'recent',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $userRoles = $this->getUserRoleSlugs();
        $result = $this->service->getHomePageData($filters, $page, $userRoles);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Blog/Views/public/partials/article_list', array_merge($result, [
                'total_pages' => $result['lastPage'],
                'categories' => $result['categories'],
            ]));
            return;
        }

        $this->render('Blog/Views/public/index', array_merge($result, ['total_pages' => $result['lastPage']], $filters, [
            'layout'     => 'main',
            'activePage' => 'blog',
            'categories' => $result['categories'],
            'tags'       => $result['tags'],
            'filters'    => $filters,
            'pageTitle'  => t('blog.title'),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title')],
            ],
        ]));
    }

    /**
     * Show single article.
     */
    public function show(string $slug): void
    {
        $userRoles = $this->getUserRoleSlugs();
        $data = $this->service->getArticlePageDataWithRelated(
            $slug,
            $userRoles,
            (int) (auth()['id'] ?? 0)
        );

        if (!$data) {
            flash_error(t('blog.exception.article_not_found'));
            $this->redirect(route('blog.index'));
            return;
        }
        $article = $data['article'];

        // SEO: prefer per-article meta, fallback to excerpt/cover.
        $metaDescription = $article['meta_description']
            ?: ($article['excerpt'] ?: app(\App\Modules\Blog\Services\BlogArticleService::class)
                   ->generateExcerpt((string) $article['content'], 200));
        $ogImageRaw = $article['og_image'] ?: $article['cover_image'];
        $ogImage = $ogImageRaw ? cover_url($ogImageRaw, 'blog') : null;

        $this->render('Blog/Views/public/show', [
            'layout'      => 'main',
            'activePage'  => 'blog',
            'article'      => $article,
            'tags'         => $data['tags'],
            'comments'     => $data['comments'],
            'related'      => $data['related'] ?? [],
            'canComment'   => $data['canComment'],
            'isLogged'     => true,
            'isLiked'      => $data['isLiked'],
            'likesCount'   => $data['likesCount'],
            'isBookmarked' => $data['isBookmarked'],
            'pageTitle'   => $article['title'],
            'metaDescription' => $metaDescription,
            'metaKeywords'    => $article['meta_keywords'] ?: null,
            'ogTitle'         => $article['title'],
            'ogType'          => 'article',
            'ogImage'         => $ogImage,
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => $article['title']],
            ],
        ]);
    }

    /**
     * Generate PDF for an article via Reports/Documents module.
     */
    public function pdf(string $slug): void
    {
        $userRoles = $this->getUserRoleSlugs();
        $article = $this->service->findVisibleArticle($slug, $userRoles);

        if (!$article) {
            flash_error(t('blog.exception.article_not_found'));
            $this->redirect(route('blog.index'));
            return;
        }

        try {
            $documentService = app(DocumentService::class);
            $result = $documentService->generate('Blog', 'articles', (int) $article['id']);
        } catch (\Throwable $e) {
            error_log('[Blog] PDF generation failed for article ' . ($article['id'] ?? '?') . ': ' . $e->getMessage());
            flash_error(t('blog.exception.pdf_generation_failed', ['error' => $e->getMessage()]));
            $this->redirect(route('blog.show', ['slug' => $slug]));
            return;
        }

        $filePath = $result['path'];
        $realPath = realpath($filePath);
        $storageBase = realpath(dirname(__DIR__, 4) . '/public/uploads/reports');
        if (!$realPath || !$storageBase || strpos($realPath, $storageBase) !== 0) {
            flash_error(t('blog.exception.invalid_pdf_file'));
            $this->redirect(route('blog.show', ['slug' => $slug]));
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    }

    /**
     * Articles filtered by category.
     */
    public function category(string $slug): void
    {
        $result = $this->service->getCategoryPageData($slug, $_GET['search'] ?? '', max(1, (int) ($_GET['page'] ?? 1)), $this->getUserRoleSlugs());
        if (!$result) {
            flash_error(t('blog.exception.category_not_found'));
            $this->redirect(route('blog.index'));
            return;
        }

        $category = $result['currentCategory'];

        $this->render('Blog/Views/public/index', array_merge($result, [
            'total_pages' => $result['lastPage'],
            'layout'          => 'main',
            'activePage'      => 'blog',
            'categories'      => $result['categories'],
            'tags'            => $result['tags'],
            'filters'         => $result['filters'],
            'currentCategory' => $category,
            'pageTitle'       => t('blog.public.category.title', ['name' => $category['name']]),
            'breadcrumbs'     => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => $category['name']],
            ],
        ]));
    }

    /**
     * Articles filtered by tag.
     */
    public function tag(string $slug): void
    {
        $result = $this->service->getTagPageData($slug, $_GET['search'] ?? '', max(1, (int) ($_GET['page'] ?? 1)), $this->getUserRoleSlugs());
        if (!$result) {
            flash_error(t('blog.exception.tag_not_found'));
            $this->redirect(route('blog.index'));
            return;
        }

        $tag = $result['currentTag'];

        $this->render('Blog/Views/public/index', array_merge($result, [
            'total_pages' => $result['lastPage'],
            'layout'     => 'main',
            'activePage' => 'blog',
            'categories' => $result['categories'],
            'tags'       => $result['tags'],
            'filters'    => $result['filters'],
            'currentTag' => $tag,
            'pageTitle'  => t('blog.public.tag.title', ['name' => $tag['name']]),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => '#' . $tag['name']],
            ],
        ]));
    }

    /**
     * Articles authored by a specific user.
     */
    public function author(string $id): void
    {
        $userId = (int) $id;
        if ($userId <= 0) {
            flash_error(t('blog.exception.invalid_author'));
            $this->redirect(route('blog.index'));
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'search' => $_GET['search'] ?? '',
            'sort'   => in_array($_GET['sort'] ?? '', ['popular', 'recent'], true) ? $_GET['sort'] : 'recent',
        ];

        $result = $this->service->getAuthorPageData($userId, $filters, $page, $this->getUserRoleSlugs());
        if (!$result) {
            flash_error(t('blog.exception.author_not_found'));
            $this->redirect(route('blog.index'));
            return;
        }

        $author = $result['author'];
        $this->render('Blog/Views/public/author', array_merge($result, [
            'total_pages' => $result['lastPage'],
            'layout'     => 'main',
            'activePage' => 'blog',
            'pageTitle'  => t('blog.public.author.title', ['name' => $author['name'] ?? '']),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => $author['name'] ?? t('blog.public.author.fallback')],
            ],
        ]));
    }

    /**
     * Search articles.
     */
    public function search(): void
    {
        $search = $_GET['q'] ?? '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->service->getSearchPageData($search, $page, $this->getUserRoleSlugs());

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Blog/Views/public/partials/article_list', array_merge($result, [
                'total_pages' => $result['lastPage'],
                'categories' => $result['categories'],
                'paginationUrl' => route('blog.search'),
            ]));
            return;
        }

        $this->render('Blog/Views/public/index', array_merge($result, [
            'total_pages' => $result['lastPage'],
            'layout'     => 'main',
            'activePage' => 'blog',
            'categories' => $result['categories'],
            'tags'       => $result['tags'],
            'filters'    => $result['filters'],
            'paginationUrl' => route('blog.search'),
            'pageTitle'  => t('blog.public.search.title', ['query' => $search]),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.public.search.breadcrumb', ['query' => $search])],
            ],
        ]));
    }

    // ── Private helpers ─────────────────────────────────────────

    private function getUserRoleSlugs(): array
    {
        $authData = auth();
        $roles    = $authData['roles'] ?? [];
        return array_map(fn ($r) => is_array($r) ? ($r['slug'] ?? '') : (string) $r, $roles);
    }

    /**
     * Normalize a YYYY-MM-DD date string from input. Returns '' if not a valid date.
     */
    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }
}
