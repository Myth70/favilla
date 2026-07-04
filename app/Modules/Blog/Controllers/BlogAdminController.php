<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers;

use App\Core\Controller;
use App\Modules\Blog\Services\BlogAdminService;
use App\Modules\Blog\Services\BlogArticleService;
use App\Security\Sanitizer;
use App\Services\AuditService;
use App\Traits\ControllerHelpers;

class BlogAdminController extends Controller
{
    use ControllerHelpers;
    private BlogAdminService $adminService;
    private BlogArticleService $service;

    public function __construct()
    {
        $this->adminService = app(BlogAdminService::class);
        $this->service = app(BlogArticleService::class);
    }

    // ── Dashboard ───────────────────────────────────────────────

    public function index(): void
    {
        $metrics = $this->adminService->getDashboardMetrics();

        $this->render('Blog/Views/admin/index', [
            'articleCounts' => $metrics['articleCounts'],
            'commentCount'  => $metrics['commentCount'],
            'commentStatusCounts' => $metrics['commentStatusCounts'] ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            'categoryCount' => $metrics['categoryCount'],
            'tagCount'      => $metrics['tagCount'],
            'pinnedCount'   => $metrics['pinnedCount'],
            'pageTitle'     => t('blog.admin.title'),
            'breadcrumbs'   => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.admin.breadcrumb')],
            ],
        ]);
    }

    // ── Articles management ─────────────────────────────────────

    public function articles(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'sort'   => $_GET['sort'] ?? 'created_at',
            'dir'    => $_GET['dir'] ?? 'DESC',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->adminService->getArticlesPage($filters, $page);

        $this->htmxOrRender(
            'Blog/Views/admin/partials/article_table',
            'Blog/Views/admin/articles',
            array_merge($result, [
                'total_pages' => $result['lastPage'],
                'filters'    => $filters,
                'pageTitle'  => t('blog.admin.articles.title'),
                'breadcrumbs' => [
                    ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                    ['label' => t('blog.title'), 'route' => 'blog.index'],
                    ['label' => t('blog.admin.breadcrumb'), 'route' => 'blog.admin.index'],
                    ['label' => t('blog.admin.articles.breadcrumb')],
                ],
            ])
        );
    }

    // ── Batch actions ─────────────────────────────────────────────

    public function batchAction(): void
    {
        $ids    = array_map('intval', (array) ($_POST['ids'] ?? []));
        $action = trim($_POST['batch_action'] ?? '');

        if (empty($ids) || !in_array($action, ['publish', 'unpublish', 'delete', 'pin', 'unpin'], true)) {
            flash_error(t('blog.exception.select_articles_and_action'));
            $this->redirect(route('blog.admin.articles'));
            return;
        }

        $processed = [];
        $skipped   = [];
        foreach ($ids as $id) {
            $article = $this->adminService->findArticleForEdit($id);
            if (!$article) {
                $skipped[] = $id;
                continue;
            }

            $applied = false;
            switch ($action) {
                case 'publish':
                    if ($article['status'] !== 'published') {
                        $this->adminService->setArticleStatus($id, 'published', date('Y-m-d H:i:s'));
                        $applied = true;
                    }
                    break;
                case 'unpublish':
                    if ($article['status'] === 'published') {
                        $this->adminService->setArticleStatus($id, 'draft', null);
                        $applied = true;
                    }
                    break;
                case 'delete':
                    $this->service->deleteArticle($id);
                    $applied = true;
                    break;
                case 'pin':
                    if (empty($article['is_pinned'])) {
                        $this->service->togglePin($id, true);
                        $applied = true;
                    }
                    break;
                case 'unpin':
                    if (!empty($article['is_pinned'])) {
                        $this->service->togglePin($id, false);
                        $applied = true;
                    }
                    break;
            }

            if ($applied) {
                $processed[] = $id;
            } else {
                $skipped[] = $id;
            }
        }

        $labels = [
            'publish'   => t('blog.batch.action_publish'),
            'unpublish' => t('blog.batch.action_unpublish'),
            'delete'    => t('blog.batch.action_delete'),
            'pin'       => t('blog.batch.action_pin'),
            'unpin'     => t('blog.batch.action_unpin'),
        ];
        $count = count($processed);
        AuditService::log('blog_batch_' . $action, 'blog_article', 0, null, [
            'processed_ids' => $processed,
            'skipped_ids'   => $skipped,
            'count'         => $count,
            'actor_id'      => (int) (auth()['id'] ?? 0),
        ]);

        if ($count === 0) {
            // Il layout (app/Views/layouts/main.php) legge solo _flash_success/_flash_error
            // dalla sessione: nessun canale "info" disponibile senza toccare quel file.
            flash_error(t('blog.flash.batch_none'));
        } else {
            $msg = t('blog.flash.batch_success', ['count' => $count, 'label' => $labels[$action]]);
            if (!empty($skipped)) {
                $msg .= ' ' . t('blog.flash.batch_skipped', ['count' => count($skipped)]);
            }
            flash_success($msg);
        }
        $this->redirect(route('blog.admin.articles'));
    }

    // ── Pin/Unpin ────────────────────────────────────────────────

    public function pin(string $id): void
    {
        $article = $this->adminService->findArticleForEdit((int) $id);
        if (!$article) {
            flash_error(t('blog.exception.article_not_found'));
            $this->redirect(route('blog.admin.articles'));
            return;
        }

        $this->service->togglePin((int) $id, true);
        AuditService::log('blog_article_pinned', 'blog_article', (int) $id, null, ['title' => $article['title']]);

        flash_success(t('blog.flash.article_pinned'));
        $this->redirect(route('blog.admin.articles'));
    }

    public function unpin(string $id): void
    {
        $article = $this->adminService->findArticleForEdit((int) $id);
        if (!$article) {
            flash_error(t('blog.exception.article_not_found'));
            $this->redirect(route('blog.admin.articles'));
            return;
        }

        $this->service->togglePin((int) $id, false);
        AuditService::log('blog_article_unpinned', 'blog_article', (int) $id, null, ['title' => $article['title']]);

        flash_success(t('blog.flash.article_unpinned'));
        $this->redirect(route('blog.admin.articles'));
    }

    // ── Trash management ───────────────────────────────────────

    public function trash(): void
    {
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->adminService->getTrashPage($page);

        $this->htmxOrRender(
            'Blog/Views/admin/partials/trash_table',
            'Blog/Views/admin/trash',
            array_merge($result, [
                'total_pages' => $result['lastPage'],
                'pageTitle'  => t('blog.admin.trash.title'),
                'breadcrumbs' => [
                    ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                    ['label' => t('blog.title'), 'route' => 'blog.index'],
                    ['label' => t('blog.admin.breadcrumb'), 'route' => 'blog.admin.index'],
                    ['label' => t('blog.admin.trash.breadcrumb')],
                ],
            ])
        );
    }

    public function restoreArticle(string $id): void
    {
        $restored = $this->service->restoreArticle((int) $id);
        if ($restored) {
            flash_success(t('blog.flash.article_restored'));
        } else {
            flash_error(t('blog.exception.article_not_found_in_trash'));
        }
        $this->redirect(route('blog.admin.trash'));
    }

    public function forceDestroy(string $id): void
    {
        $deleted = $this->service->forceDeleteArticle((int) $id);
        if ($deleted) {
            flash_success(t('blog.flash.article_force_deleted'));
        } else {
            flash_error(t('blog.exception.article_not_found_in_trash'));
        }
        $this->redirect(route('blog.admin.trash'));
    }

    // ── Comments management ─────────────────────────────────────

    public function comments(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true)
                ? $_GET['status']
                : '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->adminService->getCommentsPage($filters, $page);

        $this->htmxOrRender(
            'Blog/Views/admin/partials/comment_table',
            'Blog/Views/admin/comments',
            array_merge($result, [
                'total_pages' => $result['lastPage'],
                'filters'    => $filters,
                'pageTitle'  => t('blog.admin.comments.title'),
                'breadcrumbs' => [
                    ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                    ['label' => t('blog.title'), 'route' => 'blog.index'],
                    ['label' => t('blog.admin.comments.breadcrumb')],
                ],
            ])
        );
    }

    public function deleteComment(string $id): void
    {
        if (!$this->adminService->deleteComment((int) $id)) {
            flash_error(t('blog.exception.comment_not_found'));
            $this->redirect(route('blog.admin.comments'));
            return;
        }

        flash_success(t('blog.flash.comment_deleted'));
        $this->redirect(route('blog.admin.comments'));
    }

    public function approveComment(string $id): void
    {
        $moderatorId = (int) (auth()['id'] ?? 0);
        if (!$this->adminService->approveComment((int) $id, $moderatorId)) {
            flash_error(t('blog.exception.comment_not_found'));
        } else {
            flash_success(t('blog.flash.comment_approved'));
        }
        $this->redirect(route('blog.admin.comments'));
    }

    public function rejectComment(string $id): void
    {
        $moderatorId = (int) (auth()['id'] ?? 0);
        if (!$this->adminService->rejectComment((int) $id, $moderatorId)) {
            flash_error(t('blog.exception.comment_not_found'));
        } else {
            flash_success(t('blog.flash.comment_rejected'));
        }
        $this->redirect(route('blog.admin.comments'));
    }

    // ── Categories management ───────────────────────────────────

    public function categories(): void
    {
        $categories = $this->adminService->getCategories();

        $this->render('Blog/Views/admin/categories', [
            'categories' => $categories,
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
            'pageTitle'  => t('blog.admin.categories.title'),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.admin.breadcrumb'), 'route' => 'blog.admin.index'],
                ['label' => t('blog.admin.categories.breadcrumb')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function storeCategory(): void
    {
        $name = trim(Sanitizer::clean($_POST['name'] ?? ''));
        $description = trim(Sanitizer::clean($_POST['description'] ?? ''));

        if (empty($name)) {
            $this->flashErrors(['name' => t('blog.validation.name_required')], compact('name', 'description'), 'blog.admin.categories');
            return;
        }

        $this->adminService->createCategory($name, $description ?: null, (int) (auth()['id'] ?? 0));
        flash_success(t('blog.flash.category_created'));
        $this->redirect(route('blog.admin.categories'));
    }

    public function updateCategory(string $id): void
    {
        $name = trim(Sanitizer::clean($_POST['name'] ?? ''));
        if (empty($name)) {
            flash_error(t('blog.validation.name_required'));
            $this->redirect(route('blog.admin.categories'));
            return;
        }

        if (!$this->adminService->updateCategory(
            (int) $id,
            $name,
            trim(Sanitizer::clean($_POST['description'] ?? '')) ?: null
        )) {
            flash_error(t('blog.exception.category_not_found'));
            $this->redirect(route('blog.admin.categories'));
            return;
        }

        flash_success(t('blog.flash.category_updated'));
        $this->redirect(route('blog.admin.categories'));
    }

    public function destroyCategory(string $id): void
    {
        if (!$this->adminService->deleteCategory((int) $id)) {
            flash_error(t('blog.exception.category_not_found'));
            $this->redirect(route('blog.admin.categories'));
            return;
        }
        flash_success(t('blog.flash.category_deleted'));
        $this->redirect(route('blog.admin.categories'));
    }

    // ── Tags management ─────────────────────────────────────────

    public function tags(): void
    {
        $tags = $this->adminService->getTags();

        $this->render('Blog/Views/admin/tags', [
            'tags'       => $tags,
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
            'pageTitle'  => t('blog.admin.tags.title'),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.admin.breadcrumb'), 'route' => 'blog.admin.index'],
                ['label' => t('blog.admin.tags.breadcrumb')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    public function storeTag(): void
    {
        $name = trim(Sanitizer::clean($_POST['name'] ?? ''));
        if (empty($name)) {
            $this->flashErrors(['name' => t('blog.validation.name_required')], ['name' => $name], 'blog.admin.tags');
            return;
        }

        $this->adminService->createTag($name);

        flash_success(t('blog.flash.tag_created'));
        $this->redirect(route('blog.admin.tags'));
    }

    public function destroyTag(string $id): void
    {
        if (!$this->adminService->deleteTag((int) $id)) {
            flash_error(t('blog.exception.tag_not_found'));
            $this->redirect(route('blog.admin.tags'));
            return;
        }
        flash_success(t('blog.flash.tag_deleted'));
        $this->redirect(route('blog.admin.tags'));
    }

    // ── Blacklist management ────────────────────────────────────

    public function blacklist(): void
    {
        $banned = $this->adminService->getBannedUsers();
        $userOptions = $this->adminService->getActiveUsersForSelection();

        $this->render('Blog/Views/admin/blacklist', [
            'banned'     => $banned,
            'userOptions' => $userOptions,
            'pageTitle'  => t('blog.admin.blacklist.title'),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('blog.title'), 'route' => 'blog.index'],
                ['label' => t('blog.admin.blacklist.breadcrumb')],
            ],
        ]);
    }

    public function ban(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $reason = trim(Sanitizer::clean($_POST['reason'] ?? ''));

        if ($userId <= 0) {
            flash_error(t('blog.exception.invalid_user'));
            $this->redirect(route('blog.admin.blacklist'));
            return;
        }

        if (!$this->adminService->banUser($userId, $reason, (int) (auth()['id'] ?? 0))) {
            flash_error(t('blog.exception.user_not_found'));
            $this->redirect(route('blog.admin.blacklist'));
            return;
        }

        flash_success(t('blog.flash.user_banned'));
        $this->redirect(route('blog.admin.blacklist'));
    }

    public function unban(string $userId): void
    {
        if (!$this->adminService->unbanUser((int) $userId)) {
            flash_error(t('blog.exception.user_not_in_blacklist'));
            $this->redirect(route('blog.admin.blacklist'));
            return;
        }

        flash_success(t('blog.flash.ban_removed'));
        $this->redirect(route('blog.admin.blacklist'));
    }
}
