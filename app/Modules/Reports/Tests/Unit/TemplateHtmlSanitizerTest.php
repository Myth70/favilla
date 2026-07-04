<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Services\TemplateHtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Il template_html dei report è HTML autorato che viene riaperto nell'anteprima
 * del designer da altri utenti: è un vettore di stored-XSS. Questi test fissano
 * il contratto di sicurezza (rimozione vettori) e di compatibilità (preservazione
 * di CSS inline e attributi Smart Component) del sanitizer.
 */
class TemplateHtmlSanitizerTest extends TestCase
{
    private TemplateHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new TemplateHtmlSanitizer();
    }

    public function testRemovesScriptTags(): void
    {
        $out = (string) $this->sanitizer->sanitize('<p>ciao</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>ciao</p>', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    public function testStripsEventHandlerAttributes(): void
    {
        $out = (string) $this->sanitizer->sanitize('<div onclick="steal()" onmouseover="x()">testo</div>');

        $this->assertStringContainsString('testo', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onmouseover', $out);
    }

    public function testBlocksJavascriptProtocolInHref(): void
    {
        $out = (string) $this->sanitizer->sanitize('<a href="javascript:alert(1)">link</a>');

        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function testNeutralizesDataTextHtmlVector(): void
    {
        // HTMLPurifier consente data: solo per immagini: un data:text/html con
        // payload non deve sopravvivere come sorgente eseguibile.
        $out = (string) $this->sanitizer->sanitize(
            '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">'
        );

        $this->assertStringNotContainsString('text/html', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    public function testPreservesSmartComponentAttributes(): void
    {
        $html = '<div data-prm-type="ContactsList" data-prm-config="{&quot;limit&quot;:5}">x</div>';
        $out = (string) $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('data-prm-type', $out);
        $this->assertStringContainsString('ContactsList', $out);
        $this->assertStringContainsString('data-prm-config', $out);
    }

    public function testPreservesInlineCss(): void
    {
        $out = (string) $this->sanitizer->sanitize('<div style="color: red; background: #fff;">box</div>');

        $this->assertStringContainsString('style=', $out);
        $this->assertStringContainsString('color', $out);
    }

    public function testPreservesHtml5BlockElements(): void
    {
        $out = (string) $this->sanitizer->sanitize('<section><header>titolo</header><footer>pie</footer></section>');

        $this->assertStringContainsString('<section', $out);
        $this->assertStringContainsString('<header', $out);
        $this->assertStringContainsString('<footer', $out);
    }

    public function testStyleBlocksAreSanitizedAndKept(): void
    {
        $out = (string) $this->sanitizer->sanitize('<style>.box { color: red; }</style><div class="box">x</div>');

        // Il blocco <style> ripulito viene reincorporato in testa.
        $this->assertStringContainsString('<style>', $out);
        $this->assertStringContainsString('.box', $out);
    }

    public function testNullPassesThroughUnchanged(): void
    {
        $this->assertNull($this->sanitizer->sanitize(null));
    }

    public function testEmptyStringPassesThroughUnchanged(): void
    {
        $this->assertSame('   ', $this->sanitizer->sanitize('   '));
    }
}
