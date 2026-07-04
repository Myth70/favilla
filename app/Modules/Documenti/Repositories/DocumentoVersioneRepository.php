<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoVersioneRepository extends BaseRepository
{
    protected string $table     = 'documenti_versioni';
    protected bool   $timestamps = false;
    protected bool   $auditable  = true;
    protected string $auditEntity = 'documento.versione';

    protected array $fillable = [
        'documento_id', 'versione_no', 'file_id', 'note_modifica',
        'stato', 'ripristino_di', 'created_by', 'pubblicato_il',
    ];

    /**
     * Restituisce TUTTE le versioni di un documento (no paginazione), ordinate DESC per versione_no.
     * Usato dalla timeline di show.php e dai partial HTMX.
     */
    public function findByDocumento(int $documentoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, f.original_name, f.mime_type, f.size_bytes, f.extension
             FROM documenti_versioni v
             LEFT JOIN documenti_files f ON f.id = v.file_id
             WHERE v.documento_id = ?
             ORDER BY v.versione_no DESC'
        );
        $stmt->execute([$documentoId]);
        return $stmt->fetchAll();
    }

    /**
     * Versione di findByDocumento con paginazione (per admin/audit page).
     */
    public function findByDocumentoPaginated(int $documentoId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM documenti_versioni WHERE documento_id = ?');
        $count->execute([$documentoId]);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT v.*, f.original_name, f.mime_type, f.size_bytes, f.extension
             FROM documenti_versioni v
             LEFT JOIN documenti_files f ON f.id = v.file_id
             WHERE v.documento_id = ?
             ORDER BY v.versione_no DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$documentoId]);

        return [
            'data'  => $stmt->fetchAll(),
            'total' => $total,
            'lastPage' => (int) ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    public function findVersione(int $documentoId, int $versioneId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.*, f.original_name, f.stored_name, f.directory, f.mime_type, f.size_bytes
             FROM documenti_versioni v
             LEFT JOIN documenti_files f ON f.id = v.file_id
             WHERE v.id = ? AND v.documento_id = ?
             LIMIT 1'
        );
        $stmt->execute([$versioneId, $documentoId]);
        return $stmt->fetch() ?: null;
    }

    public function maxVersioneNo(int $documentoId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(versione_no), 0) FROM documenti_versioni WHERE documento_id = ?'
        );
        $stmt->execute([$documentoId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Marca tutte le versioni precedenti come 'sostituito'.
     */
    public function markPreviousSostituite(int $documentoId, int $excludeVersioneId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE documenti_versioni SET stato = 'sostituito'
             WHERE documento_id = ? AND id <> ? AND stato = 'pubblicato'"
        );
        $stmt->execute([$documentoId, $excludeVersioneId]);
    }
}
