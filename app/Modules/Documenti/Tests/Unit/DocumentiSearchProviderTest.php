<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Providers\DocumentiSearchProvider;
use Tests\ControllerTestCase;

/**
 * Verifica che la ricerca globale rispetti la visibility dei documenti
 * (coerente con DocumentoRepository::listPaginated()): un utente senza
 * documenti.admin non deve trovare bozze o documenti rifiutati altrui,
 * solo i propri e quelli in stato pubblico.
 */
class DocumentiSearchProviderTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titolo TEXT NOT NULL,
                descrizione TEXT,
                tag TEXT,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                scade_il TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT DEFAULT NULL
            );
        ");

        $catId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);
        // Bozza di proprietà dell'utente 2 (non l'utente che cerca).
        $this->insertRow('documenti', [
            'titolo' => 'Bozza Riservata Fusione Aziendale', 'categoria_id' => $catId,
            'owner_user_id' => 2, 'stato' => 'bozza',
        ]);
        // Documento pubblicato, visibile a tutti.
        $this->insertRow('documenti', [
            'titolo' => 'Bozza Riservata Procedura Pubblica', 'categoria_id' => $catId,
            'owner_user_id' => 2, 'stato' => 'pubblicato',
        ]);
    }

    public function testNonAdminUserDoesNotSeeOthersDrafts(): void
    {
        $this->actingAs(1, ['documenti.access']);

        $results = (new DocumentiSearchProvider())->search('Bozza Riservata', 1, 5);

        $titles = array_column($results, 'title');
        $this->assertNotContains('Bozza Riservata Fusione Aziendale', $titles, 'La bozza altrui non deve comparire nei risultati di ricerca.');
        $this->assertContains('Bozza Riservata Procedura Pubblica', $titles);
    }

    public function testOwnerSeesTheirOwnDraft(): void
    {
        $this->actingAs(2, ['documenti.access']);

        $results = (new DocumentiSearchProvider())->search('Bozza Riservata', 2, 5);

        $titles = array_column($results, 'title');
        $this->assertContains('Bozza Riservata Fusione Aziendale', $titles);
    }

    public function testAdminSeesAllDocuments(): void
    {
        $this->actingAs(3, ['documenti.access', 'documenti.admin']);

        $results = (new DocumentiSearchProvider())->search('Bozza Riservata', 3, 5);

        $this->assertCount(2, $results);
    }
}
