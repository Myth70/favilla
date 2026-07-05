<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Setup\DemoSeeder;
use PDO;

/**
 * Carica i dati dimostrativi "Aurora Studio" (utenti test + contenuti per i
 * moduli abilitati). Rifiuta il doppio caricamento salvo --force; i seed sono
 * comunque idempotenti (ID fissi + INSERT IGNORE).
 *
 * Usage:
 *   php favilla demo:seed
 *   php favilla demo:seed --force
 *   php favilla demo:seed --enable-modules   # abilita anche Progetti/Teams/Documenti/Blog
 */
class DemoSeedCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $force         = in_array('--force', $args, true);
        $enableModules = in_array('--enable-modules', $args, true);

        echo "\n=== Favilla — caricamento dati demo ===\n";
        if ($force) {
            echo "[--force] Guard di doppio caricamento ignorato.\n";
        }
        echo "\n";

        $seeder = new DemoSeeder(app(PDO::class), BASE_PATH);

        if ($enableModules) {
            $seeder->enableOptionalModules();
            echo "  [OK] Moduli opzionali abilitati (Progetti, Teams, Documenti, Blog)\n";
        }

        try {
            $summary = $seeder->run($force, static function (string $line): void {
                echo $line . "\n";
            });
        } catch (\RuntimeException $e) {
            echo '[ERRORE] ' . $e->getMessage() . "\n";
            exit(1);
        }

        $loaded  = count(array_filter($summary['sections'], static fn (string $s): bool => $s === 'ok'));
        $skipped = count($summary['sections']) - $loaded;

        echo "\n--- Riepilogo ---\n";
        echo "  Sezioni caricate: {$loaded}\n";
        echo "  Sezioni saltate:  {$skipped}" . ($skipped > 0 ? ' (moduli disabilitati: abilita e rilancia con --force)' : '') . "\n";
        echo "  Asset copiati:    {$summary['files_copied']}\n";
        echo "\nLogin dimostrativi (password = username, es. lucamarinelli/lucamarinelli):\n";
        echo "  vedi database/seeds/test_users.sql — SOLO per ambienti di valutazione.\n";
    }
}
