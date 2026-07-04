<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationQueueRepository;
use Tests\ModuleTestCase;

class NotificationQueueRepositoryTest extends ModuleTestCase
{
    private NotificationQueueRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE notification_queue (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                delivery_id  INTEGER NOT NULL,
                channel_slug TEXT NOT NULL,
                payload_json TEXT NULL,
                status       TEXT NOT NULL DEFAULT "pending",
                available_at TEXT NULL,
                locked_at    TEXT NULL,
                attempts     INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                last_error   TEXT NULL,
                created_at   TEXT NULL,
                updated_at   TEXT NULL
            );
        ');
        $this->repo = new NotificationQueueRepository();
    }

    public function testEnqueueCreatesPendingJob(): void
    {
        $id = $this->repo->enqueue(10, 'email', ['k' => 'v'], 3);
        $row = $this->repo->find($id);

        $this->assertSame('pending', $row['status']);
        $this->assertSame('email', $row['channel_slug']);
        $this->assertSame(3, (int) $row['max_attempts']);
        $this->assertSame(10, (int) $row['delivery_id']);
        $this->assertStringContainsString('"k":"v"', (string) $row['payload_json']);
    }

    public function testMarkSentSkippedFailedSetStatus(): void
    {
        $a = $this->repo->enqueue(1, 'email');
        $this->repo->markSent($a);
        $this->assertSame('sent', $this->repo->find($a)['status']);

        $b = $this->repo->enqueue(2, 'email');
        $this->repo->markSkipped($b, 'no link');
        $this->assertSame('skipped', $this->repo->find($b)['status']);
        $this->assertSame('no link', $this->repo->find($b)['last_error']);

        $c = $this->repo->enqueue(3, 'email');
        $this->repo->markFailed($c, 'boom');
        $this->assertSame('failed', $this->repo->find($c)['status']);
    }

    public function testReleaseForRetryResetsToPendingWithDelay(): void
    {
        $id = $this->repo->enqueue(1, 'email');
        $this->repo->releaseForRetry($id, 2, 'temporary');

        $row = $this->repo->find($id);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('temporary', $row['last_error']);
        $this->assertNotNull($row['available_at']);
    }

    public function testGetStatusCountsByChannelGroups(): void
    {
        $this->repo->markSent($this->repo->enqueue(1, 'email'));
        $this->repo->markSent($this->repo->enqueue(2, 'email'));
        $this->repo->markFailed($this->repo->enqueue(3, 'telegram'), 'x');

        $map = $this->repo->getStatusCountsByChannel();
        $this->assertSame(2, $map['email']['sent']);
        $this->assertSame(1, $map['telegram']['failed']);
    }

    public function testResetToRetryOnlyAffectsFailedJobs(): void
    {
        $failed = $this->repo->enqueue(1, 'email');
        $this->repo->markFailed($failed, 'x');
        $sent = $this->repo->enqueue(2, 'email');
        $this->repo->markSent($sent);

        $this->assertTrue($this->repo->resetToRetry($failed));
        $this->assertSame('pending', $this->repo->find($failed)['status']);

        // Un job non-failed non viene resettato.
        $this->assertFalse($this->repo->resetToRetry($sent));
    }

    public function testResetAllFailedToRetryReturnsCount(): void
    {
        $this->repo->markFailed($this->repo->enqueue(1, 'email'), 'x');
        $this->repo->markFailed($this->repo->enqueue(2, 'email'), 'x');
        $this->repo->markSent($this->repo->enqueue(3, 'email'));

        $this->assertSame(2, $this->repo->resetAllFailedToRetry());
    }

    public function testGetDeliveryIdReturnsValueOrNull(): void
    {
        $id = $this->repo->enqueue(77, 'email');
        $this->assertSame(77, $this->repo->getDeliveryId($id));
        $this->assertNull($this->repo->getDeliveryId(99999));
    }

    public function testClaimPendingLocksAndIncrementsAttempts(): void
    {
        $id = $this->insertRow('notification_queue', [
            'delivery_id' => 1, 'channel_slug' => 'email', 'status' => 'pending',
            'available_at' => date('Y-m-d H:i:s', time() - 60), 'attempts' => 0, 'max_attempts' => 5,
        ]);
        // Serve la catena delivery+dispatch per findJobByQueueId (JOIN).
        $this->migrate('
            CREATE TABLE notification_deliveries (id INTEGER PRIMARY KEY, dispatch_id INTEGER, user_id INTEGER, subject TEXT, body TEXT, link TEXT, icon TEXT, color TEXT);
            CREATE TABLE notification_dispatches (id INTEGER PRIMARY KEY, title TEXT, body TEXT, type TEXT, link TEXT, icon TEXT, color TEXT, event_slug TEXT, source_module TEXT, created_by INTEGER, payload_json TEXT);
        ');
        $this->insertRow('notification_dispatches', ['id' => 1, 'title' => 'T', 'source_module' => 'M']);
        $this->insertRow('notification_deliveries', ['id' => 1, 'dispatch_id' => 1, 'user_id' => 1]);

        $claimed = $this->repo->claimPending(10);

        $this->assertCount(1, $claimed);
        $row = $this->repo->find($id);
        $this->assertSame('processing', $row['status']);
        $this->assertSame(1, (int) $row['attempts']);
    }
}
