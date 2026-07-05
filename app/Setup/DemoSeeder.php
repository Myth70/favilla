<?php

declare(strict_types=1);

namespace App\Setup;

use PDO;

/**
 * Carica i dati dimostrativi "Aurora Studio": utenti test + contenuti per i
 * moduli core (sezioni 10-50 di database/seeds/demo/) e, se i rispettivi
 * moduli risultano abilitati in module_states, per Progetti/Teams/Documenti/
 * Blog (sezioni 60-90). Copia inoltre gli asset fisici referenziati dalle
 * righe di files e documenti_files.
 *
 * Idempotente: i seed usano ID fissi + INSERT IGNORE; un guard su
 * app_settings.demo_data_loaded evita ricarichi accidentali (override con
 * $force). Invocato dal comando CLI demo:seed, dal setup wizard e
 * dall'entrypoint Docker (DEMO_DATA=true).
 */
final class DemoSeeder
{
    private const GUARD_KEY = 'demo_data_loaded';

    /** Sezioni core: caricate sempre. */
    private const CORE_SECTIONS = [
        '10_tasks.sql',
        '20_calendar.sql',
        '30_contacts.sql',
        '40_files.sql',
        '50_notifications.sql',
    ];

    /** Sezioni opzionali: caricate solo se il modulo è abilitato. */
    private const MODULE_SECTIONS = [
        '60_progetti.sql'  => 'Progetti',
        '70_teams.sql'     => 'Teams',
        '80_documenti.sql' => 'Documenti',
        '90_blog.sql'      => 'Blog',
    ];

    /** Asset del modulo Files: sorgente => stored_name in public/uploads/files/. */
    private const FILES_ASSETS = [
        'contratto-rossetti.pdf'  => ['demo-contratto-rossetti.pdf'],
        'logo-aurora.png'         => ['demo-logo-aurora.png'],
        'listino-2026.csv'        => ['demo-listino-2026.csv'],
        'verbale-kickoff.txt'     => ['demo-verbale-kickoff.txt'],
        'moodboard-campagna.png'  => ['demo-moodboard-campagna.png'],
        'guida-stile.pdf'         => ['demo-guida-stile.pdf', 'demo-guida-stile-v1.pdf'],
    ];

    /** Asset del modulo Documenti: sorgente => stored_name in storage/uploads/documenti/demo/. */
    private const DOCUMENTI_ASSETS = [
        'procedura-ferie.pdf'   => ['demo-doc-procedura-ferie.pdf'],
        'contratto-quadro.pdf'  => ['demo-doc-contratto-quadro.pdf'],
        'politica-backup.pdf'   => ['demo-doc-politica-backup.pdf'],
        'offerta-ecommerce.pdf' => ['demo-doc-offerta-ecommerce.pdf'],
        'verbale-cda.pdf'       => ['demo-doc-verbale-cda.pdf'],
        'guida-stile.pdf'       => ['demo-doc-manuale-brand.pdf'],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $basePath,
    ) {
    }

    /**
     * Abilita i moduli opzionali coperti dalle sezioni demo (usato dal boot
     * Docker hands-off, dove nessun wizard li ha abilitati).
     */
    public function enableOptionalModules(): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_states SET enabled = 1 WHERE name = ?');
        foreach (array_values(self::MODULE_SECTIONS) as $module) {
            $stmt->execute([$module]);
        }
    }

    public function alreadyLoaded(): bool
    {
        $stmt = $this->pdo->prepare('SELECT `value` FROM app_settings WHERE `key` = ?');
        $stmt->execute([self::GUARD_KEY]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array{sections: array<string,string>, files_copied: int}
     */
    public function run(bool $force = false, ?callable $log = null): array
    {
        $log ??= static function (string $line): void {
        };

        if (!$force && $this->alreadyLoaded()) {
            throw new \RuntimeException(
                'I dati demo risultano già caricati (app_settings.' . self::GUARD_KEY . '). Usa --force per ricaricarli.'
            );
        }

        $seedDir = $this->basePath . '/database/seeds';
        $demoDir = $seedDir . '/demo';
        if (!is_dir($demoDir)) {
            throw new \RuntimeException("Directory dei seed demo non trovata: {$demoDir}");
        }

        $summary = ['sections' => [], 'files_copied' => 0];

        // 1. Utenti test (cast dei contenuti demo): ID fissi 3-12, INSERT IGNORE.
        $this->executeSqlFile($seedDir . '/test_users.sql');
        $summary['sections']['test_users.sql'] = 'ok';
        $log('  [OK] test_users.sql (utenti 3-12)');

        // 2. Sezioni core.
        foreach (self::CORE_SECTIONS as $section) {
            $this->executeSqlFile($demoDir . '/' . $section);
            $summary['sections'][$section] = 'ok';
            $log("  [OK] {$section}");
        }

        // 3. Asset fisici del modulo Files (core).
        $summary['files_copied'] += $this->copyAssets(
            self::FILES_ASSETS,
            $this->basePath . '/public/uploads/files'
        );

        // 4. Sezioni dei moduli opzionali, solo se abilitati.
        foreach (self::MODULE_SECTIONS as $section => $module) {
            if (!$this->isModuleEnabled($module)) {
                $summary['sections'][$section] = 'saltata (modulo disabilitato)';
                $log("  [SALTATA] {$section} — modulo {$module} disabilitato");
                continue;
            }
            $this->executeSqlFile($demoDir . '/' . $section);
            $summary['sections'][$section] = 'ok';
            $log("  [OK] {$section}");

            if ($module === 'Documenti') {
                $summary['files_copied'] += $this->copyAssets(
                    self::DOCUMENTI_ASSETS,
                    $this->basePath . '/storage/uploads/documenti/demo'
                );
            }
        }

        // 5. Guard: registra il caricamento.
        $this->pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`, `type`, `group`, `label`)
             VALUES (?, NOW(), \'string\', \'system\', \'Dati demo caricati il\')
             ON DUPLICATE KEY UPDATE `value` = NOW()'
        )->execute([self::GUARD_KEY]);

        $log('  [OK] Asset copiati: ' . $summary['files_copied']);

        return $summary;
    }

    private function isModuleEnabled(string $module): bool
    {
        $stmt = $this->pdo->prepare('SELECT enabled FROM module_states WHERE name = ?');
        $stmt->execute([$module]);
        $enabled = $stmt->fetchColumn();

        return $enabled !== false && (int) $enabled === 1;
    }

    private function executeSqlFile(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException("File seed non trovato: {$path}");
        }

        $sql = (string) file_get_contents($path);
        foreach ($this->splitSqlStatements($sql) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    /**
     * @param array<string, list<string>> $assets sorgente => stored_name di destinazione
     */
    private function copyAssets(array $assets, string $targetDir): int
    {
        $sourceDir = $this->basePath . '/database/seeds/demo/files';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("Impossibile creare la directory: {$targetDir}");
        }

        $copied = 0;
        foreach ($assets as $source => $targets) {
            $sourcePath = $sourceDir . '/' . $source;
            if (!is_file($sourcePath)) {
                throw new \RuntimeException("Asset demo mancante: {$sourcePath}");
            }
            foreach ($targets as $storedName) {
                $targetPath = $targetDir . '/' . $storedName;
                if (!is_file($targetPath)) {
                    if (!copy($sourcePath, $targetPath)) {
                        throw new \RuntimeException("Copia fallita: {$sourcePath} → {$targetPath}");
                    }
                    $copied++;
                }
            }
        }

        return $copied;
    }

    /**
     * Split di statement SQL: stessa logica di database/migrate.php
     * (commenti riga/blocco, stringhe quotate con escape, delimitatore ;).
     *
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $len        = strlen($sql);
        $i          = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($ch === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                $i   = ($end === false) ? $len : $end + 1;
                continue;
            }

            if ($ch === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = ($end === false) ? $len : $end + 2;
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $quote   = $ch;
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    $current .= $c;
                    if ($c === '\\') {
                        $i++;
                        if ($i < $len) {
                            $current .= $sql[$i];
                        }
                    } elseif ($c === $quote) {
                        if (isset($sql[$i + 1]) && $sql[$i + 1] === $quote) {
                            $i++;
                            $current .= $sql[$i];
                        } else {
                            break;
                        }
                    }
                    $i++;
                }
                $i++;
                continue;
            }

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
}
