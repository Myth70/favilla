<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\ChangelogRepository;
use Tests\ModuleTestCase;

/**
 * Test per ChangelogRepository.
 *
 * Copre: CRUD, findByVersion, getLatestPublished, listPaginated con filtri.
 */
class ChangelogRepositoryTest extends ModuleTestCase
{
    private ChangelogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // users necessaria per il LEFT JOIN in listPaginated()
        $this->createUsersTable();

        $this->migrate('
            CREATE TABLE changelogs (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                version      TEXT    UNIQUE NOT NULL,
                title        TEXT    NOT NULL,
                notes        TEXT,
                release_date TEXT,
                is_published INTEGER DEFAULT 0,
                created_by   INTEGER,
                created_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->migrate('
            CREATE TABLE changelog_translations (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                changelog_id INTEGER NOT NULL,
                locale       TEXT NOT NULL,
                title        TEXT NOT NULL,
                notes        TEXT NOT NULL
            )
        ');

        $this->repo = new ChangelogRepository();
    }

    // -------------------------------------------------------------------------
    // Helpers privati
    // -------------------------------------------------------------------------

    private function createRelease(string $version, bool $published = false, string $date = '2026-01-01'): int
    {
        return $this->repo->create([
            'version'      => $version,
            'title'        => "Release {$version}",
            'is_published' => $published ? 1 : 0,
            'release_date' => $date,
        ]);
    }

    // -------------------------------------------------------------------------
    // CRUD base
    // -------------------------------------------------------------------------

    public function testCreateAndFind(): void
    {
        $id  = $this->createRelease('1.0.0');
        $row = $this->repo->find($id);

        $this->assertNotNull($row);
        $this->assertSame('1.0.0', $row['version']);
        $this->assertSame('Release 1.0.0', $row['title']);
        $this->assertSame(0, (int) $row['is_published']);
    }

    public function testUpdate(): void
    {
        $id = $this->createRelease('1.0.0');
        $this->repo->update($id, ['title' => 'Updated title', 'is_published' => 1]);

        $row = $this->repo->find($id);
        $this->assertSame('Updated title', $row['title']);
        $this->assertSame(1, (int) $row['is_published']);
    }

    // -------------------------------------------------------------------------
    // findByVersion()
    // -------------------------------------------------------------------------

    public function testFindByVersion(): void
    {
        $this->createRelease('2.3.1');

        $row = $this->repo->findByVersion('2.3.1');

        $this->assertNotNull($row);
        $this->assertSame('2.3.1', $row['version']);
    }

    public function testFindByVersionReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByVersion('99.99.99'));
    }

    // -------------------------------------------------------------------------
    // getLatestPublished()
    // -------------------------------------------------------------------------

    public function testGetLatestPublished(): void
    {
        $this->createRelease('1.0.0', true, '2026-01-01');
        $this->createRelease('1.1.0', true, '2026-02-01');
        $this->createRelease('1.2.0', false, '2026-03-01'); // bozza
        $this->createRelease('1.1.5', true, '2026-01-15');

        $latest = $this->repo->getLatestPublished();

        $this->assertNotNull($latest);
        // Deve essere 1.1.0 (data più recente tra le pubblicate: 2026-02-01)
        $this->assertSame('1.1.0', $latest['version']);
    }

    public function testGetLatestPublishedReturnsNullWhenNonePublished(): void
    {
        $this->createRelease('1.0.0', false);
        $this->createRelease('1.1.0', false);

        $this->assertNull($this->repo->getLatestPublished());
    }

    public function testGetLatestPublishedReturnsNullOnEmptyTable(): void
    {
        $this->assertNull($this->repo->getLatestPublished());
    }

    // -------------------------------------------------------------------------
    // i18n: getLatestPublished localizzato + CRUD traduzioni
    // -------------------------------------------------------------------------

    public function testGetLatestPublishedLocalizesTitleWithItalianFallback(): void
    {
        $id = $this->createRelease('1.0.0', true, '2026-01-01');
        $this->insertRow('changelog_translations', [
            'changelog_id' => $id, 'locale' => 'en', 'title' => 'EN title', 'notes' => 'EN notes',
        ]);

        $this->assertSame('EN title', $this->repo->getLatestPublished('en')['title']);
        // FR senza traduzione → fallback all'italiano base.
        $this->assertSame('Release 1.0.0', $this->repo->getLatestPublished('fr')['title']);
        // IT → riga base.
        $this->assertSame('Release 1.0.0', $this->repo->getLatestPublished('it')['title']);
    }

    public function testSaveTranslationsUpsertsPrunesEmptyAndIgnoresFallback(): void
    {
        $id = $this->createRelease('1.0.0', true);

        $this->repo->saveTranslations($id, [
            'en' => ['title' => 'EN title', 'notes' => 'EN notes'],
            'fr' => ['title' => '', 'notes' => ''],                 // vuoto → non salvato
            'it' => ['title' => 'ignored', 'notes' => 'ignored'],   // fallback → ignorato
            'xx' => ['title' => 'nope', 'notes' => 'nope'],         // non supportata → ignorata
        ]);

        $tr = $this->repo->getTranslations($id);
        $this->assertSame(['en'], array_keys($tr));
        $this->assertSame('EN title', $tr['en']['title']);
        $this->assertSame('EN notes', $tr['en']['notes']);

        // Re-save: svuotare EN lo rimuove, DE viene creato.
        $this->repo->saveTranslations($id, [
            'en' => ['title' => '', 'notes' => ''],
            'de' => ['title' => 'DE title', 'notes' => 'DE notes'],
        ]);

        $tr2 = $this->repo->getTranslations($id);
        $this->assertSame(['de'], array_keys($tr2));
        $this->assertSame('DE title', $tr2['de']['title']);
    }

    // -------------------------------------------------------------------------
    // listPaginated()
    // -------------------------------------------------------------------------

    public function testListPaginatedReturnsAll(): void
    {
        $this->createRelease('1.0.0', true);
        $this->createRelease('1.1.0', true);
        $this->createRelease('2.0.0', false);

        $result = $this->repo->listPaginated();

        $this->assertSame(3, $result['total']);
        $this->assertCount(3, $result['items']);
    }

    public function testListPaginatedFiltersPublished(): void
    {
        $this->createRelease('1.0.0', true);
        $this->createRelease('1.1.0', true);
        $this->createRelease('2.0.0', false); // bozza

        $result = $this->repo->listPaginated(['published' => '1']);

        $this->assertSame(2, $result['total']);
        foreach ($result['items'] as $item) {
            $this->assertSame(1, (int) $item['is_published']);
        }
    }

    public function testListPaginatedFiltersDraft(): void
    {
        $this->createRelease('1.0.0', true);
        $this->createRelease('2.0.0', false);

        $result = $this->repo->listPaginated(['published' => '0']);

        $this->assertSame(1, $result['total']);
        $this->assertSame(0, (int) $result['items'][0]['is_published']);
    }

    public function testListPaginatedFiltersBySearch(): void
    {
        $this->createRelease('1.0.0', true);
        $this->repo->create([
            'version'      => '2.0.0',
            'title'        => 'Grande refactoring',
            'is_published' => 1,
            'release_date' => '2026-03-01',
        ]);

        $result = $this->repo->listPaginated(['search' => 'refactoring']);

        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString('refactoring', $result['items'][0]['title']);
    }

    public function testListPaginatedPaginates(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createRelease("1.{$i}.0", true, "2026-0{$i}-01");
        }

        $result = $this->repo->listPaginated([], 2, 2);

        $this->assertSame(5, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertCount(2, $result['items']);
        $this->assertSame(3, $result['lastPage']);
    }

    // -------------------------------------------------------------------------
    // Vincolo UNIQUE su version
    // -------------------------------------------------------------------------

    public function testVersionMustBeUnique(): void
    {
        $this->createRelease('3.0.0');

        $this->expectException(\PDOException::class);
        $this->createRelease('3.0.0'); // duplicato → UNIQUE constraint
    }
}
