<?php

declare(strict_types=1);

namespace App\Cli\Commands;

/**
 * Ripristina la demo a uno stato PRISTINE (per un'istanza dedicata alla demo,
 * es. reset orario da cron/scheduler). Esegue, come sottoprocessi:
 *   1) database/migrate.php --fresh   (DROP ALL → schema.sql → required.sql)
 *   2) svuotamento delle directory di upload (contenuti caricati dai visitatori)
 *   3) favilla demo:seed --enable-modules
 *
 * A differenza di demo:reset (che solo cancella i dati demo), questo riporta
 * l'INTERO database allo stato iniziale, rimuovendo anche utenti registrati,
 * modifiche all'admin e file caricati.
 *
 * ⚠️  DISTRUTTIVO: droppa tutte le tabelle. Usare SOLO su istanze dedicate
 *     alla demo, mai dove risiedono dati reali.
 *
 * Usage:
 *   php favilla demo:refresh --yes                 # non interattivo (cron)
 *   php favilla demo:refresh --yes --keep-uploads  # non svuota public/uploads
 */
class DemoRefreshCommand
{
    /** Directory di upload svuotate (contenuti runtime/visitatori). */
    private const UPLOAD_DIRS = [
        'public/uploads/files',
        'public/uploads/avatars',
        'public/uploads/teams',
        'public/uploads/blog',
        'storage/uploads/documenti',
    ];

    public function handle(array $args): void
    {
        $assumeYes   = in_array('--yes', $args, true) || in_array('-y', $args, true);
        $keepUploads = in_array('--keep-uploads', $args, true);

        echo "\n=== Favilla — refresh completo demo (PRISTINE) ===\n";
        echo "DROP di tutte le tabelle → schema + seed → dati demo.\n";
        echo "⚠️  Distruttivo: solo su istanze dedicate alla demo.\n";

        if (!$assumeYes) {
            echo "\nProcedere? Digita 'si' per confermare: ";
            $answer = trim((string) fgets(STDIN));
            if (!in_array(mb_strtolower($answer), ['si', 'sì', 'y', 'yes'], true)) {
                echo "Annullato.\n";
                return;
            }
        }

        $php     = escapeshellarg(PHP_BINARY);
        $migrate = escapeshellarg(BASE_PATH . '/database/migrate.php');
        $favilla = escapeshellarg(BASE_PATH . '/favilla');

        // 1. Fresh install (schema + required.sql).
        echo "\n[1/3] migrate.php --fresh…\n";
        $code = $this->runProcess("{$php} {$migrate} --fresh");
        if ($code !== 0) {
            echo "[ERRORE] migrate --fresh fallito (exit {$code}).\n";
            exit(1);
        }

        // 2. Svuota gli upload dei visitatori (opzionale).
        if (!$keepUploads) {
            echo "\n[2/3] Svuotamento directory di upload…\n";
            $removed = 0;
            foreach (self::UPLOAD_DIRS as $rel) {
                $removed += $this->emptyDir(BASE_PATH . '/' . $rel);
            }
            echo "  [OK] File rimossi: {$removed}\n";
        } else {
            echo "\n[2/3] Upload conservati (--keep-uploads).\n";
        }

        // 3. Ricarica i dati demo (moduli opzionali inclusi).
        echo "\n[3/3] demo:seed --enable-modules…\n";
        $code = $this->runProcess("{$php} {$favilla} demo:seed --enable-modules");
        if ($code !== 0) {
            echo "[ERRORE] demo:seed fallito (exit {$code}).\n";
            exit(1);
        }

        echo "\n[OK] Demo ripristinata allo stato iniziale.\n";
    }

    /**
     * Esegue un sottoprocesso ereditando l'ambiente corrente e streammando
     * l'output su stdout. Usa proc_open (preferito) o exec come fallback, così
     * non dipende da passthru/system (spesso in disable_functions).
     *
     * @return int exit code del processo (o -1 se nessun runner disponibile)
     */
    private function runProcess(string $cmd): int
    {
        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stdout', 'w'],
            ];
            $proc = proc_open($cmd, $descriptors, $pipes);
            if (is_resource($proc)) {
                return proc_close($proc);
            }
        }

        if (function_exists('exec')) {
            $output = [];
            $code   = 0;
            exec($cmd . ' 2>&1', $output, $code);
            echo implode("\n", $output) . "\n";
            return $code;
        }

        echo "[ERRORE] Nessuna funzione di esecuzione disponibile (proc_open/exec disabilitate).\n";
        return -1;
    }

    /**
     * Svuota una directory (file e sottocartelle) mantenendo la directory stessa.
     * Best-effort: ignora ciò che non riesce a rimuovere.
     */
    private function emptyDir(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } elseif (@unlink($item->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }
}
