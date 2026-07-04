<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Controllers\StyleController;
use App\Modules\Reports\Services\StyleService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for StyleController via the HTTP harness.
 * Covers the DB-free create() render, the non-ajax validation redirect of
 * store(), and the not-found render of edit() (style service mocked).
 */
class StyleControllerTest extends ControllerTestCase
{
    public function testCreateRendersForm(): void
    {
        $this->actingAs(1);

        $result = $this->dispatch(StyleController::class, 'create');

        $this->assertTrue($result->didRender());
        $this->assertSame('Reports/Views/styles/form', $result->renderedTemplate());
        $this->assertNull($result->renderedData()['style']);
    }

    public function testStoreRejectsEmptyNameAndRedirects(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['name' => ''])->dispatch(StyleController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/reports.styles.create', $result->redirectUrl());
        $this->assertArrayHasKey('name', $_SESSION['_errors'] ?? []);
    }

    public function testEditRendersNotFoundWhenStyleMissing(): void
    {
        $service = $this->createMock(StyleService::class);
        $service->method('find')->willReturn(null);
        $this->bindInstance(StyleService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(StyleController::class, 'edit', ['99']);

        $this->assertTrue($result->didRender());
        $this->assertSame('errors/404', $result->renderedTemplate());
    }
}
