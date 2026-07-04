<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Admin\Repositories\RoleRepository;

/**
 * RoleResolver — punto unico di accesso ai ruoli per moduli non-Admin.
 *
 * Centralizza la risoluzione slug → id e l'elenco ruoli evitando che
 * altri moduli importino direttamente `App\Modules\Admin\Repositories\RoleRepository`
 * (cross-module coupling). Il Repository di Admin rimane fonte di verità per
 * la gestione amministrativa dei ruoli; questo Service espone solo letture.
 *
 * Cache per-request: la mappa slug→id viene costruita una volta sola per request.
 */
class RoleResolver
{
    private RoleRepository $roleRepo;

    /** @var array<string,int>|null */
    private ?array $slugToId = null;

    public function __construct()
    {
        $this->roleRepo = app(RoleRepository::class);
    }

    /**
     * Mappa completa `slug => id` di tutti i ruoli esistenti.
     *
     * @return array<string,int>
     */
    public function getSlugToIdMap(): array
    {
        if ($this->slugToId === null) {
            $this->slugToId = [];
            foreach ($this->roleRepo->all() as $role) {
                $this->slugToId[(string) $role['slug']] = (int) $role['id'];
            }
        }
        return $this->slugToId;
    }

    /**
     * Risolve un array di slug nei rispettivi id, scartando slug sconosciuti.
     *
     * @param string[] $slugs
     * @return int[]
     */
    public function getIdsBySlugs(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }
        $map = $this->getSlugToIdMap();
        $ids = [];
        foreach ($slugs as $slug) {
            if (isset($map[$slug])) {
                $ids[] = $map[$slug];
            }
        }
        return $ids;
    }

    /**
     * Elenco completo dei ruoli (id, slug, name, description, ...).
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->roleRepo->all();
    }

    /**
     * Single role lookup by id.
     *
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->roleRepo->find($id);
    }
}
