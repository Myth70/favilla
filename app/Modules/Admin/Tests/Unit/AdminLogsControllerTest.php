<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\AdminLogsController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AdminLogsController via the HTTP harness.
 * Focus on the input-validation / routing branches that do not require the
 * full log schema (those belong to the Integration suite).
 */
class AdminLogsControllerTest extends ControllerTestCase
{
    public function testCleanupRejectsInvalidTargetAndRedirects(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['target' => 'bogus', 'days' => '30'])
            ->dispatch(AdminLogsController::class, 'cleanup');

        $this->assertTrue($result->isRedirect(), 'Un target non valido deve reindirizzare alla index');
        $this->assertSame('/admin.logs.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'), 'Deve essere impostato un flash di errore');
    }

    public function testCleanupRejectsInvalidDaysAndRedirects(): void
    {
        $this->actingAsAdmin();

        // 'audit' è un target valido ma 13 non è tra i giorni consentiti.
        $result = $this->withPost(['target' => 'audit', 'days' => '13'])
            ->dispatch(AdminLogsController::class, 'cleanup');

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testExportRejectsUnknownTypeWithoutRendering(): void
    {
        $this->actingAsAdmin();

        $result = $this->withGet(['type' => 'bogus'])
            ->dispatch(AdminLogsController::class, 'export');

        // export() con type non valido imposta HTTP 400 e ritorna: niente redirect, niente render.
        $this->assertFalse($result->isRedirect());
        $this->assertFalse($result->didRender());
    }
}
