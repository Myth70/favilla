<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Contacts\Services\ContactsService;
use App\Modules\Contacts\Services\RecurrencesService;
use App\Traits\ControllerHelpers;

class RecurrencesController extends Controller
{
    use ControllerHelpers;

    private ContactsService   $contattiService;
    private RecurrencesService $ricService;

    public function __construct()
    {
        $this->contattiService = app(ContactsService::class);
        $this->ricService      = app(RecurrencesService::class);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────

    public function store(string $id): void
    {
        $contattoId = (int) $id;
        $userId     = (int) $_SESSION['user_id'];

        $contatto = $this->contattiService->findForUser($contattoId, $userId);
        if (!$contatto) {
            http_response_code(404);
            echo '<p class="text-danger">Contatto non trovato.</p>';
            return;
        }

        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        if (!empty($errors)) {
            http_response_code(422);
            $this->renderPartial('Contacts/Views/partials/recurrence_form', [
                'contattoId' => $contattoId,
                'contatto'   => $contatto,
                'ric'        => null,
                'errors'     => $errors,
                'old'        => $data,
            ]);
            return;
        }

        $ricId = $this->ricService->create($data, $contattoId, $userId);

        // Integrazione calendario (gestita dal Service)
        $this->ricService->sincronizzaCalendario($ricId, $contatto, $userId);

        $this->hxToast('Ricorrenza aggiunta.', 'success', ['source' => 'contatti-ricorrenze']);

        $ricorrenze = $this->ricService->allForContatto($contattoId);
        $this->renderPartial('Contacts/Views/partials/recurrences_list', [
            'ricorrenze'  => $ricorrenze,
            'contattoId'  => $contattoId,
            'contatto'    => $contatto,
            'showAddForm' => false,
        ]);
    }

    // ── LIST PARTIAL (for Annulla / refresh) ─────────────────────────────────

    public function listPartial(string $id): void
    {
        $contattoId = (int) $id;
        $userId     = (int) $_SESSION['user_id'];

        $contatto = $this->contattiService->findForUser($contattoId, $userId);
        if (!$contatto) {
            http_response_code(404);
            return;
        }

        $ricorrenze = $this->ricService->allForContatto($contattoId);
        $this->renderPartial('Contacts/Views/partials/recurrences_list', [
            'ricorrenze'  => $ricorrenze,
            'contattoId'  => $contattoId,
            'contatto'    => $contatto,
            'showAddForm' => false,
        ]);
    }

    // ── EDIT FORM (HTMX inline) ───────────────────────────────────────────────

    public function editForm(string $id, string $rid): void
    {
        $userId     = (int) $_SESSION['user_id'];
        $contattoId = (int) $id;

        $contatto = $this->contattiService->findForUser($contattoId, $userId);
        if (!$contatto) {
            http_response_code(404);
            return;
        }

        // 'new' = blank form per aggiunta
        $ric = ($rid === 'new') ? null : $this->ricService->find((int) $rid, $userId);
        if ($rid !== 'new' && !$ric) {
            http_response_code(404);
            return;
        }

        $this->renderPartial('Contacts/Views/partials/recurrence_form', [
            'contattoId' => $contattoId,
            'contatto'   => $contatto,
            'ric'        => $ric,
            'errors'     => [],
            'old'        => [],
        ]);
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function update(string $id, string $rid): void
    {
        $contattoId = (int) $id;
        $ricId      = (int) $rid;
        $userId     = (int) $_SESSION['user_id'];

        $contatto = $this->contattiService->findForUser($contattoId, $userId);
        $ric      = $this->ricService->find($ricId, $userId);

        if (!$contatto || !$ric) {
            http_response_code(404);
            return;
        }

        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        if (!empty($errors)) {
            http_response_code(422);
            $this->renderPartial('Contacts/Views/partials/recurrence_form', [
                'contattoId' => $contattoId,
                'contatto'   => $contatto,
                'ric'        => $ric,
                'errors'     => $errors,
                'old'        => $data,
            ]);
            return;
        }

        // Rimuovi vecchio evento calendario se presente
        $this->ricService->rimuoviEventoCalendario($ric, $userId);

        $this->ricService->update($ricId, $data);

        // Ricrea evento calendario se richiesto (gestito dal Service)
        $this->ricService->sincronizzaCalendario($ricId, $contatto, $userId);

        $this->hxToast('Ricorrenza aggiornata.', 'success', ['source' => 'contatti-ricorrenze']);

        $ricorrenze = $this->ricService->allForContatto($contattoId);
        $this->renderPartial('Contacts/Views/partials/recurrences_list', [
            'ricorrenze'  => $ricorrenze,
            'contattoId'  => $contattoId,
            'contatto'    => $contatto,
            'showAddForm' => false,
        ]);
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────

    public function destroy(string $id, string $rid): void
    {
        $contattoId = (int) $id;
        $ricId      = (int) $rid;
        $userId     = (int) $_SESSION['user_id'];

        $contatto = $this->contattiService->findForUser($contattoId, $userId);
        $ric      = $this->ricService->find($ricId, $userId);

        if (!$contatto || !$ric) {
            http_response_code(404);
            return;
        }

        $this->ricService->rimuoviEventoCalendario($ric, $userId);
        $this->ricService->delete($ricId);

        $this->hxToast('Ricorrenza eliminata.', 'warning', ['source' => 'contatti-ricorrenze']);

        $ricorrenze = $this->ricService->allForContatto($contattoId);
        $this->renderPartial('Contacts/Views/partials/recurrences_list', [
            'ricorrenze'  => $ricorrenze,
            'contattoId'  => $contattoId,
            'contatto'    => $contatto,
            'showAddForm' => false,
        ]);
    }

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function readFormData(): array
    {
        $clean = $this->cleanPost(['titolo', 'data_ricorrenza', 'anno_riferimento', 'note']);

        return [
            'tipo'                    => $_POST['tipo']                   ?? 'evento',
            'titolo'                  => $clean['titolo']                 ?? '',
            'data_ricorrenza'         => $clean['data_ricorrenza']        ?? '',
            'annuale'                 => $_POST['annuale']                ?? null,
            'anno_riferimento'        => $clean['anno_riferimento']       ?? '',
            'promemoria_giorni_prima' => (int) ($_POST['promemoria_giorni_prima'] ?? 7),
            'notifica_giorno_stesso'  => $_POST['notifica_giorno_stesso'] ?? null,
            'crea_evento_calendario'  => $_POST['crea_evento_calendario'] ?? 'no',
            'note'                    => $clean['note']                   ?? '',
        ];
    }

    private function validateForm(array $data): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'titolo'                  => 'required|max:255',
            'data_ricorrenza'         => 'required|date',
            'tipo'                    => 'required|in:compleanno,anniversario,evento',
            'crea_evento_calendario'  => 'required|in:no,prossimo,annuale',
            'note'                    => 'nullable|max:500',
        ], [
            'titolo'                  => 'Titolo',
            'data_ricorrenza'         => 'Data',
            'tipo'                    => 'Tipo',
            'crea_evento_calendario'  => 'Integrazione calendario',
            'note'                    => 'Note',
        ]);
        $errors = $validator->errors();

        $giorni = (int) ($data['promemoria_giorni_prima'] ?? 0);
        if ($giorni < 0 || $giorni > 90) {
            $errors['promemoria_giorni_prima'] = ['Il promemoria deve essere compreso tra 0 e 90 giorni.'];
        }

        return $errors;
    }
}
