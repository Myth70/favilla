<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Core\Container;
use App\Modules\Reports\Controllers\DocumentController;
use App\Modules\Reports\Services\DocumentService;
use App\Modules\Reports\Services\ReportsDocumentBindingService;
use Tests\ModuleTestCase;

/**
 * Tests for DocumentController.
 *
 * Every action emits JSON/PDF and terminates with raw echo+exit() (not the
 * json() seam), so the actions cannot be driven through the HTTP harness
 * without killing the test runner — they belong to the Integration suite. Here
 * we assert the container wires the controller's full dependency graph and that
 * it exposes the expected public surface.
 */
class DocumentControllerTest extends ModuleTestCase
{
    public function testControllerIsResolvableThroughContainer(): void
    {
        $controller = Container::getInstance()->make(DocumentController::class);

        $this->assertInstanceOf(DocumentController::class, $controller);
    }

    public function testDependenciesAreInjected(): void
    {
        $controller = Container::getInstance()->make(DocumentController::class);

        $binding = new \ReflectionProperty(DocumentController::class, 'bindingService');
        $binding->setAccessible(true);
        $document = new \ReflectionProperty(DocumentController::class, 'documentService');
        $document->setAccessible(true);

        $this->assertInstanceOf(ReportsDocumentBindingService::class, $binding->getValue($controller));
        $this->assertInstanceOf(DocumentService::class, $document->getValue($controller));
    }

    public function testExposesPublicBindingActions(): void
    {
        foreach (['edit', 'storeBind', 'update', 'destroyBind', 'generate'] as $action) {
            $this->assertTrue(
                method_exists(DocumentController::class, $action),
                "DocumentController deve esporre l'azione {$action}()"
            );
        }
    }
}
