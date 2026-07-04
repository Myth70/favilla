<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminTrashController;
use Tests\ControllerTestCase;

class AdminTrashControllerTest extends ControllerTestCase
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
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                protocollo TEXT NULL,
                titolo TEXT NOT NULL,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                updated_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_versioni (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_id INTEGER NOT NULL,
                versione_no INTEGER NOT NULL,
                file_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);
        $this->insertRow('users', ['name' => 'Alice']);

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersOnlyTrashedDocuments(): void
    {
        $this->insertRow('documenti', ['titolo' => 'Attivo', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1]);
        $this->insertRow('documenti', [
            'titolo' => 'Nel cestino', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1,
            'deleted_at' => '2026-01-01 00:00:00',
        ]);

        $result = $this->dispatch(AdminTrashController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['total']);
    }

    public function testRestoreClearsDeletedAt(): void
    {
        $docId = $this->insertRow('documenti', [
            'titolo' => 'Nel cestino', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1,
            'deleted_at' => '2026-01-01 00:00:00',
        ]);

        $result = $this->dispatch(AdminTrashController::class, 'restore', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertNull($this->pdo->query("SELECT deleted_at FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testPurgeRemovesDocumentPermanently(): void
    {
        $docId = $this->insertRow('documenti', [
            'titolo' => 'Nel cestino', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1,
            'deleted_at' => '2026-01-01 00:00:00',
        ]);

        $result = $this->dispatch(AdminTrashController::class, 'purge', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti')->fetchColumn());
    }

    public function testPurgeRefusesActiveDocument(): void
    {
        $docId = $this->insertRow('documenti', ['titolo' => 'Attivo', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1]);

        $result = $this->dispatch(AdminTrashController::class, 'purge', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti')->fetchColumn());
    }
}
