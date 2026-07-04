<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\ChangelogController;
use App\Modules\Admin\Services\ChangelogService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for the Admin ChangelogController via the HTTP harness.
 * DB-free validation branches of store() plus the version() JSON endpoint
 * (driven through a mocked ChangelogService).
 */
class ChangelogControllerTest extends ControllerTestCase
{
    public function testStoreRejectsEmptyDataAndRedirectsToCreate(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost([])->dispatch(ChangelogController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.changelog.create', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_errors'] ?? []);
    }

    public function testStoreRejectsInvalidSemver(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost([
            'version'      => 'not-semver',
            'title'        => 'Release',
            'notes'        => 'Some notes',
            'release_date' => '2026-01-01',
        ])->dispatch(ChangelogController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.changelog.create', $result->redirectUrl());
        $this->assertArrayHasKey('version', $_SESSION['_errors'] ?? []);
    }

    public function testVersionReturnsLatestPublishedAsJson(): void
    {
        $service = $this->createMock(ChangelogService::class);
        $service->method('getLatestPublished')
            ->willReturn(['version' => '2.1.0', 'title' => 'Big release']);
        $this->bindInstance(ChangelogService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(ChangelogController::class, 'version');

        $this->assertTrue($result->isJson());
        $this->assertSame('2.1.0', $result->jsonPayload()['version']);
        $this->assertSame('Big release', $result->jsonPayload()['title']);
    }

    public function testVersionReturnsNullsWhenNoRelease(): void
    {
        $service = $this->createMock(ChangelogService::class);
        $service->method('getLatestPublished')->willReturn(null);
        $this->bindInstance(ChangelogService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(ChangelogController::class, 'version');

        $this->assertTrue($result->isJson());
        $this->assertNull($result->jsonPayload()['version']);
        $this->assertNull($result->jsonPayload()['title']);
    }

    public function testPaginationTargetsExistingChangelogContainer(): void
    {
        $path = __DIR__ . '/../../Views/changelog/partials/table.php';

        $this->assertFileExists($path);

        $markup = file_get_contents($path);

        $this->assertIsString($markup);
        $this->assertStringContainsString('hx-target="#ch-table-container"', $markup);
        $this->assertStringNotContainsString('hx-target="#adm-table-container"', $markup);
    }
}
