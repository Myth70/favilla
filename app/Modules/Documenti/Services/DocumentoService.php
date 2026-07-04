<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoApprovazioneRepository;
use App\Modules\Documenti\Repositories\DocumentoCategoriaRepository;
use App\Modules\Documenti\Repositories\DocumentoCollegamentoRepository;
use App\Modules\Documenti\Repositories\DocumentoFileRepository;
use App\Modules\Documenti\Repositories\DocumentoRepository;
use App\Modules\Documenti\Repositories\DocumentoVersioneRepository;
use App\Security\Sanitizer;

/**
 * Facade per operazioni CRUD sui documenti.
 */
class DocumentoService
{
    private DocumentoRepository         $docRepo;
    private DocumentoCategoriaRepository $catRepo;
    private DocumentoVersioneRepository  $verRepo;
    private DocumentoCollegamentoRepository $collRepo;
    private DocumentoApprovazioneRepository $approvRepo;
    private DocumentoFileRepository      $fileRepo;
    private ProtocolGeneratorService     $protocolSvc;
    private DocumentiStorageService      $storageSvc;

    public function __construct()
    {
        $this->docRepo     = app(DocumentoRepository::class);
        $this->catRepo     = app(DocumentoCategoriaRepository::class);
        $this->verRepo     = app(DocumentoVersioneRepository::class);
        $this->collRepo    = app(DocumentoCollegamentoRepository::class);
        $this->approvRepo  = app(DocumentoApprovazioneRepository::class);
        $this->fileRepo    = app(DocumentoFileRepository::class);
        $this->protocolSvc = app(ProtocolGeneratorService::class);
        $this->storageSvc  = app(DocumentiStorageService::class);
    }

    /**
     * Crea un nuovo documento con eventuale file allegato.
     */
    public function create(array $data, ?array $uploadedFile, int $userId): int
    {
        $categoriaId = (int) ($data['categoria_id'] ?? 0);
        if (!$this->catRepo->find($categoriaId)) {
            throw new \InvalidArgumentException(t('documenti.exception.categoria_non_valida'));
        }

        $categoria  = $this->catRepo->find($categoriaId);
        $approvazioneRichiesta = isset($data['approvazione_richiesta'])
            ? (int) $data['approvazione_richiesta']
            : (int) $categoria['approvazione_richiesta'];

        $descrizione = isset($data['descrizione']) && $data['descrizione'] !== ''
            ? Sanitizer::sanitizeHtml($data['descrizione'])
            : null;

        $docData = [
            'titolo'                => htmlspecialchars(strip_tags(trim($data['titolo'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'descrizione'           => $descrizione,
            'categoria_id'          => $categoriaId,
            'owner_user_id'         => $userId,
            'stato'                 => 'bozza',
            'step_corrente'         => 'redazione',
            'approvazione_richiesta' => $approvazioneRichiesta,
            'scade_il'              => !empty($data['scade_il']) ? $data['scade_il'] : null,
            'reminder_giorni'       => !empty($data['reminder_giorni']) ? json_encode((array) $data['reminder_giorni']) : null,
            'tag'                   => !empty($data['tag']) ? htmlspecialchars(strip_tags(trim($data['tag'])), ENT_QUOTES, 'UTF-8') : null,
            'versione_no'           => 0,
            'created_by'            => $userId,
            'updated_by'            => $userId,
        ];

        $docId = $this->docRepo->create($docData);

        // Genera protocollo
        try {
            $protocollo = $this->protocolSvc->generate($categoriaId);
            $this->docRepo->update($docId, ['protocollo' => $protocollo]);
        } catch (\Throwable $e) {
            error_log('[DocumentoService] Protocollo non generato: ' . $e->getMessage());
        }

        // Upload file iniziale
        if ($uploadedFile && !empty($uploadedFile['name'])) {
            $fileId     = $this->storageSvc->store($uploadedFile, $userId);
            $versioneId = $this->verRepo->create([
                'documento_id'  => $docId,
                'versione_no'   => 1,
                'file_id'       => $fileId,
                'note_modifica' => t('documenti.exception.versione_iniziale'),
                'stato'         => 'bozza',
                'created_by'    => $userId,
            ]);
            $this->docRepo->update($docId, [
                'versione_no'          => 1,
                'versione_corrente_id' => $versioneId,
                'file_corrente_id'     => $fileId,
            ]);
        }

        return $docId;
    }

    /**
     * Aggiorna metadati di un documento.
     */
    public function update(int $docId, array $data, int $userId): void
    {
        $doc = $this->docRepo->find($docId);
        if (!$doc) {
            throw new \RuntimeException(t('documenti.exception.documento_non_trovato'));
        }
        // Self-enforcement (coerente con destroy()): il Service non si fida del
        // chiamante. Solo proprietario o documenti.admin possono modificare.
        if (!has_permission('documenti.admin') && (int) $doc['owner_user_id'] !== $userId) {
            throw new \RuntimeException(t('documenti.exception.non_autorizzato_modifica'));
        }

        $descrizione = isset($data['descrizione']) && $data['descrizione'] !== ''
            ? Sanitizer::sanitizeHtml($data['descrizione'])
            : null;

        $updateData = [
            'titolo'      => htmlspecialchars(strip_tags(trim($data['titolo'] ?? $doc['titolo'])), ENT_QUOTES, 'UTF-8'),
            'descrizione' => $descrizione,
            'scade_il'    => !empty($data['scade_il']) ? $data['scade_il'] : null,
            'reminder_giorni' => !empty($data['reminder_giorni']) ? json_encode((array) $data['reminder_giorni']) : null,
            'tag'         => !empty($data['tag']) ? htmlspecialchars(strip_tags(trim($data['tag'])), ENT_QUOTES, 'UTF-8') : null,
            'updated_by'  => $userId,
        ];

        if (isset($data['approvazione_richiesta'])) {
            $updateData['approvazione_richiesta'] = (int) $data['approvazione_richiesta'];
        }

        $this->docRepo->update($docId, $updateData);
    }

    /**
     * Soft-delete di un documento.
     */
    public function destroy(int $docId, int $userId): void
    {
        $doc = $this->docRepo->find($docId);
        if (!$doc) {
            throw new \RuntimeException(t('documenti.exception.documento_non_trovato'));
        }
        if (!has_permission('documenti.admin') && (int) $doc['owner_user_id'] !== $userId) {
            throw new \RuntimeException(t('documenti.exception.non_autorizzato_eliminazione'));
        }
        $this->docRepo->delete($docId);
    }

    /**
     * Restituisce lista documenti paginata.
     * Espone sia 'data' (chiave nativa del repository) sia 'items' (alias
     * usato storicamente dalle view: lo manteniamo per compatibilità).
     */
    public function listPaginated(array $filters, int $userId): array
    {
        $adminMode = has_permission('documenti.admin');
        $filters['current_user_id'] = $userId;
        $result = $this->docRepo->listPaginated($filters, $adminMode);
        $result['items'] = $result['data'] ?? [];
        return $result;
    }

    /**
     * Trova un documento con visibilità applicata.
     */
    public function findVisible(int $docId, int $userId): ?array
    {
        $doc = $this->docRepo->find($docId);
        if (!$doc) {
            return null;
        }

        if (has_permission('documenti.admin')) {
            return $doc;
        }

        if (in_array($doc['stato'], ['pubblicato', 'scaduto', 'archiviato'], true)) {
            return $doc;
        }

        if ((int) $doc['owner_user_id'] === $userId) {
            return $doc;
        }

        $stepMap = [
            'inviato'          => 'documenti.controllo',
            'in_controllo'     => 'documenti.controllo',
            'controllato'      => 'documenti.approvazione',
            'in_approvazione'  => 'documenti.approvazione',
            'approvato'        => 'documenti.approvazione',
        ];

        $permNeeded = $stepMap[$doc['stato']] ?? null;
        if ($permNeeded && has_permission($permNeeded)) {
            return $doc;
        }

        return null;
    }

    /**
     * Bundle completo per la pagina di dettaglio, applicando la visibilità.
     * Ritorna null se il documento non è visibile all'utente.
     *
     * @return array{doc:array,versioni:array,collegamenti:array,approvazioni:array,categoria:?array}|null
     */
    public function dettaglioVisibile(int $id, int $userId): ?array
    {
        $doc = $this->findVisible($id, $userId);
        if (!$doc) {
            return null;
        }

        return [
            'doc'          => $doc,
            'versioni'     => $this->verRepo->findByDocumento($id),
            'collegamenti' => $this->collRepo->findByDocumento($id),
            'approvazioni' => $this->arricchisciApprovazioni($this->approvRepo->findByDocumento($id)),
            'categoria'    => $this->catRepo->find((int) $doc['categoria_id']),
        ];
    }

    /**
     * Dati per il re-render HTMX della timeline versioni.
     *
     * @return array{versioni:array,versioneCorrenteId:?int}
     */
    public function versioniTimeline(int $docId): array
    {
        $doc = $this->docRepo->find($docId);
        return [
            'versioni'           => $this->verRepo->findByDocumento($docId),
            'versioneCorrenteId' => $doc['versione_corrente_id'] ?? null,
        ];
    }

    /**
     * Dati per il re-render HTMX del pannello approvazione.
     *
     * @return array{doc:?array,approvazioni:array}
     */
    public function pannelloApprovazione(int $docId): array
    {
        return [
            'doc'          => $this->docRepo->find($docId),
            'approvazioni' => $this->arricchisciApprovazioni($this->approvRepo->findByDocumento($docId)),
        ];
    }

    /**
     * Aggiunge `user_name` a ogni riga di approvazione risolvendo gli ID
     * tramite DocumentiRecipientService (evita un JOIN diretto per tenere
     * la query di findByDocumento() semplice e riusabile).
     *
     * @param  array<int,array<string,mixed>> $approvazioni
     * @return array<int,array<string,mixed>>
     */
    private function arricchisciApprovazioni(array $approvazioni): array
    {
        if (empty($approvazioni)) {
            return $approvazioni;
        }
        $names = app(DocumentiRecipientService::class)->displayNamesByIds(
            array_map(static fn ($a): int => (int) ($a['user_id'] ?? 0), $approvazioni)
        );
        foreach ($approvazioni as &$a) {
            $a['user_name'] = $names[(int) ($a['user_id'] ?? 0)] ?? null;
        }
        unset($a);
        return $approvazioni;
    }

    /**
     * Lista inbox documenti in workflow, in base ai permessi dell'utente corrente.
     * Senza permessi di workflow ritorna una lista vuota (niente query, niente leak).
     */
    public function inboxFor(): array
    {
        $stati = [];
        if (has_permission('documenti.controllo')) {
            $stati = array_merge($stati, ['inviato', 'in_controllo']);
        }
        if (has_permission('documenti.approvazione')) {
            $stati = array_merge($stati, ['controllato', 'in_approvazione']);
        }
        if (has_permission('documenti.admin')) {
            $stati = ['inviato', 'in_controllo', 'controllato', 'in_approvazione'];
        }

        if (empty($stati)) {
            return ['data' => [], 'items' => [], 'total' => 0, 'pages' => 0, 'page' => 1];
        }

        // adminMode=true: il filtro di visibility user-side escluderebbe i documenti di altri
        // owner ancora in workflow. Il fine-grained sulle azioni è nel WorkflowApprovazioneService.
        $result = $this->docRepo->listPaginated(['stato' => $stati, 'page' => 1], true);
        $result['items'] = $result['data'] ?? [];
        return $result;
    }

    /**
     * Risolve il record file di una versione applicando la visibilità del documento.
     * Ritorna null se il documento non è visibile all'utente, se la versione non
     * appartiene al documento, o se il file non esiste. (Chiude il gap IDOR su download/preview.)
     *
     * @return array<string,mixed>|null
     */
    public function fileVersioneVisibile(int $docId, int $versioneId, int $userId): ?array
    {
        if (!$this->findVisible($docId, $userId)) {
            return null;
        }
        $versione = $this->verRepo->find($versioneId);
        if (!$versione || (int) $versione['documento_id'] !== $docId) {
            return null;
        }
        return $this->fileRepo->find((int) $versione['file_id']);
    }
}
