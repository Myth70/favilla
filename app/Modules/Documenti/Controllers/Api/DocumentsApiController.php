<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Api;

use App\Modules\Api\Http\ApiController;
use App\Modules\Documenti\Services\DocumentoService;

/**
 * API v1 — Documenti (sola lettura, metadati). Riusa DocumentoService con le
 * stesse regole di visibilità della UI (stati pubblici, owner, permessi di
 * workflow, documenti.admin vede tutto) risolte dai permessi del token.
 * Il download dei binari resta fuori dallo scope v1.
 */
class DocumentsApiController extends ApiController
{
    private const ALLOWED_STATO = [
        'bozza', 'inviato', 'in_controllo', 'controllato', 'in_approvazione',
        'approvato', 'rifiutato', 'pubblicato', 'scaduto', 'archiviato',
    ];

    private DocumentoService $documents;

    public function __construct()
    {
        $this->documents = app(DocumentoService::class);
    }

    public function index(): void
    {
        $this->requireScope('documenti.view');

        $filters = ['page' => $this->queryInt('page', 1, 1, 100000)];
        if (isset($_GET['q']) && $_GET['q'] !== '') {
            $filters['q'] = (string) $_GET['q'];
        }
        if (isset($_GET['stato'])) {
            if (!in_array($_GET['stato'], self::ALLOWED_STATO, true)) {
                $this->fail('validation_failed', 'Validation failed.', 422, ['stato' => ['invalid']]);
                return;
            }
            $filters['stato'] = (string) $_GET['stato'];
        }
        if (isset($_GET['categoria_id'])) {
            $filters['categoria_id'] = (int) $_GET['categoria_id'];
        }

        $result = $this->documents->listPaginated($filters, $this->userId(), $this->can());

        $items = array_map([$this, 'serialize'], $result['items'] ?? []);
        $this->paginated(
            $items,
            (int) ($result['page'] ?? 1),
            20,
            (int) ($result['total'] ?? count($items))
        );
    }

    public function show(string $id): void
    {
        $this->requireScope('documenti.view');

        $doc = $this->documents->findVisible((int) $id, $this->userId(), $this->can());
        if ($doc === null) {
            $this->fail('not_found', 'Document not found.', 404);
            return;
        }
        $this->ok($this->serialize($doc));
    }

    /**
     * Permission-checker sui permessi del token (min(permessi utente, scope)),
     * passato al Service al posto di has_permission() di sessione.
     */
    private function can(): callable
    {
        return fn (string $permission): bool => $this->context()->can($permission);
    }

    /**
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    private function serialize(array $doc): array
    {
        return [
            'id'            => (int) $doc['id'],
            'protocollo'    => $doc['protocollo'] ?? null,
            'titolo'        => $doc['titolo'] ?? '',
            'descrizione'   => $doc['descrizione'] ?? null,
            'stato'         => $doc['stato'] ?? null,
            'categoria_id'  => isset($doc['categoria_id']) ? (int) $doc['categoria_id'] : null,
            'versione_no'   => (int) ($doc['versione_no'] ?? 0),
            'tag'           => $doc['tag'] ?? null,
            'pubblicato_il' => $doc['pubblicato_il'] ?? null,
            'scade_il'      => $doc['scade_il'] ?? null,
            'owner_user_id' => (int) ($doc['owner_user_id'] ?? 0),
            'created_at'    => $doc['created_at'] ?? null,
            'updated_at'    => $doc['updated_at'] ?? null,
        ];
    }
}
