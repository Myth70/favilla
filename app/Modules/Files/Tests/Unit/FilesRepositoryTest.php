<?php

namespace App\Modules\Files\Tests\Unit;

use App\Modules\Files\Repositories\FilesRepository;
use Tests\ModuleTestCase;

class FilesRepositoryTest extends ModuleTestCase
{
    private FilesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();
        $this->migrate('
            CREATE TABLE files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                directory TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                extension TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                folder TEXT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                tags TEXT DEFAULT NULL,
                visibility TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL,
                deleted_at TEXT DEFAULT NULL
            )
        ');

        $this->insertRow('users', ['name' => 'Mario Rossi']);
        $this->repo = new FilesRepository();
    }

    public function testFindActiveWithOwnerReturnsAssociativeRowWithUploaderName(): void
    {
        $fileId = $this->insertRow('files', [
            'original_name' => 'contratto.pdf',
            'stored_name' => 'file_001.pdf',
            'directory' => 'files',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'folder' => 'documenti',
            'description' => 'Contratto firmato',
            'tags' => 'contratto, legale',
            'visibility' => 'private',
            'created_by' => 1,
            'created_at' => '2026-04-01 10:00:00',
            'updated_at' => '2026-04-01 10:00:00',
            'deleted_at' => null,
        ]);

        $file = $this->repo->findActiveWithOwner($fileId);

        $this->assertIsArray($file);
        $this->assertSame('contratto.pdf', $file['original_name']);
        $this->assertSame('Mario Rossi', $file['uploader_name']);
        $this->assertSame('application/pdf', $file['mime_type']);
    }

    public function testFindActiveWithOwnerExcludesSoftDeletedRecords(): void
    {
        $fileId = $this->insertRow('files', [
            'original_name' => 'archivio.zip',
            'stored_name' => 'file_002.zip',
            'directory' => 'files',
            'mime_type' => 'application/zip',
            'extension' => 'zip',
            'size_bytes' => 2048,
            'folder' => 'backup',
            'description' => null,
            'tags' => null,
            'visibility' => 'internal',
            'created_by' => 1,
            'created_at' => '2026-04-01 11:00:00',
            'updated_at' => '2026-04-01 11:00:00',
            'deleted_at' => '2026-04-01 12:00:00',
        ]);

        $this->assertNull($this->repo->findActiveWithOwner($fileId));

        $file = $this->repo->findWithOwner($fileId);
        $this->assertIsArray($file);
        $this->assertSame('archivio.zip', $file['original_name']);
    }
}
