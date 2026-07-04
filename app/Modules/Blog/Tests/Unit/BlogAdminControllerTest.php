<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Controllers\BlogAdminController;
use Tests\ControllerTestCase;

class BlogAdminControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE blog_articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                content TEXT NOT NULL,
                cover_image TEXT NULL,
                category_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                is_pinned INTEGER NOT NULL DEFAULT 0,
                visibility TEXT NOT NULL DEFAULT 'all',
                reading_time INTEGER NOT NULL DEFAULT 0,
                view_count INTEGER NOT NULL DEFAULT 0,
                published_at TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE blog_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE blog_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE blog_article_tags (
                article_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL
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
            CREATE TABLE blog_article_likes (
                article_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NULL,
                avatar_path TEXT NULL,
                deleted_at TEXT NULL
            );
        ");
        $this->insertRow('users', ['id' => 1, 'name' => 'Admin']);

        $this->actingAs(1, ['blog.admin', 'blog.comment.moderate']);
    }

    // ── Dashboard ───────────────────────────────────────────────

    public function testIndexRendersDashboardMetrics(): void
    {
        $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => 'published']);

        $result = $this->dispatch(BlogAdminController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['articleCounts']['published']);
    }

    // ── Articles ────────────────────────────────────────────────

    public function testArticlesRendersAllArticles(): void
    {
        $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x']);

        $result = $this->dispatch(BlogAdminController::class, 'articles', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testBatchActionPublishesSelectedArticles(): void
    {
        $id = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => 'draft']);

        $result = $this->withPost(['ids' => [(string) $id], 'batch_action' => 'publish'])
            ->dispatch(BlogAdminController::class, 'batchAction', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('published', $this->pdo->query("SELECT status FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    public function testBatchActionRejectsInvalidAction(): void
    {
        $result = $this->withPost(['ids' => ['1'], 'batch_action' => 'bogus'])
            ->dispatch(BlogAdminController::class, 'batchAction', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame("Seleziona almeno un articolo e un'azione valida.", $this->flashOf('error'));
    }

    public function testPinSetsIsPinned(): void
    {
        $id = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x']);

        $result = $this->dispatch(BlogAdminController::class, 'pin', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query("SELECT is_pinned FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    public function testUnpinClearsIsPinned(): void
    {
        $id = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x', 'is_pinned' => 1]);

        $result = $this->dispatch(BlogAdminController::class, 'unpin', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query("SELECT is_pinned FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    // ── Trash ───────────────────────────────────────────────────

    public function testTrashRendersSoftDeletedArticles(): void
    {
        $this->insertRow('blog_articles', ['title' => 'Gone', 'slug' => 'gone', 'content' => 'x', 'deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->dispatch(BlogAdminController::class, 'trash', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testRestoreArticleClearsDeletedAt(): void
    {
        $id = $this->insertRow('blog_articles', ['title' => 'Gone', 'slug' => 'gone', 'content' => 'x', 'deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->dispatch(BlogAdminController::class, 'restoreArticle', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertNull($this->pdo->query("SELECT deleted_at FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    public function testForceDestroyRemovesArticlePermanently(): void
    {
        $id = $this->insertRow('blog_articles', ['title' => 'Gone', 'slug' => 'gone', 'content' => 'x', 'deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->dispatch(BlogAdminController::class, 'forceDestroy', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_articles')->fetchColumn());
    }

    // ── Comments ────────────────────────────────────────────────

    public function testCommentsRendersAllComments(): void
    {
        $artId = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x']);
        $this->insertRow('blog_comments', ['article_id' => $artId, 'user_id' => 1, 'body' => 'Nice']);

        $result = $this->dispatch(BlogAdminController::class, 'comments', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testApproveCommentSetsStatusApproved(): void
    {
        $artId = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x']);
        $cid = $this->insertRow('blog_comments', ['article_id' => $artId, 'user_id' => 1, 'body' => 'Pending', 'status' => 'pending']);

        $result = $this->dispatch(BlogAdminController::class, 'approveComment', [(string) $cid]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('approved', $this->pdo->query("SELECT status FROM blog_comments WHERE id = {$cid}")->fetchColumn());
    }

    public function testRejectCommentSetsStatusRejected(): void
    {
        $artId = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x']);
        $cid = $this->insertRow('blog_comments', ['article_id' => $artId, 'user_id' => 1, 'body' => 'Spam']);

        $result = $this->dispatch(BlogAdminController::class, 'rejectComment', [(string) $cid]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('rejected', $this->pdo->query("SELECT status FROM blog_comments WHERE id = {$cid}")->fetchColumn());
    }

    public function testDeleteCommentSoftDeletes(): void
    {
        $artId = $this->insertRow('blog_articles', ['title' => 'A', 'slug' => 'a', 'content' => 'x']);
        $cid = $this->insertRow('blog_comments', ['article_id' => $artId, 'user_id' => 1, 'body' => 'Bad']);

        $result = $this->dispatch(BlogAdminController::class, 'deleteComment', [(string) $cid]);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM blog_comments WHERE id = {$cid}")->fetchColumn());
    }

    // ── Categories ──────────────────────────────────────────────

    public function testCategoriesRendersList(): void
    {
        $this->insertRow('blog_categories', ['name' => 'Tech', 'slug' => 'tech']);

        $result = $this->dispatch(BlogAdminController::class, 'categories', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['categories']);
    }

    public function testStoreCategoryCreatesCategory(): void
    {
        $result = $this->withPost(['name' => 'News', 'description' => 'Latest news'])
            ->dispatch(BlogAdminController::class, 'storeCategory', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_categories')->fetchColumn());
    }

    public function testUpdateCategoryUpdatesName(): void
    {
        $id = $this->insertRow('blog_categories', ['name' => 'Old', 'slug' => 'old']);

        $result = $this->withPost(['name' => 'New Name'])
            ->dispatch(BlogAdminController::class, 'updateCategory', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('New Name', $this->pdo->query("SELECT name FROM blog_categories WHERE id = {$id}")->fetchColumn());
    }

    public function testDestroyCategoryRemovesCategory(): void
    {
        $id = $this->insertRow('blog_categories', ['name' => 'Temp', 'slug' => 'temp']);

        $result = $this->dispatch(BlogAdminController::class, 'destroyCategory', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_categories')->fetchColumn());
    }

    // ── Tags ────────────────────────────────────────────────────

    public function testTagsRendersList(): void
    {
        $this->insertRow('blog_tags', ['name' => 'php', 'slug' => 'php']);

        $result = $this->dispatch(BlogAdminController::class, 'tags', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['tags']);
    }

    public function testStoreTagCreatesTag(): void
    {
        $result = $this->withPost(['name' => 'laravel'])
            ->dispatch(BlogAdminController::class, 'storeTag', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_tags')->fetchColumn());
    }

    public function testDestroyTagRemovesTag(): void
    {
        $id = $this->insertRow('blog_tags', ['name' => 'old-tag', 'slug' => 'old-tag']);

        $result = $this->dispatch(BlogAdminController::class, 'destroyTag', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_tags')->fetchColumn());
    }

    // ── Blacklist ───────────────────────────────────────────────

    public function testBlacklistRendersBannedUsers(): void
    {
        $this->insertRow('users', ['id' => 9, 'name' => 'Bad Actor']);
        $this->insertRow('blog_comment_blacklist', ['user_id' => 9]);

        $result = $this->dispatch(BlogAdminController::class, 'blacklist', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['banned']);
    }

    public function testBanCreatesBlacklistEntry(): void
    {
        $this->insertRow('users', ['id' => 9, 'name' => 'Bad Actor']);

        $result = $this->withPost(['user_id' => '9', 'reason' => 'Spam'])
            ->dispatch(BlogAdminController::class, 'ban', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comment_blacklist')->fetchColumn());
    }

    public function testUnbanRemovesBlacklistEntry(): void
    {
        $this->insertRow('users', ['id' => 9, 'name' => 'Reformed']);
        $this->insertRow('blog_comment_blacklist', ['user_id' => 9]);

        $result = $this->dispatch(BlogAdminController::class, 'unban', ['9']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_comment_blacklist')->fetchColumn());
    }
}
