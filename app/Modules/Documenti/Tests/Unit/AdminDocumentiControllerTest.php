<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminDocumentiController;
use Tests\ControllerTestCase;

class AdminDocumentiControllerTest extends ControllerTestCase
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
                path TEXT NULL,
                ordine INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                protocollo TEXT NULL,
                titolo TEXT NOT NULL,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                scade_il TEXT NULL,
                updated_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
        ");

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN', 'path' => '/1/']);
        $this->insertRow('users', ['name' => 'Alice']);
        $this->insertRow('users', ['name' => 'Bob']);

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testElencoRendersAllDocumentsBypassingVisibility(): void
    {
        $this->insertRow('documenti', ['titolo' => 'Bozza altrui', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 2, 'stato' => 'bozza']);

        $result = $this->dispatch(AdminDocumentiController::class, 'elenco', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['result']['total']);
    }

    public function testRiassegnaOwnerUpdatesOwner(): void
    {
        $docId = $this->insertRow('documenti', ['titolo' => 'Doc', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1]);

        $result = $this->withPost(['owner_user_id' => '2'])
            ->dispatch(AdminDocumentiController::class, 'riassegnaOwner', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(2, (int) $this->pdo->query("SELECT owner_user_id FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testRiassegnaOwnerRejectsUnknownUser(): void
    {
        $docId = $this->insertRow('documenti', ['titolo' => 'Doc', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1]);

        $result = $this->withPost(['owner_user_id' => '999'])
            ->dispatch(AdminDocumentiController::class, 'riassegnaOwner', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query("SELECT owner_user_id FROM documenti WHERE id = {$docId}")->fetchColumn());
    }
}
