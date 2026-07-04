<?php

declare(strict_types=1);

namespace App\Cli\Commands;

class MakeMigrationCommand
{
    public function handle(array $args): void
    {
        $module = $args[0] ?? '';
        $name   = $args[1] ?? '';

        if ($module === '' || $name === '') {
            echo "\033[31m[ERR]\033[0m Uso: php favilla make:migration <Modulo> <nome>\n";
            echo "  Esempio: php favilla make:migration Clienti add_telefono_secondario\n";
            return;
        }

        $moduleDir = BASE_PATH . '/app/Modules/' . $module;
        if (!is_dir($moduleDir)) {
            echo "\033[31m[ERR]\033[0m Modulo \"{$module}\" non trovato in app/Modules/.\n";
            return;
        }

        $migDir = $moduleDir . '/migrations';
        if (!is_dir($migDir)) {
            mkdir($migDir, 0755, true);
        }

        $next     = $this->nextNumber($migDir);
        $filename = sprintf('%03d_%s.sql', $next, $this->sanitizeName($name));
        $path     = $migDir . '/' . $filename;

        $content = $this->template($module, $filename);
        file_put_contents($path, $content);

        echo "\033[32m[OK]\033[0m Migration creata:\n";
        echo "  app/Modules/{$module}/migrations/{$filename}\n\n";
        echo "Ricordati di:\n";
        echo "  1. Scrivere le istruzioni SQL nel file (usa IF NOT EXISTS / IF EXISTS)\n";
        echo "  2. Aggiornare \"version\" in app/Modules/{$module}/module.json\n";
        echo "  3. Eseguire: php database/migrate.php\n\n";
    }

    private function nextNumber(string $dir): int
    {
        $files = glob($dir . '/*.sql');
        if (empty($files)) {
            return 2; // 001 è riservato alla migration iniziale del make:module
        }
        $max = 0;
        foreach ($files as $f) {
            if (preg_match('/^(\d{3})_/', basename($f), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $max + 1;
    }

    private function sanitizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
    }

    private function template(string $module, string $filename): string
    {
        return <<<SQL
        -- ================================================================
        -- {$filename} — Modulo {$module}
        -- Aggiorna "version" in module.json dopo questo file.
        -- ================================================================

        -- USA SEMPRE IF NOT EXISTS / IF EXISTS per idempotenza.
        -- ALTER TABLE tabella ADD COLUMN IF NOT EXISTS colonna VARCHAR(255) NULL;
        -- CREATE TABLE IF NOT EXISTS nuova_tabella ( ... );
        -- INSERT IGNORE INTO permissions ...;

        SQL;
    }
}
