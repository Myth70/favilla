<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Controllers\BlogAuthorController;
use Tests\ControllerTestCase;

class BlogAuthorControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE blog_articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                excerpt TEXT NULL,
                meta_description TEXT NULL,
                meta_keywords TEXT NULL,
                og_image TEXT NULL,
                content TEXT NOT NULL,
                cover_image TEXT NULL,
                category_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                is_pinned INTEGER NOT NULL DEFAULT 0,
                visibility TEXT NOT NULL DEFAULT 'all',
                reading_time INTEGER NOT NULL DEFAULT 0,
                view_count INTEGER NOT NULL DEFAULT 0,
                published_at TEXT NULL,
                publish_at TEXT NULL,
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE blog_article_likes (
                article_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL
            );
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL,
                name TEXT NOT NULL
            );
        ");
        $this->createUsersTable();

        $this->actingAs(1, ['blog.write']);
    }

    public function testIndexRendersAuthorArticles(): void
    {
        $this->insertRow('blog_articles', [
            'title' => 'Draft One', 'slug' => 'draft-one', 'content' => '<p>x</p>', 'created_by' => 1,
        ]);

        $result = $this->dispatch(BlogAuthorController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testCreateRendersForm(): void
    {
        $result = $this->dispatch(BlogAuthorController::class, 'create', []);

        $this->assertTrue($result->didRender());
        $this->assertNull($result->renderedData()['article']);
    }

    public function testStoreCreatesDraftArticle(): void
    {
        $result = $this->withPost([
            'title' => 'New Article', 'excerpt' => '', 'content' => '<p>Body text</p>',
        ])->dispatch(BlogAuthorController::class, 'store', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_articles')->fetchColumn());
        $this->assertSame('draft', $this->pdo->query('SELECT status FROM blog_articles')->fetchColumn());
    }

    public function testStorePersistsSeoMetaFields(): void
    {
        $result = $this->withPost([
            'title' => 'SEO Article', 'excerpt' => '', 'content' => '<p>Body text</p>',
            'meta_description' => 'A great description', 'meta_keywords' => 'blog, seo',
            'og_image' => 'cover_seo.jpg',
        ])->dispatch(BlogAuthorController::class, 'store', []);

        $this->assertTrue($result->isRedirect());
        $row = $this->pdo->query('SELECT meta_description, meta_keywords, og_image FROM blog_articles')->fetch();
        $this->assertSame('A great description', $row['meta_description']);
        $this->assertSame('blog, seo', $row['meta_keywords']);
        $this->assertSame('cover_seo.jpg', $row['og_image']);
    }

    public function testUpdatePersistsSeoMetaFields(): void
    {
        $id = $this->insertRow('blog_articles', [
            'title' => 'Mine', 'slug' => 'mine-seo', 'content' => '<p>old</p>', 'created_by' => 1,
        ]);

        $result = $this->withPost([
            'title' => 'Mine Updated', 'content' => '<p>new</p>',
            'meta_description' => 'Updated description', 'meta_keywords' => 'updated, tags',
            'og_image' => 'cover_updated.jpg',
        ])->dispatch(BlogAuthorController::class, 'update', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $row = $this->pdo->query("SELECT meta_description, meta_keywords, og_image FROM blog_articles WHERE id = {$id}")->fetch();
        $this->assertSame('Updated description', $row['meta_description']);
        $this->assertSame('updated, tags', $row['meta_keywords']);
        $this->assertSame('cover_updated.jpg', $row['og_image']);
    }

    public function testEditRedirectsWhenNotOwner(): void
    {
        $id = $this->insertRow('blog_articles', [
            'title' => 'Other', 'slug' => 'other', 'content' => '<p>x</p>', 'created_by' => 99,
        ]);

        $result = $this->dispatch(BlogAuthorController::class, 'edit', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Non hai i permessi per modificare questo articolo.', $this->flashOf('error'));
    }

    public function testUpdateUpdatesOwnedArticle(): void
    {
        $id = $this->insertRow('blog_articles', [
            'title' => 'Mine', 'slug' => 'mine', 'content' => '<p>old</p>', 'created_by' => 1,
        ]);

        $result = $this->withPost(['title' => 'Mine Updated', 'content' => '<p>new</p>'])
            ->dispatch(BlogAuthorController::class, 'update', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Mine Updated', $this->pdo->query("SELECT title FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    public function testPublishSetsStatusPublished(): void
    {
        $id = $this->insertRow('blog_articles', [
            'title' => 'Ready', 'slug' => 'ready', 'content' => '<p>x</p>', 'created_by' => 1, 'status' => 'draft',
        ]);

        $result = $this->dispatch(BlogAuthorController::class, 'publish', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('published', $this->pdo->query("SELECT status FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    public function testUnpublishRevertsToDraft(): void
    {
        $id = $this->insertRow('blog_articles', [
            'title' => 'Live', 'slug' => 'live', 'content' => '<p>x</p>', 'created_by' => 1, 'status' => 'published',
        ]);

        $result = $this->dispatch(BlogAuthorController::class, 'unpublish', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('draft', $this->pdo->query("SELECT status FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }

    public function testDestroySoftDeletesOwnedArticle(): void
    {
        $id = $this->insertRow('blog_articles', [
            'title' => 'ToDelete', 'slug' => 'to-delete', 'content' => '<p>x</p>', 'created_by' => 1,
        ]);

        $result = $this->dispatch(BlogAuthorController::class, 'destroy', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM blog_articles WHERE id = {$id}")->fetchColumn());
    }
}
