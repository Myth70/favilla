<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\DocumentiController;
use Tests\ControllerTestCase;

class DocumentiControllerTest extends ControllerTestCase
{
    private int $categoriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE IF NOT EXISTS users (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_categorie (
                id                      INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id               INTEGER NULL,
                nome                    TEXT NOT NULL,
                slug                    TEXT NOT NULL,
                codice                  TEXT NOT NULL,
                descrizione             TEXT NULL,
                colore                  TEXT NULL,
                icona                   TEXT NULL,
                path                    TEXT NULL,
                depth                   INTEGER NOT NULL DEFAULT 0,
                approvazione_richiesta  INTEGER NOT NULL DEFAULT 1,
                reminder_giorni_default TEXT NULL,
                ordine                  INTEGER NOT NULL DEFAULT 0,
                created_by              INTEGER NULL,
                updated_by              INTEGER NULL,
                created_at              TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at              TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at              TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id                          INTEGER PRIMARY KEY AUTOINCREMENT,
                protocollo                  TEXT NULL,
                titolo                      TEXT NOT NULL,
                descrizione                 TEXT NULL,
                categoria_id                INTEGER NOT NULL,
                owner_user_id               INTEGER NOT NULL,
                versione_corrente_id        INTEGER NULL,
                file_corrente_id            INTEGER NULL,
                versione_no                 INTEGER NOT NULL DEFAULT 0,
                stato                       TEXT NOT NULL DEFAULT 'bozza',
                approvazione_richiesta      INTEGER NOT NULL DEFAULT 1,
                step_corrente               TEXT NOT NULL DEFAULT 'redazione',
                pubblicato_il               TEXT NULL,
                scade_il                    TEXT NULL,
                reminder_giorni             TEXT NULL,
                reminder_stage_inviato      INTEGER NOT NULL DEFAULT 0,
                reminder_ultimo_invio_at    TEXT NULL,
                reminder_destinatari_extra  TEXT NULL,
                lock_user_id                INTEGER NULL,
                lock_acquired_at            TEXT NULL,
                tag                         TEXT NULL,
                created_by                  INTEGER NULL,
                updated_by                  INTEGER NULL,
                created_at                  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at                  TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at                  TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_files (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name   TEXT NOT NULL,
                stored_name     TEXT NOT NULL,
                directory       TEXT NOT NULL,
                mime_type       TEXT NOT NULL,
                extension       TEXT NOT NULL,
                size_bytes      INTEGER NOT NULL DEFAULT 0,
                checksum_sha256 TEXT NULL,
                created_by      INTEGER NULL,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at      TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_versioni (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_id   INTEGER NOT NULL,
                versione_no    INTEGER NOT NULL,
                file_id        INTEGER NOT NULL,
                note_modifica  TEXT NULL,
                stato          TEXT NOT NULL DEFAULT 'bozza',
                ripristino_di  INTEGER NULL,
                created_by     INTEGER NULL,
                created_at     TEXT DEFAULT CURRENT_TIMESTAMP,
                pubblicato_il  TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_approvazioni (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_id INTEGER NOT NULL,
                versione_id  INTEGER NULL,
                step         TEXT NOT NULL,
                azione       TEXT NOT NULL,
                user_id      INTEGER NOT NULL,
                note         TEXT NULL,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS documenti_collegamenti (
                id                         INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_origine_id       INTEGER NOT NULL,
                documento_destinazione_id  INTEGER NOT NULL,
                tipo                       TEXT NOT NULL,
                note                       TEXT NULL,
                created_by                 INTEGER NULL,
                created_at                 TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS documenti_protocollo_sequenze (
                categoria_id  INTEGER NOT NULL,
                anno          INTEGER NOT NULL,
                ultimo_numero INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (categoria_id, anno)
            );
        ");

        $this->insertRow('users', ['name' => 'Alice']);
        $this->insertRow('users', ['name' => 'Bob']);

        $this->categoriaId = $this->insertRow('documenti_categorie', [
            'nome' => 'Generale', 'slug' => 'generale', 'codice' => 'GEN', 'path' => '/1/',
        ]);

        $this->actingAs(1, ['documenti.access', 'documenti.view', 'documenti.create', 'documenti.redazione', 'documenti.delete']);
    }

    private function insertDoc(array $overrides = []): int
    {
        return $this->insertRow('documenti', array_merge([
            'titolo'        => 'Documento test',
            'categoria_id'  => $this->categoriaId,
            'owner_user_id' => 1,
            'stato'         => 'bozza',
        ], $overrides));
    }

    public function testIndexRendersDocumentList(): void
    {
        $this->insertDoc(['titolo' => 'Doc A']);

        $result = $this->dispatch(DocumentiController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['result']['total']);
    }

    public function testCreateRendersFormWithCategories(): void
    {
        $result = $this->dispatch(DocumentiController::class, 'create', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['categorie']);
    }

    public function testStoreCreatesDocumentAndRedirects(): void
    {
        $result = $this->withPost(['titolo' => 'Nuovo documento', 'categoria_id' => (string) $this->categoriaId])
            ->dispatch(DocumentiController::class, 'store', []);

        $this->assertTrue($result->isRedirect());
        $row = $this->pdo->query("SELECT protocollo FROM documenti WHERE titolo = 'Nuovo documento'")->fetch();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row['protocollo']);
    }

    public function testStoreRejectsMissingTitolo(): void
    {
        $result = $this->withPost(['categoria_id' => (string) $this->categoriaId])
            ->dispatch(DocumentiController::class, 'store', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti')->fetchColumn());
    }

    public function testShowRendersVisibleDocument(): void
    {
        $docId = $this->insertDoc(['stato' => 'pubblicato']);

        $result = $this->dispatch(DocumentiController::class, 'show', [(string) $docId]);

        $this->assertTrue($result->didRender());
        $this->assertSame('Documento test', $result->renderedData()['doc']['titolo']);
    }

    public function testShowReturns404ForInvisibleDocument(): void
    {
        $this->actingAs(2, ['documenti.access', 'documenti.view']);
        $docId = $this->insertDoc(['owner_user_id' => 1, 'stato' => 'bozza']);

        $this->dispatch(DocumentiController::class, 'show', [(string) $docId]);

        $this->assertSame(404, http_response_code());
    }

    public function testEditRendersFormForOwner(): void
    {
        $docId = $this->insertDoc(['stato' => 'bozza']);

        $result = $this->dispatch(DocumentiController::class, 'edit', [(string) $docId]);

        $this->assertTrue($result->didRender());
    }

    public function testEditReturns403ForNonOwner(): void
    {
        $this->actingAs(2, ['documenti.access', 'documenti.view', 'documenti.redazione']);
        $docId = $this->insertDoc(['owner_user_id' => 1, 'stato' => 'pubblicato']);

        $this->dispatch(DocumentiController::class, 'edit', [(string) $docId]);

        $this->assertSame(403, http_response_code());
    }

    public function testUpdateUpdatesDocumentAndRedirects(): void
    {
        $docId = $this->insertDoc(['stato' => 'bozza']);

        $result = $this->withPost(['titolo' => 'Titolo aggiornato'])
            ->dispatch(DocumentiController::class, 'update', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Titolo aggiornato', $this->pdo->query("SELECT titolo FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testDestroyDeletesDocumentAndRedirects(): void
    {
        $docId = $this->insertDoc();

        $result = $this->dispatch(DocumentiController::class, 'destroy', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testInboxRendersInboxForUserWithPermission(): void
    {
        $this->actingAs(1, ['documenti.access', 'documenti.controllo']);
        $this->insertDoc(['stato' => 'inviato']);

        $result = $this->dispatch(DocumentiController::class, 'inbox', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['result']['total']);
    }

    public function testScadenzeRendersUpcomingDeadlines(): void
    {
        $this->insertDoc(['stato' => 'pubblicato', 'scade_il' => date('Y-m-d H:i:s', strtotime('+5 days'))]);

        $result = $this->dispatch(DocumentiController::class, 'scadenze', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['result']['total']);
    }

    public function testTreeRendersPartial(): void
    {
        $result = $this->dispatch(DocumentiController::class, 'tree', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['categorie']);
    }
}
