<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoFileRepository extends BaseRepository
{
    protected string $table     = 'documenti_files';
    protected bool   $timestamps = true;
    protected bool   $softDelete = true;

    protected array $fillable = [
        'original_name', 'stored_name', 'directory', 'mime_type',
        'extension', 'size_bytes', 'checksum_sha256', 'created_by',
    ];

    public function findByChecksum(string $checksum): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti_files WHERE checksum_sha256 = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$checksum]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Conta file orfani: in documenti_files ma non referenziati in documenti_versioni.
     */
    public function countOrphans(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM documenti_files df
             WHERE df.deleted_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM documenti_versioni dv WHERE dv.file_id = df.id)'
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Restituisce i file orfani più vecchi di N ore. Usato dal job di cleanup
     * per evitare di cancellare file appena caricati che non hanno ancora
     * un record versione (race window).
     *
     * @return array<array<string,mixed>>
     */
    public function findOrphansOlderThan(int $hours): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT df.* FROM documenti_files df
             WHERE df.deleted_at IS NULL
               AND df.created_at < ?
               AND NOT EXISTS (SELECT 1 FROM documenti_versioni dv WHERE dv.file_id = df.id)'
        );
        $stmt->execute([date('Y-m-d H:i:s', time() - $hours * 3600)]);
        return $stmt->fetchAll();
    }

    public function allStoredNames(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, stored_name, directory FROM documenti_files WHERE deleted_at IS NULL'
        );
        return $stmt->fetchAll();
    }

    /**
     * Tutti i file con i dati necessari alla verifica integrità (hash su disco).
     * Usato da documenti:verify-integrity e dall'health check.
     *
     * @return array<array<string,mixed>>
     */
    public function allForIntegrity(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, stored_name, directory, checksum_sha256, size_bytes
             FROM documenti_files WHERE deleted_at IS NULL ORDER BY id'
        );
        return $stmt->fetchAll();
    }

    /**
     * Conta i file privi di checksum (record legacy o backfill mai eseguito).
     */
    public function countNullChecksum(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM documenti_files
             WHERE deleted_at IS NULL AND (checksum_sha256 IS NULL OR checksum_sha256 = '')"
        );
        return (int) $stmt->fetchColumn();
    }
}
