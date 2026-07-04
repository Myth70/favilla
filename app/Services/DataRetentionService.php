<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ISO 27001 A.18.1.3 — Data Retention Policy service.
 *
 * Manages entity-level retention policies with configurable TTL and action
 * (delete or anonymize). Policies are stored in `data_retention_policies`.
 */
class DataRetentionService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    // ── Policy CRUD ──────────────────────────────────────────────────────

    /**
     * Get all policies sorted by entity.
     */
    public function allPolicies(): array
    {
        return $this->pdo->query(
            'SELECT * FROM data_retention_policies ORDER BY entity ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a policy by ID.
     */
    public function findPolicy(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM data_retention_policies WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update retention_days and action for a policy.
     */
    public function updatePolicy(int $id, int $retentionDays, string $action): bool
    {
        $retentionDays = max(0, $retentionDays);
        $action = in_array($action, ['delete', 'anonymize'], true) ? $action : 'delete';
        $stmt = $this->pdo->prepare(
            'UPDATE data_retention_policies
             SET retention_days = ?, action = ?, updated_at = NOW()
             WHERE id = ?'
        );
        return $stmt->execute([$retentionDays, $action, $id]);
    }

    /**
     * Toggle enabled status of a policy.
     */
    public function togglePolicy(int $id, bool $enabled): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE data_retention_policies SET enabled = ?, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$enabled ? 1 : 0, $id]);
    }

    // ── Execution ────────────────────────────────────────────────────────

    /**
     * Execute all enabled retention policies. Returns summary of actions taken.
     *
     * @param bool $dryRun If true, only count without deleting
     * @return array [ ['entity' => ..., 'action' => ..., 'affected' => int], ... ]
     */
    public function executeAll(bool $dryRun = false): array
    {
        $policies = $this->pdo->query(
            'SELECT * FROM data_retention_policies WHERE enabled = 1'
        )->fetchAll(PDO::FETCH_ASSOC);

        $results = [];

        foreach ($policies as $policy) {
            try {
                $result = $this->executePolicy($policy, $dryRun);
                if ($result !== null) {
                    $results[] = $result;
                }
            } catch (\Throwable $e) {
                // Table may not exist or have different schema — skip gracefully
                $results[] = [
                    'entity'   => $policy['entity'],
                    'table'    => $policy['table_name'],
                    'action'   => $policy['action'],
                    'cutoff'   => '',
                    'affected' => 0,
                    'dry_run'  => $dryRun,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        // Log execution
        if (!$dryRun) {
            try {
                AuditService::log('retention_executed', 'system', null, null, [
                    'policies_run' => count($results),
                    'total_affected' => array_sum(array_column($results, 'affected')),
                    'error_count' => count(array_filter($results, static fn (array $row): bool => !empty($row['error']))),
                ]);
            } catch (\Throwable $e) {
                app_log('error', self::class . ': retention audit log failed: ' . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Execute a single policy.
     */
    private function executePolicy(array $policy, bool $dryRun): ?array
    {
        $entity   = $policy['entity'];
        $table    = $this->sanitizeIdentifier((string) ($policy['table_name'] ?? ''), 'tabella');
        $column   = $this->sanitizeIdentifier((string) ($policy['date_column'] ?? ''), 'colonna data');
        $action   = ($policy['action'] ?? 'delete') === 'anonymize' ? 'anonymize' : 'delete';
        $days     = (int) $policy['retention_days'];

        if ($days < 0 || $table === '' || $column === '') {
            return null;
        }

        if (!$this->tableExists($table)) {
            throw new \RuntimeException("La tabella '{$table}' non esiste.");
        }

        if (!$this->columnExists($table, $column)) {
            throw new \RuntimeException("La colonna '{$column}' non esiste nella tabella '{$table}'.");
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $whereClause = $this->buildRetentionWhereClause($table, $column, $action);

        // Count affected rows
        $countSql = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $whereClause;
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute([$cutoff]);
        $affected = (int) $stmt->fetchColumn();

        if ($affected === 0 || $dryRun) {
            return [
                'entity'   => $entity,
                'table'    => $table,
                'action'   => $action,
                'cutoff'   => $cutoff,
                'affected' => $affected,
                'dry_run'  => $dryRun,
            ];
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($action === 'anonymize') {
                $fields = json_decode((string) ($policy['anonymize_fields'] ?? '[]'), true);
                $this->anonymize($table, $whereClause, $cutoff, is_array($fields) ? $fields : []);
            } else {
                $this->purge($table, $whereClause, $cutoff);
            }

            // Update last_run_at
            $stmt = $this->pdo->prepare(
                'UPDATE data_retention_policies SET last_run_at = NOW() WHERE id = ?'
            );
            $stmt->execute([(int) $policy['id']]);

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return [
            'entity'   => $entity,
            'table'    => $table,
            'action'   => $action,
            'cutoff'   => $cutoff,
            'affected' => $affected,
            'dry_run'  => false,
        ];
    }

    /**
     * Hard delete old rows from table.
     */
    private function purge(string $table, string $whereClause, string $cutoff): void
    {
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $whereClause;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cutoff]);
    }

    /**
     * Anonymize PII fields in old rows.
     */
    private function anonymize(string $table, string $whereClause, string $cutoff, array $fields): void
    {
        if (empty($fields)) {
            throw new \RuntimeException("La policy di anonimizzazione per '{$table}' non definisce campi validi.");
        }

        $sets = [];
        foreach ($fields as $field) {
            $candidate = trim((string) $field);
            if ($candidate === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $candidate)) {
                continue;
            }

            if ($this->columnExists($table, $candidate)) {
                $sets[] = $this->quoteIdentifier($candidate) . " = '[RIMOSSO]'";
            }
        }

        if (empty($sets)) {
            throw new \RuntimeException("La policy di anonimizzazione per '{$table}' non contiene colonne esistenti da anonimizzare.");
        }

        $sql = 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $whereClause;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cutoff]);
    }

    /**
     * Check if a column exists in a table.
     */
    private function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->getTableColumns($table), true);
    }

    private function tableExists(string $table): bool
    {
        return $this->getTableColumns($table) !== [];
    }

    /**
     * @return string[]
     */
    private function getTableColumns(string $table): array
    {
        static $cache = [];

        $table = $this->sanitizeIdentifier($table, 'tabella');
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        if ($this->isSqlite()) {
            $stmt = $this->pdo->query('PRAGMA table_info("' . $table . '")');
            $columns = $stmt !== false ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name') : [];
            return $cache[$table] = array_values(array_filter(array_map('strval', $columns)));
        }

        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return $cache[$table] = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    private function buildRetentionWhereClause(string $table, string $column, string $action): string
    {
        $clauses = [$this->quoteIdentifier($column) . ' < ?'];

        if ($this->columnExists($table, 'deleted_at')) {
            $deletedAt = $this->quoteIdentifier('deleted_at');
            if ($action === 'delete') {
                $clauses[] = $deletedAt . ' IS NOT NULL';
            } elseif ($action === 'anonymize') {
                $clauses[] = $deletedAt . ' IS NULL';
            }
        }

        return implode(' AND ', $clauses);
    }

    private function sanitizeIdentifier(string $identifier, string $label): string
    {
        $identifier = trim($identifier);
        if ($identifier === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \RuntimeException("Identificatore non valido per {$label} nella policy retention.");
        }

        return $identifier;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . $this->sanitizeIdentifier($identifier, 'identificatore SQL') . '`';
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    /**
     * Get summary stats for dashboard display.
     */
    public function getStats(): array
    {
        $policies = $this->allPolicies();
        $total    = count($policies);
        $enabled  = 0;
        $overdue  = 0;

        foreach ($policies as $p) {
            if ($p['enabled']) {
                $enabled++;
            }
            if ($p['enabled'] && $p['last_run_at'] === null) {
                $overdue++;
            } elseif ($p['enabled'] && $p['last_run_at'] !== null) {
                // Check if last run is older than interval_minutes / retention_days
                $lastRun = strtotime($p['last_run_at']);
                $interval = (int) $p['retention_days'];
                // Consider overdue if not run in the last 7 days
                if (time() - $lastRun > 7 * 86400) {
                    $overdue++;
                }
            }
        }

        return [
            'total'   => $total,
            'enabled' => $enabled,
            'overdue' => $overdue,
        ];
    }
}
