<?php

namespace Tests\Unit;

use App\Services\LocaleResolver;
use App\Services\Translator;
use App\Support\ConfigCache;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the locale resolution priority:
 *   query > session > cookie > user pref > Accept-Language > default.
 * The user-pref (DB) step is skipped here (no PDO bound) and degrades to null.
 */
class LocaleResolverTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_GET = [];
        $_COOKIE = [];
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);

        // Pin localization config so the resolver/translator are deterministic.
        ConfigCache::$data['localization'] = [
            'default'     => 'it',
            'fallback'    => 'it',
            'supported'   => ['it', 'en', 'fr', 'de', 'es'],
            'query_param' => 'lang',
            'cookie_name' => 'favilla_lang',
            'cookie_days' => 365,
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_COOKIE = [];
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        ConfigCache::$data = [];
    }

    private function resolver(): array
    {
        $translator = new Translator(ConfigCache::$data['localization']);
        return [new LocaleResolver($translator), $translator];
    }

    public function testDefaultWhenNothingSet(): void
    {
        [$resolver] = $this->resolver();
        $this->assertSame('it', $resolver->resolve());
    }

    public function testSessionWins(): void
    {
        $_SESSION['user_language'] = 'de';
        [$resolver, $translator] = $this->resolver();
        $this->assertSame('de', $resolver->resolve());
        $this->assertSame('de', $translator->getLocale());
    }

    public function testCookieUsedWhenNoSession(): void
    {
        $_COOKIE['favilla_lang'] = 'fr';
        [$resolver] = $this->resolver();
        $this->assertSame('fr', $resolver->resolve());
        $this->assertSame('fr', $_SESSION['user_language']);
    }

    public function testAcceptLanguageHeaderParsed(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9,en;q=0.8';
        [$resolver] = $this->resolver();
        $this->assertSame('es', $resolver->resolve());
    }

    public function testAcceptLanguageFallsThroughUnsupported(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'zh-CN,ja;q=0.8';
        [$resolver] = $this->resolver();
        $this->assertSame('it', $resolver->resolve());
    }

    public function testSessionBeatsCookieAndHeader(): void
    {
        $_SESSION['user_language'] = 'en';
        $_COOKIE['favilla_lang'] = 'fr';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
        [$resolver] = $this->resolver();
        $this->assertSame('en', $resolver->resolve());
    }

    public function testApplyWithoutPersistSetsSessionAndTranslator(): void
    {
        [$resolver, $translator] = $this->resolver();
        $applied = $resolver->apply('fr', false);
        $this->assertSame('fr', $applied);
        $this->assertSame('fr', $translator->getLocale());
        $this->assertSame('fr', $_SESSION['user_language']);
    }

    public function testApplyUnsupportedKeepsCurrent(): void
    {
        [$resolver, $translator] = $this->resolver();
        $applied = $resolver->apply('zz', false);
        $this->assertSame('it', $applied);
        $this->assertSame('it', $translator->getLocale());
    }
}
