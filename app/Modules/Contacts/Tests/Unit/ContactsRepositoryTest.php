<?php

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Repositories\ContactsRepository;
use Tests\ModuleTestCase;

class ContactsRepositoryTest extends ModuleTestCase
{
    private ContactsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE contact_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                colore TEXT NULL
            );
            CREATE TABLE contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                categoria_id INTEGER NULL,
                nome TEXT NOT NULL,
                cognome TEXT NULL,
                azienda TEXT NULL,
                ruolo TEXT NULL,
                email TEXT NULL,
                telefono TEXT NULL,
                telefono_alt TEXT NULL,
                indirizzo TEXT NULL,
                latitude TEXT NULL,
                longitude TEXT NULL,
                geocoding_source TEXT NULL,
                geocoded_at TEXT NULL,
                sito_web TEXT NULL,
                linkedin TEXT NULL,
                instagram TEXT NULL,
                twitter TEXT NULL,
                facebook TEXT NULL,
                whatsapp TEXT NULL,
                telegram TEXT NULL,
                avatar TEXT NULL,
                tags TEXT NULL,
                note TEXT NULL,
                preferito INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE contact_recurrences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contatto_id INTEGER NOT NULL
            );
        ');

        $this->repo = new ContactsRepository();
    }

    public function testListPaginatedFiltersSortsAndAddsCategoryAndRecurrences(): void
    {
        $cat = $this->insertRow('contact_categories', ['nome' => 'Clienti', 'colore' => '#00aaff']);

        $idA = $this->insertRow('contacts', [
            'user_id' => 1,
            'categoria_id' => $cat,
            'nome' => 'Anna',
            'cognome' => 'Verdi',
            'azienda' => 'Acme',
            'email' => 'anna@example.com',
            'telefono' => '111',
            'tags' => 'vip, cliente',
            'preferito' => 1,
            'created_at' => '2026-04-24 10:00:00',
            'updated_at' => '2026-04-24 10:00:00',
        ]);
        $idB = $this->insertRow('contacts', [
            'user_id' => 1,
            'categoria_id' => null,
            'nome' => 'Bruno',
            'cognome' => 'Rossi',
            'azienda' => 'Beta',
            'email' => 'bruno@example.com',
            'telefono' => '222',
            'tags' => 'fornitore',
            'preferito' => 0,
            'created_at' => '2026-04-24 10:01:00',
            'updated_at' => '2026-04-24 10:01:00',
        ]);

        $this->insertRow('contact_recurrences', ['contatto_id' => $idA]);
        $this->insertRow('contact_recurrences', ['contatto_id' => $idA]);

        $result = $this->repo->listPaginated(1, [
            'q' => 'a',
            'sort' => 'nome',
            'dir' => 'asc',
            'page' => 1,
        ]);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
        $this->assertSame('Anna', $result['data'][0]['nome']);
        $this->assertSame('Clienti', $result['data'][0]['categoria_nome']);
        $this->assertSame(2, (int) $result['data'][0]['num_ricorrenze']);
        $this->assertSame('Bruno', $result['data'][1]['nome']);
    }

    public function testFindAccessibleReturnsNullOwnerMetadataWhenUsersTableIsMissing(): void
    {
        $id = $this->insertRow('contacts', [
            'user_id' => 4,
            'categoria_id' => null,
            'nome' => 'Elena',
            'cognome' => 'Blu',
            'azienda' => 'Gamma',
            'email' => 'elena@example.com',
            'telefono' => '333',
            'tags' => null,
            'preferito' => 0,
            'created_at' => '2026-04-24 10:02:00',
            'updated_at' => '2026-04-24 10:02:00',
        ]);

        $contact = $this->repo->findAccessible($id, 4);

        $this->assertNotNull($contact);
        $this->assertSame('Elena', $contact['nome']);
        $this->assertArrayHasKey('owner_name', $contact);
        $this->assertNull($contact['owner_name']);
        $this->assertArrayHasKey('owner_email', $contact);
        $this->assertNull($contact['owner_email']);
    }

    public function testListSharesReturnsNullSharerNameWhenUsersTableIsMissing(): void
    {
        $this->migrate('
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            );
            CREATE TABLE contact_shares (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contatto_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                shared_by_user_id INTEGER NOT NULL,
                created_at TEXT NULL
            );
        ');

        $contattoId = $this->insertRow('contacts', [
            'user_id' => 4,
            'categoria_id' => null,
            'nome' => 'Luca',
            'cognome' => 'Riva',
            'azienda' => null,
            'email' => 'luca@example.com',
            'telefono' => '444',
            'tags' => null,
            'preferito' => 0,
            'created_at' => '2026-04-24 10:03:00',
            'updated_at' => '2026-04-24 10:03:00',
        ]);
        $roleId = $this->insertRow('roles', [
            'name' => 'Commerciale',
            'slug' => 'commerciale',
        ]);
        $this->insertRow('contact_shares', [
            'contatto_id' => $contattoId,
            'role_id' => $roleId,
            'shared_by_user_id' => 99,
            'created_at' => '2026-04-24 10:04:00',
        ]);

        $shares = $this->repo->listShares($contattoId);

        $this->assertCount(1, $shares);
        $this->assertSame('Commerciale', $shares[0]['role_name']);
        $this->assertArrayHasKey('shared_by_name', $shares[0]);
        $this->assertNull($shares[0]['shared_by_name']);
    }

    public function testTogglePreferitoAndGetAllTagsAndStats(): void
    {
        $id = $this->insertRow('contacts', [
            'user_id' => 9,
            'categoria_id' => null,
            'nome' => 'Marco',
            'cognome' => 'Neri',
            'azienda' => null,
            'email' => null,
            'telefono' => null,
            'tags' => 'vip, cliente, vip',
            'preferito' => 0,
            'created_at' => '2026-04-24 10:00:00',
            'updated_at' => '2026-04-24 10:00:00',
        ]);
        $this->insertRow('contacts', [
            'user_id' => 9,
            'categoria_id' => null,
            'nome' => 'Luisa',
            'cognome' => 'Bianchi',
            'azienda' => null,
            'email' => null,
            'telefono' => null,
            'tags' => 'fornitore',
            'preferito' => 1,
            'created_at' => '2026-04-24 10:00:01',
            'updated_at' => '2026-04-24 10:00:01',
        ]);

        $this->assertFalse($this->repo->getPreferito($id, 9));
        $this->assertTrue($this->repo->togglePreferito($id, 9));
        $this->assertTrue($this->repo->getPreferito($id, 9));

        $tags = $this->repo->getAllTags(9);
        $this->assertSame(['cliente', 'fornitore', 'vip'], $tags);

        $stats = $this->repo->getStats(9);
        $this->assertSame(2, $stats['totale']);
        $this->assertSame(2, $stats['preferiti']);
    }
}
