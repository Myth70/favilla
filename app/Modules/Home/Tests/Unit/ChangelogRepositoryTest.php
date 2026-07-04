<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Repositories\ChangelogRepository;
use Tests\ModuleTestCase;

class ChangelogRepositoryTest extends ModuleTestCase
{
    private ChangelogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE changelogs (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                version      TEXT NOT NULL,
                title        TEXT NOT NULL,
                notes        TEXT NOT NULL,
                release_date TEXT NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 0,
                created_by   INTEGER NULL,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE changelog_translations (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                changelog_id INTEGER NOT NULL,
                locale       TEXT NOT NULL,
                title        TEXT NOT NULL,
                notes        TEXT NOT NULL
            );
        ');
        $this->repo = new ChangelogRepository();
    }

    private function addChangelog(string $version, string $date, int $published): void
    {
        $this->insertRow('changelogs', [
            'version'      => $version,
            'title'        => "Release {$version}",
            'notes'        => 'note',
            'release_date' => $date,
            'is_published' => $published,
        ]);
    }

    public function testListPublishedReturnsOnlyPublishedNewestFirst(): void
    {
        $this->addChangelog('1.0.0', '2026-01-01', 1);
        $this->addChangelog('1.2.0', '2026-03-01', 1);
        $this->addChangelog('1.1.0', '2026-02-01', 0); // bozza, esclusa

        $rows = $this->repo->listPublished();

        $this->assertCount(2, $rows);
        // Ordinati per release_date DESC.
        $this->assertSame('1.2.0', $rows[0]['version']);
        $this->assertSame('1.0.0', $rows[1]['version']);
    }

    public function testListPublishedRespectsLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->addChangelog("1.0.{$i}", "2026-01-0{$i}", 1);
        }

        $this->assertCount(2, $this->repo->listPublished(2));
    }

    public function testListPublishedClampsLimitToSafeRange(): void
    {
        $this->addChangelog('1.0.0', '2026-01-01', 1);

        // limit <= 0 viene riportato ad almeno 1 (niente errore SQL).
        $this->assertCount(1, $this->repo->listPublished(0));
        $this->assertCount(1, $this->repo->listPublished(-10));
    }

    public function testListPublishedLocalizesTitleAndNotesWithItalianFallback(): void
    {
        $id1 = $this->insertRow('changelogs', [
            'version' => '2.0.0', 'title' => 'Titolo IT', 'notes' => 'Note IT',
            'release_date' => '2026-03-01', 'is_published' => 1,
        ]);
        $this->insertRow('changelogs', [
            'version' => '1.0.0', 'title' => 'Solo IT', 'notes' => 'Note solo IT',
            'release_date' => '2026-01-01', 'is_published' => 1,
        ]);
        // Traduzione EN solo per la prima release.
        $this->insertRow('changelog_translations', [
            'changelog_id' => $id1, 'locale' => 'en', 'title' => 'EN title', 'notes' => 'EN notes',
        ]);

        // EN: la release tradotta usa la traduzione; quella senza ricade sull'italiano.
        $en = array_column($this->repo->listPublished(30, 'en'), null, 'version');
        $this->assertSame('EN title', $en['2.0.0']['title']);
        $this->assertSame('EN notes', $en['2.0.0']['notes']);
        $this->assertSame('Solo IT', $en['1.0.0']['title']);

        // IT: sempre la riga base canonica.
        $it = array_column($this->repo->listPublished(30, 'it'), null, 'version');
        $this->assertSame('Titolo IT', $it['2.0.0']['title']);
        $this->assertSame('Note IT', $it['2.0.0']['notes']);
    }

    public function testCountPublishedCountsOnlyPublished(): void
    {
        $this->addChangelog('1.0.0', '2026-01-01', 1);
        $this->addChangelog('1.1.0', '2026-02-01', 1);
        $this->addChangelog('1.2.0', '2026-03-01', 0);

        $this->assertSame(2, $this->repo->countPublished());
    }
}
