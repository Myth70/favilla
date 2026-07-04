<?php

namespace Tests\Unit;

use App\Core\Router;
use App\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;

/**
 * Dispatch-level tests for the Router error paths.
 *
 * Since the seam refactor, an unmatched URI (404) and a method mismatch (405)
 * raise an HttpException instead of rendering + exit, so they can be asserted
 * directly. The 405 case also carries the Allow header on the exception.
 */
class RouterDispatchTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/items', ['ItemController', 'index'])->name('items.index');
        $this->router->get('/items/{id}', ['ItemController', 'show'])->name('items.show');
        $this->router->post('/items', ['ItemController', 'store'])->name('items.store');
    }

    protected function tearDown(): void
    {
        unset($_POST['_method']);
    }

    public function testUnknownUriThrows404(): void
    {
        try {
            $this->router->dispatch('GET', '/does-not-exist');
            $this->fail('Dispatching an unknown URI must raise a 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testMethodMismatchThrows405WithAllowHeader(): void
    {
        try {
            // /items exists for GET and POST, but not DELETE → 405
            $this->router->dispatch('DELETE', '/items');
            $this->fail('A method mismatch must raise a 405');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->getStatusCode());

            $headers = $e->getHeaders();
            $this->assertArrayHasKey('Allow', $headers);
            $this->assertStringContainsString('GET', $headers['Allow']);
            $this->assertStringContainsString('POST', $headers['Allow']);
            $this->assertStringNotContainsString('DELETE', $headers['Allow']);
        }
    }

    public function testStaticRouteWinsOverParametric(): void
    {
        $router = new Router();
        $router->get('/items/create', ['ItemController', 'create'])->name('items.create');
        $router->get('/items/{id}', ['ItemController', 'show'])->name('items.show');

        $match = $router->dispatch('GET', '/items/create');

        $this->assertSame('create', $match['method']);
        $this->assertEmpty($match['params']);
    }
}
