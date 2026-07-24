<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Setup\DemoSeeder;
use PDO;

/**
 * Ripulisce completamente i dati dimostrativi "Aurora Studio": righe con
 * id >= 9000, utenti demo (3-12), grant di permessi demo, asset copiati e il
 * guard di caricamento. Dopo il reset, `demo:seed` può ricaricare da zero.
 *
 * Usage:
 *   php favilla demo:reset            # chiede conferma
 *   php favilla demo:reset --yes      # senza conferma (script/CI)
 */
class DemoResetCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $assumeYes = in_array('--yes', $args, true) || in_array('-y', $args, true);

        echo "\n=== Favilla — reset dati demo ===\n";
        echo "Rimuove le righe demo (id >= 9000), gli utenti 3-12, i grant demo e gli asset copiati.\n";

        if (!$assumeYes) {
            echo "\nProcedere? Digita 'si' per confermare: ";
            $answer = trim((string) fgets(STDIN));
            if (!in_array(mb_strtolower($answer), ['si', 'sì', 'y', 'yes'], true)) {
                echo "Annullato.\n";
                return;
            }
        }

        $seeder = new DemoSeeder(app(PDO::class), BASE_PATH);

        try {
            $summary = $seeder->reset(static function (string $line): void {
                echo $line . "\n";
            });
        } catch (\Throwable $e) {
            echo '[ERRORE] ' . $e->getMessage() . "\n";
            exit(1);
        }

        echo "\n--- Riepilogo ---\n";
        echo "  Righe rimosse:   {$summary['rows_deleted']}\n";
        echo "  Utenti rimossi:  {$summary['users_removed']}\n";
        echo "  Asset rimossi:   {$summary['files_removed']}\n";
        echo "\nRicarica con: php favilla demo:seed --enable-modules\n";
    }
}
