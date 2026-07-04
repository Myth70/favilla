<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Controllers\BlogInteractionController;
use Tests\ControllerTestCase;

class BlogInteractionControllerTest extends ControllerTestCase
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
                created_by INTEGER NULL,
                published_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE blog_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
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
        ");
        $this->createUsersTable();

        $this->articleId = $this->insertRow('blog_articles', [
            'title' => 'Likeable', 'slug' => 'likeable', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAs(1, ['blog.view']);
    }

    public function testToggleLikeAddsLike(): void
    {
        $result = $this->dispatch(BlogInteractionController::class, 'toggleLike', ['likeable']);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_article_likes')->fetchColumn());
    }

    public function testToggleLikeTwiceRemovesLike(): void
    {
        $this->dispatch(BlogInteractionController::class, 'toggleLike', ['likeable']);
        $this->dispatch(BlogInteractionController::class, 'toggleLike', ['likeable']);

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_article_likes')->fetchColumn());
    }

    public function testToggleBookmarkAddsBookmark(): void
    {
        $result = $this->dispatch(BlogInteractionController::class, 'toggleBookmark', ['likeable']);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM blog_article_bookmarks')->fetchColumn());
    }

    public function testSavedRendersBookmarkedArticles(): void
    {
        $this->insertRow('blog_article_bookmarks', ['article_id' => $this->articleId, 'user_id' => 1]);

        $result = $this->dispatch(BlogInteractionController::class, 'saved', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['items']);
    }
}
