<?php

declare(strict_types=1);

namespace App\Modules\Backup\Repositories;

use App\Repositories\BaseRepository;

class BackupRepository extends BaseRepository
{
    protected string $table = 'backup_history';

    /**
     * Registra un backup appena creato nello storico.
     *
     * @param string     $format    'sqlgz' (legacy single-DB) | 'zip' (set multi-DB)
     * @param array|null $databases Dettaglio per-database (serializzato in JSON)
     * @param array|null $files     Riepilogo file per radice (serializzato in JSON)
     */
    public function record(
        string $filename,
        int $sizeBytes,
        int $tableCount,
        ?int $createdBy,
        string $format = 'sqlgz',
        ?array $databases = null,
        ?array $files = null
    ): int {
        return $this->create([
            'filename'    => $filename,
            'format'      => $format,
            'size_bytes'  => $sizeBytes,
            'table_count' => $tableCount,
            'databases_json' => $databases !== null
                ? json_encode($databases, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'files_json' => $files !== null
                ? json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'created_by'  => $createdBy,
        ]);
    }

    /**
     * Ritorna lo storico dei backup (solo record DB) ordinato per data decrescente.
     *
     * @return array[]
     */
    public function listHistory(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bh.*, u.name AS created_by_name
             FROM backup_history bh
             LEFT JOIN users u ON u.id = bh.created_by
             ORDER BY bh.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Elimina un record dallo storico per filename.
     */
    public function deleteByFilename(string $filename): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM backup_history WHERE filename = ?');
        $stmt->execute([$filename]);
        return $stmt->rowCount() > 0;
    }
}
