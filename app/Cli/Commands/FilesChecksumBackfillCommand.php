<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;

class FilesChecksumBackfillCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $dryRun = in_array('--dry-run', $args, true);

        echo '=== Backfill Checksum File (ISO 27001 A.12.2) ===' . PHP_EOL;
        if ($dryRun) {
            echo '[DRY-RUN] Nessuna modifica verrà salvata.' . PHP_EOL;
        }
        echo PHP_EOL;

        $pdo       = app(\PDO::class);
        $uploadDir = defined('BASE_PATH')
            ? BASE_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'files'
            : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'files';

        $uploadDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDir);

        $stmt = $pdo->query(
            'SELECT id, stored_name, original_name FROM files
              WHERE checksum_sha256 IS NULL
                AND deleted_at IS NULL
              ORDER BY id ASC'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total     = count($rows);
        $updated   = 0;
        $skipped   = 0;
        $notFound  = 0;

        echo "File senza checksum trovati: {$total}" . PHP_EOL . PHP_EOL;

        if ($total === 0) {
            echo '[OK] Tutti i file hanno già il checksum. Nessuna azione necessaria.' . PHP_EOL;
            return;
        }

        foreach ($rows as $row) {
            $physicalPath = $uploadDir . DIRECTORY_SEPARATOR . basename($row['stored_name']);

            if (!file_exists($physicalPath)) {
                echo "  [MISSING] #{$row['id']} {$row['original_name']} → file fisico non trovato" . PHP_EOL;
                $notFound++;
                continue;
            }

            $checksum = hash_file('sha256', $physicalPath);

            if ($checksum === false) {
                echo "  [ERROR]   #{$row['id']} {$row['original_name']} → errore nel calcolo checksum" . PHP_EOL;
                $skipped++;
                continue;
            }

            if (!$dryRun) {
                $upd = $pdo->prepare(
                    'UPDATE files SET checksum_sha256 = ? WHERE id = ?'
                );
                $upd->execute([$checksum, $row['id']]);
            }

            echo "  [OK]      #{$row['id']} {$row['original_name']} → " . substr($checksum, 0, 16) . '...' . PHP_EOL;
            $updated++;
        }

        echo PHP_EOL;
        echo '--- Riepilogo ---' . PHP_EOL;
        echo "  Aggiornati:     {$updated}" . PHP_EOL;
        echo "  Non trovati:    {$notFound}" . PHP_EOL;
        echo "  Saltati/errori: {$skipped}" . PHP_EOL;

        if ($dryRun) {
            echo PHP_EOL . '[DRY-RUN] Nessuna modifica salvata. Rilanciare senza --dry-run per applicare.' . PHP_EOL;
        }
    }
}
