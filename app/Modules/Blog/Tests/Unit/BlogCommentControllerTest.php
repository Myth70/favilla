<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Controllers\BlogCommentController;
use Tests\ControllerTestCase;

class BlogCommentControllerTest extends ControllerTestCase
{
    private int $articleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE blog_articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                content TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'published',
                category_id INTEGER NULL,
                visibility TEXT NOT NULL DEFAULT 'all',
                created_by INTEGER NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE blog_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            );
            CREATE TABLE blog_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                parent_id INTEGER NULL,
                body TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'approved',
                moderated_by INTEGER NULL,
                moderated_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE blog_comment_blacklist (
                user_id INTEGER NOT NULL PRIMARY KEY,
                reason TEXT NULL,
                banned_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE app_settings (
                `key` TEXT PRIMARY KEY,
                value TEXT NULL
            );
        ");
        $this->createUsersTable();

        $this->articleId = $this->insertRow('blog_articles', [
            'title' => 'Commentable', 'slug' => 'commentable', 'content' => '<p>x</p>', 'created_by' => 2,
        ]);
        $this->insertRow('users', ['id' => 2, 'name' => 'Author']);

        $this->actingAs(1, ['blog.comment']);
    }

    public function testStoreCreatesApprovedComment(): void
    {
        $result = $this->withPost(['body' => 'Great article!'])
            ->dispatch(BlogCommentController::class, 'store', ['commentable']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comments')->fetchColumn());
        $this->assertSame('approved', $this->pdo->query('SELECT status FROM blog_comments')->fetchColumn());
    }

    public function testStoreRejectsEmptyBody(): void
    {
        $result = $this->withPost(['body' => '   '])
            ->dispatch(BlogCommentController::class, 'store', ['commentable']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Il commento non può essere vuoto.', $this->flashOf('error'));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comments')->fetchColumn());
    }

    public function testStoreRejectsBannedUser(): void
    {
        $this->insertRow('blog_comment_blacklist', ['user_id' => 1]);

        $result = $this->withPost(['body' => 'Trying anyway'])
            ->dispatch(BlogCommentController::class, 'store', ['commentable']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comments')->fetchColumn());
    }

    public function testReplyCreatesReplyToRootComment(): void
    {
        $parentId = $this->insertRow('blog_comments', [
            'article_id' => $this->articleId, 'user_id' => 2, 'body' => 'Root comment',
        ]);

        $result = $this->withPost(['body' => 'A reply'])
            ->dispatch(BlogCommentController::class, 'reply', ['commentable', (string) $parentId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comments')->fetchColumn());
    }

    public function testReplyRejectsReplyToReply(): void
    {
        $rootId = $this->insertRow('blog_comments', [
            'article_id' => $this->articleId, 'user_id' => 2, 'body' => 'Root',
        ]);
        $replyId = $this->insertRow('blog_comments', [
            'article_id' => $this->articleId, 'user_id' => 2, 'parent_id' => $rootId, 'body' => 'Reply',
        ]);

        $result = $this->withPost(['body' => 'Nested reply'])
            ->dispatch(BlogCommentController::class, 'reply', ['commentable', (string) $replyId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comments')->fetchColumn());
    }

    public function testStoreRejectsCommentOnRoleRestrictedArticleUserCannotSee(): void
    {
        // Regression: storeComment() used to fetch the article via a raw
        // findBySlug() that only checks status=published, bypassing the
        // per-article role visibility restriction enforced everywhere else
        // (show(), listings...). A user without the required role could
        // still comment on/reply to a restricted article by guessing its slug.
        $restrictedId = $this->insertRow('blog_articles', [
            'title' => 'HR only', 'slug' => 'hr-only', 'content' => '<p>x</p>',
            'visibility' => 'hr', 'created_by' => 2,
        ]);

        // actingAs(1, ['blog.comment']) has no roles set -> not in 'hr'.
        $result = $this->withPost(['body' => 'Sneaky comment'])
            ->dispatch(BlogCommentController::class, 'store', ['hr-only']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM blog_comments WHERE article_id = {$restrictedId}")->fetchColumn());
    }

    public function testReplyRejectsReplyOnRoleRestrictedArticleUserCannotSee(): void
    {
        $restrictedId = $this->insertRow('blog_articles', [
            'title' => 'HR only', 'slug' => 'hr-only-2', 'content' => '<p>x</p>',
            'visibility' => 'hr', 'created_by' => 2,
        ]);
        $parentId = $this->insertRow('blog_comments', [
            'article_id' => $restrictedId, 'user_id' => 2, 'body' => 'Root comment',
        ]);

        $result = $this->withPost(['body' => 'Sneaky reply'])
            ->dispatch(BlogCommentController::class, 'reply', ['hr-only-2', (string) $parentId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM blog_comments WHERE article_id = {$restrictedId}")->fetchColumn());
    }

    public function testStoreAllowsCommentOnRoleRestrictedArticleUserCanSee(): void
    {
        $restrictedId = $this->insertRow('blog_articles', [
            'title' => 'HR only', 'slug' => 'hr-only-3', 'content' => '<p>x</p>',
            'visibility' => 'hr', 'created_by' => 2,
        ]);

        $this->actingAs(1, ['blog.comment'], ['roles' => [['slug' => 'hr']]]);

        $result = $this->withPost(['body' => 'Legit comment'])
            ->dispatch(BlogCommentController::class, 'store', ['hr-only-3']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM blog_comments WHERE article_id = {$restrictedId}")->fetchColumn());
    }
}
