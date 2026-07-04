<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationPreferenceRepository;
use Tests\ModuleTestCase;

class NotificationPreferenceRepositoryTest extends ModuleTestCase
{
    private NotificationPreferenceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE user_notification_preferences (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                module_slug  TEXT NOT NULL,
                event_slug   TEXT NOT NULL DEFAULT "",
                channel_slug TEXT NOT NULL,
                is_enabled   INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, module_slug, event_slug, channel_slug)
            );
        ');
        $this->repo = new NotificationPreferenceRepository();
    }

    public function testUpsertPreferenceInsertsThenUpdates(): void
    {
        $this->repo->upsertPreference(1, 'contacts', 'birthday', 'email', true);
        $map = $this->repo->getEventPreferenceMap(1);
        $this->assertTrue($map['contacts']['birthday']['email']);

        // Stessa chiave → update, non duplicato.
        $this->repo->upsertPreference(1, 'contacts', 'birthday', 'email', false);
        $map = $this->repo->getEventPreferenceMap(1);
        $this->assertFalse($map['contacts']['birthday']['email']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM user_notification_preferences')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testGetModulePreferenceMapReturnsOnlyModuleLevel(): void
    {
        // Livello modulo: event_slug = ''.
        $this->repo->upsertPreference(1, 'contacts', '', 'email', true);
        $this->repo->upsertPreference(1, 'contacts', '', 'telegram', false);
        // Livello evento: deve essere escluso dalla mappa modulo.
        $this->repo->upsertPreference(1, 'contacts', 'birthday', 'email', true);

        $map = $this->repo->getModulePreferenceMap(1);

        $this->assertTrue($map['contacts']['email']);
        $this->assertFalse($map['contacts']['telegram']);
        $this->assertArrayNotHasKey('birthday', $map['contacts'] ?? []);
    }

    public function testDeletePreferenceReturnsTrueOnlyWhenRowExisted(): void
    {
        $this->repo->upsertPreference(1, 'contacts', 'birthday', 'email', true);

        $this->assertTrue($this->repo->deletePreference(1, 'contacts', 'birthday', 'email'));
        $this->assertFalse($this->repo->deletePreference(1, 'contacts', 'birthday', 'email'));
    }

    public function testResolveChannelBindingsUsesBindingDefaultWhenNoPreference(): void
    {
        $bindings = [
            ['channel_slug' => 'email', 'is_enabled' => 1, 'channel_active' => 1],
            ['channel_slug' => 'telegram', 'is_enabled' => 0, 'channel_active' => 1],
        ];

        $resolved = $this->repo->resolveChannelBindings(1, 'contacts', 'birthday', $bindings);

        $this->assertTrue($resolved[0]['resolved_enabled']);
        $this->assertFalse($resolved[1]['resolved_enabled']);
    }

    public function testResolveChannelBindingsModuleDefaultOverridesBinding(): void
    {
        // Default a livello modulo: disabilita email.
        $this->repo->upsertPreference(1, 'contacts', '', 'email', false);

        $bindings = [['channel_slug' => 'email', 'is_enabled' => 1, 'channel_active' => 1]];
        $resolved = $this->repo->resolveChannelBindings(1, 'contacts', 'birthday', $bindings);

        $this->assertFalse($resolved[0]['resolved_enabled'], 'Il default di modulo deve prevalere sul binding');
    }

    public function testResolveChannelBindingsEventOverrideBeatsModuleDefault(): void
    {
        $this->repo->upsertPreference(1, 'contacts', '', 'email', false);          // modulo: off
        $this->repo->upsertPreference(1, 'contacts', 'birthday', 'email', true);   // evento: on

        $bindings = [['channel_slug' => 'email', 'is_enabled' => 0, 'channel_active' => 1]];
        $resolved = $this->repo->resolveChannelBindings(1, 'contacts', 'birthday', $bindings);

        $this->assertTrue($resolved[0]['resolved_enabled'], "L'override di evento deve prevalere");
    }

    public function testResolveChannelBindingsInactiveChannelForcesDisabled(): void
    {
        $this->repo->upsertPreference(1, 'contacts', 'birthday', 'email', true);

        // channel_active = 0 → sempre disabilitato, qualunque preferenza.
        $bindings = [['channel_slug' => 'email', 'is_enabled' => 1, 'channel_active' => 0]];
        $resolved = $this->repo->resolveChannelBindings(1, 'contacts', 'birthday', $bindings);

        $this->assertFalse($resolved[0]['resolved_enabled']);
    }

    public function testResolveChannelBindingsEmptyReturnsEmpty(): void
    {
        $this->assertSame([], $this->repo->resolveChannelBindings(1, 'contacts', 'birthday', []));
    }
}
