<?php

declare(strict_types=1);

namespace App\Modules\Backup\Tests\Unit;

use App\Modules\Backup\Controllers\BackupController;
use App\Modules\Backup\Services\BackupService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for BackupController via the HTTP harness.
 *
 * index() is the only action that does not terminate with raw header()+exit, so
 * it is exercised with a mocked BackupService; the mutating/streaming actions
 * (store/download/destroy/restore) belong to the Integration suite. A contract
 * check documents that surface.
 */
class BackupControllerTest extends ControllerTestCase
{
    public function testIndexRendersBackupDashboard(): void
    {
        $service = $this->createMock(BackupService::class);
        $service->method('listBackups')->willReturn([['filename' => 'db_2026.zip']]);
        $service->method('listHistory')->willReturn([]);
        $service->method('getExcludedTables')->willReturn([]);
        $service->method('isBackupRunning')->willReturn(false);
        $this->bindInstance(BackupService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(BackupController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Backup/Views/index', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['backups']);
        $this->assertFalse($result->renderedData()['isRunning']);
    }

    public function testExposesMutatingActions(): void
    {
        foreach (['store', 'download', 'destroy', 'restore'] as $action) {
            $this->assertTrue(
                method_exists(BackupController::class, $action),
                "BackupController deve esporre l'azione {$action}()"
            );
        }
    }
}
