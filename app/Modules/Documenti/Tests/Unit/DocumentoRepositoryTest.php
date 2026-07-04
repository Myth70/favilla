<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Repositories\DocumentoRepository;
use Tests\ModuleTestCase;

/**
 * Verifica B3: il filtro di visibility usa parentesi attorno all'OR.
 * Un utente non admin che NON è owner non deve vedere bozze altrui
 * anche quando applica un filtro per categoria.
 */
class DocumentoRepositoryTest extends ModuleTestCase
{
    private DocumentoRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Schema SQLite compatibile (TEXT al posto di ENUM, INTEGER al posto di UNSIGNED).
        $this->migrate("
            CREATE TABLE documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                protocollo TEXT,
                titolo TEXT NOT NULL,
                descrizione TEXT,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                versione_no INTEGER DEFAULT 0,
                stato TEXT DEFAULT 'bozza',
                approvazione_richiesta INTEGER DEFAULT 1,
                step_corrente TEXT DEFAULT 'redazione',
                scade_il TEXT,
                pubblicato_il TEXT,
                tag TEXT,
                created_by INTEGER,
                updated_by INTEGER,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT DEFAULT NULL
            );
        ");

        $this->repo = new DocumentoRepository();
    }

    private function insertDoc(array $overrides = []): int
    {
        $data = array_merge([
            'titolo'        => 'Doc',
            'categoria_id'  => 1,
            'owner_user_id' => 1,
            'stato'         => 'pubblicato',
        ], $overrides);
        return $this->insertRow('documenti', $data);
    }

    public function testUserNonOwnerCannotSeeBozzeOfOthers(): void
    {
        $this->insertRow('documenti_categorie', ['nome' => 'Qualità', 'codice' => 'QUAL']);

        $this->insertDoc(['owner_user_id' => 99, 'stato' => 'bozza',     'titolo' => 'Bozza altrui']);
        $this->insertDoc(['owner_user_id' => 99, 'stato' => 'rifiutato', 'titolo' => 'Rifiutato altrui']);
        $this->insertDoc(['owner_user_id' => 1,  'stato' => 'pubblicato','titolo' => 'Pubblicato mio']);

        // User 1, NON admin: deve vedere solo "Pubblicato mio". Non le bozze altrui.
        $result = $this->repo->listPaginated([
            'current_user_id' => 1,
            'categoria_id'    => 1,
        ], false);

        $this->assertSame(1, $result['total'], 'Solo i documenti pubblici devono essere visibili');
        $this->assertSame('Pubblicato mio', $result['data'][0]['titolo']);
    }

    public function testOwnerSeesOwnBozze(): void
    {
        $this->insertRow('documenti_categorie', ['nome' => 'Qualità', 'codice' => 'QUAL']);

        $this->insertDoc(['owner_user_id' => 1, 'stato' => 'bozza',      'titolo' => 'Mia bozza']);
        $this->insertDoc(['owner_user_id' => 1, 'stato' => 'pubblicato', 'titolo' => 'Mio pubblicato']);

        $result = $this->repo->listPaginated([
            'current_user_id' => 1,
        ], false);

        $this->assertSame(2, $result['total'], 'L\'owner vede sia bozze che pubblicati propri');
    }

    public function testAdminModeBypassesVisibility(): void
    {
        $this->insertRow('documenti_categorie', ['nome' => 'Qualità', 'codice' => 'QUAL']);

        $this->insertDoc(['owner_user_id' => 99, 'stato' => 'bozza',     'titolo' => 'A']);
        $this->insertDoc(['owner_user_id' => 99, 'stato' => 'rifiutato', 'titolo' => 'B']);
        $this->insertDoc(['owner_user_id' => 1,  'stato' => 'pubblicato','titolo' => 'C']);

        $result = $this->repo->listPaginated(['current_user_id' => 1], true);
        $this->assertSame(3, $result['total']);
    }

    public function testSortWhitelistRejectsArbitraryColumn(): void
    {
        $this->insertRow('documenti_categorie', ['nome' => 'Qualità', 'codice' => 'QUAL']);
        $this->insertDoc(['owner_user_id' => 1, 'stato' => 'pubblicato']);

        // sort non in whitelist deve fallback a 'created_at', non causare SQL injection
        $result = $this->repo->listPaginated([
            'current_user_id' => 1,
            'sort'            => '(SELECT password FROM users)',
        ], true);

        $this->assertSame(1, $result['total']);
    }
}
