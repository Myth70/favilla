<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Modules\Contacts\Services\ContactsService;
use App\Traits\ControllerHelpers;

class SharingController extends Controller
{
    use ControllerHelpers;

    private ContactsService $service;

    public function __construct()
    {
        $this->service = app(ContactsService::class);
    }

    // ── EDIT: pagina di gestione condivisioni ───────────────────────────────

    public function edit(string $id): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $contattoId = (int) $id;

        $contatto = $this->service->findForUser($contattoId, $userId);
        if (!$contatto) {
            flash_error('Contatto non trovato.');
            $this->redirect(route('contacts.index'));
            return;
        }

        $roles            = $this->service->getAllRoles();
        $sharedRoleIds    = $this->service->getShareRoleIds($contattoId, $userId) ?? [];
        $nomeCompleto     = trim($contatto['nome'] . ' ' . ($contatto['cognome'] ?? ''));

        $this->render('Contacts/Views/sharing', [
            'pageTitle'      => 'Condividi: ' . $nomeCompleto,
            'item'           => $contatto,
            'nomeCompleto'   => $nomeCompleto,
            'roles'          => $roles,
            'sharedRoleIds'  => $sharedRoleIds,
            'breadcrumbs'    => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => $nomeCompleto, 'route' => 'contacts.show', 'params' => ['id' => $contattoId]],
                ['label' => 'Condivisione'],
            ],
        ]);
    }

    // ── UPDATE: salva la lista di ruoli condivisi ───────────────────────────

    public function update(string $id): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $contattoId = (int) $id;

        $rolesInput = $_POST['roles'] ?? [];
        if (!is_array($rolesInput)) {
            $rolesInput = [];
        }
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $rolesInput))));
        $sharerName = (string) (auth()['name'] ?? '');

        $result = $this->service->shareWithRoles($contattoId, $userId, $roleIds, $sharerName);
        if ($result === null) {
            flash_error('Contatto non trovato.');
            $this->redirect(route('contacts.index'));
            return;
        }

        $added   = count($result['added']);
        $removed = count($result['removed']);
        if ($added === 0 && $removed === 0) {
            flash_success('Nessuna modifica alla condivisione.');
        } else {
            $parts = [];
            if ($added > 0) {
                $parts[] = $added   . ' ruol' . ($added   === 1 ? 'o aggiunto' : 'i aggiunti');
            }
            if ($removed > 0) {
                $parts[] = $removed . ' ruol' . ($removed === 1 ? 'o rimosso' : 'i rimossi');
            }
            flash_success('Condivisione aggiornata: ' . implode(', ', $parts) . '.');
        }

        $this->redirect(route('contacts.show', ['id' => $contattoId]));
    }

    // ── DESTROY: rimuove una singola condivisione (HTMX) ────────────────────

    public function destroy(string $id, string $rid): void
    {
        $userId     = (int) $_SESSION['user_id'];
        $contattoId = (int) $id;
        $roleId     = (int) $rid;

        $ok = $this->service->unshare($contattoId, $userId, $roleId);
        if (!$ok) {
            http_response_code(404);
            return;
        }

        // HTMX: ritorna il pannello aggiornato
        if ($this->isHtmxRequest()) {
            $shares = $this->service->getShares($contattoId, $userId) ?? [];
            $this->renderPartial('Contacts/Views/partials/sharing_panel', [
                'item'   => $this->service->findForUser($contattoId, $userId),
                'shares' => $shares,
            ]);
            return;
        }

        flash_success('Condivisione rimossa.');
        $this->redirect(route('contacts.show', ['id' => $contattoId]));
    }
}
