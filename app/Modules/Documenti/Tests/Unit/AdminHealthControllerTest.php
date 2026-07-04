<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminHealthController;
use Tests\ControllerTestCase;

class AdminHealthControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS documenti_files (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name   TEXT NOT NULL,
                stored_name     TEXT NOT NULL,
                directory       TEXT NOT NULL,
                mime_type       TEXT NOT NULL,
                extension       TEXT NOT NULL,
                size_bytes      INTEGER NOT NULL DEFAULT 0,
                checksum_sha256 TEXT NULL,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at      TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_versioni (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_id INTEGER NOT NULL,
                file_id      INTEGER NOT NULL
            );
        ');

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersHealthChecks(): void
    {
        $result = $this->dispatch(AdminHealthController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $checks = $result->renderedData()['checks'];
        $this->assertArrayHasKey('storage_dir', $checks);
        $this->assertArrayHasKey('orphan_files', $checks);
        $this->assertArrayHasKey('file_integrity', $checks);
    }

    public function testOrphanFilesCheckDetectsUnreferencedFile(): void
    {
        $this->insertRow('documenti_files', [
            'original_name' => 'orfano.pdf', 'stored_name' => 'orfano.pdf',
            'directory' => '2026/01', 'mime_type' => 'application/pdf', 'extension' => 'pdf',
        ]);

        $result = $this->dispatch(AdminHealthController::class, 'index', []);

        $this->assertSame('warning', $result->renderedData()['checks']['orphan_files']['status']);
    }
}
