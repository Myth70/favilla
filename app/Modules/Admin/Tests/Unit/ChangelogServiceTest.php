<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ChangelogService;
use Tests\ModuleTestCase;

class ChangelogServiceTest extends ModuleTestCase
{
    private ChangelogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUsersTable();
        $this->migrate('
            CREATE TABLE changelogs (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                version      TEXT NOT NULL,
                title        TEXT NOT NULL,
                notes        TEXT NOT NULL DEFAULT "",
                release_date TEXT NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 0,
                created_by   INTEGER NULL,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->service = new ChangelogService();
    }

    private function makeRelease(string $version, int $published = 0, string $date = '2026-01-01'): int
    {
        return (int) $this->service->create([
            'version' => $version, 'title' => "Release {$version}", 'notes' => 'n',
            'release_date' => $date, 'is_published' => $published,
        ]);
    }

    public function testCreateAndFind(): void
    {
        $id = $this->makeRelease('1.0.0');
        $row = $this->service->find($id);
        $this->assertSame('1.0.0', $row['version']);
    }

    public function testTogglePublishedFlipsState(): void
    {
        $id = $this->makeRelease('1.0.0', 0);

        $after = $this->service->togglePublished($id);
        $this->assertSame(1, (int) $after['is_published']);

        $after2 = $this->service->togglePublished($id);
        $this->assertSame(0, (int) $after2['is_published']);
    }

    public function testTogglePublishedThrowsForMissingRelease(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->togglePublished(999);
    }

    public function testFindByVersion(): void
    {
        $this->makeRelease('2.1.0');
        $this->assertNotNull($this->service->findByVersion('2.1.0'));
        $this->assertNull($this->service->findByVersion('9.9.9'));
    }

    public function testGetLatestPublishedReturnsNewestPublished(): void
    {
        $this->makeRelease('1.0.0', 1, '2026-01-01');
        $this->makeRelease('1.2.0', 1, '2026-03-01');
        $this->makeRelease('1.3.0', 0, '2026-04-01'); // bozza, ignorata

        $latest = $this->service->getLatestPublished();
        $this->assertSame('1.2.0', $latest['version']);
    }

    public function testListPaginatedFiltersBySearchAndPublished(): void
    {
        $this->makeRelease('1.0.0', 1, '2026-01-01');
        $this->makeRelease('2.0.0', 0, '2026-02-01');

        $published = $this->service->listPaginated(['published' => 1], 1);
        $this->assertSame(1, $published['total']);

        $search = $this->service->listPaginated(['search' => '2.0'], 1);
        $this->assertSame(1, $search['total']);
        $this->assertSame('2.0.0', $search['items'][0]['version']);
    }

    public function testDeleteRemovesRelease(): void
    {
        $id = $this->makeRelease('1.0.0');
        $this->service->delete($id);
        $this->assertNull($this->service->find($id));
    }
}
