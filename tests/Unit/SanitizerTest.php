<?php

namespace Tests\Unit;

use App\Security\Sanitizer;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    public function testCleanTrimsAndStripsTags(): void
    {
        $this->assertSame('hello', Sanitizer::clean('  <b>hello</b>  '));
        // strip_tags leaves inner text; only tags are removed
        $this->assertSame('alert(1)', Sanitizer::clean('<script>alert(1)</script>'));
        $this->assertSame('plain', Sanitizer::clean('plain'));
        $this->assertSame('mixed text', Sanitizer::clean('<div>mixed <span>text</span></div>'));
    }

    public function testEmailReturnsValidAddress(): void
    {
        $this->assertSame('mario+tag@example.com', Sanitizer::email('mario+tag@example.com'));
        $this->assertSame('user@example.it', Sanitizer::email('  user@example.it  '));
    }

    public function testEmailReturnsEmptyForInvalid(): void
    {
        $this->assertSame('', Sanitizer::email('not-an-email'));
        $this->assertSame('', Sanitizer::email(''));
        $this->assertSame('', Sanitizer::email('foo@'));
    }

    public function testIntCastsVariousInputs(): void
    {
        $this->assertSame(42, Sanitizer::int('42'));
        $this->assertSame(10, Sanitizer::int('10abc'));
        $this->assertSame(0, Sanitizer::int('abc'));
        $this->assertSame(7, Sanitizer::int(7.9));
    }

    public function testHtmlEscapesSpecials(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', Sanitizer::html('<b>x</b>'));
        $this->assertSame('a &amp; b', Sanitizer::html('a & b'));
        $this->assertSame('&quot;x&quot;', Sanitizer::html('"x"'));
    }

    public function testColorAcceptsValidHex(): void
    {
        $this->assertSame('#ff00aa', Sanitizer::color('#ff00aa'));
        $this->assertSame('#ABCDEF', Sanitizer::color('  #ABCDEF  '));
    }

    public function testColorReturnsDefaultForInvalid(): void
    {
        $this->assertSame('#3b82f6', Sanitizer::color('red'));
        $this->assertSame('#3b82f6', Sanitizer::color('#fff'));
        $this->assertSame('#000000', Sanitizer::color('not-a-color', '#000000'));
    }

    public function testSanitizeHtmlReturnsEmptyForEmpty(): void
    {
        $this->assertSame('', Sanitizer::sanitizeHtml(''));
        $this->assertSame('', Sanitizer::sanitizeHtml('   '));
    }

    public function testSanitizeHtmlStripsScript(): void
    {
        $html = '<p>hi</p><script>alert(1)</script>';
        $out = Sanitizer::sanitizeHtml($html);
        $this->assertStringContainsString('<p>hi</p>', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert', $out);
    }

    public function testSanitizeHtmlStripsIframeAndStyle(): void
    {
        $html = '<div>ok</div><iframe src="x"></iframe><style>body{}</style>';
        $out = Sanitizer::sanitizeHtml($html);
        $this->assertStringContainsString('ok', $out);
        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringNotContainsString('<style', $out);
    }

    public function testSanitizeHtmlRemovesEventHandlers(): void
    {
        $html = '<a href="/ok" onclick="bad()">click</a>';
        $out = Sanitizer::sanitizeHtml($html);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('href="/ok"', $out);
    }

    public function testSanitizeHtmlStripsJavascriptHref(): void
    {
        $html = '<a href="javascript:alert(1)">x</a>';
        $out = Sanitizer::sanitizeHtml($html);
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function testSanitizeHtmlPreservesFormatting(): void
    {
        $html = '<p><strong>bold</strong> <em>italic</em></p>';
        $out = Sanitizer::sanitizeHtml($html);
        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringContainsString('<em>italic</em>', $out);
    }
}
