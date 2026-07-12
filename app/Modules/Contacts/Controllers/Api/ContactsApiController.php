<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers\Api;

use App\Modules\Api\Http\ApiController;
use App\Modules\Contacts\Services\ContactsService;

/**
 * API v1 — Rubrica contatti. Lettura (inclusi i contatti condivisi coi ruoli
 * dell'utente, risolti da ApiRequestContext e non dalla sessione) e scrittura
 * (create/update/delete, limitate ai contatti di proprietà come nella UI web).
 * L'avatar non è gestito via API (upload multipart fuori scope v1).
 */
class ContactsApiController extends ApiController
{
    /** Campi accettati in scrittura (mass-assignment ristretto ai campi anagrafici). */
    private const WRITABLE_FIELDS = [
        'nome', 'cognome', 'azienda', 'ruolo', 'email', 'telefono', 'telefono_alt',
        'indirizzo', 'sito_web', 'linkedin', 'instagram', 'twitter', 'facebook',
        'whatsapp', 'telegram', 'tags', 'note',
    ];
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
            $this->fail('not_found', 'Contact not found.', 404);
            return;
        }
        $this->ok($this->serialize($contact));
    }

    public function store(): void
    {
        $this->requireScope('contacts.create');

        $input = $this->input();
        $data = $this->cleanContactData($input);

        $details = $this->validate($data, true);
        if ($details !== []) {
            $this->fail('validation_failed', 'Validation failed.', 422, $details);
            return;
        }

        $id = $this->contacts->create($data, $this->userId());
        $contact = $this->contacts->find($id, $this->userId(), $this->context()->roles());
        $this->ok($contact !== null ? $this->serialize($contact) : ['id' => $id], null, 201);
    }

    public function update(string $id): void
    {
        $this->requireScope('contacts.edit');

        // La scrittura è limitata ai contatti di proprietà (findForUser), come
        // nella UI web: un contatto solo condiviso via ruoli non è modificabile.
        $existing = $this->contacts->findForUser((int) $id, $this->userId());
        if ($existing === null) {
            $this->fail('not_found', 'Contact not found.', 404);
            return;
        }

        $input = $this->input();
        $cleaned = $this->cleanContactData($input);

        $details = $this->validate($cleaned, false);
        if ($details !== []) {
            $this->fail('validation_failed', 'Validation failed.', 422, $details);
            return;
        }

        // Update parziale sopra un Service pensato per il form completo: i campi
        // non inviati ripartono dal valore esistente (altrimenti tags e geodati
        // verrebbero azzerati dalla normalizzazione del Service). Se l'indirizzo
        // cambia, le coordinate geocodificate decadono (sono di quello vecchio).
        $base = array_intersect_key($existing, array_flip(array_merge(
            self::WRITABLE_FIELDS,
            ['latitude', 'longitude', 'geocoding_source', 'geocoded_at']
        )));
        $data = array_merge($base, $cleaned);
        if (isset($cleaned['indirizzo']) && $cleaned['indirizzo'] !== ($existing['indirizzo'] ?? '')) {
            unset($data['latitude'], $data['longitude'], $data['geocoding_source'], $data['geocoded_at']);
        }

        if (!$this->contacts->update((int) $id, $data, $this->userId())) {
            $this->fail('update_failed', 'Update failed.', 400);
            return;
        }

        $contact = $this->contacts->find((int) $id, $this->userId(), $this->context()->roles());
        $this->ok($contact !== null ? $this->serialize($contact) : ['id' => (int) $id]);
    }

    public function destroy(string $id): void
    {
        $this->requireScope('contacts.delete');

        if ($this->contacts->findForUser((int) $id, $this->userId()) === null) {
            $this->fail('not_found', 'Contact not found.', 404);
            return;
        }

        if (!$this->contacts->delete((int) $id, $this->userId())) {
            $this->fail('delete_failed', 'Delete failed.', 400);
            return;
        }
        $this->ok(['deleted' => true]);
    }

    /**
     * Whitelist + trim dei campi scrivibili presenti nell'input (update parziale).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function cleanContactData(array $input): array
    {
        $data = [];
        foreach (self::WRITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = trim((string) $input[$field]);
            // 'note' è un campo testo libero, gli altri sono varchar(255).
            $data[$field] = $field === 'note' ? $value : mb_substr($value, 0, 255);
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string[]> details per l'envelope (vuoto = ok)
     */
    private function validate(array $data, bool $isCreate): array
    {
        $details = [];
        if ($isCreate && trim((string) ($data['nome'] ?? '')) === '') {
            $details['nome'] = ['required'];
        }
        if (!$isCreate && array_key_exists('nome', $data) && trim((string) $data['nome']) === '') {
            $details['nome'] = ['required'];
        }
        if (!empty($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $details['email'] = ['invalid'];
        }
        return $details;
    }

    /**
     * @param array<string, mixed> $contact
     * @return array<string, mixed>
     */
    private function serialize(array $contact): array
    {
        return [
            'id'           => (int) $contact['id'],
            'nome'         => $contact['nome'] ?? ($contact['name'] ?? ''),
            'cognome'      => $contact['cognome'] ?? null,
            'azienda'      => $contact['azienda'] ?? null,
            'ruolo'        => $contact['ruolo'] ?? null,
            'email'        => $contact['email'] ?? null,
            'telefono'     => $contact['telefono'] ?? null,
            'telefono_alt' => $contact['telefono_alt'] ?? null,
            'indirizzo'    => $contact['indirizzo'] ?? null,
            'sito_web'     => $contact['sito_web'] ?? null,
            'linkedin'     => $contact['linkedin'] ?? null,
            'instagram'    => $contact['instagram'] ?? null,
            'twitter'      => $contact['twitter'] ?? null,
            'facebook'     => $contact['facebook'] ?? null,
            'whatsapp'     => $contact['whatsapp'] ?? null,
            'telegram'     => $contact['telegram'] ?? null,
            'tags'         => $contact['tags'] ?? null,
            'note'         => $contact['note'] ?? null,
            'created_at'   => $contact['created_at'] ?? null,
            'updated_at'   => $contact['updated_at'] ?? null,
        ];
    }
}
