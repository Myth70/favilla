<?php

declare(strict_types=1);

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  SERVICE DI MODULO — Business logic layer                      ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * ISTRUZIONI:
 * 1. Rinomina la classe (es. ClientiService)
 * 2. Aggiorna il namespace al tuo modulo
 * 3. Aggiorna il Repository importato
 *
 * PATTERN 3-LAYER:
 *   Controller → Service → Repository
 *
 * - Il Controller si occupa di: input HTTP, chiamata Service, output (render/redirect/json)
 * - Il Service si occupa di: validazione business, orchestrazione, logica di dominio
 * - Il Repository si occupa di: accesso dati (query SQL)
 *
 * Il Controller NON deve mai chiamare il Repository direttamente per operazioni
 * di business logic (create, update, delete). Può delegare la lettura al Service
 * o, per query semplici di sola lettura, usare il Repository.
 */

namespace App\Modules\_Template\Services;

use App\Modules\_Template\Repositories\ExampleRepository;

class ExampleService
{
    private ExampleRepository $repo;

    public function __construct(ExampleRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Lista paginata con filtri (owner-scoped).
     */
    public function list(array $filters, int $userId): array
    {
        return $this->repo->listPaginated($filters, $userId);
    }

    /**
     * Conteggio per status (badge header lista), owner-scoped.
     */
    public function statusCounts(int $userId): array
    {
        return $this->repo->countByStatus($userId);
    }

    /**
     * Dettaglio singolo record con autore (null se non esiste o non è suo).
     */
    public function findWithAuthor(int $id, int $userId): ?array
    {
        return $this->repo->findWithAuthor($id, $userId);
    }

    /**
     * Trova un record visibile all'utente (null se non esiste o non è suo).
     * Il permesso autorizza l'azione, lo scoping limita i dati: servono entrambi.
     */
    public function find(int $id, int $userId): ?array
    {
        return $this->repo->findForUser($id, $userId);
    }

    /**
     * Crea un nuovo record.
     *
     * Qui va la validazione business (unicità, regole di dominio, ecc.).
     * La validazione form (required, formato) resta nel Controller.
     *
     * @param array $data    Dati già sanitizzati dal controller
     * @param int   $userId  Utente autore
     * @return int  ID del record creato
     */
    public function create(array $data, int $userId): int
    {
        $data['created_by'] = $userId;
        return $this->repo->create($data);
    }

    /**
     * Aggiorna un record esistente (solo se visibile all'utente).
     */
    public function update(int $id, array $data, int $userId): bool
    {
        if (!$this->repo->findForUser($id, $userId)) {
            return false;
        }
        return $this->repo->update($id, $data);
    }

    /**
     * Elimina un record (solo se visibile all'utente).
     */
    public function delete(int $id, int $userId): bool
    {
        if (!$this->repo->findForUser($id, $userId)) {
            return false;
        }
        return $this->repo->delete($id);
    }
}
