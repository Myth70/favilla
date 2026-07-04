<?php

declare(strict_types=1);

namespace App\Modules\Files\Tests\Unit;

use App\Modules\Files\Controllers\FilesController;
use App\Modules\Files\Services\FilesService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for FilesController via the HTTP harness.
 * Covers the DB-free upload validation and the not-found renders (file service
 * mocked). The streaming/download actions terminate with exit and stay in the
 * Integration suite.
 */
class FilesControllerTest extends ControllerTestCase
{
    public function testStoreWithoutFileRedirectsToUpload(): void
    {
        $this->actingAs(1);

        // No $_FILES['file'] → "select a file" validation error before any storage.
        $result = $this->withPost([])->dispatch(FilesController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/files.upload', $result->redirectUrl());
        $this->assertArrayHasKey('file', $_SESSION['_errors'] ?? []);
    }

    public function testShowRendersNotFoundWhenFileMissing(): void
    {
        $service = $this->createMock(FilesService::class);
        $service->method('findActiveWithOwner')->willReturn(null);
        $this->bindInstance(FilesService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(FilesController::class, 'show', ['99']);

        $this->assertTrue($result->didRender());
        $this->assertSame('errors/404', $result->renderedTemplate());
    }

    public function testEditRendersNotFoundWhenFileMissing(): void
    {
        $service = $this->createMock(FilesService::class);
        $service->method('findActiveWithOwner')->willReturn(null);
        $this->bindInstance(FilesService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(FilesController::class, 'edit', ['99']);

        $this->assertTrue($result->didRender());
        $this->assertSame('errors/404', $result->renderedTemplate());
    }
}
