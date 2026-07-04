<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use App\Modules\Notifications\Repositories\NotificationDeliveryRepository;
use App\Modules\Notifications\Repositories\NotificationDispatchRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;
use App\Modules\Notifications\Repositories\NotificationPreferenceRepository;
use App\Modules\Notifications\Repositories\NotificationQueueRepository;
use App\Modules\Notifications\Services\NotificationModuleCatalogService;
use Tests\ModuleTestCase;

/**
 * Test per i repository e servizi del dispatcher notifiche refactored (v1.7.0).
 *
 * Copre: NotificationChannelRepository, NotificationEventRepository,
 *        NotificationQueueRepository, NotificationDispatchRepository,
 *        NotificationDeliveryRepository, NotificationPreferenceRepository,
 *        NotificationModuleCatalogService (metodi statici).
 */
class NotificationDispatcherRepositoryTest extends ModuleTestCase
{
    private NotificationChannelRepository $channelRepo;
    private NotificationEventRepository $eventRepo;
    private NotificationDispatchRepository $dispatchRepo;
    private NotificationDeliveryRepository $deliveryRepo;
    private NotificationQueueRepository $queueRepo;
    private NotificationPreferenceRepository $prefRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUsersTable();
        $this->createDispatcherSchema();

        $this->channelRepo = new NotificationChannelRepository();
        $this->eventRepo = new NotificationEventRepository();
        $this->dispatchRepo = new NotificationDispatchRepository();
        $this->deliveryRepo = new NotificationDeliveryRepository();
        $this->queueRepo = new NotificationQueueRepository();
        $this->prefRepo = new NotificationPreferenceRepository();

        $this->seedChannels();
    }

    // =========================================================================
    // Schema + seed helpers
    // =========================================================================

    private function createDispatcherSchema(): void
    {
        $this->migrate('
            CREATE TABLE IF NOT EXISTS notification_channels (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                slug        TEXT NOT NULL UNIQUE,
                name        TEXT NOT NULL,
                description TEXT,
                is_enabled  INTEGER NOT NULL DEFAULT 1,
                sort_order  INTEGER NOT NULL DEFAULT 10,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->migrate("
            CREATE TABLE IF NOT EXISTS notification_event_types (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                slug          TEXT NOT NULL UNIQUE,
                module_slug   TEXT NOT NULL,
                name          TEXT NOT NULL,
                description   TEXT,
                default_level TEXT NOT NULL DEFAULT 'info',
                icon          TEXT,
                color         TEXT,
                is_system     INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at    TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->migrate('
            CREATE TABLE IF NOT EXISTS notification_event_channel_bindings (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type_id    INTEGER NOT NULL,
                channel_slug     TEXT NOT NULL,
                is_enabled       INTEGER NOT NULL DEFAULT 1,
                subject_template TEXT,
                body_template    TEXT,
                layout_config    TEXT,
                created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(event_type_id, channel_slug)
            )
        ');

        $this->migrate("
            CREATE TABLE IF NOT EXISTS user_notification_preferences (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                module_slug  TEXT NOT NULL,
                event_slug   TEXT NOT NULL DEFAULT '',
                channel_slug TEXT NOT NULL,
                is_enabled   INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, module_slug, event_slug, channel_slug)
            )
        ");

        $this->migrate("
            CREATE TABLE IF NOT EXISTS notification_dispatches (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                event_slug          TEXT,
                source_module       TEXT NOT NULL,
                recipient_user_id   INTEGER,
                recipient_role_slug TEXT,
                title               TEXT NOT NULL,
                body                TEXT,
                type                TEXT NOT NULL DEFAULT 'info',
                link                TEXT,
                icon                TEXT,
                color               TEXT,
                payload_json        TEXT,
                created_by          INTEGER,
                bypass_preferences  INTEGER NOT NULL DEFAULT 0,
                status              TEXT NOT NULL DEFAULT 'pending',
                total_recipients    INTEGER NOT NULL DEFAULT 0,
                total_deliveries    INTEGER NOT NULL DEFAULT 0,
                created_at          TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->migrate("
            CREATE TABLE IF NOT EXISTS notification_deliveries (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                dispatch_id         INTEGER NOT NULL,
                user_id             INTEGER NOT NULL,
                channel_slug        TEXT NOT NULL,
                status              TEXT NOT NULL DEFAULT 'pending',
                subject             TEXT,
                body                TEXT,
                link                TEXT,
                icon                TEXT,
                color               TEXT,
                provider_message_id TEXT,
                error_message       TEXT,
                attempts            INTEGER NOT NULL DEFAULT 0,
                sent_at             TEXT,
                created_at          TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at          TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->migrate("
            CREATE TABLE IF NOT EXISTS notification_queue (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                delivery_id  INTEGER NOT NULL,
                channel_slug TEXT NOT NULL,
                payload_json TEXT,
                status       TEXT NOT NULL DEFAULT 'pending',
                available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                locked_at    TEXT,
                attempts     INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                last_error   TEXT,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function seedChannels(): void
    {
        $this->insertRow('notification_channels', [
            'slug' => 'in_app', 'name' => 'In-App', 'is_enabled' => 1, 'sort_order' => 1,
        ]);
        $this->insertRow('notification_channels', [
            'slug' => 'email', 'name' => 'Email', 'is_enabled' => 1, 'sort_order' => 2,
        ]);
        $this->insertRow('notification_channels', [
            'slug' => 'telegram', 'name' => 'Telegram', 'is_enabled' => 1, 'sort_order' => 3,
        ]);
    }

    private function createDispatch(array $extra = []): int
    {
        return $this->insertRow('notification_dispatches', array_merge([
            'event_slug'    => 'test.event',
            'source_module' => 'Test',
            'title'         => 'Test dispatch',
            'type'          => 'info',
            'status'        => 'pending',
        ], $extra));
    }

    private function createDelivery(int $dispatchId, int $userId, string $channel = 'email', string $status = 'pending'): int
    {
        return $this->insertRow('notification_deliveries', [
            'dispatch_id'  => $dispatchId,
            'user_id'      => $userId,
            'channel_slug' => $channel,
            'status'       => $status,
        ]);
    }

    private function createQueueItem(int $deliveryId, string $channel = 'email', string $status = 'pending'): int
    {
        return $this->insertRow('notification_queue', [
            'delivery_id'  => $deliveryId,
            'channel_slug' => $channel,
            'status'       => $status,
            'available_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // NotificationChannelRepository
    // =========================================================================

    public function testGetAllOrderedReturnsChannelsSortedBySortOrder(): void
    {
        $channels = $this->channelRepo->getAllOrdered();

        $this->assertCount(3, $channels);
        $this->assertSame('in_app', $channels[0]['slug']);
        $this->assertSame('email', $channels[1]['slug']);
        $this->assertSame('telegram', $channels[2]['slug']);
    }

    public function testGetAllOrderedReturnsEmptyWhenNoChannels(): void
    {
        $this->pdo->exec('DELETE FROM notification_channels');
        $channels = $this->channelRepo->getAllOrdered();
        $this->assertSame([], $channels);
    }

    // =========================================================================
    // NotificationEventRepository
    // =========================================================================

    public function testGetExplicitEventCatalogExcludesLegacy(): void
    {
        // Legacy event (should be excluded)
        $this->insertRow('notification_event_types', [
            'slug'          => 'legacy.contatti.direct',
            'module_slug'   => 'contatti',
            'name'          => 'Legacy Contatti',
            'default_level' => 'info',
        ]);

        // Explicit events
        $this->insertRow('notification_event_types', [
            'slug'          => 'contacts.reminder_due',
            'module_slug'   => 'contatti',
            'name'          => 'Reminder scadenza',
            'default_level' => 'warning',
        ]);
        $this->insertRow('notification_event_types', [
            'slug'          => 'tasks.assigned',
            'module_slug'   => 'tasks',
            'name'          => 'Attività assegnata',
            'default_level' => 'info',
        ]);

        $catalog = $this->eventRepo->getExplicitEventCatalog();

        $this->assertArrayHasKey('contatti', $catalog);
        $this->assertArrayHasKey('tasks', $catalog);
        $this->assertCount(1, $catalog['contatti']);
        $this->assertCount(1, $catalog['tasks']);
        $this->assertSame('contacts.reminder_due', $catalog['contatti'][0]['slug']);
    }

    public function testUpsertChannelBindingCreatesNew(): void
    {
        $eventId = $this->insertRow('notification_event_types', [
            'slug' => 'test.event', 'module_slug' => 'test',
            'name' => 'Test Event', 'default_level' => 'info',
        ]);

        $this->eventRepo->upsertChannelBinding($eventId, 'email', true, 'Subj', 'Body', null);

        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_event_channel_bindings WHERE event_type_id = ? AND channel_slug = ?'
        );
        $stmt->execute([$eventId, 'email']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame(1, (int) $row['is_enabled']);
        $this->assertSame('Subj', $row['subject_template']);
        $this->assertSame('Body', $row['body_template']);
    }

    public function testUpsertChannelBindingUpdatesExisting(): void
    {
        $eventId = $this->insertRow('notification_event_types', [
            'slug' => 'test.event', 'module_slug' => 'test',
            'name' => 'Test Event', 'default_level' => 'info',
        ]);

        $this->eventRepo->upsertChannelBinding($eventId, 'email', true, 'Old Subject', 'Old Body', null);
        $this->eventRepo->upsertChannelBinding($eventId, 'email', false, 'New Subject', 'New Body', null);

        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_event_channel_bindings WHERE event_type_id = ? AND channel_slug = ?'
        );
        $stmt->execute([$eventId, 'email']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(0, (int) $row['is_enabled']);
        $this->assertSame('New Subject', $row['subject_template']);
    }

    public function testFindOrCreateLegacyEventCreatesAndReturns(): void
    {
        $event = $this->eventRepo->findOrCreateLegacyEvent('contatti', 'Contatti', 'info', 'fa-solid fa-address-book');

        $this->assertSame('legacy.contatti.direct', $event['slug']);
        $this->assertSame('contatti', $event['module_slug']);

        // Idempotent
        $again = $this->eventRepo->findOrCreateLegacyEvent('contatti', 'Contatti', 'info', 'fa-solid fa-address-book');
        $this->assertSame((int) $event['id'], (int) $again['id']);
    }

    // =========================================================================
    // NotificationDispatchRepository
    // =========================================================================

    public function testGetStatusCountsReturnsCorrectCounts(): void
    {
        $this->createDispatch(['status' => 'sent']);
        $this->createDispatch(['status' => 'sent']);
        $this->createDispatch(['status' => 'failed']);
        $this->createDispatch(['status' => 'pending']);

        $counts = $this->dispatchRepo->getStatusCounts();

        $this->assertSame(2, $counts['sent']);
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(1, $counts['pending']);
    }

    public function testGetStatusCountsReturnsEmptyWhenNone(): void
    {
        $counts = $this->dispatchRepo->getStatusCounts();
        $this->assertSame([], $counts);
    }

    public function testGetPayloadPlaceholderHintsExtractsKeys(): void
    {
        $this->createDispatch([
            'event_slug'   => 'contacts.reminder_due',
            'payload_json' => json_encode([
                'contact_name' => 'Mario',
                'company'      => 'ACME',
                'nested'       => ['sub_key' => 'val'],
            ]),
        ]);
        $this->createDispatch([
            'event_slug'   => 'tasks.assigned',
            'payload_json' => json_encode([
                'task_title' => 'Riunione',
                'assignee'   => 'Luigi',
            ]),
        ]);

        $hints = $this->dispatchRepo->getPayloadPlaceholderHints();

        $this->assertSame(2, $hints['sampled_dispatches']);
        $this->assertContains('contact_name', $hints['global']);
        $this->assertContains('company', $hints['global']);
        $this->assertContains('task_title', $hints['global']);
        $this->assertContains('nested', $hints['global']);
        $this->assertContains('nested.sub_key', $hints['global']);

        $this->assertArrayHasKey('contacts.reminder_due', $hints['per_event']);
        $this->assertContains('contact_name', $hints['per_event']['contacts.reminder_due']);
        $this->assertNotContains('task_title', $hints['per_event']['contacts.reminder_due']);
    }

    public function testGetPayloadPlaceholderHintsIgnoresEmptyPayloads(): void
    {
        $this->createDispatch(['payload_json' => null]);
        $this->createDispatch(['payload_json' => '']);
        $this->createDispatch(['payload_json' => '{}']);

        $hints = $this->dispatchRepo->getPayloadPlaceholderHints();
        $this->assertSame(0, $hints['sampled_dispatches']);
        $this->assertSame([], $hints['global']);
    }

    // =========================================================================
    // NotificationDeliveryRepository
    // =========================================================================

    public function testGetStatusCountsByChannelForDeliveries(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();

        $this->createDelivery($dId, 1, 'email', 'sent');
        $this->createDelivery($dId, 1, 'email', 'sent');
        $this->createDelivery($dId, 1, 'email', 'failed');
        $this->createDelivery($dId, 1, 'telegram', 'sent');

        $counts = $this->deliveryRepo->getStatusCountsByChannel();

        $this->assertArrayHasKey('email', $counts);
        $this->assertSame(2, $counts['email']['sent']);
        $this->assertSame(1, $counts['email']['failed']);
        $this->assertArrayHasKey('telegram', $counts);
        $this->assertSame(1, $counts['telegram']['sent']);
    }

    public function testResetToPendingClearsErrorAndStatus(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $deliveryId = $this->createDelivery($dId, 1, 'email', 'failed');

        $this->pdo->prepare('UPDATE notification_deliveries SET error_message = ? WHERE id = ?')
            ->execute(['Timeout', $deliveryId]);

        $this->deliveryRepo->resetToPending($deliveryId);

        $row = $this->deliveryRepo->find($deliveryId);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['error_message']);
    }

    // =========================================================================
    // NotificationQueueRepository
    // =========================================================================

    public function testGetStatusCountsByChannelForQueue(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $delId1 = $this->createDelivery($dId, 1, 'email', 'queued');
        $delId2 = $this->createDelivery($dId, 1, 'email', 'queued');
        $delId3 = $this->createDelivery($dId, 1, 'telegram', 'queued');

        $this->createQueueItem($delId1, 'email', 'sent');
        $this->createQueueItem($delId2, 'email', 'failed');
        $this->createQueueItem($delId3, 'telegram', 'pending');

        $counts = $this->queueRepo->getStatusCountsByChannel();

        $this->assertSame(1, $counts['email']['sent']);
        $this->assertSame(1, $counts['email']['failed']);
        $this->assertSame(1, $counts['telegram']['pending']);
    }

    public function testResetToRetryChangesFailedToPending(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $delId = $this->createDelivery($dId, 1, 'email', 'queued');
        $qId = $this->createQueueItem($delId, 'email', 'failed');

        $this->pdo->prepare('UPDATE notification_queue SET last_error = ? WHERE id = ?')
            ->execute(['Connection refused', $qId]);

        $result = $this->queueRepo->resetToRetry($qId);

        $this->assertTrue($result);

        $row = $this->queueRepo->find($qId);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['last_error']);
        $this->assertNull($row['locked_at']);
    }

    public function testResetToRetryReturnsFalseForNonFailed(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $delId = $this->createDelivery($dId, 1, 'email', 'queued');
        $qId = $this->createQueueItem($delId, 'email', 'sent');

        $result = $this->queueRepo->resetToRetry($qId);
        $this->assertFalse($result);
    }

    public function testResetAllFailedToRetryResetsMultiple(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $del1 = $this->createDelivery($dId, 1, 'email', 'queued');
        $del2 = $this->createDelivery($dId, 1, 'telegram', 'queued');
        $del3 = $this->createDelivery($dId, 1, 'email', 'queued');

        $this->createQueueItem($del1, 'email', 'failed');
        $this->createQueueItem($del2, 'telegram', 'failed');
        $this->createQueueItem($del3, 'email', 'sent');

        $count = $this->queueRepo->resetAllFailedToRetry();

        $this->assertSame(2, $count);
    }

    public function testResetAllFailedToRetryReturnsZeroWhenNoneFailed(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $delId = $this->createDelivery($dId, 1, 'email', 'queued');
        $this->createQueueItem($delId, 'email', 'sent');

        $count = $this->queueRepo->resetAllFailedToRetry();
        $this->assertSame(0, $count);
    }

    public function testGetDeliveryIdReturnsCorrectId(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);
        $dId = $this->createDispatch();
        $delId = $this->createDelivery($dId, 1, 'email');
        $qId = $this->createQueueItem($delId, 'email');

        $result = $this->queueRepo->getDeliveryId($qId);
        $this->assertSame($delId, $result);
    }

    public function testGetDeliveryIdReturnsNullForInvalidId(): void
    {
        $result = $this->queueRepo->getDeliveryId(99999);
        $this->assertNull($result);
    }

    // =========================================================================
    // NotificationPreferenceRepository
    // =========================================================================

    public function testGetModulePreferenceMapReturnsCorrectStructure(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);

        $this->prefRepo->upsertPreference(1, 'contatti', '', 'email', true);
        $this->prefRepo->upsertPreference(1, 'contatti', '', 'telegram', false);
        $this->prefRepo->upsertPreference(1, 'tasks', '', 'email', false);

        $map = $this->prefRepo->getModulePreferenceMap(1);

        $this->assertArrayHasKey('contatti', $map);
        $this->assertTrue($map['contatti']['email']);
        $this->assertFalse($map['contatti']['telegram']);
        $this->assertArrayHasKey('tasks', $map);
        $this->assertFalse($map['tasks']['email']);
    }

    public function testGetModulePreferenceMapReturnsEmptyForNewUser(): void
    {
        $map = $this->prefRepo->getModulePreferenceMap(999);
        $this->assertSame([], $map);
    }

    public function testGetEventPreferenceMapReturnsCorrectStructure(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);

        $this->prefRepo->upsertPreference(1, 'contatti', 'contacts.reminder_due', 'email', true);
        $this->prefRepo->upsertPreference(1, 'contatti', 'contacts.reminder_due', 'telegram', false);

        // Module-level pref (should NOT appear in event map)
        $this->prefRepo->upsertPreference(1, 'contatti', '', 'email', true);

        $map = $this->prefRepo->getEventPreferenceMap(1);

        $this->assertArrayHasKey('contatti', $map);
        $this->assertArrayHasKey('contacts.reminder_due', $map['contatti']);
        $this->assertTrue($map['contatti']['contacts.reminder_due']['email']);
        $this->assertFalse($map['contatti']['contacts.reminder_due']['telegram']);
    }

    public function testUpsertPreferenceIsIdempotent(): void
    {
        $this->insertRow('users', ['id' => 1, 'name' => 'Test User']);

        $this->prefRepo->upsertPreference(1, 'contatti', '', 'email', true);
        $this->prefRepo->upsertPreference(1, 'contatti', '', 'email', false);

        $map = $this->prefRepo->getModulePreferenceMap(1);
        $this->assertFalse($map['contatti']['email']);

        // Should be exactly 1 row, not 2
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM user_notification_preferences');
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // =========================================================================
    // NotificationModuleCatalogService — metodi statici
    // =========================================================================

    public function testModuleNameToSlugConvertsCorrectly(): void
    {
        $this->assertSame('health_check', NotificationModuleCatalogService::moduleNameToSlug('HealthCheck'));
        $this->assertSame('contatti', NotificationModuleCatalogService::moduleNameToSlug('Contatti'));
        $this->assertSame('tasks', NotificationModuleCatalogService::moduleNameToSlug('Tasks'));
        $this->assertSame('home', NotificationModuleCatalogService::moduleNameToSlug('Home'));
    }

    public function testModuleNameToSlugHandlesMultipleCamelCaseWords(): void
    {
        $this->assertSame('my_long_module', NotificationModuleCatalogService::moduleNameToSlug('MyLongModule'));
    }
}
