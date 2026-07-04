<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Services\ChangelogService;
use Tests\ModuleTestCase;

class ChangelogServiceTest extends ModuleTestCase
{
    private ChangelogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE changelogs (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                version      TEXT NOT NULL,
                title        TEXT NOT NULL,
                notes        TEXT NOT NULL DEFAULT "",
                release_date TEXT NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 0,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->service = new ChangelogService();
    }

    private function add(string $version, int $published, string $date): void
    {
        $this->insertRow('changelogs', [
            'version' => $version, 'title' => "R {$version}", 'notes' => 'n',
            'release_date' => $date, 'is_published' => $published,
        ]);
    }

    public function testGetPublicTimelineReturnsPublishedItemsTotalAndLatest(): void
    {
        $this->add('1.0.0', 1, '2026-01-01');
        $this->add('1.2.0', 1, '2026-03-01');
        $this->add('1.1.0', 0, '2026-02-01'); // bozza esclusa

        $timeline = $this->service->getPublicTimeline();

        $this->assertSame(2, $timeline['total']);
        $this->assertCount(2, $timeline['items']);
        // latest = primo elemento (più recente per release_date).
        $this->assertSame('1.2.0', $timeline['latest']['version']);
    }

    public function testGetPublicTimelineEmptyWhenNoPublished(): void
    {
        $this->add('1.0.0', 0, '2026-01-01');

        $timeline = $this->service->getPublicTimeline();
        $this->assertSame(0, $timeline['total']);
        $this->assertSame([], $timeline['items']);
        $this->assertNull($timeline['latest']);
    }
}
