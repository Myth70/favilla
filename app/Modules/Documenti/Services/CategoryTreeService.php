<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoCategoriaRepository;

/**
 * Gestione albero categorie: path materializzato, depth, spostamento, cycle-check.
 */
class CategoryTreeService
{
    private DocumentoCategoriaRepository $repo;

    public function __construct()
    {
        $this->repo = app(DocumentoCategoriaRepository::class);
    }

    /**
     * Costruisce l'albero annidato dall'array flat.
     */
    public function buildTree(array $flat, ?int $parentId = null): array
    {
        $result = [];
        foreach ($flat as $row) {
            $rowParent = $row['parent_id'] ? (int) $row['parent_id'] : null;
            if ($rowParent === $parentId) {
                $row['children'] = $this->buildTree($flat, (int) $row['id']);
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * Calcola il path (prefisso) e il depth per i figli di una categoria.
     * Il `path` restituito è il path materializzato COMPLETO del parent
     * (già terminante con `/{parentId}/`); il figlio vi appende il proprio id
     * tramite {@see pathFiglio()}. Per la radice il prefisso è `/`.
     */
    public function computePathDepth(?int $parentId): array
    {
        if ($parentId === null) {
            return ['path' => '/', 'depth' => 0];
        }
        $parent = $this->repo->find($parentId);
        if (!$parent) {
            return ['path' => '/', 'depth' => 0];
        }
        // Il path del parent contiene già il proprio id: non va ri-appeso (bug doppio /{id}/{id}/).
        $parentPath = ($parent['path'] ?? '') !== ''
            ? rtrim($parent['path'], '/') . '/'
            : '/' . $parentId . '/';
        return ['path' => $parentPath, 'depth' => (int) $parent['depth'] + 1];
    }

    /**
     * Path materializzato di un figlio, dato il path completo del parent.
     * Funzione pura (nessuna dipendenza) per essere testabile senza DB.
     * Es. pathFiglio('/5/', 12) === '/5/12/'; pathFiglio('/', 5) === '/5/'.
     */
    public static function pathFiglio(?string $parentPath, int $childId): string
    {
        $base = ($parentPath !== null && $parentPath !== '') ? rtrim($parentPath, '/') . '/' : '/';
        return $base . $childId . '/';
    }

    /**
     * Verifica se spostare $categoriaId sotto $newParentId crea un ciclo.
     */
    public function wouldCreateCycle(int $categoriaId, int $newParentId): bool
    {
        if ($categoriaId === $newParentId) {
            return true;
        }
        $discendenti = $this->repo->findDiscendenti($categoriaId);
        foreach ($discendenti as $d) {
            if ((int) $d['id'] === $newParentId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sposta una categoria aggiornando path e depth di tutta la sua sottoalbero.
     */
    public function sposta(int $categoriaId, ?int $newParentId, int $userId): void
    {
        if ($newParentId !== null && $this->wouldCreateCycle($categoriaId, $newParentId)) {
            throw new \RuntimeException(t('documenti.exception.spostamento_ciclo'));
        }

        ['path' => $path, 'depth' => $depth] = $this->computePathDepth($newParentId);

        $this->repo->update($categoriaId, [
            'parent_id'  => $newParentId,
            'path'       => self::pathFiglio($path, $categoriaId),
            'depth'      => $depth,
            'updated_by' => $userId,
        ]);

        // Ricalcola i discendenti
        $this->ricalcolaDiscendenti($categoriaId);
    }

    /**
     * Ricalcola path e depth di tutti i discendenti.
     */
    private function ricalcolaDiscendenti(int $parentId): void
    {
        $parent = $this->repo->find($parentId);
        if (!$parent) {
            return;
        }
        // Il path del parent è già completo (`/.../{parentId}/`): i figli vi appendono il proprio id.
        $basePath = $parent['path'] ?? '';
        $newDepth = (int) $parent['depth'] + 1;
        $figli = $this->repo->where(['parent_id' => $parentId]);
        foreach ($figli as $figlio) {
            $id = (int) $figlio['id'];
            $this->repo->update($id, ['path' => self::pathFiglio($basePath, $id), 'depth' => $newDepth]);
            $this->ricalcolaDiscendenti($id);
        }
    }

    /**
     * Crea una categoria validando unicità slug e codice.
     */
    public function create(array $data, int $userId): int
    {
        $data['slug']  = $data['slug']  ?? $this->slugify($data['nome'] ?? '');
        $data['codice'] = strtoupper(trim($data['codice'] ?? ''));

        if ($this->repo->findBySlug($data['slug'])) {
            throw new \RuntimeException(t('documenti.exception.slug_duplicato'));
        }
        if ($this->repo->findByCodice($data['codice'])) {
            throw new \RuntimeException(t('documenti.exception.codice_duplicato'));
        }

        ['path' => $path, 'depth' => $depth] = $this->computePathDepth($data['parent_id'] ?? null);

        $data['path']       = $path; // verrà aggiornato dopo l'insert con l'id
        $data['depth']      = $depth;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $id = $this->repo->create($data);

        // Aggiorna il path materializzato ora che l'id è noto.
        $this->repo->update($id, ['path' => self::pathFiglio($path, $id)]);

        return $id;
    }

    /**
     * Elenco piatto delle categorie ordinato per path (per dropdown/selettori).
     */
    public function alberoOrdinato(): array
    {
        return $this->repo->allTree();
    }

    /**
     * Albero annidato completo (per le pagine di gestione categorie).
     * Include `n_documenti` per categoria: Views/partials/albero_categorie.php lo
     * usa per abilitare/disabilitare il pulsante elimina (una categoria con
     * documenti non può essere cancellata — vedi elimina()).
     */
    public function treeCompleto(): array
    {
        return $this->buildTree($this->repo->allWithDocumentCount(), null);
    }

    /**
     * Elenco piatto grezzo (non ordinato per path).
     */
    public function flat(): array
    {
        return $this->repo->all();
    }

    /**
     * Singola categoria per id.
     */
    public function categoria(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Aggiorna i metadati di una categoria.
     *
     * @param array{nome:string,descrizione?:?string,codice:string,reminder_giorni_default?:mixed} $data
     */
    public function aggiorna(int $id, array $data, int $userId): void
    {
        $this->repo->update($id, [
            'nome'                    => $data['nome'],
            'descrizione'             => ($data['descrizione'] ?? '') !== '' ? $data['descrizione'] : null,
            'codice'                  => strtoupper(trim((string) ($data['codice'] ?? ''))),
            'reminder_giorni_default' => !empty($data['reminder_giorni_default']) ? $data['reminder_giorni_default'] : null,
            'updated_by'              => $userId,
        ]);
    }

    /**
     * Elimina una categoria, bloccando se contiene documenti o sottocategorie.
     *
     * @throws \RuntimeException
     */
    public function elimina(int $id): void
    {
        if ($this->repo->hasDocumenti($id)) {
            throw new \RuntimeException(t('documenti.exception.categoria_con_documenti'));
        }
        if ($this->repo->hasFigli($id)) {
            throw new \RuntimeException(t('documenti.exception.categoria_con_figli'));
        }
        $this->repo->delete($id);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
