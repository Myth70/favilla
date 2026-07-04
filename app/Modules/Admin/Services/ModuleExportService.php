<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\ModuleLoader;

/**
 * Builds a portable ZIP archive for a Favilla module.
 *
 * The ZIP includes: module source (with module.json), migrations, assets,
 * modules.php config block, manifest.json, INSTALL.md, and optionally
 * table data (SQL) and uploaded files.
 */
class ModuleExportService
{
    /** Services whose presence is worth noting in INSTALL.md */
    private const KNOWN_SERVICES = [
        'FileUploadService',
        'AuditService',
        'NotificationService',
        'CsvExportService',
        'MailerService',
    ];

    /**
     * Build the ZIP and return its absolute path.
     * Caller is responsible for streaming + unlinking the file.
     *
     * @param string $moduleName   Module directory name
     * @param bool   $includeData  Include table data as SQL INSERT files
     * @param bool   $includeUploads Include uploaded files (images, PDFs, etc.)
     * @throws \RuntimeException
     */
    public static function build(
        string $moduleName,
        bool $includeData = false,
        bool $includeUploads = false
    ): string {
        if (!extension_loaded('zip')) {
            throw new \RuntimeException("PHP extension 'zip' non disponibile sul server.");
        }

        // Validate module name (security: prevent path traversal)
        if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $moduleName)) {
            throw new \RuntimeException("Nome modulo non valido: '{$moduleName}'.");
        }

        $moduleDir = BASE_PATH . '/app/Modules/' . $moduleName;
        if (!is_dir($moduleDir)) {
            throw new \RuntimeException("Modulo '{$moduleName}' non trovato in app/Modules/.");
        }

        $tmpDir = BASE_PATH . '/storage/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir . '/' . $moduleName . '_export_' . date('Ymd_His') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossibile creare il file ZIP in '{$zipPath}'.");
        }

        try {
            // Load module metadata
            $loader = app(ModuleLoader::class);
            $meta = $loader->readModuleJson($moduleName);

            // Auto-generate module.json if missing (retrocompatibility)
            if ($meta === null) {
                $meta = self::generateModuleJson($moduleName);
                // Write the generated file to the module directory
                file_put_contents(
                    $moduleDir . '/module.json',
                    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }

            // 1. Module source files (includes module.json, migrations/, permissions.php, etc.)
            self::addDirectoryToZip($zip, $moduleDir, 'module/' . $moduleName);

            // 2. CSS / JS assets
            $assets = self::resolveAssets($moduleName, $meta);
            foreach ($assets['css'] as $path) {
                $zip->addFile($path, 'assets/css/' . basename($path));
            }
            foreach ($assets['js'] as $path) {
                $zip->addFile($path, 'assets/js/' . basename($path));
            }

            // 3. modules.php block as valid PHP file
            $modulesBlock = self::extractModulesBlock($moduleName);
            $zip->addFromString('config/modules_block.php', $modulesBlock);

            // 4. Table data (optional)
            $dataTables = [];
            if ($includeData) {
                $dataTables = ModuleDataExporter::exportAllTables($moduleName);
                foreach ($dataTables as $table => $sql) {
                    $zip->addFromString('data/' . $table . '.sql', $sql);
                }
            }

            // 5. Uploaded files (optional)
            $uploadsDir = $meta['uploads_directory'] ?? null;
            $uploadsIncluded = false;
            if ($includeUploads && $uploadsDir) {
                // Security: sanitize uploads_directory from module.json
                $safeUploadsDir = basename($uploadsDir);
                if ($safeUploadsDir !== '' && $safeUploadsDir === $uploadsDir) {
                    $uploadsPath = BASE_PATH . '/public/uploads/' . $safeUploadsDir;
                    if (is_dir($uploadsPath)) {
                        self::addDirectoryToZip($zip, $uploadsPath, 'uploads/' . $safeUploadsDir);
                        $uploadsIncluded = true;
                    }
                }
            }

            // 6. Permissions and service detection
            $permissions = self::getPermissions($moduleName);
            $services    = $meta['services'] ?? self::detectServices($moduleDir);

            // 6b. Count bundled report templates
            $reportTemplateFiles = glob($moduleDir . '/report_templates/*.json') ?: [];

            // 7. manifest.json
            $manifest = self::buildManifest(
                $moduleName,
                $meta,
                $assets,
                $permissions,
                $services,
                $dataTables,
                $uploadsIncluded,
                $includeData,
                $reportTemplateFiles
            );
            $zip->addFromString('manifest.json', $manifest);

            // 8. INSTALL.md (fallback for manual installation)
            $migrations = self::detectMigrations($moduleName);
            $installMd = self::generateInstallMd(
                $moduleName,
                $migrations,
                $assets,
                $permissions,
                $services,
                $modulesBlock,
                $meta,
                $reportTemplateFiles
            );
            $zip->addFromString('INSTALL.md', $installMd);

            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            throw $e;
        }

        return $zipPath;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private static function addDirectoryToZip(\ZipArchive $zip, string $dir, string $zipPrefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $realPath = $file->getRealPath();
            $relative = str_replace('\\', '/', substr($realPath, strlen($dir) + 1));

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPrefix . '/' . $relative);
            } else {
                $zip->addFile($realPath, $zipPrefix . '/' . $relative);
            }
        }
    }

    /**
     * Resolve asset files. Uses module.json if available, falls back to auto-detection.
     */
    private static function resolveAssets(string $moduleName, ?array $meta): array
    {
        $cssDir = BASE_PATH . '/public/assets/css';
        $jsDir  = BASE_PATH . '/public/assets/js';

        // If module.json declares assets explicitly, use those
        if ($meta && !empty($meta['assets'])) {
            $css = [];
            foreach ($meta['assets']['css'] ?? [] as $file) {
                $path = $cssDir . '/' . $file;
                if (file_exists($path)) {
                    $css[] = $path;
                }
            }
            $js = [];
            foreach ($meta['assets']['js'] ?? [] as $file) {
                $path = $jsDir . '/' . $file;
                if (file_exists($path)) {
                    $js[] = $path;
                }
            }
            return ['css' => $css, 'js' => $js];
        }

        // Fallback: auto-detect by module name variants
        return self::detectAssets($moduleName);
    }

    /**
     * Find CSS / JS assets whose filename matches the module name
     * in any common case variant (lower, kebab, snake).
     * Uses exact match or prefix match (with separator) to avoid
     * false positives (e.g. "Test" matching "global-test.css").
     */
    private static function detectAssets(string $moduleName): array
    {
        $cssDir  = BASE_PATH . '/public/assets/css';
        $jsDir   = BASE_PATH . '/public/assets/js';
        $variants = array_unique([
            strtolower($moduleName),
            self::toKebab($moduleName),
            self::toSnake($moduleName),
        ]);

        $css = [];
        foreach (glob($cssDir . '/*.css') ?: [] as $file) {
            $base = strtolower(basename($file, '.css'));
            foreach ($variants as $v) {
                if ($v !== '' && ($base === $v || str_starts_with($base, $v . '-') || str_starts_with($base, $v . '_'))) {
                    $css[] = $file;
                    break;
                }
            }
        }

        $js = [];
        foreach (glob($jsDir . '/*.js') ?: [] as $file) {
            $base = strtolower(basename($file, '.js'));
            foreach ($variants as $v) {
                if ($v !== '' && ($base === $v || str_starts_with($base, $v . '-') || str_starts_with($base, $v . '_'))) {
                    $js[] = $file;
                    break;
                }
            }
        }

        return ['css' => $css, 'js' => $js];
    }

    /**
     * Find migration files for a module.
     * Looks in app/Modules/{name}/migrations/ first, then fallback to database/migrations/.
     */
    private static function detectMigrations(string $moduleName): array
    {
        $moduleDir = BASE_PATH . '/app/Modules/' . $moduleName . '/migrations';
        if (is_dir($moduleDir)) {
            $found = glob($moduleDir . '/*.sql') ?: [];
            sort($found);
            return $found;
        }

        // Fallback: scan central directory (legacy / core modules)
        $migrDir  = BASE_PATH . '/database/migrations';
        $variants = [
            strtolower($moduleName),
            self::toKebab($moduleName),
            self::toSnake($moduleName),
        ];

        $found = [];
        foreach (glob($migrDir . '/*.sql') ?: [] as $file) {
            $slug = strtolower(preg_replace('/^\d+_/', '', basename($file, '.sql')));
            foreach ($variants as $v) {
                if ($v !== '' && str_contains($slug, $v)) {
                    $found[] = $file;
                    break;
                }
            }
        }

        sort($found);
        return $found;
    }

    /**
     * Extract the module's block from modules.php as a valid PHP file.
     */
    private static function extractModulesBlock(string $moduleName): string
    {
        $modules = include BASE_PATH . '/app/Config/modules.php';

        foreach ($modules as $mod) {
            if ($mod['name'] === $moduleName) {
                $exported = var_export($mod, true);
                return "<?php\n\n"
                     . "// Blocco di registrazione per il modulo {$moduleName}\n"
                     . "// Se il modulo ha un module.json, questo file non e' necessario\n"
                     . "// (il ModuleLoader scopre automaticamente i moduli con module.json).\n"
                     . "// Altrimenti inserisci questo blocco in app/Config/modules.php PRIMA del blocco Admin.\n\n"
                     . "return {$exported};\n";
            }
        }

        // Module not in modules.php — try to build from module.json
        $loader = app(ModuleLoader::class);
        $meta = $loader->readModuleJson($moduleName);
        if ($meta) {
            $block = [
                'name'    => $moduleName,
                'enabled' => true,
                'menu'    => $meta['menu'] ?? [],
            ];
            $exported = var_export($block, true);
            return "<?php\n\nreturn {$exported};\n";
        }

        return "<?php\n\n// Blocco non trovato per il modulo {$moduleName}.\n"
             . "// Il modulo sara' scoperto automaticamente se ha un module.json.\n"
             . "return [];\n";
    }

    /** Return permission slugs declared in the module's permissions.php */
    private static function getPermissions(string $moduleName): array
    {
        $file = BASE_PATH . '/app/Modules/' . $moduleName . '/permissions.php';
        if (!file_exists($file)) {
            return [];
        }
        $perms = include $file;
        return is_array($perms) ? array_column($perms, 'slug') : [];
    }

    /**
     * Grep PHP files in Controllers/, Services/, Repositories/ for references
     * to known framework services.
     */
    private static function detectServices(string $moduleDir): array
    {
        $phpFiles = array_merge(
            glob($moduleDir . '/Controllers/*.php') ?: [],
            glob($moduleDir . '/Services/*.php') ?: [],
            glob($moduleDir . '/Repositories/*.php') ?: []
        );

        $found = [];
        foreach (self::KNOWN_SERVICES as $svc) {
            foreach ($phpFiles as $file) {
                if (str_contains((string) file_get_contents($file), $svc)) {
                    $found[] = $svc;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Auto-generate a module.json structure for modules that don't have one.
     */
    private static function generateModuleJson(string $moduleName): array
    {
        $moduleDir = BASE_PATH . '/app/Modules/' . $moduleName;

        // Get menu from modules.php if present
        $modules = include BASE_PATH . '/app/Config/modules.php';
        $menu = [];
        foreach ($modules as $mod) {
            if ($mod['name'] === $moduleName) {
                $menu = $mod['menu'] ?? [];
                break;
            }
        }

        return [
            'name'                    => $moduleName,
            'version'                 => '1.0.0',
            'description'             => "Modulo {$moduleName}",
            'author'                  => 'Favilla',
            'framework_min'           => '1.0',
            'database'                => 'shared',
            'database_suggested_name' => null,
            'tables'                  => [],
            'dependencies'     => [],
            'services'         => self::detectServices($moduleDir),
            'assets'           => [
                'css' => array_map('basename', self::detectAssets($moduleName)['css']),
                'js'  => array_map('basename', self::detectAssets($moduleName)['js']),
            ],
            'uploads_directory' => null,
            'menu'             => $menu,
        ];
    }

    /**
     * Build manifest.json content.
     */
    private static function buildManifest(
        string $moduleName,
        array $meta,
        array $assets,
        array $permissions,
        array $services,
        array $dataTables,
        bool $uploadsIncluded,
        bool $includesData,
        array $reportTemplateFiles = []
    ): string {
        return json_encode([
            'module_name'       => $moduleName,
            'version'           => $meta['version'] ?? '1.0.0',
            'export_date'       => date('c'),
            'framework'         => 'Favilla',
            'includes_data'     => $includesData && !empty($dataTables),
            'includes_uploads'  => $uploadsIncluded,
            'database_mode'           => $meta['database'] ?? 'shared',
            'database_suggested_name' => $meta['database_suggested_name'] ?? null,
            'database_env_prefix'     => $meta['database_env_prefix'] ?? null,
            'tables'            => $meta['tables'] ?? [],
            'migration_files'   => array_map('basename', self::detectMigrations($moduleName)),
            'assets'            => [
                'css' => array_map('basename', $assets['css']),
                'js'  => array_map('basename', $assets['js']),
            ],
            'permissions'       => $permissions,
            'dependencies'      => $meta['dependencies'] ?? [],
            'services_detected' => $services,
            'providers'         => array_filter([
                'dashboard' => $meta['dashboard_provider'] ?? null,
                'search'    => $meta['search_provider'] ?? null,
                'export'    => $meta['export_provider'] ?? null,
            ]),
            'report_templates'  => array_map('basename', $reportTemplateFiles),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** CamelCase → kebab-case */
    private static function toKebab(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    /** CamelCase → snake_case */
    private static function toSnake(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    // -----------------------------------------------------------------------
    // INSTALL.md generator
    // -----------------------------------------------------------------------

    private static function generateInstallMd(
        string $moduleName,
        array  $migrations,
        array  $assets,
        array  $permissions,
        array  $services,
        string $modulesBlock,
        ?array $meta,
        array  $reportTemplateFiles = []
    ): string {
        $date      = date('Y-m-d');
        $hasMig    = !empty($migrations);
        $hasCss    = !empty($assets['css']);
        $hasJs     = !empty($assets['js']);
        $hasPerms  = !empty($permissions);
        $hasModuleJson = $meta !== null;

        $migFiles  = array_map('basename', $migrations);
        $cssFiles  = array_map('basename', $assets['css']);
        $jsFiles   = array_map('basename', $assets['js']);

        $L = [];

        $L[] = "# INSTALLA QUESTO MODULO: {$moduleName}";
        $L[] = '';
        $L[] = "Questo file contiene tutti i passi per installare il modulo **{$moduleName}** in Favilla.";
        $L[] = '';
        if ($hasModuleJson) {
            $L[] = '> **NOTA**: Questo modulo ha un `module.json`. Se la tua installazione Favilla supporta';
            $L[] = "> l'auto-discovery (v1.1+), basta copiare i file e il modulo verra' rilevato automaticamente.";
            $L[] = '> Gli step sotto sono per installazioni precedenti o manuali.';
            $L[] = '';
        }
        $L[] = '---';
        $L[] = '';

        // Info
        $L[] = '## INFORMAZIONI';
        $L[] = '';
        $L[] = "- Nome modulo: **{$moduleName}**";
        $L[] = '- Versione: ' . ($meta['version'] ?? 'N/A');
        $L[] = "- Data export: {$date}";
        $L[] = '- Database: ' . ($meta['database'] ?? 'shared');
        $L[] = '- Migration: ' . ($hasMig ? implode(', ', $migFiles) : 'NESSUNA');
        $L[] = '- CSS: '       . ($hasCss ? implode(', ', $cssFiles) : 'NESSUNO');
        $L[] = '- JS: '        . ($hasJs ? implode(', ', $jsFiles) : 'NESSUNO');
        $L[] = '- Permessi: '  . ($hasPerms ? implode(', ', $permissions) : 'NESSUNO');
        $L[] = '- Report template bundled: ' . (count($reportTemplateFiles) > 0 ? count($reportTemplateFiles) : 'NESSUNO');
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 1
        $L[] = '## STEP 1 — Copia la directory del modulo';
        $L[] = '';
        $L[] = "Copia la directory `module/{$moduleName}` dentro `app/Modules/`.";
        $L[] = "Risultato: deve esistere `app/Modules/{$moduleName}/` con tutti i file dentro.";
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 2 — CSS
        $L[] = '## STEP 2 — Copia i file CSS';
        $L[] = '';
        if ($hasCss) {
            foreach ($cssFiles as $f) {
                $L[] = "Copia `assets/css/{$f}` dentro `public/assets/css/`.";
            }
        } else {
            $L[] = '**SALTA QUESTO STEP. Nessun file CSS.**';
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 3 — JS
        $L[] = '## STEP 3 — Copia i file JS';
        $L[] = '';
        if ($hasJs) {
            foreach ($jsFiles as $f) {
                $L[] = "Copia `assets/js/{$f}` dentro `public/assets/js/`.";
            }
        } else {
            $L[] = '**SALTA QUESTO STEP. Nessun file JS.**';
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 4 — DB
        $L[] = '## STEP 4 — Installa il database';
        $L[] = '';
        if ($hasMig) {
            $L[] = "Le migration sono in `app/Modules/{$moduleName}/migrations/`.";
            $L[] = "Esegui: `php database/migrate.php --module={$moduleName}`";
        } else {
            $L[] = '**SALTA QUESTO STEP. Nessuna migration.**';
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 5 — Registration
        $L[] = '## STEP 5 — Registra il modulo';
        $L[] = '';
        if ($hasModuleJson) {
            $L[] = "Il modulo ha un `module.json` e verra' scoperto automaticamente dal ModuleLoader.";
            $L[] = '**Non serve modificare `app/Config/modules.php`.**';
        } else {
            $L[] = 'Incolla il blocco seguente in `app/Config/modules.php` PRIMA del blocco Admin:';
            $L[] = '';
            $L[] = '```php';
            $L[] = $modulesBlock;
            $L[] = '```';
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 6 — Permissions
        $L[] = '## STEP 6 — Importa i permessi';
        $L[] = '';
        if ($hasPerms) {
            $L[] = "Vai su Admin > Moduli e clicca **Importa Permessi** per {$moduleName}.";
            $L[] = 'Permessi: ' . implode(', ', $permissions);
        } else {
            $L[] = '**SALTA QUESTO STEP. Nessun permesso dichiarato.**';
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 7 — Bundled report templates
        $L[] = '## STEP 7 — Report template bundled';
        $L[] = '';
        if (!empty($reportTemplateFiles)) {
            $tplFiles = array_map('basename', $reportTemplateFiles);
            $L[] = 'Questo modulo include ' . count($tplFiles) . ' report template: ' . implode(', ', $tplFiles);
            $L[] = "Se il modulo **Reports** e' attivo, i template vengono importati automaticamente.";
            $L[] = 'In alternativa: vai su Report > Template > Moduli e importa manualmente.';
        } else {
            $L[] = '**SALTA QUESTO STEP. Nessun report template bundled.**';
        }
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // STEP 8 — Verify
        $L[] = '## STEP 8 — Verifica finale';
        $L[] = '';
        $L[] = '1. Fai logout e login.';
        $L[] = "2. Controlla che **{$moduleName}** appaia nel menu.";
        $L[] = '3. Apri la pagina e verifica che funzioni.';
        $L[] = '';
        $L[] = '---';
        $L[] = '';

        // Warnings
        $L[] = '## AVVERTENZE';
        $L[] = '';
        $warnings = 0;

        if (($meta['database'] ?? 'shared') === 'independent') {
            $suggested = $meta['database_suggested_name'] ?? null;
            $prefix    = $meta['database_env_prefix'] ?? null;
            $L[] = '**ATTENZIONE (database):** Questo modulo usa un database indipendente.';
            if ($suggested) {
                $L[] = "Verra' creato il database `{$suggested}` (modificabile in fase di import).";
                $L[] = 'Le credenziali del DB principale di Favilla vengono riusate; non occorre toccare `.env`.';
            } elseif ($prefix) {
                $L[] = "Modulo legacy: configura in `.env`: {$prefix}_DB_HOST, {$prefix}_DB_PORT, {$prefix}_DB_NAME, {$prefix}_DB_USER, {$prefix}_DB_PASS.";
            } else {
                $L[] = "Nessun nome DB suggerito presente: scegli un nome durante l'import.";
            }
            $L[] = '';
            $warnings++;
        }

        if (in_array('FileUploadService', $services)) {
            $L[] = '**NOTA (file):** Assicurati che `public/uploads/` esista e sia scrivibile.';
            $L[] = '';
            $warnings++;
        }

        if (in_array('NotificationService', $services)) {
            $L[] = '**NOTA (notifiche):** Il modulo Notifications deve essere attivo.';
            $L[] = '';
            $warnings++;
        }

        if ($warnings === 0) {
            $L[] = 'Nessuna avvertenza.';
            $L[] = '';
        }

        $L[] = '---';
        $L[] = '';
        $L[] = '**Se qualcosa non funziona:** controlla `storage/logs/app.log`.';

        return implode("\n", $L);
    }
}
