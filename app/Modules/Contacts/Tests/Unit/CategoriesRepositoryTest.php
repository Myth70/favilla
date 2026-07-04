<?php

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Repositories\CategoriesRepository;
use Tests\ModuleTestCase;

class CategoriesRepositoryTest extends ModuleTestCase
{
    private CategoriesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE contact_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                nome TEXT NOT NULL,
                colore TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                categoria_id INTEGER NULL
            );
        ');

        $this->repo = new CategoriesRepository();
    }

    public function testAllForUserReturnsCategoriesWithContactCount(): void
    {
        $catA = $this->insertRow('contact_categories', ['user_id' => 7, 'nome' => 'Clienti', 'colore' => '#00aaff']);
        $catB = $this->insertRow('contact_categories', ['user_id' => 7, 'nome' => 'Fornitori', 'colore' => '#ffaa00']);

        $this->insertRow('contacts', ['user_id' => 7, 'categoria_id' => $catA]);
        $this->insertRow('contacts', ['user_id' => 7, 'categoria_id' => $catA]);
        $this->insertRow('contacts', ['user_id' => 99, 'categoria_id' => $catA]);
        $this->insertRow('contacts', ['user_id' => 7, 'categoria_id' => $catB]);

        $rows = $this->repo->allForUser(7);

        $this->assertCount(2, $rows);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['nome']] = (int) $row['totale_contatti'];
        }

        $this->assertSame(2, $counts['Clienti']);
        $this->assertSame(1, $counts['Fornitori']);
    }

    public function testFindForUserHonorsOwnership(): void
    {
        $catId = $this->insertRow('contact_categories', ['user_id' => 3, 'nome' => 'Privata', 'colore' => '#fff']);

        $this->assertNotNull($this->repo->findForUser($catId, 3));
        $this->assertNull($this->repo->findForUser($catId, 4));
    }
}
