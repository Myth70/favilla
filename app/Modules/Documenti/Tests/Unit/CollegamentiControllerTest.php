<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\CollegamentiController;
use Tests\ControllerTestCase;

class CollegamentiControllerTest extends ControllerTestCase
{
    private int $categoriaId;
    private int $docA;
    private int $docB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE IF NOT EXISTS documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                protocollo TEXT NULL,
                titolo TEXT NOT NULL,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'pubblicato',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_collegamenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_origine_id INTEGER NOT NULL,
                documento_destinazione_id INTEGER NOT NULL,
                tipo TEXT NOT NULL,
                note TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);
        $this->docA = $this->insertRow('documenti', ['titolo' => 'Doc A', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1]);
        $this->docB = $this->insertRow('documenti', ['titolo' => 'Doc B', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1]);

        $this->actingAs(1, ['documenti.access', 'documenti.view', 'documenti.manage_collegamenti']);
    }

    public function testStoreCreatesBidirectionalLink(): void
    {
        $result = $this->withPost(['destinazione_id' => (string) $this->docB, 'tipo' => 'correlato'])
            ->dispatch(CollegamentiController::class, 'store', [(string) $this->docA]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(
            2,
            (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_collegamenti')->fetchColumn()
        );
    }

    public function testStoreRejectsMissingTipo(): void
    {
        $result = $this->withPost(['destinazione_id' => (string) $this->docB])
            ->dispatch(CollegamentiController::class, 'store', [(string) $this->docA]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_collegamenti')->fetchColumn());
    }

    public function testDestroyRemovesLink(): void
    {
        $collId = $this->insertRow('documenti_collegamenti', [
            'documento_origine_id' => $this->docA, 'documento_destinazione_id' => $this->docB, 'tipo' => 'correlato',
        ]);

        $result = $this->dispatch(CollegamentiController::class, 'destroy', [(string) $this->docA, (string) $collId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_collegamenti')->fetchColumn());
    }
}
