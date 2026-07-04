<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminSequenzeController;
use Tests\ControllerTestCase;

class AdminSequenzeControllerTest extends ControllerTestCase
{
    private int $categoriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_protocollo_sequenze (
                categoria_id  INTEGER NOT NULL,
                anno          INTEGER NOT NULL,
                ultimo_numero INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (categoria_id, anno)
            );
        ');

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersSequenze(): void
    {
        $this->pdo->exec("INSERT INTO documenti_protocollo_sequenze (categoria_id, anno, ultimo_numero) VALUES ({$this->categoriaId}, 2026, 5)");

        $result = $this->dispatch(AdminSequenzeController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['sequenze']);
    }

    public function testResetRemovesSequenzaRow(): void
    {
        $this->pdo->exec("INSERT INTO documenti_protocollo_sequenze (categoria_id, anno, ultimo_numero) VALUES ({$this->categoriaId}, 2026, 5)");

        $result = $this->withPost(['anno' => '2026'])
            ->dispatch(AdminSequenzeController::class, 'reset', [(string) $this->categoriaId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_protocollo_sequenze')->fetchColumn());
    }

    public function testResetRejectsInvalidYear(): void
    {
        $result = $this->withPost(['anno' => '1800'])
            ->dispatch(AdminSequenzeController::class, 'reset', [(string) $this->categoriaId]);

        $this->assertTrue($result->isRedirect());
    }
}
