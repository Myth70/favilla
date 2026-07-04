<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers;

use App\Core\Controller;
use App\Modules\Blog\Services\BlogArticleService;
use App\Modules\Blog\Services\BlogAuthorService;
use App\Security\Sanitizer;
use App\Traits\ControllerHelpers;

class BlogAuthorController extends Controller
{
    use ControllerHelpers;
    private BlogAuthorService $authorService;
    private BlogArticleService $service;

    public function __construct()
    {
        $this->authorService = app(BlogAuthorService::class);
        $this->service = app(BlogArticleService::class);
    }

    /**
     * List current user's articles.
     */
    public function index(): void
    {
        $authData = auth() ?? [];
        $userId   = (int) ($authData['id'] ?? 0);
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'sort'   => $_GET['sort'] ?? 'created_at',
            'dir'    => $_GET['dir'] ?? 'DESC',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->authorService->getAuthorArticles($userId, $filters, $page);
        $stats  = $this->authorService->getAuthorStats($userId);

        $authorProfile = [
            'name'   => $authData['name'] ?? '',
            'email'  => $authData['email'] ?? '',
            'avatar' => $_SESSION['user_avatar'] ?? null,
            'roles'  => $authData['roles'] ?? [],
        ];

        $this->htmxOrRender(
            'Blog/Views/author/partials/table',
            'Blog/Views/author/index',
            array_merge($result, [
                'total_pages' => $result['lastPage'],
                'filters'       => $filters,
                'stats'         => $stats,
                'authorProfile' => $authorProfile,
                'pageTitle'     => t('blog.author.my_articles'),
                'breadcrumbs'   => [
                    ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                    ['label' => t('blog.title'), 'route' => 'blog.index'],
                    ['label' => t('blog.author.my_articles')],
                ],
            ])
        );
    }

    /**
     * Show create form.
     */
    public function create(): void
    {
        $formData = $this->authorService->getCreateFormData();

        $this->render('Blog/Views/author/form', [
            'article'    => null,
            'categories' => $formData['categories'],
            'allTags'    => $formData['allTags'],
            'articleTags' => [],
            'roles'      => $formData['roles'],
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
            'pageTitle'  => t('blog.author.new_article'),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.author.my_articles'), 'route' => 'blog.author.index'],
                ['label' => t('blog.author.new_article')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    /**
     * Store new article.
     */
    public function store(): void
    {
        $data   = $this->readFormData();
        $errors = $this->validate($data);

        if ($errors) {
            $this->flashErrors($errors, $data, 'blog.create');
            return;
        }

        // Handle cover image
        try {
            $coverImage = $this->service->handleCoverImage($_POST, $_FILES, null);
        } catch (\RuntimeException $e) {
            $_SESSION['_errors'] = ['cover_image' => $e->getMessage()];
            $_SESSION['_old']    = $data;
            $this->redirect(route('blog.create'));
            return;
        }

        $data['cover_image'] = $coverImage;
        $data['visibility']  = BlogArticleService::buildVisibility($_POST);
        $data['is_pinned']   = has_permission('blog.admin') ? (int) ($_POST['is_pinned'] ?? 0) : 0;

        $articleId = $this->service->createArticle($data, (int) (auth()['id'] ?? 0));

        flash_success(!empty($data['publish_at']) ? t('blog.flash.article_scheduled') : t('blog.flash.article_created_draft'));
        $this->redirect(route('blog.edit', ['id' => $articleId]));
    }

    /**
     * Show edit form.
     */
    public function edit(string $id): void
    {
        $formData = $this->authorService->getEditFormData((int) $id);
        if (!$formData) {
            flash_error(t('blog.exception.article_not_found'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        $article = $formData['article'];

        if (!$this->service->canEditArticle($article, (int) (auth()['id'] ?? 0))) {
            flash_error(t('blog.exception.edit_forbidden'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        $this->render('Blog/Views/author/form', [
            'article'    => $article,
            'categories' => $formData['categories'],
            'allTags'    => $formData['allTags'],
            'articleTags' => $formData['articleTags'],
            'roles'      => $formData['roles'],
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
            'pageTitle'  => t('blog.author.edit_title', ['title' => $article['title']]),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.author.my_articles'), 'route' => 'blog.author.index'],
                ['label' => t('blog.author.edit_breadcrumb')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    /**
     * Update article.
     */
    public function update(string $id): void
    {
        $articleId   = (int) $id;
        $article = $this->authorService->findArticleForEdit($articleId);

        if (!$article) {
            flash_error(t('blog.exception.article_not_found'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        if (!$this->service->canEditArticle($article, (int) (auth()['id'] ?? 0))) {
            flash_error(t('blog.exception.edit_forbidden'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        $data   = $this->readFormData();
        $errors = $this->validate($data);

        if ($errors) {
            $this->flashErrors($errors, $data, 'blog.edit', ['id' => $articleId]);
            return;
        }

        try {
            $coverImage = $this->service->handleCoverImage($_POST, $_FILES, $article['cover_image']);
        } catch (\RuntimeException $e) {
            $_SESSION['_errors'] = ['cover_image' => $e->getMessage()];
            $_SESSION['_old']    = $data;
            $this->redirect(route('blog.edit', ['id' => $articleId]));
            return;
        }

        $data['visibility'] = BlogArticleService::buildVisibility($_POST);
        $data['is_pinned']  = has_permission('blog.admin') ? (int) ($_POST['is_pinned'] ?? 0) : (int) $article['is_pinned'];

        $this->service->updateArticle($articleId, $data, $coverImage);

        flash_success(t('blog.flash.article_updated'));
        $this->redirect(route('blog.edit', ['id' => $articleId]));
    }

    /**
     * Publish an article.
     */
    public function publish(string $id): void
    {
        $article = $this->authorService->findArticleForEdit((int) $id);

        if (!$article || !$this->service->canEditArticle($article, (int) (auth()['id'] ?? 0))) {
            flash_error(t('blog.exception.article_not_found_or_forbidden'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        $this->service->publish((int) $id, (int) (auth()['id'] ?? 0));

        flash_success(t('blog.flash.article_published'));
        $this->redirect(route('blog.edit', ['id' => $id]));
    }

    /**
     * Unpublish an article.
     */
    public function unpublish(string $id): void
    {
        $article = $this->authorService->findArticleForEdit((int) $id);

        if (!$article || !$this->service->canEditArticle($article, (int) (auth()['id'] ?? 0))) {
            flash_error(t('blog.exception.article_not_found_or_forbidden'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        $this->service->unpublish((int) $id);

        flash_success(t('blog.flash.article_unpublished'));
        $this->redirect(route('blog.edit', ['id' => $id]));
    }

    /**
     * Soft-delete an article.
     */
    public function destroy(string $id): void
    {
        $article = $this->authorService->findArticleForEdit((int) $id);

        if (!$article || !$this->service->canEditArticle($article, (int) (auth()['id'] ?? 0))) {
            flash_error(t('blog.exception.article_not_found_or_forbidden'));
            $this->redirect(route('blog.author.index'));
            return;
        }

        $this->service->deleteArticle((int) $id);

        flash_success(t('blog.flash.article_deleted'));
        $this->redirect(route('blog.author.index'));
    }

    // ── Private helpers ─────────────────────────────────────────

    private function readFormData(): array
    {
        $publishAt = trim($_POST['publish_at'] ?? '');
        return [
            'title'             => trim(Sanitizer::clean($_POST['title'] ?? '')),
            'excerpt'           => trim(Sanitizer::clean($_POST['excerpt'] ?? '')),
            'content'           => trim($_POST['content'] ?? ''), // Allow newlines
            'category_id'       => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'tags'              => trim($_POST['tags'] ?? ''),
            'publish_at'        => $publishAt !== '' ? $publishAt : null,
            'meta_description'  => trim(Sanitizer::clean($_POST['meta_description'] ?? '')),
            'meta_keywords'     => trim(Sanitizer::clean($_POST['meta_keywords'] ?? '')),
            'og_image'          => trim(Sanitizer::clean($_POST['og_image'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors['title'] = t('blog.validation.title_required');
        } elseif (mb_strlen($data['title']) > 255) {
            $errors['title'] = t('blog.validation.title_max', ['max' => 255]);
        }

        if (mb_strlen($data['excerpt']) > 500) {
            $errors['excerpt'] = t('blog.validation.excerpt_max', ['max' => 500]);
        }

        if (empty($data['content'])) {
            $errors['content'] = t('blog.validation.content_required');
        }

        return $errors;
    }
}
