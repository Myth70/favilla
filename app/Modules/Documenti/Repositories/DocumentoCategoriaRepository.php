<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoCategoriaRepository extends BaseRepository
{
    protected string $table      = 'documenti_categorie';
    protected bool   $timestamps = true;
    protected bool   $softDelete = true;
    protected bool   $auditable  = true;
    protected string $auditEntity = 'documento.categoria';

    protected array $fillable = [
        'parent_id', 'nome', 'slug', 'codice', 'descrizione', 'colore', 'icona',
        'path', 'depth', 'approvazione_richiesta', 'reminder_giorni_default',
        'ordine', 'created_by', 'updated_by',
    ];

    public function allTree(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM documenti_categorie WHERE deleted_at IS NULL ORDER BY path, ordine, nome'
        );
        return $stmt->fetchAll();
    }

    /**
     * Elenco piatto con conteggio documenti (non cancellati) per categoria.
     * Il conteggio alimenta `n_documenti` in Views/partials/albero_categorie.php,
     * che lo usa per decidere se mostrare il pulsante elimina come attivo.
     */
    public function allWithDocumentCount(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.*, COUNT(d.id) AS n_documenti
             FROM documenti_categorie c
             LEFT JOIN documenti d ON d.categoria_id = c.id AND d.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.path, c.ordine, c.nome'
        );
        return $stmt->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti_categorie WHERE slug = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function findByCodice(string $codice): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti_categorie WHERE codice = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$codice]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Tutti i discendenti di una categoria (per ricalcolo path).
     */
    public function findDiscendenti(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti_categorie WHERE path LIKE ? AND deleted_at IS NULL ORDER BY depth'
        );
        $stmt->execute(["%/{$parentId}/%"]);
        return $stmt->fetchAll();
    }

    public function hasDocumenti(int $categoriaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documenti WHERE categoria_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$categoriaId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasFigli(int $categoriaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documenti_categorie WHERE parent_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$categoriaId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
