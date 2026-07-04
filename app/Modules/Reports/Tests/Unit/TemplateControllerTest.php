<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Controllers\TemplateController;
use App\Modules\Reports\Services\ReportsTemplateQueryService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for TemplateController via the HTTP harness.
 * Covers the DB-free store() validation redirect and the JSON/partial renders
 * of create()/preview() (template query service mocked).
 */
class TemplateControllerTest extends ControllerTestCase
{
    public function testStoreRejectsMissingFieldsAndRedirects(): void
    {
        $this->actingAs(1);

        $result = $this->withPost([])->dispatch(TemplateController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/reports.templates.create', $result->redirectUrl());
        $errors = $_SESSION['_errors'] ?? [];
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('module', $errors);
        $this->assertArrayHasKey('source_key', $errors);
    }

    public function testPreviewReturns404WhenTemplateMissing(): void
    {
        $service = $this->createMock(ReportsTemplateQueryService::class);
        $service->method('getTemplatePreviewData')->willReturn(null);
        $this->bindInstance(ReportsTemplateQueryService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(TemplateController::class, 'preview', ['99']);

        $this->assertTrue($result->isJson());
        $this->assertSame(404, $result->jsonStatus());
    }

    public function testCreateRendersDesigner(): void
    {
        $service = $this->createMock(ReportsTemplateQueryService::class);
        $service->method('getDesignerData')->willReturn([
            'template' => null,
            'sources'  => [],
            'styles'   => [],
        ]);
        $this->bindInstance(ReportsTemplateQueryService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(TemplateController::class, 'create');

        $this->assertTrue($result->didRender());
        $this->assertSame('Reports/Views/templates/grapesjs-designer', $result->renderedTemplate());
    }
}
