<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\PushSubscriptionRepository;
use Tests\ModuleTestCase;

/**
 * Repository test su SQLite in-memory. Lo schema replica push_subscriptions con
 * il mapping tipi da gotchas.md (VARCHAR/CHAR -> TEXT, UNSIGNED INT -> INTEGER,
 * timestamp -> TEXT). Copre upsert per endpoint_hash (chiave reale), la
 * riassegnazione utente sullo stesso endpoint e le delete.
 */
class PushSubscriptionRepositoryTest extends ModuleTestCase
{
    private PushSubscriptionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE push_subscriptions (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id          INTEGER NOT NULL,
                endpoint         TEXT NOT NULL,
                endpoint_hash    TEXT NOT NULL UNIQUE,
                p256dh           TEXT NOT NULL,
                auth             TEXT NOT NULL,
                content_encoding TEXT NOT NULL DEFAULT "aes128gcm",
                user_agent       TEXT NULL,
                last_used_at     TEXT NULL,
                created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at       TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->repo = new PushSubscriptionRepository();
    }

    public function testUpsertInsertsThenReadsBackForUser(): void
    {
        $id = $this->repo->upsertForDevice(7, 'https://push.example/abc', 'pub-key', 'auth-key', 'aes128gcm', 'UA/1.0');

        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $this->repo->countForUser(7));

        $rows = $this->repo->activeForUser(7);
        $this->assertCount(1, $rows);
        $this->assertSame('https://push.example/abc', $rows[0]['endpoint']);
        $this->assertSame(hash('sha256', 'https://push.example/abc'), $rows[0]['endpoint_hash']);
    }

    public function testUpsertOnSameEndpointUpdatesInPlace(): void
    {
        $first = $this->repo->upsertForDevice(7, 'https://push.example/abc', 'pub-1', 'auth-1');
        $second = $this->repo->upsertForDevice(7, 'https://push.example/abc', 'pub-2', 'auth-2');

        $this->assertSame($first, $second, 'Stesso endpoint => stessa riga');
        $this->assertSame(1, $this->repo->countForUser(7));
        $this->assertSame('pub-2', $this->repo->activeForUser(7)[0]['p256dh']);
    }

    public function testUpsertReassignsEndpointToNewUser(): void
    {
        // Browser condiviso: stesso endpoint, login diverso => la subscription
        // migra al nuovo utente e non resta duplicata sul precedente.
        $this->repo->upsertForDevice(7, 'https://push.example/shared', 'pub', 'auth');
        $this->repo->upsertForDevice(9, 'https://push.example/shared', 'pub', 'auth');

        $this->assertSame(0, $this->repo->countForUser(7));
        $this->assertSame(1, $this->repo->countForUser(9));
    }

    public function testDeleteForUserByEndpointOnlyRemovesOwnRow(): void
    {
        $this->repo->upsertForDevice(7, 'https://push.example/a', 'pub', 'auth');

        $this->assertFalse($this->repo->deleteForUserByEndpoint(99, 'https://push.example/a'));
        $this->assertTrue($this->repo->deleteForUserByEndpoint(7, 'https://push.example/a'));
        $this->assertSame(0, $this->repo->countForUser(7));
    }

    public function testDeleteByEndpointHashRemovesDeadSubscription(): void
    {
        $this->repo->upsertForDevice(7, 'https://push.example/dead', 'pub', 'auth');

        $this->repo->deleteByEndpointHash(hash('sha256', 'https://push.example/dead'));

        $this->assertSame(0, $this->repo->countForUser(7));
    }

    public function testStatsCountsSubscriptionsAndDistinctUsers(): void
    {
        $this->repo->upsertForDevice(7, 'https://push.example/1', 'pub', 'auth');
        $this->repo->upsertForDevice(7, 'https://push.example/2', 'pub', 'auth');
        $this->repo->upsertForDevice(9, 'https://push.example/3', 'pub', 'auth');

        $stats = $this->repo->stats();

        $this->assertSame(3, $stats['subscriptions']);
        $this->assertSame(2, $stats['users']);
    }
}
