<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\VersioniController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for VersioniController.
 *
 * store()'s happy path calls move_uploaded_file(), which PHP only allows
 * against files genuinely uploaded via an HTTP request — it always fails
 * (returns false) against a manually-created temp file in a unit test, so
 * only the "no file selected" validation branch of store() is covered here.
 * The actual upload path is covered by manual QA (Gate 3).
 */
class VersioniControllerTest extends ControllerTestCase
{
    private int $categoriaId;
    private int $docId;
    private int $fileId;

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
                scade_il TEXT NULL,
                tag TEXT NULL,
                created_by INTEGER NULL,
                updated_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                directory TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                extension TEXT NOT NULL,
                size_bytes INTEGER NOT NULL DEFAULT 0,
                checksum_sha256 TEXT NULL,
                created_by INTEGER NULL,
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
        ");

        $this->categoriaId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);
        $this->docId = $this->insertRow('documenti', [
            'titolo' => 'Doc test', 'categoria_id' => $this->categoriaId, 'owner_user_id' => 1, 'stato' => 'pubblicato',
        ]);
        $this->fileId = $this->insertRow('documenti_files', [
            'original_name' => 'doc.pdf', 'stored_name' => 'stored_doc.pdf', 'directory' => '2026/01',
            'mime_type' => 'application/pdf', 'extension' => 'pdf',
        ]);

        $this->actingAs(1, ['documenti.access', 'documenti.view', 'documenti.redazione']);
    }

    public function testStoreRejectsWhenNoFileSelected(): void
    {
        $result = $this->withPost(['note' => 'test'])
            ->dispatch(VersioniController::class, 'store', [(string) $this->docId]);

        $this->assertTrue($result->isRedirect());
    }

    public function testDownloadReturns404ForInvisibleDocument(): void
    {
        $this->actingAs(2, ['documenti.access', 'documenti.view']);
        $this->pdo->exec("UPDATE documenti SET stato = 'bozza' WHERE id = {$this->docId}");
        $verId = $this->insertRow('documenti_versioni', [
            'documento_id' => $this->docId, 'versione_no' => 1, 'file_id' => $this->fileId,
        ]);

        $this->dispatch(VersioniController::class, 'download', [(string) $this->docId, (string) $verId]);

        $this->assertSame(404, http_response_code());
    }

    public function testDownloadReturns404WhenPhysicalFileMissing(): void
    {
        $verId = $this->insertRow('documenti_versioni', [
            'documento_id' => $this->docId, 'versione_no' => 1, 'file_id' => $this->fileId,
        ]);

        $this->dispatch(VersioniController::class, 'download', [(string) $this->docId, (string) $verId]);

        $this->assertSame(404, http_response_code());
    }

    public function testPreviewReturns404WhenPhysicalFileMissing(): void
    {
        $verId = $this->insertRow('documenti_versioni', [
            'documento_id' => $this->docId, 'versione_no' => 1, 'file_id' => $this->fileId,
        ]);

        $this->dispatch(VersioniController::class, 'preview', [(string) $this->docId, (string) $verId]);

        $this->assertSame(404, http_response_code());
    }

    public function testRipristinaRestoresOlderVersion(): void
    {
        $verId = $this->insertRow('documenti_versioni', [
            'documento_id' => $this->docId, 'versione_no' => 1, 'file_id' => $this->fileId, 'note_modifica' => 'v1',
        ]);
        $this->pdo->exec("UPDATE documenti SET versione_no = 1, versione_corrente_id = {$verId}, file_corrente_id = {$this->fileId} WHERE id = {$this->docId}");

        $result = $this->dispatch(VersioniController::class, 'ripristina', [(string) $this->docId, (string) $verId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(2, (int) $this->pdo->query("SELECT versione_no FROM documenti WHERE id = {$this->docId}")->fetchColumn());
    }
}
