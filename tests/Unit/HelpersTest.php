<?php

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Router;
use App\Support\ConfigCache;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    private array $savedEnv;

    protected function setUp(): void
    {
        $this->savedEnv = $_ENV;
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        ConfigCache::$data = [];
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        $_SESSION = [];
        ConfigCache::$data = [];
    }

    public function testEscapesHtmlSpecialChars(): void
    {
        $this->assertSame('&lt;b&gt;', e('<b>'));
        $this->assertSame('a &amp; b', e('a & b'));
        $this->assertSame('&quot;x&quot;', e('"x"'));
    }

    public function testEscapesNullAsEmpty(): void
    {
        $this->assertSame('', e(null));
    }

    public function testEnvReturnsDefaultForMissingKey(): void
    {
        unset($_ENV['THIS_KEY_DOES_NOT_EXIST']);
        $this->assertSame('default', env('THIS_KEY_DOES_NOT_EXIST', 'default'));
        $this->assertNull(env('THIS_KEY_DOES_NOT_EXIST'));
    }

    public function testEnvCastsCommonStringsToNativeTypes(): void
    {
        $_ENV['FLAG_TRUE']  = 'true';
        $_ENV['FLAG_FALSE'] = 'false';
        $_ENV['FLAG_NULL']  = 'null';
        $_ENV['FLAG_EMPTY'] = 'empty';
        $_ENV['PLAIN']      = 'hello';

        $this->assertTrue(env('FLAG_TRUE'));
        $this->assertFalse(env('FLAG_FALSE'));
        $this->assertNull(env('FLAG_NULL'));
        $this->assertSame('', env('FLAG_EMPTY'));
        $this->assertSame('hello', env('PLAIN'));
    }

    public function testConfigReadsDotNotation(): void
    {
        ConfigCache::$data['foo'] = ['bar' => ['baz' => 'qux']];
        $this->assertSame('qux', config('foo.bar.baz'));
        $this->assertSame(['baz' => 'qux'], config('foo.bar'));
    }

    public function testConfigReturnsDefaultForMissingSegments(): void
    {
        ConfigCache::$data['foo'] = ['bar' => 'baz'];
        $this->assertSame('fallback', config('foo.missing', 'fallback'));
        $this->assertSame('fallback', config('foo.bar.missing', 'fallback'));
    }

    public function testAppReturnsContainerWhenNoArgument(): void
    {
        $this->assertInstanceOf(Container::class, app());
    }

    public function testAppResolvesBoundAbstract(): void
    {
        $c = Container::getInstance();
        $c->instance('my_key', 'my_value');
        $this->assertSame('my_value', app('my_key'));
    }

    public function testCsrfFieldProducesHiddenInput(): void
    {
        $_ENV['APP_KEY'] = 'test-key-for-helpers-32bytes-xxxxxxxx';
        $field = csrf_field();
        $this->assertStringStartsWith('<input type="hidden" name="_token" value="', $field);
        $this->assertStringEndsWith('">', $field);
    }

    public function testCsrfTokenIsStableForSameSession(): void
    {
        $_ENV['APP_KEY'] = 'test-key-for-helpers-32bytes-xxxxxxxx';
        $a = csrf_token();
        $b = csrf_token();
        $this->assertSame($a, $b);
        $this->assertNotEmpty($a);
    }

    public function testRouteGeneratesUrlFromName(): void
    {
        $router = new Router();
        $router->get('/users/{id}', ['X', 'y'])->name('users.show');
        $c = Container::getInstance();
        $c->instance(Router::class, $router);

        $this->assertStringEndsWith('/users/42', route('users.show', ['id' => 42]));
    }

    public function testAuthReturnsNullWhenNotLoggedIn(): void
    {
        unset($_SESSION['user_id']);
        $this->assertNull(auth());
    }

    public function testAuthReturnsArrayOfUserFields(): void
    {
        $_SESSION['user_id']          = 7;
        $_SESSION['user_name']        = 'Mario';
        $_SESSION['user_email']       = 'mario@example.com';
        $_SESSION['user_username']    = 'mrossi';
        $_SESSION['user_roles']       = ['admin'];
        $_SESSION['user_permissions'] = ['perm.a'];

        $u = auth();
        $this->assertSame(7, $u['id']);
        $this->assertSame('Mario', $u['name']);
        $this->assertSame('mario@example.com', $u['email']);
        $this->assertSame('mrossi', $u['username']);
        $this->assertSame(['admin'], $u['roles']);
        $this->assertSame(['perm.a'], $u['permissions']);
    }

    public function testHasPermissionAdminBypass(): void
    {
        $_SESSION['user_roles'] = ['admin'];
        $_SESSION['user_permissions'] = [];
        $this->assertTrue(has_permission('anything.here'));
    }

    public function testHasPermissionChecksList(): void
    {
        $_SESSION['user_roles'] = ['user'];
        $_SESSION['user_permissions'] = ['foo.view'];
        $this->assertTrue(has_permission('foo.view'));
        $this->assertFalse(has_permission('foo.edit'));
    }

    public function testHasPermissionFalseWhenNoSession(): void
    {
        unset($_SESSION['user_roles'], $_SESSION['user_permissions']);
        $this->assertFalse(has_permission('x'));
    }

    public function testFormatDateItReturnsEmptyForNull(): void
    {
        $this->assertSame('', format_date_it(null));
        $this->assertSame('', format_date_it(''));
        $this->assertSame('', format_date_it('not-a-date'));
    }

    public function testFormatDateItTimeMode(): void
    {
        $this->assertSame('14:05', format_date_it('2026-04-20 14:05:00', 'time'));
    }

    public function testFormatDateItShortMode(): void
    {
        $this->assertSame('20/04/2026', format_date_it('2026-04-20 14:05', 'short'));
    }

    public function testFormatDateItRelativeForToday(): void
    {
        $today = date('Y-m-d H:i:s');
        $this->assertSame('Oggi', format_date_it($today, 'relative'));
    }

    public function testFormatDateItRelativeForYesterday(): void
    {
        $ts = date('Y-m-d H:i:s', strtotime('yesterday') + 3600);
        $this->assertSame('Ieri', format_date_it($ts, 'relative'));
    }

    public function testFormatDateItLongMode(): void
    {
        $out = format_date_it('2026-04-20 14:05', 'long');
        // "Lunedì 20 aprile 2026 14:05" -- April 20 2026 is Monday
        $this->assertStringContainsString('aprile', $out);
        $this->assertStringContainsString('2026', $out);
        $this->assertStringContainsString('14:05', $out);
    }

    public function testFormatDateItCompactToday(): void
    {
        $today = date('Y-m-d') . ' 09:30:00';
        $this->assertSame('09:30', format_date_it($today, 'compact'));
    }

    public function testFormatDateItCompactYesterday(): void
    {
        $y = date('Y-m-d H:i:s', strtotime('yesterday') + 3600);
        $this->assertSame('Ieri', format_date_it($y, 'compact'));
    }

    public function testCoverUrlReturnsNullForEmpty(): void
    {
        $this->assertNull(cover_url(null, 'avatars'));
        $this->assertNull(cover_url('', 'avatars'));
    }

    public function testSortLinkProducesAnchorWithExpectedAttributes(): void
    {
        $html = sort_link(
            'name',
            'Nome',
            'created_at',
            'DESC',
            ['q' => 'foo', 'empty' => ''],
            '/items',
            '#tbl'
        );
        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('hx-target="#tbl"', $html);
        $this->assertStringContainsString('sort=name', $html);
        $this->assertStringContainsString('dir=ASC', $html);
        $this->assertStringContainsString('q=foo', $html);
        $this->assertStringNotContainsString('empty=', $html);
        $this->assertStringContainsString('Nome', $html);
    }

    public function testSortLinkTogglesDirectionOnSameColumn(): void
    {
        $html = sort_link('name', 'Nome', 'name', 'ASC', [], '/items', '#t');
        $this->assertStringContainsString('dir=DESC', $html);
        // Shows current-sort icon
        $this->assertStringContainsString('fa-sort-up', $html);
    }

    public function testSortLinkShowsDownIconForDesc(): void
    {
        $html = sort_link('name', 'Nome', 'name', 'DESC', [], '/items', '#t');
        $this->assertStringContainsString('fa-sort-down', $html);
    }

    public function testSortContextProducesClosureEquivalentToSortLink(): void
    {
        $sh = sort_context('name', 'ASC', ['q' => 'x'], '/items', '#tbl');
        $closureOut = $sh('title', 'Titolo');
        $direct = sort_link('title', 'Titolo', 'name', 'ASC', ['q' => 'x'], '/items', '#tbl');
        $this->assertSame($direct, $closureOut);
    }

    public function testJsI18nDictKeysMatchClientSideJsPrefixedLookups(): void
    {
        // public/assets/js/*.js always call t('js.xxx', fallback) client-side against
        // window.__I18N. The dict built here must use the same 'js.'-prefixed keys,
        // otherwise every lookup silently falls back to the hardcoded JS string.
        $dict = js_i18n_dict();

        $this->assertArrayHasKey('js.scheduler.jobs_running', $dict);
        $this->assertSame(':count job in esecuzione', $dict['js.scheduler.jobs_running']);

        $this->assertArrayHasKey('js.common.close', $dict);
        $this->assertSame('Chiudi', $dict['js.common.close']);

        $this->assertArrayNotHasKey('scheduler.jobs_running', $dict);
    }
}
