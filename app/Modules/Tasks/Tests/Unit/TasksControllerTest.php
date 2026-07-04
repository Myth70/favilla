<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Tests\Unit;

use App\Modules\Tasks\Controllers\TasksController;
use Tests\ModuleTestCase;

/**
 * Controller test for Tasks::store().
 *
 * Validation fails before the service is touched, so the AJAX validation-error
 * branch is exercised without a DB. A capturing subclass intercepts json()/
 * redirect() (which otherwise exit) to assert the 422 + field errors.
 */
class TasksControllerTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['user_id' => 1, 'user_name' => 'Tester'];
        $_POST = [];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest'; // AJAX → JSON branch
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_HX_REQUEST']);
    }

    public function testStoreRejectsEmptyTitleWith422(): void
    {
        $_POST = ['title' => '', 'description' => 'qualcosa'];

        $controller = new CapturingTasksController();
        $controller->store();

        $this->assertSame(422, $controller->jsonStatus);
        $this->assertArrayHasKey('errors', (array) $controller->jsonData);
        $this->assertArrayHasKey('title', $controller->jsonData['errors']);
        $this->assertNull($controller->redirectUrl, 'AJAX validation failure must not redirect');
    }
}

class CapturingTasksController extends TasksController
{
    public ?array $jsonData = null;
    public ?int $jsonStatus = null;
    public ?string $redirectUrl = null;

    protected function json(array $data, int $status = 200): void
    {
        $this->jsonData = $data;
        $this->jsonStatus = $status;
    }

    protected function redirect(string $url): void
    {
        $this->redirectUrl = $url;
    }
}
