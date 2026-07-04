<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminAuditController;
use Tests\ControllerTestCase;

/**
 * exportCsv() ends with a raw `exit;` (streams the CSV directly), which
 * terminates the PHPUnit process outright rather than being catchable like
 * HaltResponse — testing it here would kill the rest of the suite. Covered
 * by manual QA only (Gate 3).
 */
class AdminAuditControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS audit_logs (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                action     TEXT NOT NULL,
                entity     TEXT NOT NULL,
                entity_id  INTEGER NOT NULL,
                user_id    INTEGER NULL,
                ip         TEXT NULL,
                old_value  TEXT NULL,
                new_value  TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->insertRow('users', ['name' => 'Alice']);
        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersFilteredLogs(): void
    {
        $this->insertRow('audit_logs', ['action' => 'documento_invia', 'entity' => 'documento', 'entity_id' => 1, 'user_id' => 1]);
        $this->insertRow('audit_logs', ['action' => 'contatto_creato', 'entity' => 'contatto', 'entity_id' => 5, 'user_id' => 1]);

        $result = $this->dispatch(AdminAuditController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['total']);
    }

    public function testDettaglioRendersLogsForEntity(): void
    {
        $this->insertRow('audit_logs', [
            'action' => 'documento_invia', 'entity' => 'documento', 'entity_id' => 42, 'user_id' => 1,
            'old_value' => '{"stato":"bozza"}', 'new_value' => '{"stato":"inviato"}',
        ]);

        $result = $this->dispatch(AdminAuditController::class, 'dettaglio', ['documento', '42']);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['logs']);
    }
}
