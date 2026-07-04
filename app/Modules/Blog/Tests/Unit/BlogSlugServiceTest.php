<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Services\BlogSlugService;
use Tests\ModuleTestCase;

/**
 * Test per BlogSlugService.
 *
 * Il service accede al DB tramite i Repository del Blog (per verificare unicità slug).
 * Usiamo SQLite in-memory con le tabelle minime necessarie.
 *
 * Il Container è configurato in ModuleTestCase::setUp() con il PDO SQLite,
 * quindi app(BlogArticleRepository::class) funziona via autowiring.
 */
class BlogSlugServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Schema minimo: solo le colonne usate da slugExists()
        $this->migrate('
            CREATE TABLE blog_articles (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                slug       TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            )
        ');

        $this->migrate('
            CREATE TABLE blog_categories (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL
            )
        ');

        $this->migrate('
            CREATE TABLE blog_tags (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL
            )
        ');
    }

    // -------------------------------------------------------------------------
    // articleSlug()
    // -------------------------------------------------------------------------

    public function testArticleSlugFromSimpleTitle(): void
    {
        // DB vuoto → nessun conflitto → slug diretto
        $slug = BlogSlugService::articleSlug('Hello World');
        $this->assertSame('hello-world', $slug);
    }

    public function testArticleSlugHandlesAccentedChars(): void
    {
        $slug = BlogSlugService::articleSlug('Caffè Americano');
        $this->assertSame('caffe-americano', $slug);
    }

    public function testArticleSlugHandlesSpecialChars(): void
    {
        $slug = BlogSlugService::articleSlug('Test!@#$%^&*()');
        $this->assertSame('test', $slug);
    }

    public function testArticleSlugIsUniqueOnConflict(): void
    {
        // Inserisci articolo con slug "hello"
        $this->insertRow('blog_articles', ['slug' => 'hello']);

        // Lo stesso titolo → deve dare "hello-1"
        $slug = BlogSlugService::articleSlug('Hello');
        $this->assertSame('hello-1', $slug);
    }

    public function testArticleSlugIsUniqueWithMultipleConflicts(): void
    {
        $this->insertRow('blog_articles', ['slug' => 'hello']);
        $this->insertRow('blog_articles', ['slug' => 'hello-1']);

        // "hello" e "hello-1" esistono → deve dare "hello-2"
        $slug = BlogSlugService::articleSlug('Hello');
        $this->assertSame('hello-2', $slug);
    }

    public function testArticleSlugExcludesOwnIdOnEdit(): void
    {
        // Articolo esistente con id=1 e slug="my-article"
        $id = $this->insertRow('blog_articles', ['slug' => 'my-article']);

        // Editing stesso articolo → excludeId=1 → slug invariato
        $slug = BlogSlugService::articleSlug('My Article', $id);
        $this->assertSame('my-article', $slug);
    }

    public function testReservedSlugGetsArticleSuffix(): void
    {
        // "My" → slug "my" → è riservato → diventa "my-articolo"
        $slug = BlogSlugService::articleSlug('My');
        $this->assertSame('my-articolo', $slug);
    }

    public function testReservedSlugAdmin(): void
    {
        $slug = BlogSlugService::articleSlug('Admin');
        $this->assertSame('admin-articolo', $slug);
    }

    // -------------------------------------------------------------------------
    // categorySlug()
    // -------------------------------------------------------------------------

    public function testCategorySlugBasic(): void
    {
        $slug = BlogSlugService::categorySlug('Tech News');
        $this->assertSame('tech-news', $slug);
    }

    public function testCategorySlugIsUniqueOnConflict(): void
    {
        $this->insertRow('blog_categories', ['slug' => 'php']);

        $slug = BlogSlugService::categorySlug('PHP');
        $this->assertSame('php-1', $slug);
    }

    // -------------------------------------------------------------------------
    // tagSlug()
    // -------------------------------------------------------------------------

    public function testTagSlugBasic(): void
    {
        $slug = BlogSlugService::tagSlug('PHP Development');
        $this->assertSame('php-development', $slug);
    }

    public function testTagSlugIsUniqueOnConflict(): void
    {
        $this->insertRow('blog_tags', ['slug' => 'laravel']);

        $slug = BlogSlugService::tagSlug('Laravel');
        $this->assertSame('laravel-1', $slug);
    }
}
