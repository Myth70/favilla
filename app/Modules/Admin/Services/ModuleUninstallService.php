<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\ModuleLoader;
use App\Services\ModuleDatabaseResolver;
use PDO;

/**
 * Completely uninstalls a Favilla module.
 *
 * Removes: DB tables, permissions, module_states, migration records,
 * CSS/JS assets, uploaded files, and the module directory.
 */
class ModuleUninstallService
{
    /**
     * Find all non-core modules whose module.json "dependencies" include $moduleName.
     * Returns an array of module names that hard-depend on the given module.
     */
    public static function getDependentModules(string $moduleName): array
    {
        $loader = app(ModuleLoader::class);
        $allModules = $loader->getModules();
        $dependents = [];

        foreach ($allModules as $mod) {
            $name = $mod['name'] ?? '';
            if ($name === $moduleName || $name === '_Template') {
                continue;
            }
            $meta = $loader->readModuleJson($name);
            if ($meta === null) {
                continue;
            }
            $deps = $meta['dependencies'] ?? [];
            if (in_array($moduleName, $deps, true)) {
                $dependents[] = $name;
            }
        }

        return $dependents;
    }

    /**
     * Uninstall a module.
     *
     * @param string $moduleName    Module directory name
     * @param bool   $dropTables    Drop database tables declared in module.json
     * @param bool   $deleteUploads Delete uploaded files (public/uploads/{dir}/)
     */
    public static function uninstall(
        string $moduleName,
        bool $dropTables = true,
        bool $deleteUploads = true,
        bool $dropDatabase = false
    ): UninstallResult {
        $log      = [];
        $warnings = [];

        // Validate module name (security)
        if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $moduleName)) {
            return UninstallResult::fail("Nome modulo non valido: '{$moduleName}'.");
        }

        // Prevent uninstalling core modules (read from config)
        $allModules = config('modules') ?? [];
        $coreModules = array_column(
            array_filter($allModules, fn ($m) => !empty($m['core'])),
            'name'
        );
        if (in_array($moduleName, $coreModules, true)) {
            return UninstallResult::fail("Il modulo '{$moduleName}' e' un modulo core e non puo' essere disinstallato.");
        }

        // Block uninstall if other modules hard-depend on this one
        $dependents = self::getDependentModules($moduleName);
        if (!empty($dependents)) {
            $depList = implode(', ', $dependents);
            return UninstallResult::fail(
                "Impossibile disinstallare '{$moduleName}': i seguenti moduli ne dipendono: {$depList}. Rimuovili o disinstallali prima."
            );
        }

        $moduleDir = BASE_PATH . '/app/Modules/' . $moduleName;
        if (!is_dir($moduleDir)) {
            return UninstallResult::fail("Modulo '{$moduleName}' non trovato in app/Modules/.");
        }

        // Read module.json
        $loader = app(ModuleLoader::class);
        $meta = $loader->readModuleJson($moduleName);

        if ($meta === null) {
            $warnings[] = 'module.json non trovato. Solo i file verranno rimossi; tabelle e permessi dovranno essere gestiti manualmente.';
        }

        $pdo = app(PDO::class);
        $tables = $meta['tables'] ?? [];
        $dbMode = $meta['database'] ?? 'shared';
        $dbPrefix = $meta['database_env_prefix'] ?? null;

        // ── 1. Drop tables ────────────────────────────────────────────
        if ($dropTables && !empty($tables)) {
            try {
                $targetPdo = $pdo;
                if ($dbMode === 'independent') {
                    try {
                        $targetPdo = app(ModuleDatabaseResolver::class)->pdoFor($moduleName);
                    } catch (\Throwable $e) {
                        $warnings[] = 'Impossibile risolvere DB modulo per drop tabelle: ' . $e->getMessage()
                                    . '. Drop tabelle saltato (mantenute nel DB indipendente).';
                        $targetPdo = null;
                    }
                }

                if ($targetPdo !== null) {

                    $targetPdo->exec('SET FOREIGN_KEY_CHECKS=0');

                    try {
                        // Drop in reverse order (respect FK dependencies)
                        $reverseTables = array_reverse($tables);
                        foreach ($reverseTables as $table) {
                            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                                $warnings[] = "Nome tabella non valido ignorato: {$table}";
                                continue;
                            }
                            try {
                                $targetPdo->exec("DROP TABLE IF EXISTS `{$table}`");
                                $log[] = "Tabella eliminata: {$table}";
                            } catch (\PDOException $e) {
                                $warnings[] = "Errore eliminazione tabella {$table}: " . $e->getMessage();
                            }
                        }
                    } finally {
                        $targetPdo->exec('SET FOREIGN_KEY_CHECKS=1');
                    }

                } // close if ($targetPdo !== null)

            } catch (\Throwable $e) {
                $warnings[] = 'Errore eliminazione tabelle: ' . $e->getMessage();
            }
        }

        // ── 1b. Drop dedicated DB (only if explicitly requested) ─────
        if ($dropDatabase && $dbMode === 'independent') {
            try {
                $resolver = app(ModuleDatabaseResolver::class);
                $mapping  = $resolver->getMapping($moduleName);
                if ($mapping && !empty($mapping['database_name'])) {
                    $resolver->dropDatabase($moduleName);
                    $log[] = 'Database dedicato eliminato: ' . $mapping['database_name'];
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Impossibile droppare il database dedicato: ' . $e->getMessage();
            }
        }

        // Always mark mapping as removed (audit trail). Resolver handles missing rows gracefully.
        if ($dbMode === 'independent') {
            try {
                app(ModuleDatabaseResolver::class)->markRemoved($moduleName);
                $log[] = "Mapping module_databases marcato come 'removed'.";
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        // ── 2. Remove permissions ─────────────────────────────────────
        try {
            // Remove from role_permission first (FK)
            $permIds = $pdo->prepare(
                'SELECT id FROM permissions WHERE module = ?'
            );
            $permIds->execute([$moduleName]);
            $ids = $permIds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM role_permission WHERE permission_id IN ({$placeholders})")
                    ->execute($ids);
            }

            // Remove permissions
            $stmt = $pdo->prepare('DELETE FROM permissions WHERE module = ?');
            $stmt->execute([$moduleName]);
            $permCount = $stmt->rowCount();

            if ($permCount > 0) {
                $log[] = "Permessi rimossi: {$permCount}";
            }

        } catch (\Throwable $e) {
            $warnings[] = 'Errore rimozione permessi: ' . $e->getMessage();
        }

        // ── 3. Remove notification event types (CASCADE → channel bindings) ──
        try {
            $moduleSlug = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $moduleName));
            $stmt = $pdo->prepare('DELETE FROM notification_event_types WHERE module_slug = ?');
            $stmt->execute([$moduleSlug]);
            $evtCount = $stmt->rowCount();
            if ($evtCount > 0) {
                $log[] = "Tipi evento notifica rimossi: {$evtCount} (slug: {$moduleSlug})";
            }
            // Clean per-user preferences for this module
            $pdo->prepare('DELETE FROM user_notification_preferences WHERE module_slug = ?')
                ->execute([$moduleSlug]);
        } catch (\Throwable $e) {
            // Non-fatal: notifications tables may not exist
        }

        // ── 4. Remove from module_states ──────────────────────────────
        try {
            $pdo->prepare('DELETE FROM module_states WHERE name = ?')->execute([$moduleName]);
            $log[] = 'Stato modulo rimosso da module_states';
        } catch (\Throwable $e) {
            // Non-fatal
        }

        // ── 5. Remove migration records ───────────────────────────────
        try {
            $stmt = $pdo->prepare('DELETE FROM migrations WHERE module = ?');
            $stmt->execute([$moduleName]);
            $migCount = $stmt->rowCount();
            if ($migCount > 0) {
                $log[] = "Record migration rimossi: {$migCount}";
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Errore rimozione migration records: ' . $e->getMessage();
        }

        // ── 6. Delete CSS/JS assets ──────────────────────────────────
        $assetFiles = $meta['assets'] ?? [];
        $assetsRemoved = 0;

        foreach ($assetFiles['css'] ?? [] as $file) {
            $safeName = basename($file);
            if ($safeName === '' || $safeName !== $file) {
                continue;
            }
            $path = BASE_PATH . '/public/assets/css/' . $safeName;
            if (file_exists($path)) {
                unlink($path);
                $assetsRemoved++;
            }
        }
        foreach ($assetFiles['js'] ?? [] as $file) {
            $safeName = basename($file);
            if ($safeName === '' || $safeName !== $file) {
                continue;
            }
            $path = BASE_PATH . '/public/assets/js/' . $safeName;
            if (file_exists($path)) {
                unlink($path);
                $assetsRemoved++;
            }
        }

        if ($assetsRemoved > 0) {
            $log[] = "Asset rimossi: {$assetsRemoved} file CSS/JS";
        }

        // ── 7. Delete uploads ─────────────────────────────────────────
        $uploadsDir = $meta['uploads_directory'] ?? null;
        if ($deleteUploads && $uploadsDir) {
            // Security: sanitize uploads_directory
            $safeUploadsDir = basename($uploadsDir);
            if ($safeUploadsDir !== '' && $safeUploadsDir === $uploadsDir) {
                $uploadsPath = BASE_PATH . '/public/uploads/' . $safeUploadsDir;
                if (is_dir($uploadsPath)) {
                    self::deleteDirectory($uploadsPath);
                    $log[] = "Directory uploads rimossa: public/uploads/{$safeUploadsDir}/";
                }
            }
        }

        // ── 8. Delete module directory ────────────────────────────────
        try {
            self::deleteDirectory($moduleDir);
            $log[] = "Directory modulo rimossa: app/Modules/{$moduleName}/";
        } catch (\Throwable $e) {
            return UninstallResult::fail(
                'Impossibile rimuovere la directory del modulo: ' . $e->getMessage(),
                $log,
                $warnings
            );
        }

        // ── 9. Check if module is in modules.php ─────────────────────
        $modules = include BASE_PATH . '/app/Config/modules.php';
        foreach ($modules as $mod) {
            if (($mod['name'] ?? '') === $moduleName) {
                $warnings[] = "Il modulo e' registrato in app/Config/modules.php. Rimuovi manualmente il suo blocco dal file.";
                break;
            }
        }

        $log[] = 'Disinstallazione completata.';

        return UninstallResult::ok($log, $warnings);
    }

    /**
     * Get a preview of what will be removed (for confirmation page).
     */
    public static function preview(string $moduleName): array
    {
        $loader = app(ModuleLoader::class);
        $meta = $loader->readModuleJson($moduleName);

        $moduleDir = BASE_PATH . '/app/Modules/' . $moduleName;
        $pdo = app(PDO::class);

        // Count permissions
        $permStmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE module = ?');
        $permStmt->execute([$moduleName]);
        $permCount = (int) $permStmt->fetchColumn();

        // Count migration records
        $migStmt = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE module = ?');
        $migStmt->execute([$moduleName]);
        $migCount = (int) $migStmt->fetchColumn();

        // Count files in module directory
        $fileCount = 0;
        if (is_dir($moduleDir)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $fileCount++;
                }
            }
        }

        // Uploads directory size
        $uploadsDir = $meta['uploads_directory'] ?? null;
        $uploadsSize = 0;
        $uploadsCount = 0;
        if ($uploadsDir && basename($uploadsDir) === $uploadsDir) {
            $uploadsPath = BASE_PATH . '/public/uploads/' . $uploadsDir;
            if (is_dir($uploadsPath)) {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsPath, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iter as $file) {
                    if ($file->isFile()) {
                        $uploadsCount++;
                        $uploadsSize += $file->getSize();
                    }
                }
            }
        }

        // Check if in modules.php
        $inConfig = false;
        $modules = include BASE_PATH . '/app/Config/modules.php';
        foreach ($modules as $mod) {
            if (($mod['name'] ?? '') === $moduleName) {
                $inConfig = true;
                break;
            }
        }

        return [
            'name'             => $moduleName,
            'version'          => $meta['version'] ?? null,
            'description'      => $meta['description'] ?? null,
            'tables'           => $meta['tables'] ?? [],
            'database_mode'    => $meta['database'] ?? 'shared',
            'assets'           => $meta['assets'] ?? [],
            'uploads_directory' => $uploadsDir,
            'uploads_count'    => $uploadsCount,
            'uploads_size'     => $uploadsSize,
            'permissions_count' => $permCount,
            'migrations_count' => $migCount,
            'file_count'       => $fileCount,
            'in_config'        => $inConfig,
            'has_module_json'  => $meta !== null,
            'dependent_modules' => self::getDependentModules($moduleName),
        ];
    }

    /**
     * Recursively delete a directory.
     */
    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
