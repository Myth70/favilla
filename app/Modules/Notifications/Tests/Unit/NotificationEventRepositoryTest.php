<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationEventRepository;
use Tests\ModuleTestCase;

class NotificationEventRepositoryTest extends ModuleTestCase
{
    private NotificationEventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE notification_event_types (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                slug           TEXT NOT NULL UNIQUE,
                module_slug    TEXT NOT NULL,
                name           TEXT NOT NULL,
                description    TEXT NULL,
                context_schema TEXT NULL,
                source         TEXT NOT NULL DEFAULT "dynamic",
                default_level  TEXT NOT NULL DEFAULT "info",
                icon           TEXT NULL,
                color          TEXT NULL,
                is_system      INTEGER NOT NULL DEFAULT 0,
                created_at     TEXT NULL,
                updated_at     TEXT NULL
            );
            CREATE TABLE notification_event_channel_bindings (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type_id    INTEGER NOT NULL,
                channel_slug     TEXT NOT NULL,
                is_enabled       INTEGER NOT NULL DEFAULT 1,
                subject_template TEXT NULL,
                body_template    TEXT NULL,
                layout_config    TEXT NULL,
                created_at       TEXT NULL,
                updated_at       TEXT NULL,
                UNIQUE (event_type_id, channel_slug)
            );
            CREATE TABLE notification_channels (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                slug       TEXT NOT NULL UNIQUE,
                name       TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 10
            );
        ');
        $this->repo = new NotificationEventRepository();
    }

    private function countBindings(int $eventId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notification_event_channel_bindings WHERE event_type_id = ?');
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    public function testFindOrCreateEventCreatesAndSeedsDefaultBindings(): void
    {
        $event = $this->repo->findOrCreateEvent('contacts.birthday', 'contacts', 'Compleanno', 'desc');

        $this->assertSame('contacts.birthday', $event['slug']);
        // ensureDefaultBindings crea i 3 canali di default.
        $this->assertSame(3, $this->countBindings((int) $event['id']));
    }

    public function testFindOrCreateEventIsIdempotentOnSlug(): void
    {
        $a = $this->repo->findOrCreateEvent('tasks.due', 'tasks', 'Scadenza', null);
        $b = $this->repo->findOrCreateEvent('tasks.due', 'tasks', 'Scadenza aggiornata', null);

        $this->assertSame((int) $a['id'], (int) $b['id'], 'Stesso slug → stesso record');
        $this->assertSame('Scadenza aggiornata', $b['name']);
        $this->assertSame(3, $this->countBindings((int) $b['id']));
    }

    public function testFindBySlugReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->repo->findBySlug('nope'));
    }

    public function testGetAllEventsOrderedSortsByModuleThenName(): void
    {
        $this->repo->findOrCreateEvent('tasks.due', 'tasks', 'Scadenza', null);
        $this->repo->findOrCreateEvent('contacts.b', 'contacts', 'Beta', null);
        $this->repo->findOrCreateEvent('contacts.a', 'contacts', 'Alfa', null);

        $events = $this->repo->getAllEventsOrdered();
        $this->assertSame(['Alfa', 'Beta', 'Scadenza'], array_column($events, 'name'));
    }

    public function testEnsureChannelBindingIsIdempotent(): void
    {
        $event = $this->repo->findOrCreateEvent('x.y', 'x', 'XY', null);
        $before = $this->countBindings((int) $event['id']);

        // Canale già seminato dai default → non aggiunge.
        $this->repo->ensureChannelBinding((int) $event['id'], 'email', true, null, null, null);
        $this->assertSame($before, $this->countBindings((int) $event['id']));
    }

    public function testUpsertChannelBindingInsertsThenUpdates(): void
    {
        $event = $this->repo->findOrCreateEvent('x.y', 'x', 'XY', null);
        $eventId = (int) $event['id'];

        $this->repo->upsertChannelBinding($eventId, 'email', true, 'Oggetto', 'Corpo', null);
        $stmt = $this->pdo->prepare('SELECT is_enabled, subject_template FROM notification_event_channel_bindings WHERE event_type_id = ? AND channel_slug = ?');
        $stmt->execute([$eventId, 'email']);
        $row = $stmt->fetch();
        $this->assertSame(1, (int) $row['is_enabled']);
        $this->assertSame('Oggetto', $row['subject_template']);

        $this->repo->upsertChannelBinding($eventId, 'email', false, 'Nuovo', 'Corpo2', null);
        $stmt->execute([$eventId, 'email']);
        $row = $stmt->fetch();
        $this->assertSame(0, (int) $row['is_enabled']);
        $this->assertSame('Nuovo', $row['subject_template']);
    }

    public function testGetExplicitEventCatalogExcludesLegacyAndGroups(): void
    {
        $this->repo->findOrCreateEvent('contacts.birthday', 'contacts', 'Compleanno', null);
        $this->repo->findOrCreateLegacyEvent('oldmod', 'Old Module');

        $catalog = $this->repo->getExplicitEventCatalog();

        $this->assertArrayHasKey('contacts', $catalog);
        $this->assertArrayNotHasKey('oldmod', $catalog, 'Gli eventi legacy.* sono esclusi dal catalogo esplicito');
    }

    public function testGetChannelBindingsForEventJoinsChannels(): void
    {
        $this->insertRow('notification_channels', ['slug' => 'in_app', 'name' => 'In-App', 'is_enabled' => 1, 'sort_order' => 10]);
        $this->insertRow('notification_channels', ['slug' => 'email', 'name' => 'Email', 'is_enabled' => 0, 'sort_order' => 20]);
        $this->insertRow('notification_channels', ['slug' => 'telegram', 'name' => 'Telegram', 'is_enabled' => 1, 'sort_order' => 30]);

        $this->repo->findOrCreateEvent('contacts.birthday', 'contacts', 'Compleanno', null);

        $bindings = $this->repo->getChannelBindingsForEvent('contacts.birthday');
        // Un binding per canale (JOIN su notification_channels), ordinati per sort_order.
        $this->assertSame(['in_app', 'email', 'telegram'], array_column($bindings, 'channel_slug'));
        $this->assertSame('Email', $bindings[1]['channel_name']);
    }
}
