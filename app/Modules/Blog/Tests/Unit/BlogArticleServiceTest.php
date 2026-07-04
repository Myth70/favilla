<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Services\BlogArticleService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BlogArticleService.
 * Tests pure logic methods that don't require database access.
 */
class BlogArticleServiceTest extends TestCase
{
    private BlogArticleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // We test only pure methods — no DB needed
        $this->service = new class () extends BlogArticleService {
            public function __construct()
            {
                // Skip parent constructor (needs DI container)
            }
        };
    }

    // ── calculateReadingTime ────────────────────────────────────

    public function testReadingTimeMinimumOneMinute(): void
    {
        $this->assertEquals(1, $this->service->calculateReadingTime('Ciao mondo'));
    }

    public function testReadingTimeEmptyContent(): void
    {
        $this->assertEquals(1, $this->service->calculateReadingTime(''));
    }

    public function testReadingTimeShortArticle(): void
    {
        // 100 words → ceil(100/200) = 1 min
        $words = implode(' ', array_fill(0, 100, 'parola'));
        $this->assertEquals(1, $this->service->calculateReadingTime($words));
    }

    public function testReadingTimeMediumArticle(): void
    {
        // 400 words → ceil(400/200) = 2 min
        $words = implode(' ', array_fill(0, 400, 'parola'));
        $this->assertEquals(2, $this->service->calculateReadingTime($words));
    }

    public function testReadingTimeLongArticle(): void
    {
        // 1000 words → ceil(1000/200) = 5 min
        $words = implode(' ', array_fill(0, 1000, 'parola'));
        $this->assertEquals(5, $this->service->calculateReadingTime($words));
    }

    public function testReadingTimeStripsHtmlTags(): void
    {
        $html = '<p>Parola</p> <strong>altra</strong> <a href="#">link</a>';
        // 3 words → 1 min
        $this->assertEquals(1, $this->service->calculateReadingTime($html));
    }

    public function testReadingTimeCountsAccentedItalianWords(): void
    {
        // 400 parole italiane con accentate: str_word_count le ignorava,
        // ora vengono contate via \p{L} → ceil(400/200) = 2 min.
        $words = implode(' ', array_fill(0, 400, 'università'));
        $this->assertEquals(2, $this->service->calculateReadingTime($words));
    }

    public function testReadingTimeCountsMixedScriptAndNumbers(): void
    {
        // Parole alfanumeriche e tag intercalati.
        $html = '<p>PHP 8.2 introduce nuove funzionalità per gli sviluppatori</p>';
        // Conteggio: PHP, 8, 2, introduce, nuove, funzionalità, per, gli, sviluppatori = 9 → 1 min
        $this->assertEquals(1, $this->service->calculateReadingTime($html));
    }

    // ── generateExcerpt ─────────────────────────────────────────

    public function testGenerateExcerptStripsTagsAndTruncates(): void
    {
        $html = '<p>' . str_repeat('parola ', 100) . '</p>';
        $excerpt = $this->service->generateExcerpt($html, 80);
        $this->assertStringEndsWith('...', $excerpt);
        $this->assertLessThanOrEqual(83, mb_strlen($excerpt));
        $this->assertStringNotContainsString('<', $excerpt);
    }

    public function testGenerateExcerptShortContentReturnedAsIs(): void
    {
        $excerpt = $this->service->generateExcerpt('Breve testo', 100);
        $this->assertEquals('Breve testo', $excerpt);
    }

    // ── canEditArticle ──────────────────────────────────────────

    public function testCanEditArticleOwner(): void
    {
        $article = ['created_by' => 5];
        $this->assertTrue($this->service->canEditArticle($article, 5));
    }

    public function testCanEditArticleNotOwner(): void
    {
        $article = ['created_by' => 5];
        // Without admin permission, non-owner cannot edit
        // has_permission returns false in test context (no session)
        $this->assertFalse($this->service->canEditArticle($article, 99));
    }

    // ── buildVisibility ─────────────────────────────────────────

    public function testBuildVisibilityAll(): void
    {
        $this->assertEquals('all', BlogArticleService::buildVisibility(['visibility_type' => 'all']));
    }

    public function testBuildVisibilityEmptyPost(): void
    {
        $this->assertEquals('all', BlogArticleService::buildVisibility([]));
    }

    public function testBuildVisibilityRolesWithSelection(): void
    {
        $result = BlogArticleService::buildVisibility([
            'visibility_type'  => 'roles',
            'visibility_roles' => ['admin', 'manager'],
        ]);
        $this->assertEquals('admin,manager', $result);
    }

    public function testBuildVisibilityRolesEmpty(): void
    {
        $result = BlogArticleService::buildVisibility([
            'visibility_type'  => 'roles',
            'visibility_roles' => [],
        ]);
        $this->assertEquals('all', $result);
    }

    public function testBuildVisibilitySanitizesRoleSlugs(): void
    {
        $result = BlogArticleService::buildVisibility([
            'visibility_type'  => 'roles',
            'visibility_roles' => ['admin', 'MANAGER', 'user<script>'],
        ]);
        // Should be lowercased and cleaned
        $this->assertEquals('admin,manager,userscript', $result);
    }
}
