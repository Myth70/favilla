<?php

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Repositories\RecurrencesRepository;
use Tests\ModuleTestCase;

class RecurrencesRepositoryTest extends ModuleTestCase
{
    private RecurrencesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->sqliteCreateFunction('MONTH', static fn (string $d): int => (int) date('m', strtotime($d)), 1);
        $this->pdo->sqliteCreateFunction('DAY', static fn (string $d): int => (int) date('d', strtotime($d)), 1);

        $this->migrate('
            CREATE TABLE contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                cognome TEXT NULL,
                avatar TEXT NULL
            );
            CREATE TABLE contact_recurrences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contatto_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                tipo TEXT NULL,
                titolo TEXT NULL,
                data_ricorrenza TEXT NOT NULL,
                annuale INTEGER NOT NULL DEFAULT 1,
                anno_riferimento INTEGER NULL,
                promemoria_giorni_prima INTEGER NULL,
                notifica_giorno_stesso INTEGER NULL,
                crea_evento_calendario INTEGER NULL,
                calendario_event_id INTEGER NULL,
                note TEXT NULL,
                last_notified_year INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
        ');

        $this->repo = new RecurrencesRepository();
    }

    public function testAllForContattoOrdersByMonthAndDay(): void
    {
        $contact = $this->insertRow('contacts', ['nome' => 'Mario', 'cognome' => 'Rossi', 'avatar' => null]);

        $this->insertRow('contact_recurrences', [
            'contatto_id' => $contact,
            'user_id' => 3,
            'titolo' => 'Dicembre',
            'data_ricorrenza' => '2026-12-10',
        ]);
        $this->insertRow('contact_recurrences', [
            'contatto_id' => $contact,
            'user_id' => 3,
            'titolo' => 'Gennaio',
            'data_ricorrenza' => '2026-01-05',
        ]);

        $rows = $this->repo->allForContatto($contact);

        $this->assertCount(2, $rows);
        $this->assertSame('Gennaio', $rows[0]['titolo']);
        $this->assertSame('Dicembre', $rows[1]['titolo']);
    }

    public function testAllForUserWithContattoAndUpdateHelpers(): void
    {
        $contact = $this->insertRow('contacts', ['nome' => 'Luca', 'cognome' => 'Bianchi', 'avatar' => 'avatar.png']);

        $recId = $this->insertRow('contact_recurrences', [
            'contatto_id' => $contact,
            'user_id' => 8,
            'titolo' => 'Compleanno',
            'data_ricorrenza' => '2026-04-30',
            'last_notified_year' => null,
            'calendario_event_id' => null,
        ]);

        $rows = $this->repo->allForUserWithContatto(8);
        $this->assertCount(1, $rows);
        $this->assertSame('Luca', $rows[0]['nome']);

        $this->repo->updateLastNotified($recId, 2026);
        $this->repo->updateCalendarEventId($recId, 77);

        $updated = $this->repo->findForUser($recId, 8);
        $this->assertSame(2026, (int) $updated['last_notified_year']);
        $this->assertSame(77, (int) $updated['calendario_event_id']);

        $this->assertNull($this->repo->findForUser($recId, 9));
    }
}
