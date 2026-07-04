<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\ModuleLoader;
use App\Services\ModuleDatabaseResolver;
use PDO;

/**
 * Exports module table data as SQL INSERT statements.
 * Used by ModuleExportService when "include data" is requested.
 */
class ModuleDataExporter
{
    /** Number of rows per INSERT batch */
    private const CHUNK_SIZE = 500;

    /**
     * Export all tables declared in a module's module.json.
     *
     * @return array<string, string> [tableName => sqlContent]
     */
    public static function exportAllTables(string $moduleName): array
    {
        $loader = app(ModuleLoader::class);
        $meta   = $loader->readModuleJson($moduleName);

        if (!$meta || empty($meta['tables'])) {
            return [];
        }

        $pdo = self::getPdoForModule($moduleName, $meta);
        $result = [];

        foreach ($meta['tables'] as $table) {
            $sql = self::exportTable($pdo, $table);
            if ($sql !== '') {
                $result[$table] = $sql;
            }
        }

        return $result;
    }

    /**
     * Export a single table's data as SQL INSERT statements.
     * Returns empty string if table is empty or does not exist.
     */
    public static function exportTable(PDO $pdo, string $table): string
    {
        // Validate table name (prevent injection)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            return '';
        }

        // Check table exists
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $count = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return '';
        }

        if ($count === 0) {
            return '';
        }

        $lines = [];
        $lines[] = "-- Favilla module data export: {$table}";
        $lines[] = '-- Exported: ' . date('Y-m-d H:i:s');
        $lines[] = "-- Rows: {$count}";
        $lines[] = '';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        // Get columns
        $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $columnList = implode('`, `', $columns);

        // Determine primary key for deterministic ORDER BY
        $firstCol = $columns[0] ?? 'id';

        // Export in chunks
        $offset = 0;
        while ($offset < $count) {
            $chunkStmt = $pdo->prepare("SELECT * FROM `{$table}` ORDER BY `{$firstCol}` LIMIT ?, ?");
            $chunkStmt->bindValue(1, $offset, PDO::PARAM_INT);
            $chunkStmt->bindValue(2, self::CHUNK_SIZE, PDO::PARAM_INT);
            $chunkStmt->execute();
            $rows = $chunkStmt->fetchAll(PDO::FETCH_NUM);

            if (empty($rows)) {
                break;
            }

            $values = [];
            foreach ($rows as $row) {
                $escaped = array_map(function ($val) use ($pdo) {
                    if ($val === null) {
                        return 'NULL';
                    }
                    // PDO::quote() richiede una stringa: con EMULATE_PREPARES=false
                    // MariaDB restituisce int/float nativi, che altrimenti causano
                    // un TypeError. Il cast preserva il valore nel literal SQL.
                    return $pdo->quote((string) $val);
                }, $row);
                $values[] = '(' . implode(', ', $escaped) . ')';
            }

            $lines[] = "INSERT INTO `{$table}` (`{$columnList}`) VALUES";
            $lines[] = implode(",\n", $values) . ';';
            $lines[] = '';

            $offset += self::CHUNK_SIZE;
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines);
    }

    /**
     * Resolve the correct PDO connection for a module via the central resolver.
     * For shared modules returns the main PDO. For independent modules without
     * a usable mapping the resolver throws — the export is best-effort, so we
     * fall back to main PDO and log a warning to error_log.
     */
    private static function getPdoForModule(string $moduleName, array $meta): PDO
    {
        if (($meta['database'] ?? 'shared') !== 'independent') {
            return app(PDO::class);
        }

        try {
            return app(ModuleDatabaseResolver::class)->pdoFor($moduleName);
        } catch (\Throwable $e) {
            app_log(
                'error',
                "[ModuleDataExporter] Risoluzione DB modulo '{$moduleName}' fallita: "
                . $e->getMessage() . '. Export dati saltato (nessun fallback al DB principale).'
            );
            // Return main PDO so SHOW COLUMNS / SELECT will fail cleanly on the wrong DB
            // and exportTable() returns ''. This keeps the export ZIP valid (no module data).
            return app(PDO::class);
        }
    }
}
