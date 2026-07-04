<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Services\ModuleDatabaseResolver;
use PDO;

/**
 * Imports a Favilla module from a ZIP archive.
 *
 * Handles: validation, extraction, asset copy, migration execution,
 * permission import, data import, and upload files.
 * Supports rollback on failure.
 */
class ModuleImportService
{
    /** Actions performed (for rollback) */
    private static array $actions = [];

    /**
     * Import a module from a ZIP file.
     *
     * @param string  $zipPath        Absolute path to the uploaded ZIP
     * @param bool    $importData     Also import table data if present in ZIP
     * @param ?string $dbNameOverride Override the database name for `independent` modules
     * @param bool    $reuseExisting  If true, accept a pre-existing populated DB (no error)
     */
    public static function import(
        string $zipPath,
        bool $importData = false,
        ?string $dbNameOverride = null,
        bool $reuseExisting = false
    ): ImportResult {
        self::$actions = [];
        $log      = [];
        $warnings = [];

        // ── Phase 1: Validate ZIP ─────────────────────────────────────
        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return ImportResult::fail('Impossibile aprire il file ZIP.');
            }

            // Read manifest
            $manifestJson = $zip->getFromName('manifest.json');
            if ($manifestJson === false) {
                $zip->close();
                return ImportResult::fail('manifest.json non trovato nel ZIP. Non e\' un pacchetto Favilla valido.');
            }

            $manifest = json_decode($manifestJson, true);
            if (!is_array($manifest) || empty($manifest['module_name'])) {
                $zip->close();
                return ImportResult::fail('manifest.json non valido o mancante di module_name.');
            }

            $moduleName = $manifest['module_name'];

            // Validate module name (security)
            if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $moduleName)) {
                $zip->close();
                return ImportResult::fail("Nome modulo non valido: '{$moduleName}'. Deve essere CamelCase alfanumerico.");
            }

            // Check module/{Name}/ exists in ZIP
            $modulePrefix = 'module/' . $moduleName . '/';
            $hasModuleDir = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_starts_with($zip->getNameIndex($i), $modulePrefix)) {
                    $hasModuleDir = true;
                    break;
                }
            }
            if (!$hasModuleDir) {
                $zip->close();
                return ImportResult::fail("Directory '{$modulePrefix}' non trovata nel ZIP.");
            }

            // Check target directory doesn't exist
            $targetDir = BASE_PATH . '/app/Modules/' . $moduleName;
            if (is_dir($targetDir)) {
                $zip->close();
                return ImportResult::fail("Il modulo '{$moduleName}' esiste gia' in app/Modules/. Rimuovilo prima di importare.");
            }

            // Check dependencies
            $deps = $manifest['dependencies'] ?? [];
            foreach ($deps as $dep) {
                if (!is_dir(BASE_PATH . '/app/Modules/' . $dep)) {
                    $warnings[] = "Dipendenza '{$dep}' non trovata. Il modulo potrebbe non funzionare correttamente.";
                }
            }

            // Read DB declaration from manifest (support both new and legacy keys).
            $dbMode    = $manifest['database_mode']           ?? ($manifest['database']                ?? 'shared');
            $dbPrefix  = $manifest['database_env_prefix']     ?? null;
            $dbSuggest = $manifest['database_suggested_name'] ?? null;

            $log[] = "Validazione completata: modulo '{$moduleName}'";

        } catch (\Throwable $e) {
            if (isset($zip)) {
                $zip->close();
            }
            return ImportResult::fail('Errore durante la validazione: ' . $e->getMessage());
        }

        // ── Phase 1b: Provision module DB (independent only) ──────────
        // Resolve the effective DB name: override > manifest suggested > resolver suggestion.
        $effectiveDbName = null;
        if ($dbMode === 'independent') {
            try {
                $resolver = app(ModuleDatabaseResolver::class);
            } catch (\Throwable $e) {
                $zip->close();
                return ImportResult::fail(
                    'ModuleDatabaseResolver non disponibile: ' . $e->getMessage(),
                    $moduleName,
                    $log,
                    $warnings
                );
            }

            $effectiveDbName = $dbNameOverride
                ?: ($dbSuggest ?: $resolver->suggestName($moduleName));

            try {
                $resolver->validateName($effectiveDbName);
            } catch (\Throwable $e) {
                $zip->close();
                return ImportResult::fail(
                    'Nome database non valido: ' . $e->getMessage(),
                    $moduleName,
                    $log,
                    $warnings
                );
            }

            // Detect pre-existence and population BEFORE creating.
            $mainPdo = app(PDO::class);
            $exists  = self::databaseExists($mainPdo, $effectiveDbName);
            $hasData = $exists ? self::databaseHasTables($mainPdo, $effectiveDbName) : false;

            if ($exists && $hasData && !$reuseExisting) {
                $zip->close();
                return ImportResult::fail(
                    "Il database '{$effectiveDbName}' esiste e contiene tabelle. "
                    . 'Conferma esplicita richiesta per riusarlo (reuseExisting=true).',
                    $moduleName,
                    $log,
                    $warnings
                );
            }

            try {
                $resolver->provision($moduleName, $effectiveDbName);
            } catch (\Throwable $e) {
                $zip->close();
                return ImportResult::fail(
                    'Provisioning database fallito: ' . $e->getMessage()
                    . ". In hosting senza permesso CREATE DATABASE creare il DB a mano e marcare il mapping come 'manual'.",
                    $moduleName,
                    $log,
                    $warnings
                );
            }

            // Track for rollback: drop ONLY if we created it.
            self::$actions[] = [
                'type'        => 'database_created',
                'name'        => $effectiveDbName,
                'preexisted'  => $exists,
                'module_name' => $moduleName,
            ];

            $log[] = $exists
                ? "Database '{$effectiveDbName}' preesistente: mapping registrato."
                : "Database '{$effectiveDbName}' creato e mapping registrato.";
        }

        // ── Phase 2: Extract module source ────────────────────────────
        try {
            if (!mkdir($targetDir, 0755, true)) {
                $zip->close();
                return ImportResult::fail("Impossibile creare la directory {$targetDir}");
            }
            self::$actions[] = ['type' => 'dir', 'path' => $targetDir];

            // Extract module files
            $extracted = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (!str_starts_with($entryName, $modulePrefix)) {
                    continue;
                }

                $relativePath = substr($entryName, strlen($modulePrefix));
                if ($relativePath === '' || $relativePath === false) {
                    continue;
                }

                $destPath = $targetDir . '/' . $relativePath;

                // Security: prevent path traversal
                if (str_contains($relativePath, '..')) {
                    continue;
                }
                $normalizedDest = str_replace('\\', '/', $destPath);
                $normalizedTarget = str_replace('\\', '/', $targetDir);
                if (!str_starts_with($normalizedDest, $normalizedTarget . '/')) {
                    continue;
                }

                if (str_ends_with($entryName, '/')) {
                    // Directory
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0755, true);
                    }
                } else {
                    // File
                    $dir = dirname($destPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($destPath, $content);
                        $extracted++;
                    }
                }
            }

            $log[] = "Modulo estratto in app/Modules/{$moduleName}/ ({$extracted} file)";

        } catch (\Throwable $e) {
            $zip->close();
            self::rollback($log);
            return ImportResult::fail('Errore estrazione modulo: ' . $e->getMessage(), $moduleName, $log, $warnings);
        }

        // ── Phase 3: Copy assets ──────────────────────────────────────
        try {
            $assetsCopied = 0;

            // CSS
            $cssFiles = $manifest['assets']['css'] ?? [];
            foreach ($cssFiles as $cssFile) {
                $safeName = basename($cssFile);
                if ($safeName === '' || $safeName !== $cssFile) {
                    continue; // Skip path traversal attempts
                }
                $content = $zip->getFromName('assets/css/' . $safeName);
                if ($content !== false) {
                    $destPath = BASE_PATH . '/public/assets/css/' . $safeName;
                    file_put_contents($destPath, $content);
                    self::$actions[] = ['type' => 'file', 'path' => $destPath];
                    $assetsCopied++;
                }
            }

            // JS
            $jsFiles = $manifest['assets']['js'] ?? [];
            foreach ($jsFiles as $jsFile) {
                $safeName = basename($jsFile);
                if ($safeName === '' || $safeName !== $jsFile) {
                    continue; // Skip path traversal attempts
                }
                $content = $zip->getFromName('assets/js/' . $safeName);
                if ($content !== false) {
                    $destPath = BASE_PATH . '/public/assets/js/' . $safeName;
                    file_put_contents($destPath, $content);
                    self::$actions[] = ['type' => 'file', 'path' => $destPath];
                    $assetsCopied++;
                }
            }

            if ($assetsCopied > 0) {
                $log[] = "Asset copiati: {$assetsCopied} file (CSS/JS)";
            }

        } catch (\Throwable $e) {
            $zip->close();
            self::rollback($log);
            return ImportResult::fail('Errore copia asset: ' . $e->getMessage(), $moduleName, $log, $warnings);
        }

        // ── Phase 4: Run migrations ───────────────────────────────────
        try {
            $migDir = $targetDir . '/migrations';
            if (is_dir($migDir)) {
                $migFiles = glob($migDir . '/*.sql') ?: [];
                sort($migFiles);

                if (!empty($migFiles)) {
                    // Determine target PDO. NO silent fallback for independent modules.
                    $pdo = app(PDO::class);

                    if ($dbMode === 'independent') {
                        try {
                            $migPdo = app(ModuleDatabaseResolver::class)->pdoFor($moduleName);
                        } catch (\Throwable $e) {
                            $zip->close();
                            self::rollback($log);
                            return ImportResult::fail(
                                'Impossibile risolvere DB modulo per migration: ' . $e->getMessage(),
                                $moduleName,
                                $log,
                                $warnings
                            );
                        }
                    } else {
                        $migPdo = $pdo;
                    }

                    // Ensure migrations table exists in main DB (tracking is centralized)
                    self::ensureMigrationsTable($pdo);

                    $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();

                    $migRun = 0;
                    $migSkip = 0;
                    foreach ($migFiles as $migFile) {
                        $filename = basename($migFile);

                        // Check if already executed
                        $check = $pdo->prepare('SELECT id FROM migrations WHERE filename = ? AND module = ?');
                        $check->execute([$filename, $moduleName]);

                        if ($check->fetch()) {
                            $migSkip++;
                            continue;
                        }

                        $sql = file_get_contents($migFile);
                        if ($sql === false) {
                            continue;
                        }

                        // Remove comments, split, execute
                        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                        $statements = array_filter(
                            array_map('trim', explode(';', $sql)),
                            fn ($s) => $s !== ''
                        );

                        foreach ($statements as $stmt) {
                            try {
                                $migPdo->exec($stmt);
                            } catch (\PDOException $e) {
                                if (!str_contains($e->getMessage(), 'already exists')
                                    && !str_contains($e->getMessage(), 'Duplicate')) {
                                    throw $e;
                                }
                            }
                        }

                        // Record migration (always in main DB)
                        $pdo->prepare('INSERT INTO migrations (filename, module, batch) VALUES (?, ?, ?)')
                            ->execute([$filename, $moduleName, $batch]);
                        self::$actions[] = ['type' => 'migration', 'filename' => $filename, 'module' => $moduleName];
                        $migRun++;
                    }

                    $logMsg = "Migration eseguite: {$migRun}";
                    if ($migSkip > 0) {
                        $logMsg .= " (saltate: {$migSkip})";
                    }
                    if ($dbMode === 'independent') {
                        $logMsg .= ' [DB indipendente: ' . ($effectiveDbName ?? $dbPrefix ?? '?') . ']';
                    }
                    $log[] = $logMsg;
                }
            }

        } catch (\Throwable $e) {
            $zip->close();
            self::rollback($log);
            return ImportResult::fail('Errore esecuzione migration: ' . $e->getMessage(), $moduleName, $log, $warnings);
        }

        // ── Phase 5: Import permissions ───────────────────────────────
        try {
            $permFile = $targetDir . '/permissions.php';
            if (file_exists($permFile)) {

                // Security: validate source before include() to prevent RCE via crafted ZIP
                $source = file_get_contents($permFile);
                if ($source === false) {
                    $warnings[] = 'Impossibile leggere permissions.php.';
                } elseif (!self::isPermissionsFileSafe($source)) {
                    $warnings[] = 'permissions.php non ha superato la validazione di sicurezza. '
                                . 'Importa i permessi manualmente da Admin > Moduli.';
                } else {
                    $perms = include $permFile;
                    if (is_array($perms) && !empty($perms)) {
                        $pdo = app(PDO::class);
                        $insertPerm = $pdo->prepare('INSERT IGNORE INTO permissions (name, slug, module) VALUES (?, ?, ?)');
                        $imported = 0;

                        foreach ($perms as $perm) {
                            $insertPerm->execute([$perm['name'], $perm['slug'], $moduleName]);
                            $imported += $insertPerm->rowCount();
                        }

                        // Assign to admin role
                        $adminRole = $pdo->query("SELECT id FROM roles WHERE slug = 'admin'")->fetch();
                        if ($adminRole) {
                            $insertRolePerm = $pdo->prepare(
                                'INSERT IGNORE INTO role_permission (role_id, permission_id)
                                 SELECT ?, id FROM permissions WHERE module = ?'
                            );
                            $insertRolePerm->execute([$adminRole['id'], $moduleName]);
                        }

                        $log[] = "Permessi importati: {$imported} su " . count($perms) . ' dichiarati';
                    }
                }
            }

        } catch (\Throwable $e) {
            // Non-fatal: permissions can be imported manually
            $warnings[] = 'Errore import permessi: ' . $e->getMessage() . '. Importali manualmente da Admin > Moduli.';
        }

        // ── Phase 6: Import data (optional) ───────────────────────────
        if ($importData) {
            try {
                $dataImported = 0;

                // Resolve PDO once before loop. NO silent fallback for independent modules.
                if ($dbMode === 'independent') {
                    try {
                        $dataPdo = app(ModuleDatabaseResolver::class)->pdoFor($moduleName);
                    } catch (\Throwable $e) {
                        $zip->close();
                        self::rollback($log);
                        return ImportResult::fail(
                            'Impossibile risolvere DB modulo per import dati: ' . $e->getMessage(),
                            $moduleName,
                            $log,
                            $warnings
                        );
                    }
                } else {
                    $dataPdo = app(PDO::class);
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (!str_starts_with($entryName, 'data/') || !str_ends_with($entryName, '.sql')) {
                        continue;
                    }

                    // Security: reject path traversal in data entries
                    if (str_contains($entryName, '..')) {
                        continue;
                    }

                    $sql = $zip->getFromIndex($i);
                    if ($sql === false || trim($sql) === '') {
                        continue;
                    }

                    // Execute data SQL
                    $statements = array_filter(
                        array_map('trim', explode(';', $sql)),
                        fn ($s) => $s !== ''
                    );

                    foreach ($statements as $stmt) {
                        try {
                            $dataPdo->exec($stmt);
                        } catch (\PDOException $e) {
                            if (!str_contains($e->getMessage(), 'Duplicate')) {
                                $warnings[] = 'Warning dati ' . basename($entryName) . ': ' . $e->getMessage();
                            }
                        }
                    }

                    $dataImported++;
                }

                if ($dataImported > 0) {
                    $log[] = "Dati importati: {$dataImported} tabelle";
                }

            } catch (\Throwable $e) {
                $warnings[] = 'Errore import dati: ' . $e->getMessage();
            }
        }

        // ── Phase 7: Import uploads (if present) ──────────────────────
        try {
            $uploadsImported = 0;

            $uploadsBase = str_replace('\\', '/', BASE_PATH . '/public/uploads');

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (!str_starts_with($entryName, 'uploads/') || str_ends_with($entryName, '/')) {
                    continue;
                }

                // Security: reject path traversal
                if (str_contains($entryName, '..')) {
                    continue;
                }

                $destPath = BASE_PATH . '/public/' . $entryName;
                $normalizedDest = str_replace('\\', '/', $destPath);
                if (!str_starts_with($normalizedDest, $uploadsBase . '/')) {
                    continue;
                }

                $destDir = dirname($destPath);

                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($destPath, $content);
                    $uploadsImported++;
                }
            }

            if ($uploadsImported > 0) {
                $log[] = "File caricati importati: {$uploadsImported}";
            }

        } catch (\Throwable $e) {
            $warnings[] = 'Errore import file caricati: ' . $e->getMessage();
        }

        $zip->close();

        // ── Phase 8: Import bundled report templates (if Reports module active)
        try {
            $reportTemplatesDir = $targetDir . '/report_templates';
            if (is_dir($reportTemplatesDir) && isModuleEnabled('Reports')) {
                $bundledService = app(\App\Modules\Reports\Services\BundledTemplateService::class);
                $tplResult = $bundledService->importFromModule($moduleName);

                $tplCount = $tplResult['imported'] ?? 0;
                if ($tplCount > 0) {
                    $log[] = "Report template importati: {$tplCount}";
                }
                if (!empty($tplResult['errors'])) {
                    foreach ($tplResult['errors'] as $err) {
                        $warnings[] = "Report template: {$err}";
                    }
                }
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Errore import report template: ' . $e->getMessage();
        }

        $log[] = 'Import completato con successo!';

        return ImportResult::ok($moduleName, $log, $warnings);
    }

    /**
     * Validate that permissions.php contains only a static array (no executable code).
     * Uses a token whitelist: any token not in the whitelist causes rejection.
     */
    private static function isPermissionsFileSafe(string $source): bool
    {
        // Structural check: must start with <?php and contain a return statement
        $normalized = trim(preg_replace('/\s+/', ' ', preg_replace('/\/\/[^\n]*\n/', '', $source)));
        if (!preg_match('/^<\?php\s+return\s+\[/i', $normalized)) {
            return false;
        }

        $tokens = @token_get_all($source);
        if ($tokens === false) {
            return false;
        }

        // Whitelist of safe token types for a "return [...];" file
        $allowedTokens = [
            T_OPEN_TAG, T_CLOSE_TAG, T_RETURN,
            T_ARRAY, T_DOUBLE_ARROW,
            T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER,
            T_WHITESPACE, T_COMMENT, T_DOC_COMMENT,
            T_ENCAPSED_AND_WHITESPACE,
        ];

        // Allowed single-character tokens
        $allowedChars = ['[', ']', '(', ')', ',', ';', '\'', '"'];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (!in_array($token[0], $allowedTokens, true)) {
                    return false;
                }
            } else {
                // Single character token
                if (!in_array($token, $allowedChars, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Ensure the migrations table exists (same logic as migrate.php).
     */
    private static function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS migrations (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename    VARCHAR(255) NOT NULL,
                module      VARCHAR(100) NULL DEFAULT NULL,
                batch       INT UNSIGNED NOT NULL DEFAULT 1,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_migrations (filename, module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    /**
     * Rollback performed actions in reverse order.
     */
    private static function rollback(array &$log): void
    {
        $log[] = '[ROLLBACK] Ripristino in corso...';

        foreach (array_reverse(self::$actions) as $action) {
            try {
                switch ($action['type']) {
                    case 'dir':
                        self::deleteDirectory($action['path']);
                        $log[] = '[ROLLBACK] Rimossa directory: ' . $action['path'];
                        break;

                    case 'file':
                        if (file_exists($action['path'])) {
                            unlink($action['path']);
                            $log[] = '[ROLLBACK] Rimosso file: ' . basename($action['path']);
                        }
                        break;

                    case 'migration':
                        $pdo = app(PDO::class);
                        $pdo->prepare('DELETE FROM migrations WHERE filename = ? AND module = ?')
                            ->execute([$action['filename'], $action['module']]);
                        $log[] = '[ROLLBACK] Rimossa migration record: ' . $action['filename'];
                        break;

                    case 'database_created':
                        // Drop the dedicated DB ONLY if we created it during this import.
                        if (!empty($action['preexisted'])) {
                            $log[] = "[ROLLBACK] DB '{$action['name']}' preesistente: non droppato.";
                            // Best effort: mark mapping as removed so it doesn't dangle.
                            try {
                                app(ModuleDatabaseResolver::class)->markRemoved($action['module_name']);
                            } catch (\Throwable $e) { /* best effort */
                            }
                            break;
                        }
                        try {
                            app(ModuleDatabaseResolver::class)->dropDatabase($action['module_name']);
                            $log[] = '[ROLLBACK] DB dedicato eliminato: ' . $action['name'];
                        } catch (\Throwable $e) {
                            $log[] = "[ROLLBACK ERRORE] Drop DB '{$action['name']}': " . $e->getMessage();
                        }
                        // Always remove the mapping row entirely on rollback (it should never have existed).
                        try {
                            $pdo = app(PDO::class);
                            $pdo->prepare('DELETE FROM module_databases WHERE module_name = ?')
                                ->execute([$action['module_name']]);
                        } catch (\Throwable $e) { /* best effort */
                        }
                        break;
                }
            } catch (\Throwable $e) {
                $log[] = '[ROLLBACK ERRORE] ' . $e->getMessage();
            }
        }

        self::$actions = [];
    }

    /**
     * Check if a database exists on the same server as the main PDO.
     */
    private static function databaseExists(PDO $pdo, string $dbName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1'
        );
        $stmt->execute([$dbName]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Check if a database contains any tables.
     */
    private static function databaseHasTables(PDO $pdo, string $dbName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? LIMIT 1'
        );
        $stmt->execute([$dbName]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Recursively delete a directory and its contents.
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
