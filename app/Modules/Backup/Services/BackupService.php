<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

use App\Modules\Backup\Repositories\BackupRepository;
use App\Modules\Notifications\Services\NotificationService;

class BackupService
{
    private const BACKUP_DIR    = '/storage/backups';
    private const LOCK_FILE     = '.backup.lock';
    private const CHUNK_SIZE    = 1000;
    // File aggiunti allo zip tra una close() e la successiva: libzip apre le
    // sorgenti tutte insieme alla close, il lotto tiene basso il numero di fd.
    private const ZIP_BATCH_SIZE = 400;

    /**
     * Crea un backup completo del database in formato SQL compresso gzip.
     * Usa streaming gzip per evitare memory explosion su DB grandi.
     *
     * @return array{filename: string, size: int, table_count: int}
     * @throws \RuntimeException Se un backup è già in corso o errore I/O
     */
    public function createBackup(?int $userId = null): array
    {
        set_time_limit(300);

        $backupDir = BASE_PATH . self::BACKUP_DIR;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Lock per prevenire backup concorrenti
        $lockPath = $backupDir . '/' . self::LOCK_FILE;
        $lockFp   = fopen($lockPath, 'w');
        if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
            if ($lockFp) {
                fclose($lockFp);
            }
            throw new \RuntimeException('Un backup è già in corso. Riprova tra qualche minuto.');
        }

        try {
            $excluded  = $this->getExcludedTables();
            $timestamp = date('Ymd_His');
            $filename  = 'backup_' . $timestamp . '.zip';
            $path      = $backupDir . '/' . $filename;

            // Directory temporanea per i dump per-database, ripulita a fine operazione.
            $tmpDir = $backupDir . '/.tmp_' . $timestamp . '_' . bin2hex(random_bytes(3));
            if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
                throw new \RuntimeException('Impossibile creare la directory temporanea di backup.');
            }

            $manifestEntries = [];
            $warnings        = [];
            $gzFiles         = [];   // innerName => percorso temporaneo
            $totalTables     = 0;
            $partial         = false;

            try {
                foreach ($this->collectBackupTargets() as $target) {
                    $innerName = $target['key'] . '.sql.gz';

                    // Database dichiarato ma non raggiungibile: si avvisa e si prosegue.
                    if (!$target['usable']) {
                        $partial    = true;
                        $warnings[] = sprintf(
                            'Database del modulo %s (%s) non raggiungibile: escluso dal backup.',
                            $target['module'] ?? '?',
                            $target['database_name']
                        );
                        $manifestEntries[] = [
                            'key'           => $target['key'],
                            'module'        => $target['module'],
                            'database_name' => $target['database_name'],
                            'sql_file'      => null,
                            'table_count'   => 0,
                            'sha256'        => null,
                            'usable'        => false,
                        ];
                        continue;
                    }

                    $tmpPath    = $tmpDir . '/' . $innerName;
                    $tableCount = $this->dumpDatabaseToGz($target['pdo'], $excluded, $tmpPath, $target['database_name']);

                    if (!$this->verifyGzipIntegrity($tmpPath)) {
                        throw new \RuntimeException("Dump del database {$target['database_name']} corrotto. Backup annullato.");
                    }

                    $totalTables += $tableCount;
                    $gzFiles[$innerName] = $tmpPath;
                    $manifestEntries[]   = [
                        'key'           => $target['key'],
                        'module'        => $target['module'],
                        'database_name' => $target['database_name'],
                        'sql_file'      => $innerName,
                        'table_count'   => $tableCount,
                        'sha256'        => hash_file('sha256', $tmpPath),
                        'usable'        => true,
                    ];
                }

                // Guardrail opzionale: blocca se manca anche un solo DB di modulo.
                if ($partial && $this->failOnMissingModuleDb()) {
                    throw new \RuntimeException(
                        'Backup annullato: uno o più database di modulo non sono raggiungibili '
                        . 'e BACKUP_FAIL_ON_MISSING_MODULE_DB è attivo.'
                    );
                }

                // File utente (uploads + storage Documenti), se abilitati.
                $filesService = app(BackupFilesService::class);
                $fileEntries  = [];
                $fileRoots    = [];
                if ($filesService->isEnabled()) {
                    $enumerated  = $filesService->enumerate();
                    $fileRoots   = $enumerated['roots'];
                    $fileEntries = $enumerated['files'];
                    $warnings    = array_merge($warnings, $enumerated['warnings']);
                }

                // Il manifest è costruito DENTRO il packaging, sui conteggi dei
                // file effettivamente aggiunti (un upload può sparire tra
                // enumerazione e scrittura dello zip).
                $manifestBuilder = function (array $addedPerKey, array $packWarnings) use (
                    $partial,
                    $excluded,
                    $manifestEntries,
                    &$fileRoots,
                    &$warnings
                ): string {
                    foreach ($fileRoots as &$root) {
                        $actual = $addedPerKey[$root['key']] ?? ['count' => 0, 'bytes' => 0];
                        $root['file_count'] = $actual['count'];
                        $root['total_size'] = $actual['bytes'];
                    }
                    unset($root);
                    $warnings = array_merge($warnings, $packWarnings);

                    $manifest = [
                        'manifest_version' => 2,
                        'created_at'       => date('Y-m-d H:i:s'),
                        'app_version'      => (string) (config('app.version') ?? config('app.name', '')),
                        'partial'          => $partial,
                        'excluded_tables'  => $excluded,
                        'databases'        => $manifestEntries,
                        'files'            => $fileRoots,
                        'warnings'         => $warnings,
                    ];

                    return (string) json_encode(
                        $manifest,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                };

                // La copia dei file può essere lunga: riparte il timer.
                set_time_limit(300);
                $this->packageBackupSet($gzFiles, $fileEntries, $manifestBuilder, $path);
            } finally {
                $this->removeDir($tmpDir);
            }

            // Cifra l'archivio se BACKUP_ENCRYPTION_KEY è configurata (in-place sui byte dello zip).
            // Se la cifratura fallisce (es. archivio oltre il limite in-memory), l'archivio
            // in chiaro NON deve restare su disco: è esattamente ciò che la policy vieta.
            try {
                app(BackupEncryptionService::class)->encryptInPlace($path);
            } catch (\Throwable $e) {
                $this->deleteIfExists($path);
                throw $e;
            }

            $size = filesize($path);

            $fileCount = (int) array_sum(array_column($fileRoots, 'file_count'));
            $filesSize = (int) array_sum(array_column($fileRoots, 'total_size'));

            // Rotazione: elimina vecchi backup (file + record DB)
            $this->rotate();

            // Notifica in-app
            if ($userId) {
                $sizeMb = number_format($size / 1048576, 2, ',', '.');
                NotificationService::dispatchEventToUser(
                    'backup.completed',
                    'Backup',
                    $userId,
                    [
                        'filename'       => $filename,
                        'size_mb'        => $sizeMb,
                        'size'           => $size,
                        'table_count'    => $totalTables,
                        'excluded_count' => count($excluded),
                        'database_count' => count($gzFiles),
                        'file_count'     => $fileCount,
                        'partial'        => $partial,
                    ],
                    $this->buildBackupLink(),
                    null
                );
            }

            return [
                'filename'       => $filename,
                'size'           => $size,
                'table_count'    => $totalTables,
                'excluded_count' => count($excluded),
                'databases'      => $manifestEntries,
                'files'          => $fileRoots,
                'file_count'     => $fileCount,
                'files_size'     => $filesSize,
                'partial'        => $partial,
            ];
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            $this->deleteIfExists($lockPath);
        }
    }

    /**
     * Raccoglie i database da includere nel backup: principale + ogni modulo
     * con database dedicato (independent) attivo. I moduli non raggiungibili
     * vengono restituiti con usable=false (verranno segnalati, non interrompono).
     *
     * @return array<array{key:string,module:?string,database_name:string,pdo:?\PDO,usable:bool}>
     */
    private function collectBackupTargets(): array
    {
        $targets = [[
            'key'           => 'main',
            'module'        => null,
            'database_name' => (string) config('database.name', ''),
            'pdo'           => app(\PDO::class),
            'usable'        => true,
        ]];

        try {
            $resolver = app(\App\Services\ModuleDatabaseResolver::class);
            $independents = $resolver->allActiveIndependent();
        } catch (\Throwable $e) {
            return $targets;
        }

        foreach ($independents as $row) {
            $module = $row['module_name'];
            $entry  = [
                'key'           => $this->sanitizeDbKey($module),
                'module'        => $module,
                'database_name' => $row['database_name'],
                'pdo'           => null,
                'usable'        => false,
            ];

            try {
                if ($resolver->isUsable($module)) {
                    $entry['pdo']    = $resolver->pdoFor($module);
                    $entry['usable'] = true;
                }
            } catch (\Throwable $e) {
                $entry['usable'] = false;
            }

            $targets[] = $entry;
        }

        return $targets;
    }

    /**
     * Normalizza il nome modulo in una chiave file-safe per il set di backup.
     * Evita collisioni con la chiave riservata 'main'.
     */
    private function sanitizeDbKey(string $module): string
    {
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($module));
        $key = trim((string) $key, '_');
        if ($key === '' || $key === 'main') {
            $key = 'mod_' . ($key === '' ? 'x' : $key);
        }
        return $key;
    }

    /**
     * Esegue il dump completo di un database su un file .sql.gz dedicato (streaming).
     * Riusa i dump helper per-tabella, che sono già PDO-agnostici.
     *
     * @return int Numero di tabelle scritte.
     */
    private function dumpDatabaseToGz(\PDO $pdo, array $excluded, string $path, string $dbLabel): int
    {
        $tables = $this->getTables($pdo);
        if (!empty($excluded)) {
            $tables = array_values(array_diff($tables, $excluded));
        }

        $gz = gzopen($path, 'wb6');
        if (!$gz) {
            throw new \RuntimeException("Impossibile creare il dump per il database {$dbLabel}.");
        }

        gzwrite($gz, "-- Favilla Backup\n");
        gzwrite($gz, "-- Database: {$dbLabel}\n");
        gzwrite($gz, '-- Data: ' . date('Y-m-d H:i:s') . "\n");
        gzwrite($gz, '-- Tabelle: ' . count($tables) . "\n");
        if (!empty($excluded)) {
            gzwrite($gz, '-- Tabelle escluse: ' . implode(', ', $excluded) . "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            $this->dumpTableStructureToGz($pdo, $table, $gz);
            $this->dumpTableDataToGz($pdo, $table, $gz);
        }

        gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return count($tables);
    }

    /**
     * Impacchetta dump per-database, file utente e manifest in un unico .zip.
     *
     * I file vengono aggiunti a lotti con close/reopen periodici: libzip apre
     * i file sorgente solo alla close(), e con migliaia di addFile in sospeso
     * si esaurisce il limite di file descriptor del processo.
     *
     * Il manifest è prodotto da $manifestBuilder con i conteggi dei file
     * REALMENTE aggiunti (un upload sparito tra enumerazione e packaging viene
     * saltato con warning, non deve far fallire il backup).
     *
     * @param array<string,string> $gzFiles innerName => percorso file temporaneo
     * @param array<array{path: string, entry: string}> $fileEntries
     * @param callable(array<string,array{count:int,bytes:int}>,string[]):string $manifestBuilder
     */
    private function packageBackupSet(
        array $gzFiles,
        array $fileEntries,
        callable $manifestBuilder,
        string $zipPath
    ): void {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossibile creare l\'archivio di backup.');
        }

        foreach ($gzFiles as $innerName => $tmpPath) {
            if (!$zip->addFile($tmpPath, $innerName)) {
                $zip->close();
                $this->deleteIfExists($zipPath);
                throw new \RuntimeException("Impossibile aggiungere {$innerName} all'archivio di backup.");
            }
        }

        // I dump vanno flushati subito: vivono in una directory temporanea che
        // il chiamante rimuove appena questo metodo ritorna.
        if (!$zip->close()) {
            $this->deleteIfExists($zipPath);
            throw new \RuntimeException('Chiusura dell\'archivio di backup fallita.');
        }

        $addedPerKey  = [];
        $packWarnings = [];
        $pending      = 0;

        $reopen = function () use ($zip, $zipPath): void {
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                $this->deleteIfExists($zipPath);
                throw new \RuntimeException('Impossibile riaprire l\'archivio di backup.');
            }
        };
        $flush = function () use ($zip, $zipPath): void {
            if (!$zip->close()) {
                $this->deleteIfExists($zipPath);
                throw new \RuntimeException('Scrittura dei file nell\'archivio di backup fallita.');
            }
        };

        $reopen();
        foreach ($fileEntries as $file) {
            // Il file può essere sparito dopo l'enumerazione (upload transitori).
            $size = $this->callSilently(static fn () => filesize($file['path']));
            if (!is_int($size) || !$zip->addFile($file['path'], $file['entry'])) {
                $packWarnings[] = "File sparito o illeggibile, escluso dal backup: {$file['path']}";
                continue;
            }

            if (preg_match('#^files/([a-z0-9_]+)/#', $file['entry'], $m)) {
                $key = $m[1];
                $addedPerKey[$key] ??= ['count' => 0, 'bytes' => 0];
                $addedPerKey[$key]['count']++;
                $addedPerKey[$key]['bytes'] += $size;
            }

            if (++$pending >= self::ZIP_BATCH_SIZE) {
                $flush();
                $reopen();
                $pending = 0;
                set_time_limit(300);
            }
        }

        $zip->addFromString('manifest.json', $manifestBuilder($addedPerKey, $packWarnings));
        $flush();
    }

    /**
     * Vero se il file di backup è un set multi-DB (.zip), falso se legacy (.sql.gz).
     */
    public function isZipBackup(string $filename): bool
    {
        return str_ends_with(strtolower($filename), '.zip');
    }

    /**
     * Se attivo, un database di modulo non raggiungibile fa fallire l'intero backup.
     */
    private function failOnMissingModuleDb(): bool
    {
        return filter_var((string) env('BACKUP_FAIL_ON_MISSING_MODULE_DB', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Rimuove ricorsivamente una directory temporanea (un solo livello di file).
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                $this->callSilently(static fn () => unlink($file));
            }
        }
        $this->callSilently(static fn () => rmdir($dir));
    }

    /**
     * Ritorna la lista delle tabelle del database corrente.
     *
     * @return string[]
     */
    public function getTables(\PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Ritorna le tabelle da escludere dal backup (da .env).
     *
     * @return string[]
     */
    public function getExcludedTables(): array
    {
        $raw = env('BACKUP_EXCLUDE_TABLES', '');
        if (empty($raw)) {
            return [];
        }
        return array_map('trim', explode(',', $raw));
    }

    /**
     * Scrive DROP TABLE + CREATE TABLE direttamente sullo stream gzip.
     *
     * @param resource $gz Handle gzopen
     */
    public function dumpTableStructureToGz(\PDO $pdo, string $table, $gz): void
    {
        gzwrite($gz, "-- --------------------------------------------------------\n");
        gzwrite($gz, "-- Tabella: `{$table}`\n");
        gzwrite($gz, "-- --------------------------------------------------------\n\n");
        gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");

        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row  = $stmt->fetch(\PDO::FETCH_NUM);
        gzwrite($gz, $row[1] . ";\n\n");
    }

    /**
     * Scrive INSERT INTO a blocchi direttamente sullo stream gzip.
     * Usa fetch row-by-row per evitare di caricare tutta la tabella in memoria.
     *
     * @param resource $gz Handle gzopen
     */
    public function dumpTableDataToGz(\PDO $pdo, string $table, $gz): void
    {
        $stmt = $pdo->query("SELECT * FROM `{$table}`");

        $columns   = null;
        $batch     = [];
        $batchSize = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Prima riga: estrai nomi colonne
            if ($columns === null) {
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
            }

            $escaped = [];
            foreach ($row as $val) {
                $escaped[] = ($val === null) ? 'NULL' : $pdo->quote((string) $val);
            }
            $batch[] = '(' . implode(', ', $escaped) . ')';
            $batchSize++;

            if ($batchSize >= self::CHUNK_SIZE) {
                gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n");
                gzwrite($gz, implode(",\n", $batch) . ";\n");
                $batch     = [];
                $batchSize = 0;
            }
        }

        // Flush righe rimanenti
        if (!empty($batch)) {
            gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n");
            gzwrite($gz, implode(",\n", $batch) . ";\n");
        }

        if ($columns !== null) {
            gzwrite($gz, "\n");
        }
    }

    /**
     * Verifica che il file gzip sia leggibile e non corrotto.
     */
    public function verifyGzipIntegrity(string $path): bool
    {
        if (!file_exists($path) || filesize($path) === 0) {
            return false;
        }

        // Quick check: verify gzip magic bytes (1F 8B)
        $fp = $this->callSilently(static fn () => fopen($path, 'rb'));
        if (!is_resource($fp)) {
            return false;
        }
        $header = fread($fp, 2);
        fclose($fp);

        if ($header === false || strlen($header) < 2
            || ord($header[0]) !== 0x1F || ord($header[1]) !== 0x8B) {
            return false;
        }

        // Full CRC check for files up to 100MB compressed (gzdecode verifies CRC32 trailer).
        // For larger files, fall back to streaming decompression (detects most corruption
        // but not clean truncation — acceptable trade-off vs loading GBs into memory).
        $size = filesize($path);
        $crcThreshold = 100 * 1024 * 1024; // 100MB

        if ($size <= $crcThreshold) {
            $compressed = $this->callSilently(static fn () => file_get_contents($path));
            if (!is_string($compressed)) {
                return false;
            }
            $decompressed = $this->callSilently(static fn () => gzdecode($compressed));
            return is_string($decompressed) && strlen($decompressed) > 0;
        }

        // Large file: streaming check
        $gz = $this->callSilently(static fn () => gzopen($path, 'rb'));
        if (!is_resource($gz)) {
            return false;
        }

        try {
            $bytesRead = 0;
            while (!gzeof($gz)) {
                $chunk = gzread($gz, 65536);
                if ($chunk === false) {
                    return false;
                }
                $bytesRead += strlen($chunk);
            }
            return $bytesRead > 0;
        } catch (\Throwable) {
            return false;
        } finally {
            gzclose($gz);
        }
    }

    /**
     * Rotazione backup: elimina i file più vecchi se si supera BACKUP_MAX_COUNT.
     * Rimuove anche i record corrispondenti dal DB.
     */
    public function rotate(): void
    {
        $max   = (int) env('BACKUP_MAX_COUNT', 10);
        $files = $this->backupFiles();
        if ($files === []) {
            return;
        }
        usort($files, fn ($a, $b) => filemtime($a) - filemtime($b));

        $repo = app(BackupRepository::class);
        while (count($files) > $max) {
            $filePath = array_shift($files);
            $filename = basename($filePath);
            if ($this->deleteIfExists($filePath)) {
                $repo->deleteByFilename($filename);
            }
        }
    }

    /**
     * Ritorna la lista dei file backup presenti con informazioni.
     *
     * @return array<array{filename: string, size: int, date: string, path: string}>
     */
    public function listBackups(): array
    {
        $files = $this->backupFiles();
        if ($files === []) {
            return [];
        }

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        $result = [];
        foreach ($files as $path) {
            $filename = basename($path);
            $result[] = [
                'filename' => $filename,
                'size'     => filesize($path),
                'date'     => date('Y-m-d H:i:s', filemtime($path)),
                'path'     => $path,
            ];
        }

        return $result;
    }

    /**
     * Storico backup dal database.
     */
    public function listHistory(int $limit = 50): array
    {
        $repo = app(BackupRepository::class);
        return $repo->listHistory($limit);
    }

    /**
     * Registra un backup nello storico DB.
     */
    public function recordHistory(
        string $filename,
        int $size,
        int $tableCount,
        ?int $createdBy,
        string $format = 'sqlgz',
        ?array $databases = null,
        ?array $files = null
    ): void {
        $repo = app(BackupRepository::class);
        $repo->record($filename, $size, $tableCount, $createdBy, $format, $databases, $files);
    }

    /**
     * Rimuove il record storico associato a un file backup.
     */
    public function deleteHistoryByFilename(string $filename): void
    {
        $repo = app(BackupRepository::class);
        $repo->deleteByFilename($filename);
    }

    /**
     * Elimina un file backup. Ritorna true se eliminato, false se non trovato.
     */
    public function deleteBackup(string $filename): bool
    {
        if (!$this->validateFilename($filename)) {
            return false;
        }
        $path = BASE_PATH . self::BACKUP_DIR . '/' . $filename;
        if (!file_exists($path)) {
            return false;
        }
        return $this->deleteIfExists($path);
    }

    /**
     * Valida che il filename rispetti il pattern atteso (prevenzione path traversal).
     */
    public function validateFilename(string $filename): bool
    {
        // Accetta sia i set multi-DB (.zip) sia i backup legacy single-DB (.sql.gz).
        return (bool) preg_match('/^backup_\d{8}_\d{6}\.(sql\.gz|zip)$/', $filename);
    }

    /**
     * Elenca i file di backup presenti (set .zip + legacy .sql.gz).
     *
     * @return string[]
     */
    private function backupFiles(): array
    {
        $dir = BASE_PATH . self::BACKUP_DIR;
        return array_merge(
            glob($dir . '/backup_*.zip') ?: [],
            glob($dir . '/backup_*.sql.gz') ?: []
        );
    }

    /**
     * Ritorna il path assoluto di un backup validato, null se non valido o non esistente.
     */
    public function getBackupPath(string $filename): ?string
    {
        if (!$this->validateFilename($filename)) {
            return null;
        }
        $path = BASE_PATH . self::BACKUP_DIR . '/' . $filename;
        return file_exists($path) ? $path : null;
    }

    /**
     * Verifica se un backup è attualmente in corso (lock attivo).
     */
    public function isBackupRunning(): bool
    {
        $lockPath = BASE_PATH . self::BACKUP_DIR . '/' . self::LOCK_FILE;
        if (!file_exists($lockPath)) {
            return false;
        }

        $fp = $this->callSilently(static fn () => fopen($lockPath, 'r'));
        if (!is_resource($fp)) {
            return false;
        }

        // Se riesco a ottenere il lock → nessun backup in corso
        $available = flock($fp, LOCK_EX | LOCK_NB);
        if ($available) {
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return !$available;
    }

    private function buildBackupLink(): string
    {
        try {
            return route('backup.index');
        } catch (\Throwable) {
            return rtrim((string) config('app.base_path', ''), '/') . '/backup';
        }
    }

    private function deleteIfExists(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        return (bool) $this->callSilently(static fn () => unlink($path));
    }

    private function callSilently(callable $callback): mixed
    {
        set_error_handler(static fn () => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    // ------------------------------------------------------------------
    // Encryption helpers
    // ------------------------------------------------------------------

    /**
     * Verifica se la cifratura dei backup è abilitata.
     */
    public function isEncryptionEnabled(): bool
    {
        return !empty(env('BACKUP_ENCRYPTION_KEY', ''));
    }

    /**
     * Legge il contenuto di un backup, decifrando automaticamente se necessario.
     * Delegato a BackupEncryptionService (la crypto at-rest vive lì).
     *
     * @throws \RuntimeException Se la decifratura fallisce
     */
    public function readBackupContents(string $path): string
    {
        return app(BackupEncryptionService::class)->readDecrypted($path);
    }

    /**
     * Ripristina un backup esistente.
     *
     * Branch sul formato: i set multi-DB (.zip) instradano ogni dump al database
     * corretto via manifest; i backup legacy (.sql.gz) ripristinano solo il DB
     * principale. Prima del ripristino crea un backup di sicurezza (multi-DB).
     *
     * @return array{pre_restore_backup:?string, statements_executed:int, databases_restored:string[]}
     */
    public function restoreBackup(string $filename, ?int $userId = null, bool $createSafetyBackup = true): array
    {
        $path = $this->getBackupPath($filename);
        if ($path === null) {
            throw new \RuntimeException('Backup non trovato.');
        }

        $preRestoreBackup = null;
        if ($createSafetyBackup) {
            $safety = $this->createBackup($userId);
            $preRestoreBackup = $safety['filename'] ?? null;
        }

        if ($this->isZipBackup($filename)) {
            $result = $this->restoreFromZip($path);
            return [
                'pre_restore_backup'  => $preRestoreBackup,
                'statements_executed' => $result['statements'],
                'databases_restored'  => $result['databases'],
                'files_restored'      => $result['files_restored'],
                'files_skipped'       => $result['files_skipped'],
            ];
        }

        // Legacy: singolo dump .sql.gz → solo database principale.
        $gzipBytes = $this->readBackupContents($path);
        $sql = gzdecode($gzipBytes);
        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('Contenuto backup non valido o corrotto.');
        }

        $statements = $this->splitSqlStatements($sql);
        if ($statements === []) {
            throw new \RuntimeException('Nessuna istruzione SQL valida trovata nel backup.');
        }

        $executed = $this->executeStatements(app(\PDO::class), $statements);

        return [
            'pre_restore_backup'  => $preRestoreBackup,
            'statements_executed' => $executed,
            'databases_restored'  => ['main'],
            'files_restored'      => 0,
            'files_skipped'       => 0,
        ];
    }

    /**
     * Ripristina un set di backup .zip: legge il manifest, instrada ogni dump
     * al database corretto (principale o modulo dedicato) verificando il
     * checksum, poi ripristina i file utente eventualmente inclusi (manifest
     * v2). I file vengono sovrascritti, mai eliminati: quelli caricati dopo il
     * backup restano su disco come orfani (gestibili dai cleanup dei moduli).
     *
     * @return array{statements:int, databases:string[], files_restored:int, files_skipped:int}
     */
    private function restoreFromZip(string $path): array
    {
        [$zipReadPath, $isTemp] = $this->materializeReadableZip($path);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipReadPath) !== true) {
                throw new \RuntimeException('Archivio di backup illeggibile o corrotto.');
            }

            $manifestRaw = $zip->getFromName('manifest.json');
            if ($manifestRaw === false) {
                $zip->close();
                throw new \RuntimeException('Manifest mancante nell\'archivio di backup.');
            }

            $manifest = json_decode($manifestRaw, true);
            if (!is_array($manifest) || empty($manifest['databases']) || !is_array($manifest['databases'])) {
                $zip->close();
                throw new \RuntimeException('Manifest di backup non valido.');
            }

            $totalStatements = 0;
            $restored        = [];

            foreach ($manifest['databases'] as $entry) {
                if (empty($entry['usable']) || empty($entry['sql_file'])) {
                    continue;
                }

                $gzBytes = $zip->getFromName($entry['sql_file']);
                if ($gzBytes === false) {
                    $zip->close();
                    throw new \RuntimeException("Dump {$entry['sql_file']} mancante nell'archivio.");
                }

                if (!empty($entry['sha256']) && hash('sha256', $gzBytes) !== $entry['sha256']) {
                    $zip->close();
                    throw new \RuntimeException("Checksum non valido per {$entry['sql_file']}: ripristino annullato.");
                }

                $sql = gzdecode($gzBytes);
                if ($sql === false || trim($sql) === '') {
                    $zip->close();
                    throw new \RuntimeException("Dump {$entry['sql_file']} vuoto o corrotto.");
                }

                $statements = $this->splitSqlStatements($sql);
                if ($statements === []) {
                    continue;
                }

                $targetPdo        = $this->resolveRestorePdo($entry);
                $totalStatements += $this->executeStatements($targetPdo, $statements);
                $restored[]       = (string) ($entry['key'] ?? $entry['database_name']);
            }

            if ($restored === []) {
                $zip->close();
                throw new \RuntimeException('Nessun database ripristinabile trovato nel set di backup.');
            }

            // Fase file: DOPO i database, così un errore sui dump non lascia
            // file già sovrascritti con un DB non ripristinato.
            $filesRestored = 0;
            $filesSkipped  = 0;
            $filesService  = app(BackupFilesService::class);
            if ($filesService->manifestHasFiles($manifest)) {
                $fileResult    = $filesService->restoreFromZip($zip);
                $filesRestored = $fileResult['restored'];
                $filesSkipped  = $fileResult['skipped'];
                foreach ($fileResult['warnings'] as $warning) {
                    app_log('warning', '[Backup] Ripristino file: ' . $warning);
                }
            }

            $zip->close();

            return [
                'statements'     => $totalStatements,
                'databases'      => $restored,
                'files_restored' => $filesRestored,
                'files_skipped'  => $filesSkipped,
            ];
        } finally {
            if ($isTemp) {
                $this->callSilently(static fn () => unlink($zipReadPath));
            }
        }
    }

    /**
     * Ritorna un percorso zip leggibile da ZipArchive: il file originale se in
     * chiaro (nessun caricamento in RAM, fondamentale per archivi grandi), o un
     * temporaneo decifrato se cifrato (dimensione già limitata dal cap di
     * cifratura in-memory). Il temporaneo va scritto DENTRO la dir dei backup:
     * sys_get_temp_dir() può violare open_basedir (es. XAMPP).
     *
     * @return array{0: string, 1: bool} [percorso, è un temporaneo da eliminare]
     */
    private function materializeReadableZip(string $path): array
    {
        if (!app(BackupEncryptionService::class)->isEncryptedFile($path)) {
            return [$path, false];
        }

        $zipBytes = $this->readBackupContents($path);
        $tmpZip   = BASE_PATH . self::BACKUP_DIR . '/.restore_' . bin2hex(random_bytes(6)) . '.zip';
        if (file_put_contents($tmpZip, $zipBytes) === false) {
            throw new \RuntimeException('Impossibile preparare l\'archivio per il ripristino.');
        }

        return [$tmpZip, true];
    }

    /**
     * Risolve il PDO di destinazione per una entry del manifest.
     * module=null → DB principale; altrimenti il DB dedicato del modulo (mai fallback).
     */
    private function resolveRestorePdo(array $entry): \PDO
    {
        if (empty($entry['module'])) {
            return app(\PDO::class);
        }

        $resolver = app(\App\Services\ModuleDatabaseResolver::class);
        if (!$resolver->isUsable($entry['module'])) {
            throw new \RuntimeException(
                "Database del modulo {$entry['module']} non raggiungibile: ripristino annullato "
                . 'per evitare di scrivere nel database sbagliato.'
            );
        }

        return $resolver->pdoFor($entry['module']);
    }

    /**
     * Esegue una lista di statement SQL su un PDO con i controlli FK disabilitati.
     *
     * @param string[] $statements
     * @return int Numero di statement eseguiti.
     */
    private function executeStatements(\PDO $pdo, array $statements): int
    {
        $executed = 0;
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($statements as $statement) {
                $pdo->exec($statement);
                $executed++;
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $executed;
    }

    /**
     * Split SQL dump in statement completi mantenendo integri i valori quotati.
     *
     * @return string[]
     */
    private function splitSqlStatements(string $sql): array
    {
        // IMPORTANTE: split solo su CR/LF reali, NON con \R: senza il flag /u,
        // \R matcha anche il byte 0x85 (NEL), che è un byte di continuazione UTF-8
        // valido (es. 4° byte dell'emoji 📅 = F0 9F 93 85). Usare \R spezzerebbe
        // dentro i caratteri multibyte corrompendo il dump in fase di ripristino.
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $normalized = [];
        $inBlockComment = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if ($inBlockComment) {
                if (str_contains($trimmed, '*/')) {
                    $inBlockComment = false;
                }
                continue;
            }

            if (str_starts_with($trimmed, '/*')) {
                if (!str_contains($trimmed, '*/')) {
                    $inBlockComment = true;
                }
                continue;
            }

            if ($trimmed === '' || str_starts_with($trimmed, '-- ') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $normalized[] = $line;
        }

        $content = implode("\n", $normalized);
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $escape = false;

        $len = strlen($content);
        for ($i = 0; $i < $len; $i++) {
            $ch = $content[$i];
            $buffer .= $ch;

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($ch === '\\') {
                $escape = true;
                continue;
            }

            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                continue;
            }

            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                continue;
            }

            if ($ch === ';' && !$inSingle && !$inDouble) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
