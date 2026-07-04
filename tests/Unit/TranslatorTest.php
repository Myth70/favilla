<?php

namespace Tests\Unit;

use App\Services\Translator;
use App\Support\LangCache;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the translation engine against the real resources/lang files.
 * Assertions favour mechanics + stable single words over brittle full strings.
 */
class TranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        lang_flush();
    }

    protected function tearDown(): void
    {
        lang_flush();
    }

    private function translator(): Translator
    {
        return new Translator([
            'default'   => 'it',
            'fallback'  => 'it',
            'supported' => ['it', 'en', 'fr', 'de', 'es'],
        ]);
    }

    public function testDefaultLocaleIsItalian(): void
    {
        $this->assertSame('it', $this->translator()->getLocale());
    }

    public function testSetLocaleAcceptsSupportedAndRejectsOthers(): void
    {
        $t = $this->translator();
        $t->setLocale('en');
        $this->assertSame('en', $t->getLocale());

        $t->setLocale('zz'); // unsupported -> kept
        $this->assertSame('en', $t->getLocale());
    }

    public function testGetTranslatesPerLocale(): void
    {
        $t = $this->translator();
        $this->assertSame('Salva', $t->get('common.action.save', [], 'it'));
        $this->assertSame('Save', $t->get('common.action.save', [], 'en'));
        $this->assertSame('Enregistrer', $t->get('common.action.save', [], 'fr'));
    }

    public function testMissingKeyReturnsKey(): void
    {
        $this->assertSame('common.nope.missing', $this->translator()->get('common.nope.missing'));
    }

    public function testPlaceholderInterpolation(): void
    {
        $out = $this->translator()->get('validation.required', ['field' => 'Email'], 'en');
        $this->assertStringContainsString('Email', $out);
        $this->assertStringNotContainsString(':field', $out);
    }

    public function testFallsBackToDefaultLocaleForUnsupportedLocale(): void
    {
        // 'xx' is not supported: lookup misses then falls back to 'it'.
        $this->assertSame('Salva', $this->translator()->get('common.action.save', [], 'xx'));
    }

    public function testCanonicalNormalizesAndWhitelists(): void
    {
        $t = $this->translator();
        $this->assertSame('en', $t->canonical('en_GB'));
        $this->assertSame('de', $t->canonical('DE'));
        $this->assertNull($t->canonical('zz'));
    }

    public function testLineFlatLookupWithDefault(): void
    {
        $t = $this->translator();
        $this->assertSame('View users', $t->line('permissions', 'admin.users.view', 'FB', 'en'));
        $this->assertNotSame(
            $t->line('permissions', 'admin.users.view', 'FB', 'en'),
            $t->line('permissions', 'admin.users.view', 'FB', 'de')
        );
        $this->assertSame('FALLBACK', $t->line('permissions', 'does.not.exist', 'FALLBACK', 'en'));
    }

    public function testGetArrayReturnsDatetimeArrays(): void
    {
        $months = $this->translator()->getArray('datetime.months_long', 'en');
        $this->assertCount(12, $months);
        $this->assertSame('January', $months[0]);
    }

    public function testMissingKeysAreRecorded(): void
    {
        $this->translator()->get('common.totally.absent', [], 'en');
        $this->assertArrayHasKey('en:common.totally.absent', LangCache::$missing);
    }

    public function testHelperTUsesActiveLocale(): void
    {
        set_locale('es');
        $this->assertSame('Guardar', t('common.action.save'));
        $this->assertSame('es', locale());
        set_locale('it'); // restore
    }
}
