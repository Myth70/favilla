<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Core\Container;
use App\Modules\Reports\Controllers\ExportController;
use App\Modules\Reports\Services\TemplateService;
use Tests\ModuleTestCase;

/**
 * Tests for ExportController.
 *
 * Every action streams a file and terminates with raw exit() (not the
 * redirect()/json() seam), so the actions cannot be driven through the HTTP
 * harness without killing the test runner — they belong to the Integration
 * suite. What we can assert here is that the controller is wired correctly by
 * the container (its whole dependency graph autowires) and exposes the expected
 * public surface.
 */
class ExportControllerTest extends ModuleTestCase
{
    public function testControllerIsResolvableThroughContainer(): void
    {
        $controller = Container::getInstance()->make(ExportController::class);

        $this->assertInstanceOf(ExportController::class, $controller);
    }

    public function testTemplateServiceDependencyIsInjected(): void
    {
        $controller = Container::getInstance()->make(ExportController::class);

        $ref  = new \ReflectionProperty(ExportController::class, 'templateService');
        $ref->setAccessible(true);

        $this->assertInstanceOf(TemplateService::class, $ref->getValue($controller));
    }

    public function testExposesPublicExportActions(): void
    {
        $this->assertTrue(method_exists(ExportController::class, 'quickExport'));
        $this->assertTrue(method_exists(ExportController::class, 'generate'));
    }
}
