<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Repositories\DocumentoCollegamentoRepository;
use Tests\ModuleTestCase;

/**
 * Verifica che findByDocumento() esponga titolo_collegato/protocollo_collegato/stato_collegato:
 * il partial Views/partials/pannello_collegamenti.php legge esattamente queste chiavi
 * per mostrare il titolo del documento collegato (altrimenti ricade silenziosamente
 * sul fallback generico "Documento #N").
 */
class DocumentoCollegamentoRepositoryTest extends ModuleTestCase
{
    private DocumentoCollegamentoRepository $repo;
    private int $docA;
    private int $docB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titolo TEXT NOT NULL,
                protocollo TEXT,
                stato TEXT DEFAULT 'pubblicato',
                deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE documenti_collegamenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                documento_origine_id INTEGER NOT NULL,
                documento_destinazione_id INTEGER NOT NULL,
                tipo TEXT NOT NULL,
                note TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->docA = $this->insertRow('documenti', ['titolo' => 'Documento Origine', 'protocollo' => 'DOC-GEN-2026-0001']);
        $this->docB = $this->insertRow('documenti', ['titolo' => 'Documento Destinazione', 'protocollo' => 'DOC-GEN-2026-0002']);
        $this->insertRow('documenti_collegamenti', [
            'documento_origine_id'      => $this->docA,
            'documento_destinazione_id' => $this->docB,
            'tipo'                      => 'correlato',
        ]);

        $this->repo = new DocumentoCollegamentoRepository();
    }

    public function testFindByDocumentoEsponeTitoloCollegatoPerLaView(): void
    {
        $rows = $this->repo->findByDocumento($this->docA);

        $this->assertCount(1, $rows);
        $this->assertSame('Documento Destinazione', $rows[0]['titolo_collegato'] ?? null);
        $this->assertSame('DOC-GEN-2026-0002', $rows[0]['protocollo_collegato'] ?? null);
        $this->assertSame('pubblicato', $rows[0]['stato_collegato'] ?? null);
        $this->assertSame($this->docB, (int) $rows[0]['documento_destinazione_id']);
    }
}
