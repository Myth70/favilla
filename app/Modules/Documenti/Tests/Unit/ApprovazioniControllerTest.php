<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\ApprovazioniController;
use Tests\ControllerTestCase;

class ApprovazioniControllerTest extends ControllerTestCase
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
                deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                protocollo TEXT NULL,
                titolo TEXT NOT NULL,
                categoria_id INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL,
                versione_corrente_id INTEGER NULL,
                file_corrente_id INTEGER NULL,
                versione_no INTEGER NOT NULL DEFAULT 0,
                stato TEXT NOT NULL DEFAULT 'bozza',
                approvazione_richiesta INTEGER NOT NULL DEFAULT 1,
                step_corrente TEXT NOT NULL DEFAULT 'redazione',
                pubblicato_il TEXT NULL,
                scade_il TEXT NULL,
                tag TEXT NULL,
                created_by INTEGER NULL,
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
                note_modifica TEXT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                ripristino_di INTEGER NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                pubblicato_il TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_approvazioni (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_id INTEGER NOT NULL,
                versione_id INTEGER NULL,
                step TEXT NOT NULL,
                azione TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                note TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);

        $this->actingAs(1, ['documenti.access', 'documenti.redazione', 'documenti.controllo', 'documenti.approvazione', 'documenti.admin']);
    }

    private function insertDoc(array $overrides = []): int
    {
        return $this->insertRow('documenti', array_merge([
            'titolo' => 'Doc test', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1, 'stato' => 'bozza',
            'versione_corrente_id' => 1,
        ], $overrides));
    }

    public function testInviaTransitionsToInviato(): void
    {
        $docId = $this->insertDoc(['stato' => 'bozza']);

        $result = $this->dispatch(ApprovazioniController::class, 'invia', [(string) $docId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('inviato', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testPrendeInCaricoTransitionsToInControllo(): void
    {
        $docId = $this->insertDoc(['stato' => 'inviato']);

        $this->dispatch(ApprovazioniController::class, 'prendeInCarico', [(string) $docId]);

        $this->assertSame('in_controllo', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testApprovaFromInControlloTransitionsToControllato(): void
    {
        $docId = $this->insertDoc(['stato' => 'in_controllo']);

        $this->dispatch(ApprovazioniController::class, 'approva', [(string) $docId]);

        $this->assertSame('controllato', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testRifiutaFromInControlloTransitionsToRifiutato(): void
    {
        $docId = $this->insertDoc(['stato' => 'in_controllo']);

        $this->dispatch(ApprovazioniController::class, 'rifiuta', [(string) $docId]);

        $this->assertSame('rifiutato', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testRestituisciFromInControlloTransitionsToInviato(): void
    {
        $docId = $this->insertDoc(['stato' => 'in_controllo']);

        $this->dispatch(ApprovazioniController::class, 'restituisci', [(string) $docId]);

        $this->assertSame('inviato', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testPubblicaFromApprovatoTransitionsToPubblicato(): void
    {
        $docId = $this->insertDoc(['stato' => 'approvato']);

        $this->dispatch(ApprovazioniController::class, 'pubblica', [(string) $docId]);

        $this->assertSame('pubblicato', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testRiprendiFromRifiutatoTransitionsToBozza(): void
    {
        $docId = $this->insertDoc(['stato' => 'rifiutato']);

        $this->dispatch(ApprovazioniController::class, 'riprendi', [(string) $docId]);

        $this->assertSame('bozza', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testRitiraFromInviatoTransitionsToBozza(): void
    {
        $docId = $this->insertDoc(['stato' => 'inviato']);

        $this->dispatch(ApprovazioniController::class, 'ritira', [(string) $docId]);

        $this->assertSame('bozza', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testArchiviaFromPubblicatoTransitionsToArchiviato(): void
    {
        $docId = $this->insertDoc(['stato' => 'pubblicato']);

        $this->dispatch(ApprovazioniController::class, 'archivia', [(string) $docId]);

        $this->assertSame('archiviato', $this->pdo->query("SELECT stato FROM documenti WHERE id = {$docId}")->fetchColumn());
    }

    public function testInvalidTransitionReturns422OnHtmxRequest(): void
    {
        $docId = $this->insertDoc(['stato' => 'pubblicato']);

        $result = $this->asHtmx()->dispatch(ApprovazioniController::class, 'invia', [(string) $docId]);

        $this->assertSame(422, http_response_code());
    }
}
