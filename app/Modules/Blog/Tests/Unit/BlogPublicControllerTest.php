<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Controllers\BlogPublicController;
use Tests\ControllerTestCase;

class BlogPublicControllerTest extends ControllerTestCase
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
                moderated_by INTEGER NULL,
                moderated_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE blog_article_likes (
                article_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE blog_article_bookmarks (
                article_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE blog_article_views (
                article_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                viewed_on TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $this->createUsersTable();

        $this->actingAs(1, ['blog.view']);
    }

    public function testIndexRendersPublishedArticles(): void
    {
        $catId = $this->insertRow('blog_categories', ['name' => 'Tech', 'slug' => 'tech']);
        $this->insertRow('blog_articles', [
            'title' => 'Hello World', 'slug' => 'hello-world', 'content' => '<p>Ciao</p>',
            'status' => 'published', 'category_id' => $catId, 'published_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->dispatch(BlogPublicController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertSame('Blog/Views/public/index', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testShowRendersVisibleArticle(): void
    {
        $this->insertRow('blog_articles', [
            'title' => 'My Article', 'slug' => 'my-article', 'content' => '<p>Testo</p>',
            'status' => 'published', 'published_at' => date('Y-m-d H:i:s'), 'created_by' => 2,
        ]);
        $this->insertRow('users', ['id' => 2, 'name' => 'Author']);

        $result = $this->dispatch(BlogPublicController::class, 'show', ['my-article']);

        $this->assertTrue($result->didRender());
        $this->assertSame('My Article', $result->renderedData()['article']['title']);
    }

    public function testShowRedirectsWhenArticleNotFound(): void
    {
        $result = $this->dispatch(BlogPublicController::class, 'show', ['missing-slug']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Articolo non trovato.', $this->flashOf('error'));
    }

    public function testCategoryRendersArticlesForCategory(): void
    {
        $catId = $this->insertRow('blog_categories', ['name' => 'News', 'slug' => 'news']);
        $this->insertRow('blog_articles', [
            'title' => 'News Article', 'slug' => 'news-article', 'content' => '<p>x</p>',
            'status' => 'published', 'category_id' => $catId, 'published_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->dispatch(BlogPublicController::class, 'category', ['news']);

        $this->assertTrue($result->didRender());
        $this->assertSame('News', $result->renderedData()['currentCategory']['name']);
    }

    public function testCategoryRedirectsWhenNotFound(): void
    {
        $result = $this->dispatch(BlogPublicController::class, 'category', ['missing']);

        $this->assertTrue($result->isRedirect());
    }

    public function testTagRendersArticlesForTag(): void
    {
        $tagId = $this->insertRow('blog_tags', ['name' => 'php', 'slug' => 'php']);
        $artId = $this->insertRow('blog_articles', [
            'title' => 'PHP Tips', 'slug' => 'php-tips', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => date('Y-m-d H:i:s'),
        ]);
        $this->insertRow('blog_article_tags', ['article_id' => $artId, 'tag_id' => $tagId]);

        $result = $this->dispatch(BlogPublicController::class, 'tag', ['php']);

        $this->assertTrue($result->didRender());
        $this->assertSame('php', $result->renderedData()['currentTag']['slug']);
    }

    public function testAuthorRendersArticlesForAuthor(): void
    {
        $this->insertRow('users', ['id' => 5, 'name' => 'Jane Doe']);
        $this->insertRow('blog_articles', [
            'title' => 'By Jane', 'slug' => 'by-jane', 'content' => '<p>x</p>',
            'status' => 'published', 'created_by' => 5, 'published_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->dispatch(BlogPublicController::class, 'author', ['5']);

        $this->assertTrue($result->didRender());
        $this->assertSame('Jane Doe', $result->renderedData()['author']['name']);
    }

    public function testAuthorRedirectsOnInvalidId(): void
    {
        $result = $this->dispatch(BlogPublicController::class, 'author', ['0']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Autore non valido.', $this->flashOf('error'));
    }

    public function testSearchRendersMatchingArticles(): void
    {
        $this->insertRow('blog_articles', [
            'title' => 'Searchable Title', 'slug' => 'searchable-title', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withGet(['q' => 'Searchable'])
            ->dispatch(BlogPublicController::class, 'search', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }

    public function testSearchPassesOwnRouteAsPaginationUrlToFullPageView(): void
    {
        // Regression: pagination on the search page used to hardcode blog.index,
        // silently dropping the query and jumping back to the unfiltered index
        // as soon as the user clicked "page 2".
        $result = $this->withGet(['q' => 'anything'])
            ->dispatch(BlogPublicController::class, 'search', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(route('blog.search'), $result->renderedData()['paginationUrl']);
    }

    public function testSearchHtmxPartialPassesOwnRouteAsPaginationUrl(): void
    {
        $result = $this->withGet(['q' => 'anything'])->asHtmx()
            ->dispatch(BlogPublicController::class, 'search', []);

        $this->assertSame(route('blog.search'), $result->renderedData()['paginationUrl']);
    }
}
