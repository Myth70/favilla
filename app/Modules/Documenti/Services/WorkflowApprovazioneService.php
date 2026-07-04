<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoApprovazioneRepository;
use App\Modules\Documenti\Repositories\DocumentoRepository;
use App\Modules\Documenti\Repositories\DocumentoVersioneRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;

/**
 * Macchina stati del workflow tri-step.
 * Ogni transizione avviene in transaction con row lock.
 */
class WorkflowApprovazioneService
{
    // Mappa: stato_corrente -> azione -> [nuovo_stato, nuovo_step, step_approvazione, permesso_richiesto]
    private const TRANSIZIONI = [
        'bozza' => [
            'invia' => ['inviato', 'controllo', 'redazione', 'documenti.redazione'],
        ],
        'inviato' => [
            'prende_in_carico' => ['in_controllo', 'controllo', 'controllo', 'documenti.controllo'],
            // L'owner può ritirare il documento finché nessuno lo ha preso in carico.
            'ritira'           => ['bozza',        'redazione', 'redazione', 'documenti.redazione'],
        ],
        'in_controllo' => [
            'approva'      => ['controllato',     'approvazione', 'controllo', 'documenti.controllo'],
            'rifiuta'      => ['rifiutato',        'redazione',    'controllo', 'documenti.controllo'],
            'restituisci'  => ['inviato',          'controllo',    'controllo', 'documenti.controllo'],
        ],
        'controllato' => [
            'approva' => ['in_approvazione', 'approvazione', 'approvazione', 'documenti.approvazione'],
        ],
        'in_approvazione' => [
            'approva'     => ['approvato',    'completato', 'approvazione', 'documenti.approvazione'],
            'rifiuta'     => ['rifiutato',    'redazione',  'approvazione', 'documenti.approvazione'],
            'restituisci' => ['in_controllo', 'controllo',  'approvazione', 'documenti.approvazione'],
        ],
        'approvato' => [
            'pubblica' => ['pubblicato', 'completato', 'approvazione', 'documenti.admin'],
        ],
        'rifiutato' => [
            'riprendi' => ['bozza', 'redazione', 'redazione', 'documenti.redazione'],
        ],
        // Archiviazione admin a fine ciclo (rende raggiungibile lo stato 'archiviato').
        'pubblicato' => [
            'archivia' => ['archiviato', 'completato', 'approvazione', 'documenti.admin'],
        ],
        'scaduto' => [
            'archivia' => ['archiviato', 'completato', 'approvazione', 'documenti.admin'],
        ],
    ];

    /**
     * Azioni ammesse dall'ENUM documenti_approvazioni.azione: solo queste finiscono
     * nello storico transizioni. Le azioni "meta" (ritira/archivia) restano nell'audit.
     */
    private const AZIONI_LOGGATE = ['invia', 'approva', 'rifiuta', 'restituisci', 'prende_in_carico'];

    private DocumentoRepository        $docRepo;
    private DocumentoApprovazioneRepository $approvRepo;
    private DocumentoVersioneRepository $verRepo;
    private DocumentiRecipientService  $recipientSvc;
    private \PDO $pdo;

    public function __construct()
    {
        $this->docRepo      = app(DocumentoRepository::class);
        $this->approvRepo   = app(DocumentoApprovazioneRepository::class);
        $this->verRepo      = app(DocumentoVersioneRepository::class);
        $this->recipientSvc = app(DocumentiRecipientService::class);
        $this->pdo          = app(\PDO::class);
    }

    /**
     * Esegue una transizione di stato.
     *
     * @throws \RuntimeException  Su transizione non valida o permesso mancante
     */
    public function transizione(int $docId, string $azione, int $userId, ?string $note = null): array
    {
        $isAdmin = has_permission('documenti.admin');

        $this->pdo->beginTransaction();
        try {
            $doc = $this->docRepo->findForUpdate($docId);
            if (!$doc) {
                throw new \RuntimeException(t('documenti.exception.documento_non_trovato'));
            }

            $statoCorrente = $doc['stato'];
            $trans = self::TRANSIZIONI[$statoCorrente][$azione] ?? null;

            if (!$trans) {
                throw new \RuntimeException(
                    t('documenti.exception.transizione_non_consentita', ['azione' => $azione, 'stato' => $statoCorrente])
                );
            }

            [$nuovoStato, $nuovoStep, $stepApprov, $permessoRichiesto] = $trans;

            // Guard: non si invia un documento senza un file/versione da revisionare.
            if ($azione === 'invia' && empty($doc['versione_corrente_id'])) {
                throw new \RuntimeException(t('documenti.exception.allega_file_prima_invio'));
            }

            // Categoria senza approvazione richiesta: l'invio pubblica direttamente,
            // saltando la catena di revisione. L'owner resta il solo attore richiesto.
            if ($azione === 'invia' && (int) ($doc['approvazione_richiesta'] ?? 1) === 0) {
                [$nuovoStato, $nuovoStep, $stepApprov, $permessoRichiesto] =
                    ['pubblicato', 'completato', 'redazione', 'documenti.redazione'];
            }

            // Verifica permesso
            if (!$isAdmin) {
                if ($azione === 'invia' || $azione === 'riprendi' || $azione === 'ritira') {
                    $ownerOk = (int) $doc['owner_user_id'] === $userId;
                    if (!$ownerOk && !has_permission($permessoRichiesto)) {
                        throw new \RuntimeException(t('documenti.exception.non_autorizzato_azione'));
                    }
                } elseif (!has_permission($permessoRichiesto)) {
                    throw new \RuntimeException(t('documenti.exception.non_autorizzato_azione'));
                }
            }

            // Aggiorna documento
            $updateData = [
                'stato'         => $nuovoStato,
                'step_corrente' => $nuovoStep,
                'updated_by'    => $userId,
            ];

            if ($nuovoStato === 'pubblicato') {
                $nowSql = date('Y-m-d H:i:s');
                $updateData['pubblicato_il'] = $nowSql;
                // Marca la versione corrente come pubblicata e quelle precedenti come sostituite.
                // $doc['versione_corrente_id'] è la PK della versione (non versione_no).
                if (!empty($doc['versione_corrente_id'])) {
                    $verCorrenteId = (int) $doc['versione_corrente_id'];
                    $this->verRepo->update($verCorrenteId, [
                        'stato'         => 'pubblicato',
                        'pubblicato_il' => $nowSql,
                    ]);
                    $this->verRepo->markPreviousSostituite($docId, $verCorrenteId);
                }
            }

            $this->docRepo->update($docId, $updateData);

            // Log approvazione (solo azioni previste dall'ENUM; ritira/archivia restano in audit)
            if (in_array($azione, self::AZIONI_LOGGATE, true)) {
                $this->approvRepo->create([
                    'documento_id' => $docId,
                    'versione_id'  => $doc['versione_corrente_id'] ?: null,
                    'step'         => $stepApprov,
                    'azione'       => $azione,
                    'user_id'      => $userId,
                    'note'         => $note,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

        // Audit
        AuditService::log(
            'documento_' . $azione,
            'documento',
            $docId,
            ['stato' => $statoCorrente ?? ''],
            ['stato' => $nuovoStato, 'note' => $note, 'user_id' => $userId]
        );

        // Notifiche (instradate sullo stato risultante, non solo sull'azione)
        $this->dispatchNotifica($azione, $nuovoStato, $docId, $doc, $userId, $note);

        return $this->docRepo->find($docId) ?? [];
    }

    /**
     * Invia le notifiche appropriate dopo una transizione, in base allo stato
     * risultante. Una transizione può generare più notifiche a destinatari diversi
     * (es. → approvato avvisa gli admin "pronto da pubblicare" e l'owner "approvato").
     */
    private function dispatchNotifica(string $azione, string $nuovoStato, int $docId, array $doc, int $userId, ?string $note): void
    {
        $jobs = self::notifichePerTransizione($azione, $nuovoStato);
        if (empty($jobs)) {
            return;
        }

        $context = [
            'documento_id'     => $docId,
            'documento_titolo' => $doc['titolo'],
            'protocollo'       => $doc['protocollo'] ?? '',
            'note'             => $note ?? '',
        ];
        // Deep-link diretto al pannello di approvazione del documento.
        $link = route('documenti.show', ['id' => $docId]) . '#dc-approvazione-container';

        $resolve = fn (string $gruppo): array => match ($gruppo) {
            'owner'        => [(int) $doc['owner_user_id']],
            'controllo'    => $this->recipientSvc->usersWithPermission('documenti.controllo'),
            'approvazione' => $this->recipientSvc->usersWithPermission('documenti.approvazione'),
            'admin'        => $this->recipientSvc->usersWithPermission('documenti.admin'),
            default        => [],
        };

        try {
            foreach ($jobs as [$slug, $gruppo]) {
                foreach ($resolve($gruppo) as $destId) {
                    if ((int) $destId > 0 && (int) $destId !== $userId) {
                        NotificationService::dispatchEventToUser($slug, 'Documenti', (int) $destId, $context, $link);
                    }
                }
            }
        } catch (\Throwable) {
            // Le notifiche non devono bloccare il workflow
        }
    }

    /**
     * Routing delle notifiche in base ad azione + stato risultante.
     * Funzione pura (nessuna dipendenza) per essere testabile.
     *
     * @return list<array{0:string,1:string}>  coppie [slug_evento, gruppo_destinatari]
     */
    public static function notifichePerTransizione(string $azione, string $nuovoStato): array
    {
        return match (true) {
            $azione === 'invia'            && $nuovoStato === 'inviato'     => [['documenti.inviato', 'controllo']],
            $azione === 'prende_in_carico'                                 => [['documenti.preso_in_carico', 'owner']],
            $azione === 'approva'          && $nuovoStato === 'controllato' => [['documenti.approvazione_richiesta', 'approvazione']],
            $azione === 'approva'          && $nuovoStato === 'approvato'   => [['documenti.pronto_pubblicazione', 'admin'], ['documenti.approvato', 'owner']],
            $azione === 'rifiuta'                                          => [['documenti.rifiutato', 'owner']],
            $azione === 'restituisci'                                      => [['documenti.restituito', 'controllo']],
            $nuovoStato === 'pubblicato'                                   => [['documenti.approvato', 'owner']],
            default                                                        => [],
        };
    }
}
