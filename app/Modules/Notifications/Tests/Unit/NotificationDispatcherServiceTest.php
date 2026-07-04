<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Core\Container;
use App\Modules\Notifications\Services\EmailChannelDriver;
use App\Modules\Notifications\Services\NotificationChannelDriverInterface;
use App\Modules\Notifications\Services\NotificationDispatcherService;
use App\Modules\Notifications\Services\TelegramChannelDriver;
use Tests\ModuleTestCase;

class NotificationDispatcherServiceTest extends ModuleTestCase
{
    private NotificationDispatcherService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                avatar_path TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                title      TEXT NOT NULL,
                body       TEXT NULL,
                type       TEXT DEFAULT "info",
                icon       TEXT NULL,
                color      TEXT NULL,
                link       TEXT NULL,
                read_at    TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_channels (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                slug        TEXT NOT NULL UNIQUE,
                name        TEXT NOT NULL,
                description TEXT NULL,
                is_enabled  INTEGER NOT NULL DEFAULT 1,
                sort_order  INTEGER NOT NULL DEFAULT 10,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_event_types (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                slug          TEXT NOT NULL UNIQUE,
                module_slug   TEXT NOT NULL,
                name          TEXT NOT NULL,
                description   TEXT NULL,
                context_schema TEXT NULL,
                source        TEXT NULL,
                default_level TEXT NOT NULL DEFAULT "info",
                icon          TEXT NULL,
                color         TEXT NULL,
                is_system     INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at    TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_event_channel_bindings (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type_id    INTEGER NOT NULL,
                channel_slug     TEXT NOT NULL,
                is_enabled       INTEGER NOT NULL DEFAULT 1,
                subject_template TEXT NULL,
                body_template    TEXT NULL,
                layout_config    TEXT NULL,
                created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(event_type_id, channel_slug)
            );
            CREATE TABLE IF NOT EXISTS user_notification_preferences (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                module_slug  TEXT NOT NULL,
                event_slug   TEXT NOT NULL DEFAULT "",
                channel_slug TEXT NOT NULL,
                is_enabled   INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, module_slug, event_slug, channel_slug)
            );
            CREATE TABLE IF NOT EXISTS notification_dispatches (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                event_slug          TEXT NULL,
                source_module       TEXT NOT NULL,
                recipient_user_id   INTEGER NULL,
                recipient_role_slug TEXT NULL,
                title               TEXT NOT NULL,
                body                TEXT NULL,
                type                TEXT NOT NULL DEFAULT "info",
                link                TEXT NULL,
                icon                TEXT NULL,
                color               TEXT NULL,
                payload_json        TEXT NULL,
                created_by          INTEGER NULL,
                bypass_preferences  INTEGER NOT NULL DEFAULT 0,
                status              TEXT NOT NULL DEFAULT "pending",
                total_recipients    INTEGER NOT NULL DEFAULT 0,
                total_deliveries    INTEGER NOT NULL DEFAULT 0,
                created_at          TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_deliveries (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                dispatch_id         INTEGER NOT NULL,
                user_id             INTEGER NOT NULL,
                channel_slug        TEXT NOT NULL,
                status              TEXT NOT NULL DEFAULT "pending",
                subject             TEXT NULL,
                body                TEXT NULL,
                link                TEXT NULL,
                icon                TEXT NULL,
                color               TEXT NULL,
                provider_message_id TEXT NULL,
                error_message       TEXT NULL,
                attempts            INTEGER NOT NULL DEFAULT 0,
                sent_at             TEXT NULL,
                created_at          TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at          TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_queue (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                delivery_id  INTEGER NOT NULL,
                channel_slug TEXT NOT NULL,
                payload_json TEXT NULL,
                status       TEXT NOT NULL DEFAULT "pending",
                available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                locked_at    TEXT NULL,
                attempts     INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                last_error   TEXT NULL,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->insertRow('users', ['name' => 'Mario']);
        $this->insertRow('notification_channels', ['slug' => 'in_app', 'name' => 'In-App', 'is_enabled' => 1, 'sort_order' => 1]);
        $this->insertRow('notification_channels', ['slug' => 'email', 'name' => 'Email', 'is_enabled' => 1, 'sort_order' => 2]);
        $this->insertRow('notification_channels', ['slug' => 'telegram', 'name' => 'Telegram', 'is_enabled' => 1, 'sort_order' => 3]);

        $container = Container::getInstance();
        $container->instance(EmailChannelDriver::class, new class () implements NotificationChannelDriverInterface {
            public function channel(): string
            {
                return 'email';
            }
            public function send(array $job): array
            {
                return ['status' => 'skipped', 'provider_message_id' => null, 'error_message' => null];
            }
        });
        $container->instance(TelegramChannelDriver::class, new class () implements NotificationChannelDriverInterface {
            public function channel(): string
            {
                return 'telegram';
            }
            public function send(array $job): array
            {
                return ['status' => 'skipped', 'provider_message_id' => null, 'error_message' => null];
            }
        });

        $this->service = new NotificationDispatcherService();
    }

    public function testDispatchEventToUsersBackfillsDefaultTemplatesFromRegistry(): void
    {
        $result = $this->service->dispatchEventToUsers(
            'tasks.task_overdue',
            'Tasks',
            [1],
            [],
            [
                'title' => '',
                'body' => '',
                'type' => 'warning',
                'link' => '/tasks/9',
                'context' => [
                    'task_id' => 9,
                    'task_title' => 'Preventivo',
                    'due_date' => '2026-01-15',
                ],
            ],
            1,
            false
        );

        $bindingStmt = $this->pdo->prepare('SELECT channel_slug, subject_template, body_template FROM notification_event_channel_bindings b JOIN notification_event_types e ON e.id = b.event_type_id WHERE e.slug = ? ORDER BY channel_slug ASC');
        $bindingStmt->execute(['tasks.task_overdue']);
        $bindings = $bindingStmt->fetchAll();

        $notification = $this->pdo->query('SELECT title, body, type, link FROM notifications ORDER BY id DESC LIMIT 1')->fetch();

        $this->assertSame('sent', $this->pdo->query('SELECT status FROM notification_dispatches WHERE id = 1')->fetchColumn());
        $this->assertCount(3, $bindings);
        $this->assertSame('Attivita scaduta: {{task_title}}', $bindings[0]['subject_template']);
        $this->assertSame('Attivita scaduta: {{task_title}}', $bindings[1]['subject_template']);
        $this->assertSame('Attivita scaduta: Preventivo', $notification['title']);
        $this->assertSame("L'attivita Preventivo e scaduta il 2026-01-15", $notification['body']);
        $this->assertSame('/tasks/9', $notification['link']);
        $this->assertSame(1, count($result['legacy_notification_ids']));
    }
}
