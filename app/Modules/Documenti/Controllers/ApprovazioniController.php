<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentoService;
use App\Modules\Documenti\Services\WorkflowApprovazioneService;
use App\Traits\ControllerHelpers;

class ApprovazioniController extends Controller
{
    use ControllerHelpers;

    private WorkflowApprovazioneService $workflow;
    private DocumentoService            $documenti;

    public function __construct()
    {
        $this->workflow  = app(WorkflowApprovazioneService::class);
        $this->documenti = app(DocumentoService::class);
    }

    /**
     * Invia documento in redazione → step controllo.
     */
    public function invia(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'invia');
    }

    /**
     * Prende in carico per il controllo.
     */
    public function prendeInCarico(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'prende_in_carico');
    }

    /**
     * Approva (dal controllo o dall'approvazione).
     */
    public function approva(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'approva');
    }

    /**
     * Rifiuta documento.
     */
    public function rifiuta(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'rifiuta');
    }

    /**
     * Restituisce all'autore per correzioni.
     */
    public function restituisci(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'restituisci');
    }

    /**
     * Pubblica il documento approvato.
     */
    public function pubblica(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'pubblica');
    }

    /**
     * Riprendi un documento rifiutato in bozza.
     */
    public function riprendi(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'riprendi');
    }

    /**
     * Ritira un documento inviato, riportandolo in bozza (solo owner, prima della presa in carico).
     */
    public function ritira(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'ritira');
    }

    /**
     * Archivia un documento pubblicato o scaduto (admin).
     */
    public function archivia(string $docId): void
    {
        $this->eseguiTransizione((int) $docId, 'archivia');
    }

    /**
     * Esegue la transizione di workflow e gestisce la risposta.
     */
    private function eseguiTransizione(int $docId, string $azione): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $note   = trim($_POST['note'] ?? '');

        try {
            $this->workflow->transizione($docId, $azione, $userId, $note ?: null);

            if ($this->isHtmxRequest()) {
                $pannello = $this->documenti->pannelloApprovazione($docId);
                $this->hxToast(t('documenti.flash.operazione_eseguita'), 'success');
                $this->renderPartial('Documenti/Views/partials/pannello_approvazione', [
                    'doc'          => $pannello['doc'],
                    'approvazioni' => $pannello['approvazioni'],
                    'user'         => $user,
                ]);
                return;
            }

            flash_success(t('documenti.flash.operazione_eseguita'));
        } catch (\RuntimeException $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.show', ['id' => $docId]));
    }
}
