<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoRepository;
use App\Modules\Documenti\Repositories\DocumentoVersioneRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;

/**
 * Gestisce il versionamento immutabile dei documenti.
 * Ogni nuova versione viene creata con lock pessimistico.
 */
class VersioningService
{
    private DocumentoRepository        $docRepo;
    private DocumentoVersioneRepository $verRepo;
    private DocumentiStorageService    $storage;
    private \PDO $pdo;

    public function __construct()
    {
        $this->docRepo  = app(DocumentoRepository::class);
        $this->verRepo  = app(DocumentoVersioneRepository::class);
        $this->storage  = app(DocumentiStorageService::class);
        $this->pdo      = app(\PDO::class);
    }

    /**
     * Carica una nuova versione con lock pessimistico.
     *
     * @param  int   $documentoId
     * @param  array $uploadedFile   Entry da $_FILES
     * @param  string|null $note
     * @param  int   $userId
     * @return int   ID della nuova versione
     * @throws \RuntimeException
     */
    public function caricaNuovaVersione(int $documentoId, array $uploadedFile, ?string $note, int $userId): int
    {
        // Upload fisico fuori dalla transaction: tenere il lock InnoDB durante I/O
        // di rete/disco sarebbe peggio. Su rollback DB facciamo cleanup esplicito.
        $fileId = $this->storage->store($uploadedFile, $userId);

        $this->pdo->beginTransaction();
        try {
            $doc = $this->docRepo->findForUpdate($documentoId);
            if (!$doc) {
                throw new \RuntimeException(t('documenti.exception.documento_id_non_trovato', ['id' => $documentoId]));
            }

            $newNo = $this->verRepo->maxVersioneNo($documentoId) + 1;

            $versioneId = $this->verRepo->create([
                'documento_id'  => $documentoId,
                'versione_no'   => $newNo,
                'file_id'       => $fileId,
                'note_modifica' => $note,
                'stato'         => 'bozza',
                'created_by'    => $userId,
            ]);

            $this->docRepo->update($documentoId, [
                'versione_no'          => $newNo,
                'versione_corrente_id' => $versioneId,
                'file_corrente_id'     => $fileId,
                'updated_by'           => $userId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Cleanup file orfano: il record DB è andato via, anche il file deve sparire.
            try {
                $this->storage->cleanup($fileId);
            } catch (\Throwable) {
                // Cleanup è best-effort; il job documenti:cleanup-orphans recupera il resto.
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

        AuditService::log(
            'documento.versione_caricata',
            'documento.versione',
            $versioneId,
            [],
            ['documento_id' => $documentoId, 'versione_no' => $newNo, 'created_by' => $userId]
        );

        $this->notificaNuovaVersione($documentoId, $doc, $newNo, $userId);

        return $versioneId;
    }

    /**
     * Avvisa i gruppi controllo/approvazione che è disponibile una nuova versione.
     * Best-effort: non deve mai bloccare il caricamento.
     */
    private function notificaNuovaVersione(int $documentoId, array $doc, int $versioneNo, int $userId): void
    {
        try {
            $recipientSvc = app(DocumentiRecipientService::class);
            $destinatari  = array_unique(array_merge(
                $recipientSvc->usersWithPermission('documenti.controllo'),
                $recipientSvc->usersWithPermission('documenti.approvazione')
            ));
            $context = [
                'documento_id'     => $documentoId,
                'documento_titolo' => $doc['titolo'] ?? '',
                'versione_no'      => $versioneNo,
            ];
            $link = route('documenti.show', ['id' => $documentoId]) . '#dc-versioni-container';
            foreach ($destinatari as $destId) {
                if ((int) $destId > 0 && (int) $destId !== $userId) {
                    NotificationService::dispatchEventToUser('documenti.nuova_versione', 'Documenti', (int) $destId, $context, $link);
                }
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /**
     * Ripristina una versione precedente creando una nuova versione con ripristino_di.
     *
     * @param  int $documentoId
     * @param  int $versioneSorgenteId  ID della versione da ripristinare
     * @param  int $userId
     * @return int   ID della nuova versione creata
     */
    public function ripristina(int $documentoId, int $versioneSorgenteId, int $userId): int
    {
        $sorgente = $this->verRepo->find($versioneSorgenteId);
        if (!$sorgente || (int) $sorgente['documento_id'] !== $documentoId) {
            throw new \RuntimeException(t('documenti.exception.versione_sorgente_non_trovata'));
        }

        $this->pdo->beginTransaction();
        try {
            $doc   = $this->docRepo->findForUpdate($documentoId);
            $newNo = $this->verRepo->maxVersioneNo($documentoId) + 1;

            $versioneId = $this->verRepo->create([
                'documento_id'  => $documentoId,
                'versione_no'   => $newNo,
                'file_id'       => (int) $sorgente['file_id'],
                'note_modifica' => t('documenti.exception.ripristino_da_versione', ['no' => $sorgente['versione_no']]),
                'stato'         => 'bozza',
                'ripristino_di' => $versioneSorgenteId,
                'created_by'    => $userId,
            ]);

            $this->docRepo->update($documentoId, [
                'versione_no'          => $newNo,
                'versione_corrente_id' => $versioneId,
                'file_corrente_id'     => (int) $sorgente['file_id'],
                'stato'                => 'bozza',
                'step_corrente'        => 'redazione',
                'updated_by'           => $userId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

        AuditService::log(
            'documento.versione_ripristina',
            'documento.versione',
            $versioneId,
            ['ripristino_di' => $versioneSorgenteId],
            ['documento_id' => $documentoId, 'versione_no' => $newNo]
        );

        return $versioneId;
    }
}
