<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Support\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class MarkdownRendererTest extends TestCase
{
    public function testEmptyInputReturnsEmpty(): void
    {
        $this->assertSame('', MarkdownRenderer::render(''));
        $this->assertSame('', MarkdownRenderer::render('   '));
    }

    public function testPlainTextIsEscaped(): void
    {
        $out = MarkdownRenderer::render('Hello & <world>');
        $this->assertStringContainsString('Hello &amp; &lt;world&gt;', $out);
        $this->assertStringNotContainsString('<world>', $out);
    }

    public function testScriptTagIsNeutralised(): void
    {
        $out = MarkdownRenderer::render('<script>alert(1)</script>');
        // Nessun tag <script> reale (è escapato in entities)
        $this->assertStringNotContainsString('<script', $out);
        // L'HTML escapato resta visibile come testo innocuo
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&lt;/script&gt;', $out);
    }

    public function testBoldAndItalic(): void
    {
        $out = MarkdownRenderer::render('**grassetto** e *corsivo*');
        $this->assertStringContainsString('<strong>grassetto</strong>', $out);
        $this->assertStringContainsString('<em>corsivo</em>', $out);
    }

    public function testInlineCodeIsEscapedInsideCode(): void
    {
        $out = MarkdownRenderer::render('usa `<b>tag</b>` qui');
        $this->assertStringContainsString('<code class="tm-md-inlinecode">&lt;b&gt;tag&lt;/b&gt;</code>', $out);
    }

    public function testCodeBlockIsEscapedInsidePre(): void
    {
        $src = "```\n<script>boom</script>\n```";
        $out = MarkdownRenderer::render($src);
        $this->assertStringContainsString('<pre class="tm-md-codeblock">', $out);
        $this->assertStringContainsString('&lt;script&gt;boom&lt;/script&gt;', $out);
        $this->assertStringNotContainsString('<script>', $out);
    }

    public function testCodeBlockWithLanguageHint(): void
    {
        $out = MarkdownRenderer::render("```php\necho 1;\n```");
        $this->assertStringContainsString('tm-md-lang-php', $out);
        $this->assertStringContainsString('echo 1;', $out);
    }

    public function testBlockquote(): void
    {
        $out = MarkdownRenderer::render('> citazione');
        $this->assertStringContainsString('<blockquote class="tm-md-quote">citazione</blockquote>', $out);
    }

    public function testHttpUrlIsLinkified(): void
    {
        $out = MarkdownRenderer::render('vai su https://example.com adesso');
        $this->assertMatchesRegularExpression(
            '#<a href="https://example\.com"[^>]*target="_blank"[^>]*rel="noopener nofollow">https://example\.com</a>#',
            $out
        );
    }

    public function testWwwUrlIsPromotedToHttps(): void
    {
        $out = MarkdownRenderer::render('visita www.example.com');
        $this->assertStringContainsString('href="https://www.example.com"', $out);
        $this->assertStringContainsString('>www.example.com</a>', $out);
    }

    public function testJavascriptPseudoSchemeIsNotLinkified(): void
    {
        $out = MarkdownRenderer::render('payload javascript:alert(1)');
        $this->assertStringNotContainsString('href="javascript:', $out);
        $this->assertStringNotContainsString('<a ', $out);
    }

    public function testTrailingPunctuationStaysOutsideLink(): void
    {
        $out = MarkdownRenderer::render('vedi https://example.com.');
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('>https://example.com</a>.', $out);
    }

    public function testMentionIsWrappedInSpan(): void
    {
        $out = MarkdownRenderer::render('ciao @mario rossi');
        $this->assertStringContainsString('<span class="tm-mention">@mario</span>', $out);
    }

    public function testEmailAddressIsNotMistakenForMention(): void
    {
        $out = MarkdownRenderer::render('contatta info@example.com');
        // L'email non deve essere wrappata come mention (lookbehind esclude `@` doppio)
        $this->assertStringNotContainsString('<span class="tm-mention">@example', $out);
    }

    public function testSingleEmojiGetsBigEmojiWrapper(): void
    {
        $out = MarkdownRenderer::render('🎉');
        $this->assertStringContainsString('class="tm-big-emoji"', $out);
        $this->assertStringContainsString('🎉', $out);
    }

    public function testEmojiInsideTextIsNotBigEmoji(): void
    {
        $out = MarkdownRenderer::render('ottimo 🎉 lavoro');
        $this->assertStringNotContainsString('tm-big-emoji', $out);
    }

    public function testNewlinesArePreservedAsBr(): void
    {
        $out = MarkdownRenderer::render("riga 1\nriga 2");
        $this->assertStringContainsString('<br', $out);
    }

    public function testFormattingInsideCodeBlockIsNotApplied(): void
    {
        $out = MarkdownRenderer::render("```\n**non bold** *non italic*\n```");
        $this->assertStringNotContainsString('<strong>', $out);
        $this->assertStringNotContainsString('<em>', $out);
        $this->assertStringContainsString('**non bold**', $out);
    }

    public function testImgTagIsNotRenderedAsActualHtml(): void
    {
        // L'input HTML viene escapato come testo: niente <img> eseguibile.
        $out = MarkdownRenderer::render('<img src=x onerror=alert(1)>');
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('&lt;img', $out);
    }
}
