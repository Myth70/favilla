<?php

declare(strict_types=1);

namespace App\Modules\Blog\Tests\Unit;

use App\Modules\Blog\Services\BlogContentSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Il contenuto articolo è HTML autorato (Quill) reso senza ulteriore escaping
 * in Views/public/show.php: è un vettore di stored-XSS. Questi test fissano
 * il contratto di sicurezza (rimozione vettori) e di compatibilità
 * (preservazione di formattazione e classi Quill) del sanitizer.
 */
class BlogContentSanitizerTest extends TestCase
{
    public function testRemovesScriptTags(): void
    {
        $out = BlogContentSanitizer::sanitize('<p>ciao</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>ciao</p>', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    public function testStripsEventHandlerAttributes(): void
    {
        $out = BlogContentSanitizer::sanitize('<div onclick="steal()" onmouseover="x()">testo</div>');

        $this->assertStringContainsString('testo', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onmouseover', $out);
    }

    public function testBlocksJavascriptProtocolInHref(): void
    {
        $out = BlogContentSanitizer::sanitize('<a href="javascript:alert(1)">link</a>');

        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function testNeutralizesDataTextHtmlVector(): void
    {
        $out = BlogContentSanitizer::sanitize(
            '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">'
        );

        $this->assertStringNotContainsString('text/html', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    public function testStripsDisallowedTagsButUnwrapsContent(): void
    {
        $out = BlogContentSanitizer::sanitize('<iframe src="https://evil.example">x</iframe>');

        $this->assertStringNotContainsString('<iframe', $out);
    }

    public function testAddsNoopenerNoreferrerOnBlankTargetLinks(): void
    {
        $out = BlogContentSanitizer::sanitize('<a href="https://example.com" target="_blank">link</a>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function testPreservesQuillFormattingAndClasses(): void
    {
        $html = '<p class="ql-align-center">testo <strong>forte</strong></p>'
            . '<ul><li class="ql-indent-1">voce</li></ul>';
        $out = BlogContentSanitizer::sanitize($html);

        $this->assertStringContainsString('ql-align-center', $out);
        $this->assertStringContainsString('<strong>forte</strong>', $out);
        $this->assertStringContainsString('ql-indent-1', $out);
    }

    public function testPreservesImageAttributes(): void
    {
        $out = BlogContentSanitizer::sanitize('<img src="https://example.com/x.png" alt="foto" width="100" height="50">');

        $this->assertStringContainsString('src="https://example.com/x.png"', $out);
        $this->assertStringContainsString('alt="foto"', $out);
    }

    public function testEmptyStringReturnsEmptyString(): void
    {
        $this->assertSame('', BlogContentSanitizer::sanitize(''));
        $this->assertSame('', BlogContentSanitizer::sanitize('   '));
    }
}
