<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoCollegamentoRepository;

/**
 * Gestisce collegamenti tipizzati tra documenti.
 * Crea sempre la coppia inversa in modo atomico.
 */
class CollegamentoService
{
    private DocumentoCollegamentoRepository $repo;
    private \PDO $pdo;

    private const INVERSI = [
        'sostituisce'   => 'sostituito_da',
        'sostituito_da' => 'sostituisce',
        'allegato'      => 'allegato',
        'correlato'     => 'correlato',
        'riferimento'   => 'riferimento',
    ];

    public function __construct()
    {
        $this->repo = app(DocumentoCollegamentoRepository::class);
        $this->pdo  = app(\PDO::class);
    }

    /**
     * Crea coppia bidirezionale in una transaction.
     *
     * @throws \RuntimeException
     */
    public function crea(int $origineId, int $destinazioneId, string $tipo, ?string $note, int $userId): int
    {
        if ($origineId === $destinazioneId) {
            throw new \RuntimeException(t('documenti.exception.collegamento_auto'));
        }

        if (!array_key_exists($tipo, self::INVERSI)) {
            throw new \RuntimeException(t('documenti.exception.tipo_collegamento_non_valido', ['tipo' => $tipo]));
        }

        $this->pdo->beginTransaction();
        try {
            if ($this->repo->exists($origineId, $destinazioneId, $tipo)) {
                $this->pdo->rollBack();
                throw new \RuntimeException(t('documenti.exception.collegamento_esistente'));
            }

            $id = $this->repo->create([
                'documento_origine_id'      => $origineId,
                'documento_destinazione_id' => $destinazioneId,
                'tipo'                      => $tipo,
                'note'                      => $note,
                'created_by'                => $userId,
            ]);

            // Coppia inversa
            $tipoInverso = self::INVERSI[$tipo];
            if (!$this->repo->exists($destinazioneId, $origineId, $tipoInverso)) {
                $this->repo->create([
                    'documento_origine_id'      => $destinazioneId,
                    'documento_destinazione_id' => $origineId,
                    'tipo'                      => $tipoInverso,
                    'note'                      => $note,
                    'created_by'                => $userId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

        return $id;
    }

    /**
     * Rimuove un collegamento (non rimuove il reciproco per sicurezza).
     */
    public function rimuovi(int $collegamentoId, int $documentoId): void
    {
        $c = $this->repo->findById($collegamentoId);
        if (!$c || (int) $c['documento_origine_id'] !== $documentoId) {
            throw new \RuntimeException(t('documenti.exception.collegamento_non_trovato'));
        }
        $this->repo->deleteById($collegamentoId);
    }

    /**
     * Collegamenti uscenti di un documento (per il pannello e i re-render HTMX).
     */
    public function elencoPerDocumento(int $documentoId): array
    {
        return $this->repo->findByDocumento($documentoId);
    }
}
