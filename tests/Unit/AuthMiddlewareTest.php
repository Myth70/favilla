<?php

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthMiddleware.
 *
 * NOTE: Guards 1, 2, 3, 4 all terminate with exit() when they fire.
 * PHP's exit() cannot be reliably intercepted in unit tests; those guards
 * are covered by integration/browser tests instead.
 *
 * Here we test only the in-process pass-through path (all guards pass).
 */
class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        // Register routes required by AuthMiddleware (route('login') is called at the top)
        $router = new Router();
        $router->get('/login', ['AuthController',     'login'])->name('login');
        $router->get('/change-password', ['PasswordController', 'change'])->name('password.change');
        Container::getInstance()->bind(Router::class, fn () => $router);

        $this->middleware = new AuthMiddleware();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    /**
     * All guards pass → $next is called and _last_activity is updated.
     */
    public function testAuthenticatedUserCallsNextAndUpdatesActivity(): void
    {
        $oldTime = time() - 10;

        $_SESSION['user_id']        = 1;
        $_SESSION['user_name']      = 'Test';
        $_SESSION['_last_activity'] = $oldTime;
        // No _db_session_id  → Guard 3 skipped
        // No must_change_password → Guard 4 skipped

        $nextCalled = false;
        $this->middleware->handle(function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled, 'Authenticated user without flags must reach $next');
        $this->assertGreaterThan(
            $oldTime,
            $_SESSION['_last_activity'],
            '_last_activity must be refreshed on each authenticated request'
        );
    }

    /**
     * Guard 4: must_change_password = false on the change-password page itself → $next called.
     * (The middleware should NOT redirect when already on the change-password route.)
     */
    public function testChangePasswordPageIsAllowedWhenFlagSet(): void
    {
        $_SESSION['user_id']              = 1;
        $_SESSION['user_name']            = 'Test';
        $_SESSION['_last_activity']       = time();
        $_SESSION['must_change_password'] = true;

        // Simulate request to the change-password URI itself
        $router = Container::getInstance()->make(Router::class);
        $changeUrl = $router->url('password.change');
        $_SERVER['REQUEST_URI'] = parse_url($changeUrl, PHP_URL_PATH);

        $nextCalled = false;
        $this->middleware->handle(function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue(
            $nextCalled,
            'Guard 4: user on the change-password page must still reach $next'
        );
    }

    /**
     * Periodic DB sync: _last_db_sync is updated after 5+ minutes.
     * This tests the sync logic without a real DB (no _db_session_id set,
     * so Guard 3 is bypassed but the sync code path is skipped too).
     */
    public function testLastActivityTimestampIsAlwaysRefreshed(): void
    {
        $before = time() - 5;

        $_SESSION['user_id']        = 42;
        $_SESSION['_last_activity'] = $before;

        $this->middleware->handle(fn () => null);

        $this->assertGreaterThanOrEqual(
            $before + 1,
            $_SESSION['_last_activity'],
            '_last_activity should be set to at least time() during handle()'
        );
    }
}
