<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers\Api;

use App\Modules\Api\Http\ApiController;
use App\Modules\Contacts\Services\ContactsService;

/**
 * API v1 — Rubrica contatti (sola lettura nel roll-out pilota). Riusa
 * ContactsService, includendo i contatti condivisi con i ruoli dell'utente
 * (i ruoli arrivano da ApiRequestContext, non dalla sessione).
 */
class ContactsApiController extends ApiController
{
    private ContactsService $contacts;

    public function __construct()
    {
        $this->contacts = app(ContactsService::class);
    }

    public function index(): void
    {
        $this->requireScope('contacts.view');

        $page = $this->queryInt('page', 1, 1, 100000);
        $filters = ['page' => $page];
        if (isset($_GET['q']) && $_GET['q'] !== '') {
            $filters['q'] = (string) $_GET['q'];
        }

        $result = $this->contacts->list($this->userId(), $filters, $this->context()->roles());

        $items = array_map([$this, 'serialize'], $result['data'] ?? []);
        $this->paginated(
            $items,
            (int) ($result['page'] ?? $page),
            (int) ($result['perPage'] ?? 24),
            (int) ($result['total'] ?? count($items))
        );
    }

    public function show(string $id): void
    {
        $this->requireScope('contacts.view');

        $contact = $this->contacts->find((int) $id, $this->userId(), $this->context()->roles());
        if ($contact === null) {
            $this->fail('not_found', 'Contatto non trovato.', 404);
            return;
        }
        $this->ok($this->serialize($contact));
    }

    /**
     * @param array<string, mixed> $contact
     * @return array<string, mixed>
     */
    private function serialize(array $contact): array
    {
        return [
            'id'         => (int) $contact['id'],
            'nome'       => $contact['nome'] ?? ($contact['name'] ?? ''),
            'cognome'    => $contact['cognome'] ?? null,
            'email'      => $contact['email'] ?? null,
            'telefono'   => $contact['telefono'] ?? null,
            'azienda'    => $contact['azienda'] ?? null,
            'created_at' => $contact['created_at'] ?? null,
            'updated_at' => $contact['updated_at'] ?? null,
        ];
    }
}
