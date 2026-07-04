<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Repositories;

use App\Repositories\BaseRepository;
use PDO;

/**
 * Query diagnostiche di sola lettura su tabelle core / introspezione del DB.
 *
 * Concentra qui l'SQL che la vecchia HealthCheckService eseguiva grezzo al suo
 * interno (versione DB, charset, carico connessioni, hash password admin, stato
 * migrazioni), così da rispettare il layering Controller → Service → Repository.
 *
 * NOTA: alcune query usano information_schema / SHOW (specifiche MariaDB/MySQL)
 * e non sono eseguibili su SQLite — sono coperte nei test tramite fake del
 * repository iniettato nei check.
 */
class SystemDiagnosticsRepository extends BaseRepository
{
    // BaseRepository richiede una tabella; qui le query sono custom e non la usano.
    protected string $table = 'migrations';

    /**
     * Versione del server database (es. "10.4.32-MariaDB").
     */
    public function databaseVersion(): string
    {
        return (string) $this->pdo->query('SELECT VERSION()')->fetchColumn();
    }

    /**
     * Nomi delle tabelle del database corrente che NON usano collation utf8mb4.
     *
     * @return string[]
     */
    public function tablesNotUtf8mb4(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->query(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_COLLATION NOT LIKE 'utf8mb4%'
             {$this->limitClause($limit)}"
        );

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Carico connessioni: connessioni attive vs massimo consentito.
     *
     * @return array{active:int,max:int}|null  null se non determinabile.
     */
    public function connectionLoad(): ?array
    {
        $maxRow    = $this->pdo->query("SHOW VARIABLES LIKE 'max_connections'")->fetch();
        $activeRow = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch();

        $max    = (int) ($maxRow['Value'] ?? 0);
        $active = (int) ($activeRow['Value'] ?? 0);

        if ($max <= 0) {
            return null;
        }

        return ['active' => $active, 'max' => $max];
    }

    /**
     * Hash password degli utenti con ruolo admin (per la verifica password deboli).
     *
     * @return string[]
     */
    public function adminPasswordHashes(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare(
            "SELECT u.password
             FROM users u
             INNER JOIN user_role ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = 'admin'
               AND u.deleted_at IS NULL
             LIMIT ?"
        );
        $stmt->execute([$limit]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Filename delle migrazioni core già eseguite.
     *
     * @return string[]
     */
    public function executedCoreMigrations(): array
    {
        $rows = $this->pdo->query('SELECT filename FROM migrations WHERE module IS NULL')
            ->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $rows);
    }

    /**
     * Filename delle migrazioni di modulo già eseguite, raggruppati per modulo.
     *
     * @return array<string,string[]>
     */
    public function executedModuleMigrations(): array
    {
        $rows = $this->pdo->query('SELECT filename, module FROM migrations WHERE module IS NOT NULL')
            ->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $module   = (string) ($row['module'] ?? '');
            $filename = (string) ($row['filename'] ?? '');
            if ($module === '' || $filename === '') {
                continue;
            }
            $map[$module][] = $filename;
        }

        return $map;
    }
}
