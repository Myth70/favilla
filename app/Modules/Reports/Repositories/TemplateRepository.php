<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class TemplateRepository extends BaseRepository
{
    protected string $table = 'report_templates';
    protected bool $timestamps = true;
    protected bool $auditable = true;
    protected string $auditEntity = 'report_template';

    protected array $fillable = [
        'name', 'description', 'module', 'source_key', 'output_format',
        'source_type', 'filters_config', 'sorting_config',
        'template_html',
        'style_preset_id', 'visibility', 'visible_to_roles', 'max_rows',
        'bundled_module', 'created_by',
    ];

    private const SORTABLE_COLUMNS = [
        'id', 'name', 'module', 'source_key', 'output_format',
        'source_type', 'visibility', 'created_at',
    ];

    /**
     * List templates visible to a user with pagination and filters.
     *
     * Visibility rules:
     *  - own templates (created_by = userId)
     *  - global templates
        *  - role-matched templates (LIKE su JSON serializzato, compatibile con MariaDB 10.4 e SQLite test)
     *
     * Filters: q, module, format
     */
    public function listVisible(int $userId, array $userRoles, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        // --- Build base WHERE for non-visibility filters ---
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(t.name LIKE ? OR t.description LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['module'])) {
            $where[] = 't.module = ?';
            $params[] = $filters['module'];
        }

        if (!empty($filters['format'])) {
            $where[] = 't.output_format = ?';
            $params[] = $filters['format'];
        }

        [$visibilitySql, $visibilityParams] = $this->buildVisibilitySql($userId, $userRoles, 't');
        $where[] = '(' . $visibilitySql . ')';
        $params = array_merge($params, $visibilityParams);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sort
        $sort = in_array($filters['sort'] ?? '', self::SORTABLE_COLUMNS, true)
            ? $filters['sort'] : 'created_at';
        $dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $countSql = "SELECT COUNT(*)
                     FROM report_templates t
                     {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT t.*, u.name AS creator_name,
                       sp.name AS style_name
                FROM report_templates t
                LEFT JOIN users u ON u.id = t.created_by
                LEFT JOIN report_style_presets sp ON sp.id = t.style_preset_id
                {$whereClause}
                ORDER BY t.{$sort} {$dir}
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...$params, $perPage, $offset]);

        return [
            'items'       => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * Find a template with all style preset fields joined.
     */
    public function findWithStyle(int $id): ?array
    {
        $sql = 'SELECT t.*,
                       sp.name AS style_name,
                       sp.description AS style_description,
                       sp.is_default AS style_is_default,
                       sp.logo_path AS style_logo_path,
                       sp.logo_secondary_path AS style_logo_secondary_path,
                       sp.primary_color AS style_primary_color,
                       sp.secondary_color AS style_secondary_color,
                       sp.accent_color AS style_accent_color,
                       sp.header_bg_color AS style_header_bg_color,
                       sp.header_text_color AS style_header_text_color,
                       sp.zebra_color AS style_zebra_color,
                       sp.font_family AS style_font_family,
                       sp.font_size_base AS style_font_size_base
                FROM report_templates t
                LEFT JOIN report_style_presets sp ON sp.id = t.style_preset_id
                WHERE t.id = ?
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get distinct module names from templates.
     */
    public function getDistinctModules(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT module FROM report_templates ORDER BY module ASC'
        );

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'module');
    }

    /**
     * Count templates grouped by module.
     */
    public function countByModule(): array
    {
        $stmt = $this->pdo->query(
            'SELECT module, COUNT(*) AS cnt FROM report_templates GROUP BY module ORDER BY module ASC'
        );

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['module']] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * Find templates by source (module + source_key), visible to the given user.
     */
    public function findBySource(string $module, string $sourceKey, int $userId, array $userRoles): array
    {
        [$visibilitySql, $visibilityParams] = $this->buildVisibilitySql($userId, $userRoles, 't');

        $sql = "SELECT t.*, sp.name AS style_name
                FROM report_templates t
                LEFT JOIN report_style_presets sp ON sp.id = t.style_preset_id
                WHERE t.module = ? AND t.source_key = ?
                  AND ({$visibilitySql})
                ORDER BY t.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$module, $sourceKey], $visibilityParams));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string[] $userRoles
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildVisibilitySql(int $userId, array $userRoles, string $alias = 't'): array
    {
        $clauses = ["{$alias}.visibility = ?", "{$alias}.created_by = ?"];
        $params = ['global', $userId];

        $roles = array_values(array_filter(array_map(static fn ($role) => trim((string) $role), $userRoles), fn ($role) => $role !== ''));
        if ($roles !== []) {
            $roleClauses = [];
            foreach ($roles as $role) {
                $roleClauses[] = "{$alias}.visible_to_roles LIKE ?";
            }

            $clauses[] = "({$alias}.visibility = ? AND (" . implode(' OR ', $roleClauses) . '))';
            $params[] = 'role';
            foreach ($roles as $role) {
                $params[] = '%"' . $role . '"%';
            }
        }

        return [implode(' OR ', $clauses), $params];
    }

    /**
     * Find all templates bundled by a specific module.
     */
    public function findBundledByModule(string $moduleName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM report_templates WHERE bundled_module = ? ORDER BY name ASC'
        );
        $stmt->execute([$moduleName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a bundled template by module + name (for upsert logic).
     */
    public function findBundledByName(string $moduleName, string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM report_templates WHERE bundled_module = ? AND name = ? LIMIT 1'
        );
        $stmt->execute([$moduleName, $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Delete all bundled templates for a module.
     */
    public function deleteBundledByModule(string $moduleName): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM report_templates WHERE bundled_module = ?'
        );
        $stmt->execute([$moduleName]);

        return $stmt->rowCount();
    }

    /**
     * Count bundled templates grouped by module.
     */
    public function countBundledByModule(): array
    {
        $stmt = $this->pdo->query(
            'SELECT bundled_module, COUNT(*) AS cnt
             FROM report_templates
             WHERE bundled_module IS NOT NULL
             GROUP BY bundled_module
             ORDER BY bundled_module ASC'
        );

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['bundled_module']] = (int) $row['cnt'];
        }

        return $result;
    }
}
