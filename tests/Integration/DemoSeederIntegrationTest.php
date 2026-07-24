<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Setup\DemoSeeder;
use PDO;

/**
 * Verifica che il seed demo "Aurora Studio" carichi su MariaDB reale senza
 * violazioni FK, con protocolli documenti coerenti con le sequenze e con il
 * guard anti doppio caricamento. Copre anche lo skip delle sezioni dei moduli
 * disabilitati (default post-required.sql).
 */
final class DemoSeederIntegrationTest extends DatabaseIntegrationTestCase
{
    private function loadRequiredSeed(): void
    {
        // Ruoli/permessi/admin/moduli: prerequisiti del seed demo. INSERT
        // IGNORE ovunque → sicuro dentro la transazione di ogni test.
        $sql = (string) file_get_contents(BASE_PATH . '/database/seeds/required.sql');
        self::$pdo->exec($sql);
    }

    private function seeder(): DemoSeeder
    {
        return new DemoSeeder(self::$pdo, BASE_PATH);
    }

    public function testFullRunWithModulesEnabledLoadsEverySectionWithoutOrphans(): void
    {
        $this->loadRequiredSeed();

        $seeder = $this->seeder();
        $seeder->enableOptionalModules();
        $summary = $seeder->run();

        foreach ($summary['sections'] as $section => $result) {
            $this->assertSame('ok', $result, "Sezione {$section} non caricata: {$result}");
        }

        // Contenuti presenti nei moduli principali.
        foreach (['tasks', 'calendar_events', 'contacts', 'files', 'notifications', 'projects', 'teams_messages', 'documenti', 'blog_articles'] as $table) {
            $count = (int) self::$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $this->assertGreaterThan(0, $count, "Tabella {$table} vuota dopo il seed demo");
        }

        // Nessun orfano FK nei punti delicati.
        $orphanChecks = [
            'project_tasks → projects'          => 'SELECT COUNT(*) FROM project_tasks pt LEFT JOIN projects p ON p.id = pt.project_id WHERE p.id IS NULL',
            'documenti_versioni → documenti_files' => 'SELECT COUNT(*) FROM documenti_versioni v LEFT JOIN documenti_files f ON f.id = v.file_id WHERE f.id IS NULL',
            'tasks → users'                     => 'SELECT COUNT(*) FROM tasks t LEFT JOIN users u ON u.id = t.user_id WHERE u.id IS NULL',
            'teams_messages → conversations'    => 'SELECT COUNT(*) FROM teams_messages m LEFT JOIN teams_conversations c ON c.id = m.conversation_id WHERE c.id IS NULL',
        ];
        foreach ($orphanChecks as $label => $query) {
            $this->assertSame(0, (int) self::$pdo->query($query)->fetchColumn(), "Orfani FK: {$label}");
        }

        // Ogni documento punta a una versione corrente e i protocolli emessi
        // sono coerenti con le sequenze per categoria/anno.
        $this->assertSame(
            0,
            (int) self::$pdo->query('SELECT COUNT(*) FROM documenti WHERE versione_corrente_id IS NULL')->fetchColumn()
        );
        $seq = self::$pdo->query(
            'SELECT s.categoria_id, s.ultimo_numero,
                    (SELECT COUNT(*) FROM documenti d WHERE d.categoria_id = s.categoria_id AND d.protocollo IS NOT NULL) AS emessi
             FROM documenti_protocollo_sequenze s'
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($seq);
        foreach ($seq as $row) {
            $this->assertSame(
                (int) $row['emessi'],
                (int) $row['ultimo_numero'],
                "Sequenza protocollo categoria {$row['categoria_id']} non allineata ai protocolli emessi"
            );
        }

        // Guard registrato.
        $this->assertTrue($seeder->alreadyLoaded());
    }

    public function testSecondRunRefusesWithoutForceAndSectionsForDisabledModulesAreSkipped(): void
    {
        $this->loadRequiredSeed();

        // required.sql lascia i moduli opzionali disabilitati: le sezioni 60-90
        // devono essere saltate.
        $summary = $this->seeder()->run();
        foreach (['60_progetti.sql', '70_teams.sql', '80_documenti.sql', '90_blog.sql'] as $section) {
            $this->assertStringContainsString('saltata', $summary['sections'][$section]);
        }
        $this->assertSame(0, (int) self::$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn());

        // Secondo run senza force: rifiuto.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/già caricati/');
        $this->seeder()->run();
    }

    public function testForceReloadIsIdempotent(): void
    {
        $this->loadRequiredSeed();

        $seeder = $this->seeder();
        $seeder->enableOptionalModules();
        $seeder->run();

        $before = (int) self::$pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        $seeder->run(force: true);
        $after = (int) self::$pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();

        $this->assertSame($before, $after, 'Il ricaricamento --force ha duplicato le righe');
    }

    public function testExtendedSectionsLoadWithIntegrityAndUtf8(): void
    {
        $this->loadRequiredSeed();
        $seeder = $this->seeder();
        $seeder->enableOptionalModules();
        $seeder->run();

        // Sezioni/moduli nuovi popolati.
        foreach ([
            'user_preferences', 'user_widget_preferences', 'report_templates', 'report_history',
            'feedback', 'user_notification_preferences', 'healthcheck_runs', 'backup_history',
            'scheduler_jobs', 'webhook_endpoints', 'personal_access_tokens',
        ] as $table) {
            $count = (int) self::$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $this->assertGreaterThan(0, $count, "Tabella {$table} vuota dopo il seed demo");
        }

        // avatar_path valorizzato per tutti e 10 gli utenti del cast.
        $withAvatar = (int) self::$pdo->query(
            'SELECT COUNT(*) FROM users WHERE id BETWEEN 3 AND 12 AND avatar_path IS NOT NULL'
        )->fetchColumn();
        $this->assertSame(10, $withAvatar);

        // Nessun orfano FK nelle nuove relazioni delicate.
        $orphanChecks = [
            'teams_message_attachments → messages' => 'SELECT COUNT(*) FROM teams_message_attachments a LEFT JOIN teams_messages m ON m.id = a.message_id WHERE m.id IS NULL',
            'teams_message_mentions → messages'    => 'SELECT COUNT(*) FROM teams_message_mentions x LEFT JOIN teams_messages m ON m.id = x.message_id WHERE m.id IS NULL',
            'teams_message_reactions → users'      => 'SELECT COUNT(*) FROM teams_message_reactions r LEFT JOIN users u ON u.id = r.user_id WHERE u.id IS NULL',
            'user_widget_preferences → users'      => 'SELECT COUNT(*) FROM user_widget_preferences w LEFT JOIN users u ON u.id = w.user_id WHERE u.id IS NULL',
        ];
        foreach ($orphanChecks as $label => $query) {
            $this->assertSame(0, (int) self::$pdo->query($query)->fetchColumn(), "Orfani FK: {$label}");
        }

        // Grant demo: il ruolo Manager (2) ha i permessi Progetti e Documenti.
        foreach (['progetti.view', 'documenti.controllo'] as $slug) {
            $has = (int) self::$pdo->query(
                "SELECT COUNT(*) FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = 2 AND p.slug = '{$slug}'"
            )->fetchColumn();
            $this->assertSame(1, $has, "Manager privo del permesso demo {$slug}");
        }

        // utf8mb4 round-trip: accenti (DE) ed emoji (ES) preservati.
        $de = (string) self::$pdo->query('SELECT body FROM teams_messages WHERE id = 9026')->fetchColumn();
        $this->assertStringContainsString('läuft', $de);
        $es = (string) self::$pdo->query('SELECT body FROM teams_messages WHERE id = 9029')->fetchColumn();
        $this->assertStringContainsString('🍀', $es);

        // Coerenza size_bytes seedati vs file reali degli asset.
        $assetDir = BASE_PATH . '/database/seeds/demo/files';
        $fileSize = (int) self::$pdo->query('SELECT size_bytes FROM files WHERE id = 9001')->fetchColumn();
        $this->assertSame((int) filesize($assetDir . '/contratto-rossetti.pdf'), $fileSize);
        $attSize = (int) self::$pdo->query('SELECT size_bytes FROM teams_message_attachments WHERE id = 9002')->fetchColumn();
        $this->assertSame((int) filesize($assetDir . '/moodboard-campagna.png'), $attSize);
    }

    public function testResetRemovesAllDemoData(): void
    {
        $this->loadRequiredSeed();
        $seeder = $this->seeder();
        $seeder->enableOptionalModules();
        $seeder->run();

        $seeder->reset();

        // Utenti demo rimossi.
        $this->assertSame(
            0,
            (int) self::$pdo->query('SELECT COUNT(*) FROM users WHERE id BETWEEN 3 AND 12')->fetchColumn()
        );

        // Righe demo (id >= 9000) rimosse dalle tabelle principali.
        foreach (['tasks', 'calendar_events', 'contacts', 'files', 'projects', 'teams_messages', 'documenti', 'blog_articles', 'report_templates', 'feedback'] as $table) {
            $this->assertSame(
                0,
                (int) self::$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE id >= 9000")->fetchColumn(),
                "Righe demo residue in {$table}"
            );
        }

        // Guard e grant demo rimossi.
        $this->assertFalse($seeder->alreadyLoaded());
        $this->assertSame(
            0,
            (int) self::$pdo->query(
                "SELECT COUNT(*) FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = 2 AND p.slug = 'progetti.view'"
            )->fetchColumn()
        );
    }
}
