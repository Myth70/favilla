<?php

namespace Tests\Unit\Services;

use App\Repositories\MailLogRepository;
use App\Repositories\MailTemplateRepository;
use App\Repositories\SettingsRepository;
use App\Services\MailerService;
use App\Services\MailService;
use App\Services\SettingsService;
use Tests\ModuleTestCase;

class MailServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();

        $this->migrate("
            CREATE TABLE app_settings (
                key        TEXT NOT NULL PRIMARY KEY,
                value      TEXT DEFAULT NULL,
                type       TEXT NOT NULL DEFAULT 'string',
                'group'    TEXT NOT NULL DEFAULT 'general',
                label      TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ");

        $this->migrate("
            CREATE TABLE mail_templates (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                slug       TEXT NOT NULL UNIQUE,
                name       TEXT NOT NULL,
                subject    TEXT NOT NULL,
                body_html  TEXT NOT NULL,
                variables  TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->migrate("
            CREATE TABLE mail_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                to_email   TEXT NOT NULL,
                subject    TEXT NOT NULL,
                template   TEXT DEFAULT NULL,
                status     TEXT NOT NULL DEFAULT 'logged',
                error      TEXT DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");

        // Insert test settings
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES
            ('mail_driver', 'log', 'string', 'mail'),
            ('app_name', 'TestApp', 'string', 'general')
        ");

        // Insert test template
        $this->pdo->exec("INSERT INTO mail_templates (slug, name, subject, body_html, variables) VALUES
            ('test-tpl', 'Test Template', 'Hello {{name}}', '<p>Hi {{name}}, {{message}}</p>', '{{name}},{{message}}')
        ");

        // Register services in container
        $container = app();
        $container->instance(SettingsRepository::class, new SettingsRepository());
        $container->instance(MailTemplateRepository::class, new MailTemplateRepository());
        $container->instance(MailLogRepository::class, new MailLogRepository());
        $container->instance(MailerService::class, new MailerService());
        $container->instance(MailService::class, new MailService());

        SettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    public function test_send_logs_to_mail_log(): void
    {
        $service = app(MailService::class);
        $result = $service->send('test@example.com', 'Test Subject', '<p>Body</p>');

        $this->assertTrue($result);

        // Check mail_log entry
        $stmt = $this->pdo->query('SELECT * FROM mail_log ORDER BY id DESC LIMIT 1');
        $log = $stmt->fetch();

        $this->assertNotNull($log);
        $this->assertEquals('test@example.com', $log['to_email']);
        $this->assertEquals('Test Subject', $log['subject']);
        $this->assertEquals('logged', $log['status']); // driver=log → status=logged
    }

    public function test_send_from_template_replaces_variables(): void
    {
        $service = app(MailService::class);
        $result = $service->sendFromTemplate('user@test.com', 'test-tpl', [
            'name'    => 'Mario',
            'message' => 'Benvenuto!',
        ]);

        $this->assertTrue($result);

        // Check the log entry has the rendered subject
        $stmt = $this->pdo->query('SELECT * FROM mail_log ORDER BY id DESC LIMIT 1');
        $log = $stmt->fetch();

        $this->assertNotNull($log);
        $this->assertEquals('user@test.com', $log['to_email']);
        $this->assertEquals('Hello Mario', $log['subject']);
        $this->assertEquals('test-tpl', $log['template']);
    }

    public function test_send_from_template_returns_false_for_missing_template(): void
    {
        $service = app(MailService::class);
        $result = $service->sendFromTemplate('user@test.com', 'nonexistent-template', []);

        $this->assertFalse($result);
    }

    public function test_send_test_returns_bool(): void
    {
        $service = app(MailService::class);
        $result = $service->sendTest('admin@test.com');

        $this->assertTrue($result);

        // Verify it was logged
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM mail_log WHERE to_email = 'admin@test.com'");
        $count = (int) $stmt->fetchColumn();
        $this->assertEquals(1, $count);
    }
}
