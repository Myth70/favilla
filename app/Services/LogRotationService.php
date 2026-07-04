<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ISO 27001 A.12.4 — Log rotation & cleanup.
 *
 * Rotates the application log file and purges old rotated files
 * beyond the configured retention period. Since we cannot modify
 * `app/Core/ErrorHandler.php` (framework file), rotation is handled
 * externally by renaming the active log file and letting Monolog
 * recreate it on the next write.
 */
class LogRotationService
{
    /** Default retention in days. */
    private const DEFAULT_RETENTION_DAYS = 365;

    /** Active log file name. */
    private const LOG_FILENAME = 'app.log';

    private string $logDir;
    private int $retentionDays;

    public function __construct()
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

        $configured = trim((string) env('LOG_PATH', ''));

        if ($configured !== '') {
            // Considera assoluto solo se ha lettera di drive (Windows: C:\...) o è root Unix (/)
            $isAbsolute = (strlen($configured) >= 2 && $configured[1] === ':')   // C:\...
                       || ($configured[0] === '/' && strlen($configured) >= 2 && $configured[1] === '/'); // UNC o root unix reale
            if ($isAbsolute) {
                $logPath = $configured;
            } else {
                // Path relativo tipo "/storage/logs" → BASE_PATH/storage/logs
                $logPath = $basePath . DIRECTORY_SEPARATOR . ltrim($configured, '/\\');
            }
        } else {
            $logPath = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }

        $this->logDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logPath), DIRECTORY_SEPARATOR);
        $this->retentionDays = (int) setting('log_retention_days', self::DEFAULT_RETENTION_DAYS);
    }

    /**
     * Rotate the current log file.
     *
     * Renames `app.log` → `app-YYYY-MM-DD.log` (with dedup suffix if needed).
     * Generates an HMAC integrity digest for the rotated file.
     *
     * @return array{rotated: bool, file: ?string, size: int, hash: ?string, error: ?string}
     */
    public function rotate(): array
    {
        $activePath = $this->logDir . DIRECTORY_SEPARATOR . self::LOG_FILENAME;

        if (!file_exists($activePath) || filesize($activePath) === 0) {
            return ['rotated' => false, 'file' => null, 'size' => 0, 'hash' => null, 'error' => null];
        }

        $date = date('Y-m-d');
        $rotatedName = 'app-' . $date . '.log';
        $rotatedPath = $this->logDir . DIRECTORY_SEPARATOR . $rotatedName;

        // Handle multiple rotations on the same day
        $counter = 1;
        while (file_exists($rotatedPath)) {
            $counter++;
            $rotatedName = 'app-' . $date . '-' . $counter . '.log';
            $rotatedPath = $this->logDir . DIRECTORY_SEPARATOR . $rotatedName;
        }

        $size = filesize($activePath);

        // Compute HMAC before renaming (tamper-evidence)
        $hmac = $this->computeHmac($activePath);

        if (!rename($activePath, $rotatedPath)) {
            return ['rotated' => false, 'file' => null, 'size' => 0, 'hash' => null, 'error' => 'Impossibile rinominare il file di log.'];
        }

        // Write the integrity digest alongside the rotated file
        if ($hmac) {
            file_put_contents($rotatedPath . '.sha256', $hmac . '  ' . $rotatedName . "\n");
        }

        return ['rotated' => true, 'file' => $rotatedName, 'size' => $size, 'hash' => $hmac, 'error' => null];
    }

    /**
     * Purge rotated log files older than retention period.
     *
     * @return array{deleted: int, freed: int, errors: string[]}
     */
    public function purge(): array
    {
        $cutoff = time() - ($this->retentionDays * 86400);
        $deleted = 0;
        $freed = 0;
        $errors = [];

        $pattern = $this->logDir . DIRECTORY_SEPARATOR . 'app-*.log';
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                $size = filesize($file);
                if (@unlink($file)) {
                    $deleted++;
                    $freed += $size;

                    // Remove companion hash file
                    $hashFile = $file . '.sha256';
                    if (file_exists($hashFile)) {
                        @unlink($hashFile);
                    }
                } else {
                    $errors[] = 'Impossibile eliminare: ' . basename($file);
                }
            }
        }

        return ['deleted' => $deleted, 'freed' => $freed, 'errors' => $errors];
    }

    /**
     * Verify integrity of a rotated log file using its companion hash.
     *
     * @return array{valid: bool, file: string, error: ?string}
     */
    public function verify(string $filename): array
    {
        $filePath = $this->logDir . DIRECTORY_SEPARATOR . basename($filename);
        $hashPath = $filePath . '.sha256';

        if (!file_exists($filePath)) {
            return ['valid' => false, 'file' => $filename, 'error' => 'File non trovato.'];
        }
        if (!file_exists($hashPath)) {
            return ['valid' => false, 'file' => $filename, 'error' => 'File di verifica HMAC non trovato.'];
        }

        $storedLine = trim(file_get_contents($hashPath));
        $storedHmac = explode('  ', $storedLine, 2)[0] ?? '';

        $currentHmac = $this->computeHmac($filePath);

        if (!$currentHmac) {
            return ['valid' => false, 'file' => $filename, 'error' => 'Impossibile calcolare HMAC (APP_KEY non configurata).'];
        }

        return [
            'valid' => hash_equals($storedHmac, $currentHmac),
            'file'  => $filename,
            'error' => null,
        ];
    }

    /**
     * Verify all rotated log files in the log directory.
     *
     * @return array{total: int, valid: int, invalid: int, missing_hash: int, results: array}
     */
    public function verifyAll(): array
    {
        $results = [];
        $valid = 0;
        $invalid = 0;
        $missingHash = 0;

        $pattern = $this->logDir . DIRECTORY_SEPARATOR . 'app-*.log';
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            $filename = basename($file);
            $result = $this->verify($filename);
            $results[] = $result;

            if ($result['valid']) {
                $valid++;
            } elseif ($result['error'] && str_contains($result['error'], 'HMAC non trovato')) {
                $missingHash++;
            } else {
                $invalid++;
            }
        }

        return [
            'total'        => count($files),
            'valid'        => $valid,
            'invalid'      => $invalid,
            'missing_hash' => $missingHash,
            'results'      => $results,
        ];
    }

    /**
     * Get status information about log files.
     *
     * @return array{active_size: int, rotated_count: int, rotated_size: int, oldest: ?string, newest: ?string}
     */
    public function getStatus(): array
    {
        $activePath = $this->logDir . DIRECTORY_SEPARATOR . self::LOG_FILENAME;
        $activeSize = file_exists($activePath) ? filesize($activePath) : 0;

        $pattern = $this->logDir . DIRECTORY_SEPARATOR . 'app-*.log';
        $files = glob($pattern) ?: [];

        $rotatedSize = 0;
        $oldest = null;
        $newest = null;

        foreach ($files as $file) {
            $rotatedSize += filesize($file);
            $name = basename($file);
            if ($oldest === null || $name < $oldest) {
                $oldest = $name;
            }
            if ($newest === null || $name > $newest) {
                $newest = $name;
            }
        }

        return [
            'active_size'    => $activeSize,
            'rotated_count'  => count($files),
            'rotated_size'   => $rotatedSize,
            'retention_days' => $this->retentionDays,
            'oldest'         => $oldest,
            'newest'         => $newest,
        ];
    }

    /**
     * Compute HMAC-SHA256 of a file using APP_KEY.
     */
    private function computeHmac(string $filePath): ?string
    {
        $appKey = env('APP_KEY', '');
        if (empty($appKey)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        return hash_hmac('sha256', $content, $appKey);
    }

    /**
     * Human-readable file size.
     */
    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $exp = min(floor(log($bytes, 1024)), count($units));
        return round($bytes / (1024 ** $exp), 1) . ' ' . $units[$exp - 1];
    }
}
