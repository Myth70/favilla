<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminDashboardController;
use Tests\ControllerTestCase;

class AdminDashboardControllerTest extends ControllerTestCase
{
    private int $categoriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE IF NOT EXISTS documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                reminder_giorni_default TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titolo TEXT NOT NULL,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                scade_il TEXT NULL,
                reminder_giorni TEXT NULL,
                reminder_stage_inviato INTEGER NOT NULL DEFAULT 0,
                reminder_ultimo_invio_at TEXT NULL,
                reminder_destinatari_extra TEXT NULL,
                updated_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
        ");

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersKpiByStato(): void
    {
        $this->insertRow('documenti', ['titolo' => 'A', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1, 'stato' => 'bozza']);
        $this->insertRow('documenti', ['titolo' => 'B', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1, 'stato' => 'pubblicato']);

        $result = $this->dispatch(AdminDashboardController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertSame(1, $result->renderedData()['stats']['bozza']);
        $this->assertSame(1, $result->renderedData()['stats']['pubblicato']);
    }

    public function testRunRemindersReturnsSuccessRedirect(): void
    {
        $result = $this->dispatch(AdminDashboardController::class, 'runReminders', []);

        $this->assertTrue($result->isRedirect());
    }

    public function testRunExpireExpiresPublishedOverdueDocuments(): void
    {
        $this->insertRow('documenti', [
            'titolo' => 'Scaduto', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1,
            'stato' => 'pubblicato', 'scade_il' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $this->dispatch(AdminDashboardController::class, 'runExpire', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('scaduto', $this->pdo->query('SELECT stato FROM documenti')->fetchColumn());
    }
}
