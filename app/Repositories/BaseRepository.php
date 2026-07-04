<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\AuditService;
use PDO;

abstract class BaseRepository
{
    protected PDO $pdo;
    protected string $table;
    protected bool $softDelete = false;

    // --- Mass-assignment protection (opt-in) ---
    /** Whitelist: if non-empty, ONLY these columns pass through filterData(). */
    protected array $fillable = [];
    /** Blacklist: these columns are stripped. Empty by default (opt-in). */
    protected array $guarded = [];

    // --- Automatic timestamps (opt-in) ---
    protected bool $timestamps = false;

    // --- Automatic audit logging (opt-in) ---
    protected bool $auditable = false;
    /** Entity name for audit logs; defaults to $table if empty. */
    protected string $auditEntity = '';

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    // =====================================================================
    //  Lifecycle hooks — override in child repositories
    // =====================================================================

    protected function beforeCreate(array &$data): void
    {
    }
    protected function afterCreate(int $id, array $data): void
    {
    }
    protected function beforeUpdate(int $id, array &$data): void
    {
    }
    protected function afterUpdate(int $id, array $data): void
    {
    }
    protected function beforeDelete(int $id): void
    {
    }
    protected function afterDelete(int $id): void
    {
    }

    /**
     * Find a record by ID.
     */
    public function find(int $id): ?array
    {
        $deletedFilter = $this->softDelete ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = ?{$deletedFilter} LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all records.
     */
    public function all(): array
    {
        if ($this->softDelete) {
            $stmt = $this->pdo->query("SELECT * FROM {$this->table} WHERE deleted_at IS NULL");
        } else {
            $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        }
        return $stmt->fetchAll();
    }

    /**
     * Find records matching conditions.
     *
     * @param array $conditions ['column' => 'value']
     */
    public function where(array $conditions): array
    {
        $clauses = [];
        $values = [];
        foreach ($conditions as $col => $val) {
            $this->assertValidColumn($col);
            $clauses[] = "{$col} = ?";
            $values[] = $val;
        }
        $where = implode(' AND ', $clauses);
        if ($this->softDelete) {
            $where .= ' AND deleted_at IS NULL';
        }
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    /**
     * Find multiple records by id in a single query (N+1 escape hatch).
     * Returns the rows keyed by id for O(1) lookups.
     *
     * @param int[] $ids
     * @return array<int, array<string,mixed>>
     */
    public function findManyByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $deletedFilter = $this->softDelete ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id IN ({$placeholders}){$deletedFilter}"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    /**
     * Find a single record by column value.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $this->assertValidColumn($column);
        $deletedFilter = $this->softDelete ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$column} = ?{$deletedFilter} LIMIT 1"
        );
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Insert a record and return the inserted ID.
     */
    public function create(array $data): int
    {
        $data = $this->filterData($data);
        $this->applyTimestamps($data, true);
        $this->beforeCreate($data);

        foreach (array_keys($data) as $col) {
            $this->assertValidColumn($col);
        }
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $id = (int) $this->pdo->lastInsertId();

        $this->afterCreate($id, $data);
        $this->auditAction('created', $id, null, $data);

        return $id;
    }

    /**
     * Update a record by ID.
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->filterData($data);
        $this->applyTimestamps($data, false);
        $this->beforeUpdate($id, $data);

        // Snapshot old values for audit (only if auditable)
        $oldData = $this->auditable ? $this->findWithTrashed($id) : null;

        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            $this->assertValidColumn($col);
            $sets[] = "{$col} = ?";
            $values[] = $val;
        }
        $values[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($values) && $stmt->rowCount() > 0;

        if ($result) {
            $this->afterUpdate($id, $data);
            $this->auditAction('updated', $id, $oldData, $data);
        }

        return $result;
    }

    /**
     * Delete a record. Soft delete if enabled, hard delete otherwise.
     */
    public function delete(int $id): bool
    {
        $this->beforeDelete($id);

        // Snapshot for audit (only if auditable)
        $oldData = $this->auditable ? $this->findWithTrashed($id) : null;

        if ($this->softDelete) {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
            );
            $result = $stmt->execute([$id]) && $stmt->rowCount() > 0;
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $result = $stmt->execute([$id]) && $stmt->rowCount() > 0;
        }

        if ($result) {
            $this->afterDelete($id);
            $this->auditAction('deleted', $id, $oldData, null);
        }

        return $result;
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(int $id): bool
    {
        if (!$this->softDelete) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET deleted_at = NULL WHERE id = ?"
        );
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Permanently delete a record, even if soft-deleted.
     */
    public function forceDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Find a record by ID including soft-deleted records.
     */
    public function findWithTrashed(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all records including soft-deleted.
     */
    public function allWithTrashed(string $orderBy = 'id', string $direction = 'DESC'): array
    {
        $this->assertValidColumn($orderBy);
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $stmt = $this->pdo->query(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get only soft-deleted records.
     */
    public function onlyTrashed(): array
    {
        if (!$this->softDelete) {
            return [];
        }
        $stmt = $this->pdo->query(
            "SELECT * FROM {$this->table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Count records, optionally filtered.
     */
    public function count(array $conditions = []): int
    {
        $clauses = [];
        $values = [];
        foreach ($conditions as $col => $val) {
            $this->assertValidColumn($col);
            $clauses[] = "{$col} = ?";
            $values[] = $val;
        }
        if ($this->softDelete) {
            $clauses[] = 'deleted_at IS NULL';
        }
        if (empty($clauses)) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->table}");
            return (int) $stmt->fetchColumn();
        }
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $clauses);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Paginate records with optional conditions and sorting.
     *
     * @param int    $page       Current page (1-based)
     * @param int    $perPage    Items per page
     * @param array  $conditions ['column' => 'value'] filters
     * @param string $orderBy    Column to sort by
     * @param string $direction  ASC or DESC
     * @return array{data: array, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        string $orderBy = 'id',
        string $direction = 'DESC'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $this->assertValidColumn($orderBy);
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $clauses = [];
        $values = [];

        foreach ($conditions as $col => $val) {
            $this->assertValidColumn($col);
            if ($val === null) {
                $clauses[] = "{$col} IS NULL";
            } else {
                $clauses[] = "{$col} = ?";
                $values[] = $val;
            }
        }

        if ($this->softDelete) {
            $clauses[] = 'deleted_at IS NULL';
        }

        $whereSQL = !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';

        // Count totale
        $countSQL = "SELECT COUNT(*) FROM {$this->table} {$whereSQL}";
        $stmt = $this->pdo->prepare($countSQL);
        $stmt->execute($values);
        $total = (int) $stmt->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        // Fetch pagina
        $dataSQL = "SELECT * FROM {$this->table} {$whereSQL} ORDER BY {$orderBy} {$direction} {$this->limitClause($perPage, $offset)}";
        $stmt = $this->pdo->prepare($dataSQL);
        $stmt->execute($values);
        $data = $stmt->fetchAll();

        return [
            'data'    => $data,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * Execute inside a transaction.
     */
    protected function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =====================================================================
    //  Internal helpers
    // =====================================================================

    /**
     * Filter data through fillable/guarded rules.
     * If $fillable is non-empty, only those keys pass (whitelist).
     * Otherwise, $guarded keys are stripped (blacklist).
     */
    protected function filterData(array $data): array
    {
        if (!empty($this->fillable)) {
            return array_intersect_key($data, array_flip($this->fillable));
        }
        if (!empty($this->guarded)) {
            return array_diff_key($data, array_flip($this->guarded));
        }
        return $data;
    }

    /**
     * Add timestamp columns if $timestamps is enabled.
     * Uses PHP date() — zero extra DB roundtrip.
     */
    private function applyTimestamps(array &$data, bool $isCreate): void
    {
        if (!$this->timestamps) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if ($isCreate && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }
    }

    /**
     * Log an audit entry if $auditable is enabled.
     */
    private function auditAction(string $action, int $entityId, ?array $old, ?array $new): void
    {
        if (!$this->auditable) {
            return;
        }
        $entity = $this->auditEntity ?: $this->table;
        AuditService::log("{$entity}_{$action}", $entity, $entityId, $old, $new);
    }

    /**
     * Build an injection-safe "LIMIT … [OFFSET …]" SQL fragment.
     *
     * MySQL/MariaDB do not allow LIMIT/OFFSET to be bound as prepared-statement
     * parameters, so these values must be interpolated into the query string.
     * The signature already forces integers; clamping to a non-negative range
     * here guarantees the fragment can never carry injection, regardless of what
     * a caller forwards. Business bounds (e.g. max page size) stay with callers.
     */
    protected function limitClause(int $limit, ?int $offset = null): string
    {
        $clause = 'LIMIT ' . max(0, $limit);
        if ($offset !== null) {
            $clause .= ' OFFSET ' . max(0, $offset);
        }
        return $clause;
    }

    /**
     * Validate that a column name is safe for SQL interpolation.
     * Only allows alphanumeric characters and underscores.
     */
    private function assertValidColumn(string $column): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
    }
}
