<?php

/**
 * CLI Migration Runner — Module-aware.
 *
 * Fonte di verita' unica: database/schema.sql (+ seed in database/seeds/required.sql).
 * Tutto lo storico delle migration progressive (core 001-030 + moduli 001_*) e'
 * stato cristallizzato in schema.sql e archiviato in migrations/archive/.
 *
 * Nuove migration progressive vanno create in database/migrations/ (core) o
 * app/Modules/{X}/migrations/ (moduli) solo per evoluzioni future del DB.
 *
 * Usage:
 *   php database/migrate.php              # Esegue migration pendenti (core + moduli)
 *   php database/migrate.php --status     # Mostra stato migration
 *   php database/migrate.php --core-only  # Solo migration core
 *   php database/migrate.php --module=X   # Solo modulo X
 *   php database/migrate.php --fresh      # DROP ALL + schema.sql + seeds/required.sql
 *   php database/migrate.php --dry-run    # Preview senza eseguire
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad(); // run on pure-env config too (Docker), no .env required

$basePath = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}

// Bootstrap container so ModuleDatabaseResolver and ModuleLoader are available.
// migrate.php has historically opened its own PDO directly (below); we keep that
// behavior for the script's main DB connection but also register the same PDO
// in the shared container so the resolver can resolve module-aware connections.
\App\Cli\Support\CliBootstrap::boot();

// ── Parse CLI arguments ─────────────────────────────────────────

$args = $argv ?? [];
$showStatus = in_array('--status', $args, true);
$coreOnly   = in_array('--core-only', $args, true);
$freshMode  = in_array('--fresh', $args, true);
$dryRun     = in_array('--dry-run', $args, true);
$moduleOnly = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--module=')) {
        $moduleOnly = substr($arg, strlen('--module='));
    }
}

// Validation: --fresh and --dry-run are incompatible
if ($freshMode && $dryRun) {
    echo "[ERRORE] --fresh e --dry-run non sono compatibili.\n";
    exit(1);
}

// ── Connect to database ─────────────────────────────────────────

try {
    $cfg = require $basePath . '/app/Config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']
    );
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
    echo "[OK] Connesso al database: {$cfg['name']}\n";
} catch (PDOException $e) {
    echo "[ERRORE] Connessione DB fallita: {$e->getMessage()}\n";
    exit(1);
}

// ── Ensure migrations table exists (with self-upgrade) ──────────

ensureMigrationsTable($pdo);

// ── --status mode ───────────────────────────────────────────────

if ($showStatus) {
    showStatus($pdo);
    exit(0);
}

// ── --fresh mode: DROP ALL → schema.sql → seeds → module migrations

if ($freshMode) {
    freshInstall($pdo, $basePath);
    exit(0);
}

// ── Determine batch number ──────────────────────────────────────

$batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
$consolidatedModuleMigrations = getConsolidatedModuleMigrations();

$totalErrors = 0;
$totalRun    = 0;
$totalSkip   = 0;

// ── Phase 1: Core migrations ────────────────────────────────────

if (!$moduleOnly) {
    echo "\n=== Migration Core ===\n";
    $coreDir = $basePath . '/database/migrations';
    [$run, $skip, $errors] = runMigrations($pdo, $coreDir, null, $batch, null, $dryRun, []);
    $totalRun    += $run;
    $totalSkip   += $skip;
    $totalErrors += $errors;
}

// ── Phase 2: Module migrations ──────────────────────────────────

if (!$coreOnly) {
    echo "\n=== Migration Moduli ===\n";
    $modules = require $basePath . '/app/Config/modules.php';

    // Auto-discovery: include modules with module.json not in modules.php
    $registeredNames = array_column($modules, 'name');
    $discoveredJsons = glob($basePath . '/app/Modules/*/module.json') ?: [];
    foreach ($discoveredJsons as $jsonFile) {
        $dirName = basename(dirname($jsonFile));
        if ($dirName !== '_Template' && !in_array($dirName, $registeredNames, true)) {
            $modules[] = ['name' => $dirName, 'enabled' => true];
        }
    }

    foreach ($modules as $module) {
        $name = $module['name'];

        // Skip _Template — non è un modulo reale
        if ($name === '_Template') {
            continue;
        }

        // --module=X: esegui solo quello specifico
        if ($moduleOnly !== null && $name !== $moduleOnly) {
            continue;
        }

        $migDir = $basePath . '/app/Modules/' . $name . '/migrations';

        if (!is_dir($migDir)) {
            continue; // Modulo senza migrations
        }

        echo "\n--- Modulo: {$name} ---\n";

        // Resolve module PDO via central resolver (mapping > env prefix > error).
        // No silent fallback for independent modules: if resolution fails, errors are
        // surfaced and the module is skipped instead of writing to the main DB.
        $modulePdo = $pdo;
        $moduleJsonFile = $basePath . '/app/Modules/' . $name . '/module.json';
        if (file_exists($moduleJsonFile)) {
            $meta = json_decode(file_get_contents($moduleJsonFile), true);
            if (is_array($meta) && ($meta['database'] ?? 'shared') === 'independent') {
                try {
                    $modulePdo = app(\App\Services\ModuleDatabaseResolver::class)->pdoFor($name);
                    $mapping   = app(\App\Services\ModuleDatabaseResolver::class)->getMapping($name);
                    $dbLabel   = $mapping['database_name'] ?? '?';
                    echo "  [INFO] Database indipendente: {$dbLabel}\n";
                } catch (\Throwable $e) {
                    echo "  [ERRORE] " . $e->getMessage() . "\n";
                    echo "  [SKIP] Modulo '{$name}' saltato (no fallback al DB principale).\n";
                    continue;
                }
            }
        }

        // Run migrations on the appropriate PDO, tracking on main DB
        [$run, $skip, $errors] = runMigrations(
            $modulePdo,
            $migDir,
            $name,
            $batch,
            $pdo,
            $dryRun,
            $consolidatedModuleMigrations[$name] ?? []
        );
        $totalRun    += $run;
        $totalSkip   += $skip;
        $totalErrors += $errors;
    }
}

// ── Summary ─────────────────────────────────────────────────────

echo "\n";

// Show table count
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "[RISULTATO] Tabelle nel database: " . count($tables) . "\n";

if ($totalRun > 0) {
    echo "[INFO] Migration eseguite: {$totalRun}\n";
}
if ($totalSkip > 0) {
    echo "[INFO] Migration saltate (già eseguite): {$totalSkip}\n";
}

if ($totalErrors > 0) {
    echo "[ATTENZIONE] {$totalErrors} errore(i) durante l'esecuzione.\n";
    exit(1);
}

echo "[OK] Migration completate con successo.\n";

// ═════════════════════════════════════════════════════════════════
// Functions
// ═════════════════════════════════════════════════════════════════

/**
 * Ensure the migrations table exists and has the correct schema.
 * Self-upgrades from the old format (filename-only) to the new
 * format (filename + module + batch).
 */
function ensureMigrationsTable(PDO $pdo): void
{
    // Create table if it doesn't exist at all
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename    VARCHAR(255) NOT NULL,
            module      VARCHAR(100) NULL DEFAULT NULL,
            batch       INT UNSIGNED NOT NULL DEFAULT 1,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_migrations (filename, module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Self-upgrade: add module column if missing
    $cols = $pdo->query("SHOW COLUMNS FROM migrations LIKE 'module'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE migrations ADD COLUMN module VARCHAR(100) NULL DEFAULT NULL AFTER filename");
    }

    // Self-upgrade: add batch column if missing
    $cols = $pdo->query("SHOW COLUMNS FROM migrations LIKE 'batch'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE migrations ADD COLUMN batch INT UNSIGNED NOT NULL DEFAULT 1 AFTER module");
    }

    // Self-upgrade: migrate from old UNIQUE(filename) to composite UNIQUE(filename, module)
    $indices = $pdo->query("SHOW INDEX FROM migrations WHERE Key_name = 'filename'")->fetchAll();
    if (!empty($indices)) {
        try {
            $pdo->exec("ALTER TABLE migrations DROP INDEX filename");
        } catch (PDOException $e) {
            // Already dropped — ignore
        }
    }

    // Ensure composite unique exists
    $indices = $pdo->query("SHOW INDEX FROM migrations WHERE Key_name = 'uq_migrations'")->fetchAll();
    if (empty($indices)) {
        try {
            $pdo->exec("ALTER TABLE migrations ADD UNIQUE KEY uq_migrations (filename, module)");
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }
    }
}

/**
 * Run all pending SQL migrations from a directory.
 *
 * @param PDO      $pdo         PDO to execute SQL against (may be independent DB)
 * @param string   $dir         Directory containing .sql files
 * @param ?string  $module      Module name (null for core)
 * @param int      $batch       Current batch number
 * @param ?PDO     $trackingPdo Optional: PDO for recording migration status
 *                              (used when $pdo is an independent DB but tracking
 *                              must be in the main Favilla DB). Defaults to $pdo.
 * @param bool     $dryRun      If true, show what would run without executing
 * @return array{int, int, int} [executed, skipped, errors]
 */
function runMigrations(
    PDO $pdo,
    string $dir,
    ?string $module,
    int $batch,
    ?PDO $trackingPdo = null,
    bool $dryRun = false,
    array $consolidatedAliases = []
): array
{
    $trackingPdo = $trackingPdo ?? $pdo;

    $files = glob($dir . '/*.sql');
    if (empty($files)) {
        echo "  [INFO] Nessun file migration trovato.\n";
        return [0, 0, 0];
    }
    sort($files);

    $executed = 0;
    $skipped  = 0;
    $errors   = 0;

    foreach ($files as $file) {
        $filename = basename($file);

        // If a module migration is consolidated in core and the corresponding
        // core migration is present, skip it and mark as resolved for this module.
        if ($module !== null && isset($consolidatedAliases[$filename])) {
            $coreFilename = $consolidatedAliases[$filename];
            $coreDone = $trackingPdo
                ->prepare('SELECT id FROM migrations WHERE filename = ? AND module IS NULL LIMIT 1');
            $coreDone->execute([$coreFilename]);

            if ($coreDone->fetch()) {
                echo "  [SKIP] {$filename} (consolidata in {$coreFilename})\n";

                if (!$dryRun) {
                    $mark = $trackingPdo
                        ->prepare('INSERT IGNORE INTO migrations (filename, module, batch) VALUES (?, ?, ?)');
                    $mark->execute([$filename, $module, $batch]);
                }

                $skipped++;
                continue;
            }
        }

        // Check if already executed (always check on tracking DB)
        if ($module === null) {
            $check = $trackingPdo->prepare('SELECT id FROM migrations WHERE filename = ? AND module IS NULL');
            $check->execute([$filename]);
        } else {
            $check = $trackingPdo->prepare('SELECT id FROM migrations WHERE filename = ? AND module = ?');
            $check->execute([$filename, $module]);
        }

        if ($check->fetch()) {
            echo "  [SKIP] {$filename}\n";
            $skipped++;
            continue;
        }

        echo "  [RUN]  {$filename}\n";

        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "    [ERRORE] Impossibile leggere il file.\n";
            $errors++;
            continue;
        }

        // Handle --dry-run: show what would be executed without executing it
        if ($dryRun) {
            echo "    [DRY-RUN] Verrebbe eseguito: {$filename}\n";
            echo "    SQL:\n";
            // Show first 5 lines as preview
            $lines = array_slice(explode("\n", $sql), 0, 5);
            foreach ($lines as $line) {
                echo "      {$line}\n";
            }
            if (count(explode("\n", $sql)) > 5) {
                echo "      ... (altre righe)\n";
            }
            $executed++;
            continue;
        }

        $statements = splitSqlStatements($sql);
        $useTransaction = migrationSupportsTransaction($statements);

        $stmtErrors = 0;
        try {
            if ($useTransaction) {
                $pdo->beginTransaction();
            }

            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Idempotency: skip "already exists" / "Duplicate" silently
                    if (!isIdempotencyError($e)) {
                        throw $e;
                    }
                }
            }

            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $suffix = $useTransaction ? ' — rollback eseguito' : '';
            echo "    [ERRORE] {$e->getMessage()}{$suffix}\n";
            $stmtErrors++;
        }

        if ($stmtErrors === 0) {
            // Record migration (always on tracking DB — main Favilla DB)
            $insert = $trackingPdo->prepare('INSERT INTO migrations (filename, module, batch) VALUES (?, ?, ?)');
            $insert->execute([$filename, $module, $batch]);
            echo "    [OK]\n";
            $executed++;
        } else {
            $errors += $stmtErrors;
        }
    }

    return [$executed, $skipped, $errors];
}

/**
 * Show migration status table (--status flag).
 */
function showStatus(PDO $pdo): void
{
    $rows = $pdo->query(
        "SELECT filename, COALESCE(module, '(core)') AS source, batch, executed_at
         FROM migrations ORDER BY executed_at, id"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "\n[INFO] Nessuna migration registrata.\n";
        return;
    }

    echo "\n";
    echo sprintf("%-45s %-15s %-6s %s\n", 'FILENAME', 'SOURCE', 'BATCH', 'EXECUTED AT');
    echo str_repeat('-', 95) . "\n";

    foreach ($rows as $row) {
        echo sprintf(
            "%-45s %-15s %-6d %s\n",
            $row['filename'],
            $row['source'],
            $row['batch'],
            $row['executed_at']
        );
    }

    echo "\n[INFO] Totale: " . count($rows) . " migration registrate.\n";
}

/**
 * Fresh install: DROP all tables → schema.sql → seeds → module migrations.
 */
function freshInstall(PDO $pdo, string $basePath): void
{
    $schemaFile = $basePath . '/database/schema.sql';
    $seedsFile  = $basePath . '/database/seeds/required.sql';

    if (!file_exists($schemaFile)) {
        echo "[ERRORE] File schema.sql non trovato: {$schemaFile}\n";
        exit(1);
    }
    if (!file_exists($seedsFile)) {
        echo "[ERRORE] File seeds/required.sql non trovato: {$seedsFile}\n";
        exit(1);
    }

    // 1. DROP all tables
    echo "\n=== Fresh Install: DROP tabelle esistenti ===\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  [DROP] {$table}\n";
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "  [OK] " . count($tables) . " tabelle rimosse.\n";

    // 2. Execute schema.sql
    echo "\n=== Esecuzione schema.sql ===\n";
    $errors = executeSqlFile($pdo, $schemaFile);
    if ($errors > 0) {
        echo "[ERRORE] {$errors} errore(i) durante l'esecuzione di schema.sql\n";
        exit(1);
    }
    $newTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "  [OK] " . count($newTables) . " tabelle create.\n";

    // 3. Execute seeds
    echo "\n=== Esecuzione seeds/required.sql ===\n";
    $errors = executeSqlFile($pdo, $seedsFile);
    if ($errors > 0) {
        echo "[ERRORE] {$errors} errore(i) durante il seeding\n";
        exit(1);
    }
    echo "  [OK] Dati obbligatori inseriti.\n";

    // 3b. Registra le migration CORE già cristallizzate in schema.sql come
    // eseguite, SENZA rieseguirle. schema.sql è la fonte di verità per --fresh e
    // ne contiene già il DDL; senza questa registrazione un successivo
    // `migrate.php` le ritroverebbe non-registrate, le rieseguirebbe e ne
    // inghiottirebbe gli errori "already exists" (drift a due sorgenti).
    $coreMigrations = glob($basePath . '/database/migrations/*.sql') ?: [];
    if (!empty($coreMigrations)) {
        echo "\n=== Registrazione migration core consolidate ===\n";
        $mark = $pdo->prepare(
            'INSERT IGNORE INTO migrations (filename, module, batch) VALUES (?, NULL, 0)'
        );
        foreach ($coreMigrations as $coreFile) {
            $filename = basename($coreFile);
            $mark->execute([$filename]);
            echo "  [MARK] {$filename} (consolidata in schema.sql)\n";
        }
        echo "  [OK] " . count($coreMigrations) . " migration core registrate.\n";
    }

    // 4. Carica la KB Help Online da database/help/*.json (contenuto versionato
    // nel repo, indipendente dai moduli abilitati per l'edizione scelta).
    echo "\n=== Import KB Help Online ===\n";
    $helpDir = $basePath . '/database/help';
    if (is_dir($helpDir)) {
        try {
            $helpResult = app(\App\Modules\HelpOnline\Services\HelpContentService::class)->importAll($helpDir);
            echo '  [OK] Moduli importati: ' . count($helpResult['imported']) . "\n";
        } catch (\Throwable $e) {
            echo '  [ATTENZIONE] Import Help Online non riuscito: ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SALTATO] Nessuna directory database/help trovata.\n";
    }

    // 5. Run module migrations (nuove migration future, se presenti)
    echo "\n=== Migration Moduli ===\n";
    $modules = require $basePath . '/app/Config/modules.php';

    // Auto-discovery
    $registeredNames = array_column($modules, 'name');
    $discoveredJsons = glob($basePath . '/app/Modules/*/module.json') ?: [];
    foreach ($discoveredJsons as $jsonFile) {
        $dirName = basename(dirname($jsonFile));
        if ($dirName !== '_Template' && !in_array($dirName, $registeredNames, true)) {
            $modules[] = ['name' => $dirName, 'enabled' => true];
        }
    }

    $consolidatedModuleMigrations = getConsolidatedModuleMigrations();
    $totalModuleMig = 0;
    foreach ($modules as $module) {
        $name = $module['name'];
        if ($name === '_Template') continue;

        $migDir = $basePath . '/app/Modules/' . $name . '/migrations';
        if (!is_dir($migDir)) continue;

        echo "\n--- Modulo: {$name} ---\n";
        [$run, $skip, $errors] = runMigrations(
            $pdo,
            $migDir,
            $name,
            1,
            $pdo,
            false,
            $consolidatedModuleMigrations[$name] ?? []
        );
        $totalModuleMig += $run;
    }

    // Summary
    $finalTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "\n[RISULTATO] Tabelle nel database: " . count($finalTables) . "\n";
    echo "[OK] Fresh install completata con successo.\n";
}

/**
 * Execute a SQL file (multi-statement).
 * Returns number of errors.
 */
function executeSqlFile(PDO $pdo, string $file): int
{
    $sql = file_get_contents($file);
    $statements = splitSqlStatements($sql);

    $errors = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            if (!isIdempotencyError($e)) {
                echo "    [ERRORE] {$e->getMessage()}\n";
                $errors++;
            }
        }
    }
    return $errors;
}

/**
 * Determina se un errore PDO è un "già esistente / duplicato" idempotente
 * (tabella/colonna/chiave già presente, entry duplicata) e quindi ignorabile su
 * riesecuzione di una migration/seed.
 *
 * Centralizza in un unico punto la logica di swallow prima duplicata tra
 * runMigrationsInDir() ed executeSqlFile(). NB: un narrowing per solo-SQLSTATE
 * NON è affidabile qui, perché il "Duplicate foreign key constraint name" di
 * MariaDB emette SQLSTATE generico HY000 come molti altri errori reali; il match
 * sul messaggio resta quindi il discriminante più sicuro.
 */
function isIdempotencyError(PDOException $e): bool
{
    $message = $e->getMessage();

    return str_contains($message, 'already exists')
        || str_contains($message, 'Duplicate');
}

/**
 * MySQL esegue commit impliciti su molte istruzioni DDL, quindi queste
 * migration non possono essere trattate come transazionali dal runner.
 */
function migrationSupportsTransaction(array $statements): bool
{
    foreach ($statements as $statement) {
        if (preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME|LOCK|UNLOCK)\b/i', $statement) === 1) {
            return false;
        }
    }

    return true;
}

/**
 * Split a SQL file into individual statements, correctly handling:
 * - Single-quoted strings (including escaped quotes \' and doubled '')
 * - Double-quoted strings
 * - Single-line comments (-- ...)
 * - Multi-line comments (/* ... *​/)
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $i          = 0;

    while ($i < $len) {
        $ch = $sql[$i];

        // Single-line comment: skip to end of line
        if ($ch === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
            $end = strpos($sql, "\n", $i);
            $i   = ($end === false) ? $len : $end + 1;
            continue;
        }

        // Block comment: skip to */
        if ($ch === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i   = ($end === false) ? $len : $end + 2;
            continue;
        }

        // Quoted string: copy verbatim until closing quote
        if ($ch === "'" || $ch === '"') {
            $quote   = $ch;
            $current .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                $current .= $c;
                if ($c === '\\') {
                    // Backslash escape — consume next char too
                    $i++;
                    if ($i < $len) {
                        $current .= $sql[$i];
                    }
                } elseif ($c === $quote) {
                    // Doubled-quote escape (SQL standard)
                    if (isset($sql[$i + 1]) && $sql[$i + 1] === $quote) {
                        $i++;
                        $current .= $sql[$i];
                    } else {
                        break; // End of quoted string
                    }
                }
                $i++;
            }
            $i++;
            continue;
        }

        // Statement delimiter
        if ($ch === ';') {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $ch;
        $i++;
    }

    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

/**
 * Mapping "alias" per migration di moduli gia' consolidate in schema.sql
 * + seeds/required.sql. Formato: [modulo => [file_modulo => file_core_equivalente]].
 *
 * Stato 2026-04-20: tutte le migration storiche sono cristallizzate in schema.sql
 * e spostate in migrations/archive/ (sia core sia moduli). Nessun alias e' piu' necessario.
 * Future evoluzioni potranno tornare a popolare questa mappa se una migration di
 * modulo viene riassorbita nel core schema.
 */
function getConsolidatedModuleMigrations(): array
{
    return [];
}
