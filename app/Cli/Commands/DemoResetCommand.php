<?php

declare(strict_types=1);

namespace App\Cli\Commands;

/**
 * Riporta un'istanza DEMO allo stato iniziale: svuota gli upload, ricrea il
 * database da zero (migrate --fresh) e ricarica il dataset demo "Aurora
 * Studio". Pensato per il loop orario dell'istanza demo pubblica
 * (docker-compose.demo.yml / docs/demo-instance.md).
 *
 * DISTRUTTIVO PER COSTRUZIONE: cancella TUTTI i dati. Per questo si rifiuta
 * di girare se l'ambiente non dichiara esplicitamente DEMO_MODE=true — la
 * guardia protegge le installazioni reali da un'esecuzione accidentale
 * (cron copiato per errore, scheduler mal configurato, comando sbagliato).
 *
 * Uso: php favilla demo:reset
 */
class DemoResetCommand
{
    /** Radici upload da svuotare (relative a BASE_PATH); i dotfile restano. */
    private const UPLOAD_ROOTS = ['public/uploads', 'storage/uploads'];

    public function handle(array $args): void
    {
        if (!filter_var((string) getenv('DEMO_MODE'), FILTER_VALIDATE_BOOL)) {
            echo "demo:reset RIFIUTATO: questo comando cancella TUTTI i dati e va usato\n";
            echo "solo su istanze demo. Imposta DEMO_MODE=true nell'ambiente per abilitarlo.\n";
            return;
        }

        echo "\n=== Favilla — reset istanza demo ===\n";

        echo "\n[1/3] Svuoto gli upload...\n";
        foreach (self::UPLOAD_ROOTS as $root) {
            $removed = $this->wipeDirectoryContents(BASE_PATH . '/' . $root);
            echo "  {$root}: {$removed} elementi rimossi\n";
        }

        echo "\n[2/3] Ricreo il database (migrate --fresh)...\n";
        if (!$this->runSubcommand([BASE_PATH . '/database/migrate.php', '--fresh'])) {
            echo "\n[ERRORE] migrate --fresh fallito: reset interrotto.\n";
            return;
        }

        echo "\n[3/3] Carico il dataset demo...\n";
        if (!$this->runSubcommand([BASE_PATH . '/favilla', 'demo:seed', '--enable-modules'])) {
            echo "\n[ERRORE] demo:seed fallito: il database è fresco ma senza dati demo.\n";
            return;
        }

        echo "\nReset demo completato.\n";
    }

    /**
     * Rimuove il CONTENUTO di una directory preservando la directory stessa e
     * i dotfile (.gitkeep, .htaccess — servono a struttura e hardening).
     * Ritorna il numero di elementi rimossi.
     */
    private function wipeDirectoryContents(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            if (str_starts_with($item->getFilename(), '.')) {
                continue;
            }
            if ($item->isDir() && !$item->isLink()) {
                // Le directory con dotfile dentro non sono vuote: rmdir fallisce
                // in silenzio ed è il comportamento voluto (le preserviamo).
                if (@rmdir($item->getPathname())) {
                    $removed++;
                }
                continue;
            }
            if (@unlink($item->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Esegue uno script PHP figlio col binario corrente, in foreground.
     * Ritorna true se esce con codice 0.
     *
     * @param string[] $scriptAndArgs Path script + argomenti
     */
    private function runSubcommand(array $scriptAndArgs): bool
    {
        $cmd = escapeshellarg(PHP_BINARY);
        foreach ($scriptAndArgs as $part) {
            $cmd .= ' ' . escapeshellarg($part);
        }

        passthru($cmd, $exitCode);
        return $exitCode === 0;
    }
}
