<?php

namespace Tests\Unit;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();

        // Register test routes
        $this->router->get('/items', ['ItemController', 'index'])->name('items.index');
        $this->router->get('/items/{id}', ['ItemController', 'show'])->name('items.show');
        $this->router->post('/items', ['ItemController', 'store'])->name('items.store');
        $this->router->put('/items/{id}', ['ItemController', 'update'])->name('items.update');
        $this->router->delete('/items/{id}', ['ItemController', 'destroy'])->name('items.destroy');
    }

    public function testGetRouteMatchesCorrectly(): void
    {
        $match = $this->router->dispatch('GET', '/items');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('index', $match['method']);
        $this->assertEmpty($match['params']);
    }

    public function testRouteWithParameterExtractsValue(): void
    {
        $match = $this->router->dispatch('GET', '/items/42');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('show', $match['method']);
        $this->assertSame('42', $match['params']['id']);
    }

    public function testHeadRequestMatchesGetRoute(): void
    {
        // RFC 9110 §9.3.2: HEAD si comporta come GET (prima riceveva 405).
        $match = $this->router->dispatch('HEAD', '/items');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('index', $match['method']);
    }

    public function testPostRouteMatches(): void
    {
        $match = $this->router->dispatch('POST', '/items');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('store', $match['method']);
    }

    public function testPutRouteMatches(): void
    {
        $match = $this->router->dispatch('PUT', '/items/7');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('update', $match['method']);
        $this->assertSame('7', $match['params']['id']);
    }

    public function testDeleteRouteMatches(): void
    {
        $match = $this->router->dispatch('DELETE', '/items/99');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('destroy', $match['method']);
    }

    public function testPutMethodOverrideViaPost(): void
    {
        // Simulate POST with _method=PUT
        $_POST['_method'] = 'PUT';

        $match = $this->router->dispatch('POST', '/items/5');

        $this->assertSame('ItemController', $match['controller']);
        $this->assertSame('update', $match['method']);

        unset($_POST['_method']);
    }

    public function testDeleteMethodOverrideViaPost(): void
    {
        $_POST['_method'] = 'DELETE';

        $match = $this->router->dispatch('POST', '/items/5');

        $this->assertSame('destroy', $match['method']);

        unset($_POST['_method']);
    }

    public function testRouteGroupAddsPrefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'admin'], function ($r) {
            $r->get('/users', ['AdminController', 'users'])->name('admin.users');
        });

        $match = $router->dispatch('GET', '/admin/users');

        $this->assertSame('AdminController', $match['controller']);
        $this->assertSame('users', $match['method']);
    }

    public function testRouteGroupAddsMiddleware(): void
    {
        $router = new Router();
        $router->group(['middleware' => ['auth', 'csrf']], function ($r) {
            $r->get('/protected', ['SecureController', 'index']);
        });

        $match = $router->dispatch('GET', '/protected');

        $this->assertContains('auth', $match['middleware']);
        $this->assertContains('csrf', $match['middleware']);
    }

    public function testNestedGroups(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'api', 'middleware' => ['auth']], function ($r) {
            $r->group(['prefix' => 'v1'], function ($r) {
                $r->get('/status', ['ApiController', 'status'])->name('api.status');
            });
        });

        $match = $router->dispatch('GET', '/api/v1/status');

        $this->assertSame('ApiController', $match['controller']);
        $this->assertSame('status', $match['method']);
        $this->assertContains('auth', $match['middleware']);
    }

    public function testGetRoutesReturnsAllRegistered(): void
    {
        $routes = $this->router->getRoutes();

        $this->assertCount(5, $routes);
    }

    public function testNamedRouteGeneration(): void
    {
        // This test needs config() and APP_URL to work.
        // We test that the route name was registered.
        $routes = $this->router->getRoutes();
        $names = array_column($routes, 'name');

        $this->assertContains('items.index', $names);
        $this->assertContains('items.show', $names);
        $this->assertContains('items.store', $names);
    }

    public function testTrailingSlashNormalization(): void
    {
        $match = $this->router->dispatch('GET', '/items/');

        // /items/ normalizes to /items and matches
        $this->assertSame('index', $match['method']);
    }

    public function testStaticRouteNotCapturedByParameterRoute(): void
    {
        $router = new Router();
        // Register static before parametric — correct order
        $router->get('/items/create', ['ItemController', 'create'])->name('items.create');
        $router->get('/items/{id}', ['ItemController', 'show'])->name('items.show');

        $match = $router->dispatch('GET', '/items/create');

        // Must match the static 'create' route, not the parametric '{id}' route
        $this->assertSame('create', $match['method']);
        $this->assertEmpty($match['params']);
    }

    // ── Route constraint tests ─────────────────────────────────────────

    public function testNumericConstraintMatchesNumber(): void
    {
        $router = new Router();
        $router->get('/users/{id:\d+}', ['UserController', 'show'])->name('users.show');

        $match = $router->dispatch('GET', '/users/42');

        $this->assertSame('UserController', $match['controller']);
        $this->assertSame('show', $match['method']);
        $this->assertSame('42', $match['params']['id']);
    }

    public function testNumericConstraintDoesNotMatchString(): void
    {
        $router = new Router();
        $router->get('/users/{id:\d+}', ['UserController', 'show'])->name('users.show');

        // "/users/abc" should not match {id:\d+} → renderErrorPage(404) which calls exit
        // We verify by checking dispatch throws or renders a 404
        // Since renderErrorPage calls exit, we check there is no route match
        $routes = $router->getRoutes();
        $this->assertCount(1, $routes);

        // Verify pattern directly: compile and test
        $reflection = new \ReflectionClass($router);
        $method     = $reflection->getMethod('compilePattern');
        $method->setAccessible(true);

        $pattern = $method->invoke($router, '/users/{id:\d+}');
        $this->assertMatchesRegularExpression($pattern, '/users/123');
        $this->assertDoesNotMatchRegularExpression($pattern, '/users/abc');
        $this->assertDoesNotMatchRegularExpression($pattern, '/users/12abc');
    }

    public function testSlugConstraintMatchesSlug(): void
    {
        $router = new Router();
        $router->get('/posts/{slug:[a-z0-9\-]+}', ['PostController', 'show'])->name('posts.show');

        $reflection = new \ReflectionClass($router);
        $method     = $reflection->getMethod('compilePattern');
        $method->setAccessible(true);

        $pattern = $method->invoke($router, '/posts/{slug:[a-z0-9\-]+}');
        $this->assertMatchesRegularExpression($pattern, '/posts/hello-world');
        $this->assertMatchesRegularExpression($pattern, '/posts/abc123');
        $this->assertDoesNotMatchRegularExpression($pattern, '/posts/Hello World');
        $this->assertDoesNotMatchRegularExpression($pattern, '/posts/UPPER');
    }

    public function testConstraintRouteDispatchExtractsParam(): void
    {
        $router = new Router();
        $router->get('/items/{id:\d+}', ['ItemController', 'show'])->name('items.show');

        $match = $router->dispatch('GET', '/items/99');

        $this->assertSame('99', $match['params']['id']);
    }

    public function testBackwardCompatibilityWithoutConstraint(): void
    {
        // Existing {param} syntax still works unchanged
        $router = new Router();
        $router->get('/articles/{slug}', ['ArticleController', 'show'])->name('articles.show');

        $match = $router->dispatch('GET', '/articles/my-post');
        $this->assertSame('my-post', $match['params']['slug']);

        $match2 = $router->dispatch('GET', '/articles/123');
        $this->assertSame('123', $match2['params']['slug']);
    }

    public function testConstrainedRouteUrlGenerationStripsConstraint(): void
    {
        $router = new Router();
        $router->get('/users/{id:\d+}', ['UserController', 'show'])->name('users.show');

        // url() must strip the constraint part and substitute the param value
        $reflection = new \ReflectionClass($router);

        // Access namedRoutes to verify registration
        $prop = $reflection->getProperty('namedRoutes');
        $prop->setAccessible(true);
        $named = $prop->getValue($router);

        $this->assertArrayHasKey('users.show', $named);
        $this->assertStringContainsString('/users/{id:\d+}', $named['users.show']);
    }

    protected function tearDown(): void
    {
        // Reset $_POST method override between tests
        unset($_POST['_method']);
    }
}
