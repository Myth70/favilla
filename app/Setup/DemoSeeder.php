<?php

declare(strict_types=1);

namespace App\Setup;

use PDO;

/**
 * Carica i dati dimostrativi "Aurora Studio": cast internazionale + contenuti
 * per i moduli core (sezioni 05-59 di database/seeds/demo/) e, se i rispettivi
 * moduli risultano abilitati in module_states, per Progetti/Teams/Documenti/
 * Blog (sezioni 60-90). Copia inoltre gli asset fisici referenziati dalle righe
 * di files/documenti_files/teams_message_attachments/blog cover, più gli avatar
 * del cast (copia tollerante: le foto sono fornite dall'utente).
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
        '05_preferences.sql',
        '10_tasks.sql',
        '15_reports.sql',
        '20_calendar.sql',
        '30_contacts.sql',
        '40_files.sql',
        '50_notifications.sql',
        '55_feedback.sql',
        '58_system.sql',
        '59_integrations.sql',
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
        'brief-nordlys.txt'       => ['demo-brief-nordlys.txt'],
        'note-standup.txt'        => ['demo-note-standup.txt'],
    ];

    /**
     * Avatar del cast (id 3-12): sorgente => stored_name in public/uploads/avatars/.
     * Le sorgenti sono FORNITE dall'utente; la copia è TOLLERANTE ai file mancanti
     * (l'avatar_path resta valorizzato, l'immagine appare quando i file ci sono).
     */
    private const AVATARS_ASSETS = [
        'avatars/demo-avatar-giuliaferrari.jpg'  => ['demo-avatar-giuliaferrari.jpg'],
        'avatars/demo-avatar-jameswhitaker.jpg'  => ['demo-avatar-jameswhitaker.jpg'],
        'avatars/demo-avatar-annaweber.jpg'      => ['demo-avatar-annaweber.jpg'],
        'avatars/demo-avatar-marcobianchi.jpg'   => ['demo-avatar-marcobianchi.jpg'],
        'avatars/demo-avatar-emilyturner.jpg'    => ['demo-avatar-emilyturner.jpg'],
        'avatars/demo-avatar-julienmoreau.jpg'   => ['demo-avatar-julienmoreau.jpg'],
        'avatars/demo-avatar-camillelaurent.jpg' => ['demo-avatar-camillelaurent.jpg'],
        'avatars/demo-avatar-lukasbraun.jpg'     => ['demo-avatar-lukasbraun.jpg'],
        'avatars/demo-avatar-diegoherrera.jpg'   => ['demo-avatar-diegoherrera.jpg'],
        'avatars/demo-avatar-luciaromero.jpg'    => ['demo-avatar-luciaromero.jpg'],
    ];

    /** Asset chat Teams: sorgente => stored_name in public/uploads/teams/. */
    private const TEAMS_ASSETS = [
        'logo-aurora.png'        => ['demo-teams-logo.png'],
        'moodboard-campagna.png' => ['demo-teams-moodboard.png'],
        'guida-stile.pdf'        => ['demo-teams-brandbrief.pdf'],
    ];

    /** Cover del Blog: sorgente => stored_name in public/uploads/blog/. */
    private const BLOG_ASSETS = [
        'demo-blog-welcome.png'  => ['demo-blog-welcome.png'],
        'demo-blog-campaign.png' => ['demo-blog-campaign.png'],
        'demo-blog-offsite.png'  => ['demo-blog-offsite.png'],
    ];

    /**
     * DELETE per demo:reset — righe demo (id >= 9000) e child a chiave composita.
     * Eseguite con FOREIGN_KEY_CHECKS=0, quindi l'ordine non è vincolante.
     */
    private const RESET_DELETES = [
        // Tasks
        'DELETE FROM task_checklist WHERE id >= 9000',
        'DELETE FROM task_tag_map WHERE task_id >= 9000',
        'DELETE FROM tasks WHERE id >= 9000',
        'DELETE FROM task_tags WHERE id >= 9000',
        // Calendar / Contacts / Files / Notifications
        'DELETE FROM calendar_events WHERE id >= 9000',
        'DELETE FROM contact_shares WHERE id >= 9000',
        'DELETE FROM contact_recurrences WHERE id >= 9000',
        'DELETE FROM contacts WHERE id >= 9000',
        'DELETE FROM contact_categories WHERE id >= 9000',
        'DELETE FROM file_shares WHERE id >= 9000',
        'DELETE FROM file_versions WHERE id >= 9000',
        'DELETE FROM files WHERE id >= 9000',
        'DELETE FROM notifications WHERE id >= 9000',
        // Reports / Feedback / Preferenze
        'DELETE FROM report_history WHERE id >= 9000',
        'DELETE FROM report_templates WHERE id >= 9000',
        'DELETE FROM report_style_presets WHERE id >= 9000',
        'DELETE FROM feedback WHERE id >= 9000',
        'DELETE FROM user_notification_preferences WHERE id >= 9000',
        'DELETE FROM user_widget_preferences WHERE id >= 9000',
        'DELETE FROM user_preferences WHERE id >= 9000',
        // Sistema / Integrazioni
        'DELETE FROM healthcheck_runs WHERE id >= 9000',
        'DELETE FROM backup_history WHERE id >= 9000',
        'DELETE FROM scheduler_log WHERE id >= 9000',
        'DELETE FROM scheduler_jobs WHERE id >= 9000',
        'DELETE FROM webhook_deliveries WHERE id >= 9000',
        'DELETE FROM webhook_endpoints WHERE id >= 9000',
        'DELETE FROM personal_access_tokens WHERE id >= 9000',
        // Progetti
        'DELETE FROM project_timesheets WHERE id >= 9000',
        'DELETE FROM project_task_dependencies WHERE predecessor_task_id >= 9000 OR successor_task_id >= 9000',
        'DELETE FROM project_tasks WHERE id >= 9000',
        'DELETE FROM project_milestones WHERE id >= 9000',
        'DELETE FROM project_members WHERE project_id >= 9000',
        'DELETE FROM projects WHERE id >= 9000',
        // Teams
        'DELETE FROM teams_message_attachments WHERE id >= 9000',
        'DELETE FROM teams_message_edits WHERE id >= 9000',
        'DELETE FROM teams_message_reactions WHERE message_id >= 9000',
        'DELETE FROM teams_message_mentions WHERE message_id >= 9000',
        'DELETE FROM teams_messages WHERE id >= 9000',
        'DELETE FROM teams_conversation_members WHERE id >= 9000',
        'DELETE FROM teams_conversations WHERE id >= 9000',
        'DELETE FROM teams_user_presence WHERE user_id BETWEEN 1 AND 12',
        // Documenti
        'DELETE FROM documenti_collegamenti WHERE id >= 9000',
        'DELETE FROM documenti_approvazioni WHERE id >= 9000',
        'DELETE FROM documenti_versioni WHERE id >= 9000',
        'DELETE FROM documenti WHERE id >= 9000',
        'DELETE FROM documenti_files WHERE id >= 9000',
        'DELETE FROM documenti_categorie WHERE id >= 9000',
        'DELETE FROM documenti_protocollo_sequenze WHERE categoria_id >= 9000',
        // Blog
        'DELETE FROM blog_article_likes WHERE article_id >= 9000',
        'DELETE FROM blog_comments WHERE id >= 9000',
        'DELETE FROM blog_article_tags WHERE article_id >= 9000',
        'DELETE FROM blog_articles WHERE id >= 9000',
        'DELETE FROM blog_tags WHERE id >= 9000',
        'DELETE FROM blog_categories WHERE id >= 9000',
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

        // 3b. Avatar del cast (core, TOLLERANTE: le foto sono fornite dall'utente).
        $summary['files_copied'] += $this->copyAssets(
            self::AVATARS_ASSETS,
            $this->basePath . '/public/uploads/avatars',
            true
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
            } elseif ($module === 'Teams') {
                $summary['files_copied'] += $this->copyAssets(
                    self::TEAMS_ASSETS,
                    $this->basePath . '/public/uploads/teams'
                );
            } elseif ($module === 'Blog') {
                $summary['files_copied'] += $this->copyAssets(
                    self::BLOG_ASSETS,
                    $this->basePath . '/public/uploads/blog'
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

    /**
     * Ripulisce completamente i dati demo: righe con id >= 9000, utenti 3-12,
     * grant di permessi demo, asset copiati e guard. Idempotente.
     *
     * @return array{rows_deleted: int, users_removed: int, files_removed: int}
     */
    public function reset(?callable $log = null): array
    {
        $log ??= static function (string $line): void {
        };

        // 1. Rimuovi i grant di permessi demo (Progetti/Documenti a Manager/User).
        $demoGrantSlugs = [
            'progetti.view', 'progetti.create', 'progetti.edit', 'progetti.log_time',
            'progetti.view_all', 'progetti.export', 'progetti.manage_members',
            'documenti.access', 'documenti.view', 'documenti.create', 'documenti.redazione',
            'documenti.controllo', 'documenti.approvazione', 'documenti.inbox', 'documenti.export',
        ];
        $placeholders = implode(',', array_fill(0, count($demoGrantSlugs), '?'));
        $this->pdo->prepare(
            "DELETE rp FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id IN (2, 3) AND p.slug IN ({$placeholders})"
        )->execute($demoGrantSlugs);

        // 2. Cancella le righe demo (id >= 9000; child a chiave composita per FK).
        $rowsDeleted = 0;
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::RESET_DELETES as $sql) {
            $rowsDeleted += (int) $this->pdo->exec($sql);
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $log("  [OK] Righe demo rimosse: {$rowsDeleted}");

        // 3. Utenti demo 3-12 (FK ON: SET NULL/CASCADE puliscono i residui reali).
        $this->pdo->exec('DELETE FROM user_role WHERE user_id BETWEEN 3 AND 12');
        $usersRemoved = (int) $this->pdo->exec('DELETE FROM users WHERE id BETWEEN 3 AND 12');
        $log("  [OK] Utenti demo rimossi: {$usersRemoved}");

        // 4. Rimuovi gli asset copiati.
        $filesRemoved = $this->removeCopiedAssets();
        $log("  [OK] Asset rimossi: {$filesRemoved}");

        // 5. Rimuovi il guard di caricamento.
        $this->pdo->prepare('DELETE FROM app_settings WHERE `key` = ?')->execute([self::GUARD_KEY]);

        return ['rows_deleted' => $rowsDeleted, 'users_removed' => $usersRemoved, 'files_removed' => $filesRemoved];
    }

    /**
     * Rimuove i file copiati nelle directory di destinazione (best-effort).
     */
    private function removeCopiedAssets(): int
    {
        $maps = [
            [self::FILES_ASSETS,      $this->basePath . '/public/uploads/files'],
            [self::AVATARS_ASSETS,    $this->basePath . '/public/uploads/avatars'],
            [self::TEAMS_ASSETS,      $this->basePath . '/public/uploads/teams'],
            [self::BLOG_ASSETS,       $this->basePath . '/public/uploads/blog'],
            [self::DOCUMENTI_ASSETS,  $this->basePath . '/storage/uploads/documenti/demo'],
        ];

        $removed = 0;
        foreach ($maps as [$assets, $dir]) {
            foreach ($assets as $targets) {
                foreach ($targets as $storedName) {
                    $path = $dir . '/' . $storedName;
                    if (is_file($path) && @unlink($path)) {
                        $removed++;
                    }
                }
            }
        }

        return $removed;
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
     * @param bool $skipMissing se true, salta le sorgenti mancanti invece di
     *                          lanciare (usato per gli avatar forniti dall'utente).
     */
    private function copyAssets(array $assets, string $targetDir, bool $skipMissing = false): int
    {
        $sourceDir = $this->basePath . '/database/seeds/demo/files';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("Impossibile creare la directory: {$targetDir}");
        }

        $copied = 0;
        foreach ($assets as $source => $targets) {
            $sourcePath = $sourceDir . '/' . $source;
            if (!is_file($sourcePath)) {
                if ($skipMissing) {
                    continue;
                }
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
