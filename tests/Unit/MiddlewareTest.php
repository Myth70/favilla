<?php

namespace Tests\Unit;

use App\Exceptions\HttpException;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Security\CsrfToken;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * In-process middleware tests. Both the pass-through paths and the denial paths
 * are covered: denial no longer calls exit() but throws HttpException, so the
 * security invariants (CSRF rejection, permission enforcement) are now asserted
 * directly here.
 */
class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_ENV['APP_KEY'] = 'test-key-middleware-32bytes-xxxxxxxxx';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['REQUEST_METHOD']);
    }

    // --- CsrfMiddleware ---

    public function testCsrfSkipsVerificationForGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $called = false;
        (new CsrfMiddleware())->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testCsrfAcceptsValidTokenFromPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_token'] = CsrfToken::generate();

        $called = false;
        (new CsrfMiddleware())->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testCsrfAcceptsValidTokenFromHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = CsrfToken::generate();

        $called = false;
        (new CsrfMiddleware())->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testCsrfPostBodyTakesPrecedenceOverHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $valid = CsrfToken::generate();
        $_POST['_token'] = $valid;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'bogus-header-value';

        $called = false;
        (new CsrfMiddleware())->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testCsrfRejectsMissingTokenOnPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // No _token in $_POST and no X-CSRF-Token header → must be rejected.

        $called = false;
        try {
            (new CsrfMiddleware())->handle(function () use (&$called) {
                $called = true;
            });
            $this->fail('CsrfMiddleware must reject a state-changing request without a token');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertFalse($called, 'The controller action must not run when CSRF fails');
    }

    public function testCsrfRejectsInvalidTokenOnDelete(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_POST['_token'] = 'totally-wrong-token';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        (new CsrfMiddleware())->handle(fn () => null);
    }

    // --- RoleMiddleware ---

    public function testRoleEmptyPermissionPassesThrough(): void
    {
        $called = false;
        (new RoleMiddleware(''))->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testRoleAdminBypassesCheck(): void
    {
        $_SESSION['user_roles'] = ['admin'];
        $_SESSION['user_permissions'] = []; // No permissions, but admin bypasses

        $called = false;
        (new RoleMiddleware('anything.here'))->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testRoleGrantsAccessWhenPermissionPresent(): void
    {
        $_SESSION['user_roles'] = ['editor'];
        $_SESSION['user_permissions'] = ['files.view', 'files.edit'];

        $called = false;
        (new RoleMiddleware('files.edit'))->handle(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function testRoleDeniesWhenPermissionMissing(): void
    {
        $_SESSION['user_roles'] = ['editor'];
        $_SESSION['user_permissions'] = ['files.view']; // lacks files.edit

        $called = false;
        try {
            (new RoleMiddleware('files.edit'))->handle(function () use (&$called) {
                $called = true;
            });
            $this->fail('RoleMiddleware must deny access when the permission is absent');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertFalse($called, 'The controller action must not run when authorization fails');
    }

    public function testRoleWithPermissionFactory(): void
    {
        $m = RoleMiddleware::withPermission('items.manage');
        $this->assertInstanceOf(RoleMiddleware::class, $m);

        $ref = new ReflectionClass($m);
        $prop = $ref->getProperty('permission');
        $prop->setAccessible(true);
        $this->assertSame('items.manage', $prop->getValue($m));
    }

    // --- RateLimitMiddleware factories ---

    public function testRateLimitPerMinuteFactory(): void
    {
        $m = RateLimitMiddleware::perMinute(30);
        $this->assertRateLimitConfig($m, 30, 60);
    }

    public function testRateLimitPerHourFactory(): void
    {
        $m = RateLimitMiddleware::perHour(100);
        $this->assertRateLimitConfig($m, 100, 3600);
    }

    public function testRateLimitMakeFactory(): void
    {
        $m = RateLimitMiddleware::make(5, 10);
        $this->assertRateLimitConfig($m, 5, 10);
    }

    private function assertRateLimitConfig(RateLimitMiddleware $m, int $max, int $window): void
    {
        $ref = new ReflectionClass($m);

        $maxProp = $ref->getProperty('maxRequests');
        $maxProp->setAccessible(true);
        $windowProp = $ref->getProperty('windowSeconds');
        $windowProp->setAccessible(true);

        $this->assertSame($max, $maxProp->getValue($m));
        $this->assertSame($window, $windowProp->getValue($m));
    }
}
