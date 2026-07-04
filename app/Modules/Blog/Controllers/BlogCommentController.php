<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers;

use App\Core\Controller;
use App\Modules\Blog\Services\BlogCommentService;
use App\Security\Sanitizer;

class BlogCommentController extends Controller
{
    private const MAX_COMMENT_LENGTH = 2000;

    private BlogCommentService $service;

    public function __construct()
    {
        $this->service = app(BlogCommentService::class);
    }

    /**
     * Store a new comment on an article.
     */
    public function store(string $slug): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $body = trim(Sanitizer::clean($_POST['body'] ?? ''));
        if (empty($body)) {
            flash_error(t('blog.exception.comment_empty'));
            $this->redirect(route('blog.show', ['slug' => $slug]));
            return;
        }

        if (mb_strlen($body) > self::MAX_COMMENT_LENGTH) {
            flash_error(t('blog.exception.comment_too_long', ['max' => self::MAX_COMMENT_LENGTH]));
            $this->redirect(route('blog.show', ['slug' => $slug]));
            return;
        }

        $result = $this->service->storeComment($slug, $userId, $body, $this->getUserRoleSlugs());
        if (isset($result['error'])) {
            flash_error($result['error']);
            $redirectRoute = $this->service->findArticleBySlug($slug) ? route('blog.show', ['slug' => $slug]) : route('blog.index');
            $this->redirect($redirectRoute);
            return;
        }

        flash_success(($result['status'] ?? 'approved') === 'pending'
            ? t('blog.flash.comment_pending')
            : t('blog.flash.comment_published'));
        $this->redirect(route('blog.show', ['slug' => $slug]) . '#comments');
    }

    /**
     * Reply to an existing comment (1-level only).
     */
    public function reply(string $slug, string $commentId): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $body = trim(Sanitizer::clean($_POST['body'] ?? ''));
        if (empty($body)) {
            flash_error(t('blog.exception.reply_empty'));
            $this->redirect(route('blog.show', ['slug' => $slug]));
            return;
        }

        if (mb_strlen($body) > self::MAX_COMMENT_LENGTH) {
            flash_error(t('blog.exception.reply_too_long', ['max' => self::MAX_COMMENT_LENGTH]));
            $this->redirect(route('blog.show', ['slug' => $slug]));
            return;
        }

        $result = $this->service->replyToComment($slug, (int) $commentId, $userId, $body, $this->getUserRoleSlugs());
        if (isset($result['error'])) {
            flash_error($result['error']);
            $redirectRoute = $this->service->findArticleBySlug($slug) ? route('blog.show', ['slug' => $slug]) : route('blog.index');
            $this->redirect($redirectRoute);
            return;
        }

        flash_success(($result['status'] ?? 'approved') === 'pending'
            ? t('blog.flash.reply_pending')
            : t('blog.flash.reply_published'));
        $this->redirect(route('blog.show', ['slug' => $slug]) . '#comments');
    }

    // ── Private helpers ─────────────────────────────────────────

    private function getUserRoleSlugs(): array
    {
        $authData = auth();
        $roles    = $authData['roles'] ?? [];
        return array_map(fn ($r) => is_array($r) ? ($r['slug'] ?? '') : (string) $r, $roles);
    }
}
