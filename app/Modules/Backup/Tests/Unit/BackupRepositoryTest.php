<?php

namespace App\Modules\Backup\Tests\Unit;

use App\Modules\Backup\Repositories\BackupRepository;
use Tests\ModuleTestCase;

class BackupRepositoryTest extends ModuleTestCase
{
    private BackupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
            CREATE TABLE backup_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                format TEXT NOT NULL DEFAULT \'sqlgz\',
                size_bytes INTEGER NOT NULL,
                table_count INTEGER NOT NULL,
                databases_json TEXT NULL,
                files_json TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->repo = new BackupRepository();
    }

    public function testRecordAndListHistoryIncludeCreatorName(): void
    {
        $userId = $this->insertRow('users', ['name' => 'Admin']);

        $recordId = $this->repo->record('backup_20260424_101500.sql.gz', 2048, 12, $userId);

        $this->assertGreaterThan(0, $recordId);

        $items = $this->repo->listHistory(10);
        $this->assertCount(1, $items);
        $this->assertSame('backup_20260424_101500.sql.gz', $items[0]['filename']);
        $this->assertSame('Admin', $items[0]['created_by_name']);
    }

    public function testDeleteByFilenameReturnsTrueOnlyWhenRowExists(): void
    {
        $this->repo->record('backup_20260424_111500.sql.gz', 1024, 8, null);

        $this->assertTrue($this->repo->deleteByFilename('backup_20260424_111500.sql.gz'));
        $this->assertFalse($this->repo->deleteByFilename('backup_20260424_111500.sql.gz'));
    }

    public function testRecordPersistsFormatAndDatabasesForMultiDbSet(): void
    {
        $this->repo->record('backup_20260530_164000.zip', 4096, 94, null, 'zip', [
            ['key' => 'main', 'module' => null, 'database_name' => 'favilla', 'usable' => true],
            ['key' => 'documenti', 'module' => 'Documenti', 'database_name' => 'favilla_documenti', 'usable' => true],
        ]);

        $items = $this->repo->listHistory(10);
        $this->assertCount(1, $items);
        $this->assertSame('zip', $items[0]['format']);

        $decoded = json_decode((string) $items[0]['databases_json'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame('documenti', $decoded[1]['key']);
        $this->assertSame('Documenti', $decoded[1]['module']);
    }

    public function testLegacyRecordDefaultsToSqlgzFormat(): void
    {
        $this->repo->record('backup_20260424_101500.sql.gz', 2048, 12, null);

        $items = $this->repo->listHistory(10);
        $this->assertSame('sqlgz', $items[0]['format']);
        $this->assertNull($items[0]['databases_json']);
        $this->assertNull($items[0]['files_json']);
    }

    public function testRecordPersistsFilesSummary(): void
    {
        $this->repo->record('backup_20260712_090000.zip', 8192, 94, null, 'zip', null, [
            ['key' => 'public_uploads', 'base' => 'public/uploads', 'file_count' => 12, 'total_size' => 34567],
            ['key' => 'storage_uploads', 'base' => 'storage/uploads', 'file_count' => 3, 'total_size' => 999],
        ]);

        $items = $this->repo->listHistory(10);
        $decoded = json_decode((string) $items[0]['files_json'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame('public_uploads', $decoded[0]['key']);
        $this->assertSame(12, $decoded[0]['file_count']);
        $this->assertSame(999, $decoded[1]['total_size']);
    }
}
