<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Container;
use App\Core\Controller;
use App\Core\Router;
use App\Core\Testing\HaltResponse;
use Tests\Support\CapturingView;
use Tests\Support\FakeRouter;
use Tests\Support\MakesContainer;

/**
 * Base class for controller tests.
 *
 * Extends {@see ModuleTestCase} (SQLite in-memory + DI container + PDO) and adds
 * an HTTP-shaped harness around a controller action:
 *
 *  - request setup    : actingAs(), withPost(), withGet(), asHtmx()
 *  - dispatch          : instantiates the controller, injects a CapturingView,
 *                        invokes the action and captures the outcome
 *  - outcome assertions: via the returned {@see ControllerResult}
 *
 * Terminal responses work because Controller::redirect()/json() throw a
 * {@see HaltResponse} under test (FAVILLA_TESTING) instead of calling exit.
 *
 * Example:
 *   $this->actingAs(1, ['admin.logs.manage']);
 *   $r = $this->withPost(['target' => 'bogus'])
 *             ->dispatch(AdminLogsController::class, 'cleanup');
 *   $this->assertTrue($r->isRedirect());
 */
abstract class ControllerTestCase extends ModuleTestCase
{
    // Lets controller tests swap a heavy collaborator for a mock via the DI
    // container: bindInstance(Service::class, $mock) makes the controller's
    // app(Service::class) resolve the double instead of the real implementation.
    use MakesContainer;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic, never-throwing route() so controllers can build URLs.
        Container::getInstance()->instance(Router::class, new FakeRouter());

        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        parent::tearDown();
    }

    /**
     * Simulate an authenticated user.
     *
     * @param string[]             $permissions slugs granted (see has_permission())
     * @param array<string,mixed>  $user        extra user fields
     */
    protected function actingAs(int $userId = 1, array $permissions = [], array $user = []): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = array_merge(
            ['id' => $userId, 'name' => 'Test User', 'email' => 'test@example.test'],
            $user
        );
        // has_permission() reads these two keys (admin role = manage-all).
        $_SESSION['user_roles'] = $user['roles'] ?? [];
        $_SESSION['user_permissions'] = $permissions;
        // Pre-seed the preferences cache so Controller::render() never queries the DB.
        $_SESSION['user_preferences'] = [
            'theme'              => 'light',
            'primary_color'      => '#3b82f6',
            'sidebar_collapsed'  => 0,
            'sidebar_style'      => 'default',
            'background_pattern' => 'circles',
            'theme_skin'         => 'default',
            'font_family'        => 'system',
            'language'           => 'it',
        ];
    }

    /** Grant the "admin" role (manage-all override in has_permission()). */
    protected function actingAsAdmin(int $userId = 1): void
    {
        $this->actingAs($userId, [], ['roles' => ['admin']]);
    }

    protected function asHtmx(): static
    {
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        return $this;
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function withPost(array $data): static
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data;
        return $this;
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function withGet(array $data): static
    {
        $_GET = $data;
        return $this;
    }

    /**
     * Instantiate the controller (autowired through the test container), inject a
     * CapturingView, invoke $method with $args, and capture the outcome.
     *
     * @param class-string     $controllerClass
     * @param array<int,mixed> $args
     */
    protected function dispatch(string $controllerClass, string $method, array $args = []): ControllerResult
    {
        $controller = Container::getInstance()->make($controllerClass);

        $view = new CapturingView();
        if ($controller instanceof Controller) {
            $controller->setView($view);
        }

        $result = new ControllerResult($view);

        ob_start();
        try {
            $controller->{$method}(...$args);
        } catch (HaltResponse $halt) {
            $result->halt = $halt;
        } finally {
            $result->echoed = (string) ob_get_clean();
        }

        return $result;
    }

    /** Flash message of the given type set during the request, if any. */
    protected function flashOf(string $type): ?string
    {
        $value = $_SESSION['_flash_' . $type] ?? null;
        return is_string($value) ? $value : null;
    }
}
