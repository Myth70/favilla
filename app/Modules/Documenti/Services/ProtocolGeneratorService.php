<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoCategoriaRepository;
use App\Modules\Documenti\Repositories\DocumentoSequenzaRepository;

/**
 * Genera il protocollo DOC-{CODICE}-{YYYY}-{NNNN} in modo atomico.
 * L'incremento + lettura della sequenza avviene dentro una transaction.
 */
class ProtocolGeneratorService
{
    private DocumentoCategoriaRepository $catRepo;
    private DocumentoSequenzaRepository  $seqRepo;
    private \PDO $pdo;

    public function __construct()
    {
        $this->catRepo = app(DocumentoCategoriaRepository::class);
        $this->seqRepo = app(DocumentoSequenzaRepository::class);
        $this->pdo     = app(\PDO::class);
    }

    /**
     * Genera il prossimo numero di protocollo per la categoria e l'anno indicati.
     *
     * @param  int  $categoriaId
     * @param  int  $anno         Anno (default: anno corrente)
     * @return string              es. "DOC-QUAL-2026-0001"
     * @throws \RuntimeException
     */
    public function generate(int $categoriaId, int $anno = 0): string
    {
        if ($anno === 0) {
            $anno = (int) date('Y');
        }

        $categoria = $this->catRepo->find($categoriaId);
        if (!$categoria) {
            throw new \RuntimeException(t('documenti.exception.categoria_non_trovata', ['id' => $categoriaId]));
        }

        $codice = strtoupper(trim($categoria['codice']));
        if ($codice === '') {
            throw new \RuntimeException(t('documenti.exception.categoria_senza_codice'));
        }

        $this->pdo->beginTransaction();
        try {
            $numero = $this->seqRepo->incrementAndGet($categoriaId, $anno);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException(t('documenti.exception.protocollo_non_generato', ['error' => $e->getMessage()]), 0, $e);
        }

        return sprintf('DOC-%s-%d-%04d', $codice, $anno, $numero);
    }

    /**
     * Tutte le sequenze protocollo (per categoria + anno), per il pannello admin.
     */
    public function tutteLeSequenze(): array
    {
        return $this->seqRepo->allSequenze();
    }

    /**
     * Azzera la sequenza di una categoria per un dato anno.
     */
    public function azzeraSequenza(int $categoriaId, int $anno): void
    {
        $this->seqRepo->resetSequenza($categoriaId, $anno);
    }
}
