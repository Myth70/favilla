<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Tests\Unit;

use App\Core\Container;
use App\Modules\HelpOnline\Controllers\HelpOnlineController;
use App\Modules\HelpOnline\Services\HelpAdminService;
use App\Modules\HelpOnline\Services\HelpOnlineService;
use Tests\ModuleTestCase;

/**
 * Controller test for HelpOnline::ask().
 *
 * The controller is thin: it parses the POST, delegates to the service, and maps
 * the result's `ok` flag to a 200/422 JSON response. We register a stub service
 * in the container so the test asserts exactly that wiring — the right args are
 * forwarded and the status is derived correctly — without the god-class service
 * or its 5-table schema.
 */
class HelpOnlineControllerTest extends ModuleTestCase
{
    private StubHelpOnlineService $stub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stub = new StubHelpOnlineService();
        Container::getInstance()->instance(HelpOnlineService::class, $this->stub);
        Container::getInstance()->instance(HelpAdminService::class, new StubHelpAdminService());

        $_SESSION = ['user_id' => 5];
        $_POST = [];
    }

    public function testAskForwardsInputAndReturns200WhenOk(): void
    {
        $this->stub->answer = ['ok' => true, 'answer' => 'Ecco come si fa.'];
        $_POST = ['message' => 'come creo un report?'];

        $controller = new CapturingHelpOnlineController();
        $controller->ask();

        $this->assertSame(200, $controller->jsonStatus);
        $this->assertTrue($controller->jsonData['ok']);
        $this->assertSame('come creo un report?', $this->stub->lastQuery);
        $this->assertSame(5, $this->stub->lastUserId);
    }

    public function testAskReturns422WhenServiceNotOk(): void
    {
        $this->stub->answer = ['ok' => false, 'message' => 'Schema non pronto.'];
        $_POST = ['message' => 'qualsiasi'];

        $controller = new CapturingHelpOnlineController();
        $controller->ask();

        $this->assertSame(422, $controller->jsonStatus);
        $this->assertFalse($controller->jsonData['ok']);
    }
}

class StubHelpOnlineService extends HelpOnlineService
{
    /** @var array<string,mixed> */
    public array $answer = ['ok' => true];
    public ?string $lastQuery = null;
    public ?int $lastUserId = null;

    public function __construct()
    {
        // Intentionally skip parent: no repository/DB needed for this stub.
    }

    public function answerQuestion(string $query, int $userId, string $contextPath, string $pageTitle = '', ?int $selectedChunkId = null): array
    {
        $this->lastQuery = $query;
        $this->lastUserId = $userId;
        return $this->answer;
    }
}

class StubHelpAdminService extends HelpAdminService
{
    public function __construct()
    {
        // Not exercised by ask(); skip the real (DB-backed) construction.
    }
}

class CapturingHelpOnlineController extends HelpOnlineController
{
    public ?array $jsonData = null;
    public ?int $jsonStatus = null;

    protected function json(array $data, int $status = 200): void
    {
        $this->jsonData = $data;
        $this->jsonStatus = $status;
    }
}
