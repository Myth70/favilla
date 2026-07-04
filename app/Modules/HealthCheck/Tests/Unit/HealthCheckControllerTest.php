<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit;

use App\Modules\HealthCheck\Controllers\HealthCheckController;
use Tests\ModuleTestCase;

/**
 * Controller test for HealthCheck.
 *
 * The non-HTMX index() must return only the page shell (HTMX then loads the
 * heavy checks). We verify it renders the full view — never the partial and
 * never running the diagnostic service — via a capturing subclass.
 */
class HealthCheckControllerTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SERVER['HTTP_HX_REQUEST']);
    }

    public function testIndexRendersShellWithoutRunningChecks(): void
    {
        $controller = new CapturingHealthCheckController();
        $controller->index();

        $this->assertSame('HealthCheck/Views/index', $controller->renderedView);
        $this->assertSame('Health Check', $controller->renderedData['pageTitle'] ?? null);
        $this->assertNull(
            $controller->renderedPartialView,
            'A non-HTMX request must render the full shell, not the checks partial'
        );
    }
}

class CapturingHealthCheckController extends HealthCheckController
{
    public ?string $renderedView = null;
    public ?string $renderedPartialView = null;
    /** @var array<string,mixed> */
    public array $renderedData = [];

    protected function render(string $template, array $data = []): void
    {
        $this->renderedView = $template;
        $this->renderedData = $data;
    }

    protected function renderPartial(string $template, array $data = []): void
    {
        $this->renderedPartialView = $template;
    }
}
