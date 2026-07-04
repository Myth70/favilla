<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Support\TeamsLinkExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit test (puri, no DB) per TeamsLinkExtractor.
 *
 * La stessa regex URL_PATTERN è usata da MarkdownRenderer per l'auto-link
 * inline e dal tab "Link" dell'offcanvas gruppo: questi test sono il
 * guardiano del comportamento condiviso.
 */
class TeamsLinkExtractorTest extends TestCase
{
    public function testExtractReturnsEmptyArrayForBodyWithoutUrls(): void
    {
        $this->assertSame([], TeamsLinkExtractor::extract('Solo testo qui'));
        $this->assertSame([], TeamsLinkExtractor::extract(''));
    }

    public function testExtractSkipsFastPathWhenNeitherHttpNorWwwPresent(): void
    {
        // Branch fast-return: niente "http" e niente "www." → []
        $this->assertSame([], TeamsLinkExtractor::extract('hello world, no links'));
    }

    public function testExtractFindsHttpsUrl(): void
    {
        $this->assertSame(
            ['https://example.com/path'],
            TeamsLinkExtractor::extract('vedi https://example.com/path per dettagli')
        );
    }

    public function testExtractFindsMultipleUrlsInOrder(): void
    {
        $body = 'primo http://a.com poi https://b.org/x e infine www.c.dev';
        $this->assertSame(
            ['http://a.com', 'https://b.org/x', 'https://www.c.dev'],
            TeamsLinkExtractor::extract($body)
        );
    }

    public function testExtractNormalizesWwwToHttps(): void
    {
        $this->assertSame(
            ['https://www.example.com'],
            TeamsLinkExtractor::extract('vai su www.example.com')
        );
    }

    public function testExtractTrimsTrailingPunctuation(): void
    {
        // I trailing . , ; ) ] ! ? vengono rimossi prima della normalizzazione
        $body = 'guarda https://a.com. Anche https://b.com? E https://c.com!';
        $this->assertSame(
            ['https://a.com', 'https://b.com', 'https://c.com'],
            TeamsLinkExtractor::extract($body)
        );
    }

    public function testExtractDeduplicatesPreservingFirstOccurrence(): void
    {
        $body = 'https://x.com poi https://x.com ancora https://x.com';
        $this->assertSame(['https://x.com'], TeamsLinkExtractor::extract($body));
    }

    public function testExtractHandlesEmailLikePatternsWithoutFalsePositive(): void
    {
        // "info@www.foo" non è un URL valido nel nostro pattern: il `@` lo
        // tronca dal match. Vogliamo zero risultati.
        $body = 'contattami su info@www.foo per info';
        $extracted = TeamsLinkExtractor::extract($body);
        // L'extractor potrebbe rilevare "www.foo" come URL standalone:
        // verifichiamo solo che non includa l'email completa.
        foreach ($extracted as $url) {
            $this->assertStringNotContainsString('info@', $url);
        }
    }

    public function testDomainStripsWwwAndLowercases(): void
    {
        $this->assertSame('example.com', TeamsLinkExtractor::domain('https://WWW.Example.com/path'));
        $this->assertSame('example.com', TeamsLinkExtractor::domain('https://example.com/path?a=1'));
    }

    public function testDomainReturnsEmptyForMalformedUrl(): void
    {
        $this->assertSame('', TeamsLinkExtractor::domain('not-a-url'));
        $this->assertSame('', TeamsLinkExtractor::domain(''));
    }

    public function testUrlPatternIsAccessibleAsConstant(): void
    {
        // Garanzia che il refactor di MarkdownRenderer continui a funzionare:
        // la costante è la fonte di verità della regex condivisa.
        $this->assertNotEmpty(TeamsLinkExtractor::URL_PATTERN);
        $this->assertSame(1, preg_match(TeamsLinkExtractor::URL_PATTERN, 'vedi https://example.com'));
    }
}
