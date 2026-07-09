<?php

namespace Tests\Unit;

use App\Core\Container;
use App\Core\ModuleLoader;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Modules\Api\Middleware\ApiTokenMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Authorization audit over the REAL route table.
 *
 * Loads every module's routes (plus the app-level routes) and asserts that each
 * state-changing route (POST/PUT/DELETE) is protected by an authentication
 * mechanism and CSRF — except for a small, explicitly documented allowlist of
 * public / pre-authentication endpoints.
 *
 * Two authentication mechanisms are accepted:
 *  - Session (cookie) auth: AuthMiddleware + CsrfMiddleware. CSRF is mandatory
 *    because the session rides on a cookie the browser attaches automatically.
 *  - Token (Bearer) auth: ApiTokenMiddleware, used by the stateless `api/v1`
 *    surface. These carry NO cookie, so they are structurally immune to CSRF and
 *    do not (must not) use CsrfMiddleware.
 *
 * This is a regression guard: adding a mutating route without any auth (and
 * without consciously documenting it here) will fail the build.
 */
class RouteAuthzAuditTest extends TestCase
{
    /**
     * Mutating routes intentionally reachable WITHOUT an authenticated session.
     * These are the login/registration/password-recovery flow plus the Telegram
     * webhook. All except the webhook still carry CSRF protection.
     */
    private const AUTH_EXEMPT = [
        'login.post',
        'logout',
        'registrazione.post',
        'password.forgot.post',
        'password.reset.post',
        'notifications.telegram.webhook',
    ];

    /**
     * Mutating routes intentionally WITHOUT CSRF. Only the Telegram webhook:
     * it is authenticated by an unguessable {secret} path segment and is called
     * by Telegram's servers, which cannot supply a CSRF token.
     */
    private const CSRF_EXEMPT = [
        'notifications.telegram.webhook',
    ];

    /** @var array<int,array<string,mixed>> */
    private array $routes;

    protected function setUp(): void
    {
        $container = new Container();
        Container::setInstance($container);

        $loader = new ModuleLoader(BASE_PATH);
        $loader->loadConfig();
        $container->instance(ModuleLoader::class, $loader);

        $router = new Router();
        $loader->loadRoutes($router);

        $appRoutes = BASE_PATH . '/app/Config/routes.php';
        if (file_exists($appRoutes)) {
            require $appRoutes;
        }

        $this->routes = $router->getRoutes();
    }

    public function testRouteTableIsNonTrivial(): void
    {
        // Guard against a silently-empty load masking the assertions below.
        $this->assertGreaterThan(200, count($this->routes), 'Expected the full route table to load');
    }

    public function testEveryMutatingRouteHasCsrf(): void
    {
        $offenders = [];
        foreach ($this->mutatingRoutes() as $route) {
            $name = $route['name'] ?? $route['uri'];
            if (in_array($name, self::CSRF_EXEMPT, true)) {
                continue;
            }
            // Token-authenticated (Bearer) routes carry no cookie → immune to
            // CSRF by construction, and must not use CsrfMiddleware.
            if ($this->hasMiddleware($route, ApiTokenMiddleware::class)) {
                continue;
            }
            if (!$this->hasMiddleware($route, CsrfMiddleware::class)) {
                $offenders[] = $route['method'] . ' ' . $route['uri'] . ' (' . $name . ')';
            }
        }

        $this->assertSame([], $offenders, "Mutating routes missing CSRF protection:\n" . implode("\n", $offenders));
    }

    public function testEveryMutatingRouteHasAuthExceptPublicAllowlist(): void
    {
        $offenders = [];
        foreach ($this->mutatingRoutes() as $route) {
            $name = $route['name'] ?? $route['uri'];
            if (in_array($name, self::AUTH_EXEMPT, true)) {
                continue;
            }
            // Either session auth (AuthMiddleware) or token auth (ApiTokenMiddleware)
            // satisfies the "must be authenticated" requirement.
            if ($this->hasMiddleware($route, AuthMiddleware::class)
                || $this->hasMiddleware($route, ApiTokenMiddleware::class)) {
                continue;
            }
            $offenders[] = $route['method'] . ' ' . $route['uri'] . ' (' . $name . ')';
        }

        $this->assertSame([], $offenders, "Mutating routes missing auth (and not documented as public):\n" . implode("\n", $offenders));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function mutatingRoutes(): array
    {
        return array_values(array_filter(
            $this->routes,
            fn (array $r): bool => in_array($r['method'], ['POST', 'PUT', 'DELETE'], true)
        ));
    }

    /**
     * @param array<string,mixed> $route
     */
    private function hasMiddleware(array $route, string $class): bool
    {
        foreach ($route['middleware'] ?? [] as $mw) {
            if (is_string($mw) && $mw === $class) {
                return true;
            }
            if (is_object($mw) && $mw instanceof $class) {
                return true;
            }
        }
        return false;
    }
}
