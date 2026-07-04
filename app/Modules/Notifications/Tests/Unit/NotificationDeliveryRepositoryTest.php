<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationDeliveryRepository;
use Tests\ModuleTestCase;

class NotificationDeliveryRepositoryTest extends ModuleTestCase
{
    private NotificationDeliveryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE notification_deliveries (
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
                created_at          TEXT NULL,
                updated_at          TEXT NULL
            );
            CREATE TABLE notification_dispatches (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                event_slug    TEXT NULL,
                source_module TEXT NOT NULL DEFAULT "test",
                title         TEXT NOT NULL DEFAULT "t",
                body          TEXT NULL,
                type          TEXT NOT NULL DEFAULT "info",
                link          TEXT NULL,
                icon          TEXT NULL,
                color         TEXT NULL,
                created_by    INTEGER NULL,
                payload_json  TEXT NULL
            );
        ');
        $this->repo = new NotificationDeliveryRepository();
    }

    private function makeDelivery(string $channel = 'email', string $status = 'pending'): int
    {
        return $this->repo->createDelivery([
            'dispatch_id'  => 1,
            'user_id'      => 1,
            'channel_slug' => $channel,
            'status'       => $status,
            'subject'      => 'sub',
            'body'         => 'body',
        ]);
    }

    public function testCreateDeliveryPersistsRow(): void
    {
        $id = $this->makeDelivery();
        $row = $this->repo->find($id);

        $this->assertNotNull($row);
        $this->assertSame('email', $row['channel_slug']);
        $this->assertSame('pending', $row['status']);
    }

    public function testMarkSentSetsStatusAndProviderId(): void
    {
        $id = $this->makeDelivery();
        $this->repo->markSent($id, 'provider-123');

        $row = $this->repo->find($id);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('provider-123', $row['provider_message_id']);
        $this->assertNotNull($row['sent_at']);
    }

    public function testMarkFailedAndSkippedRecordError(): void
    {
        $f = $this->makeDelivery();
        $this->repo->markFailed($f, 'boom');
        $this->assertSame('failed', $this->repo->find($f)['status']);
        $this->assertSame('boom', $this->repo->find($f)['error_message']);

        $s = $this->makeDelivery();
        $this->repo->markSkipped($s, 'no channel');
        $this->assertSame('skipped', $this->repo->find($s)['status']);
    }

    public function testMarkProcessingAndResetToPending(): void
    {
        $id = $this->makeDelivery();
        $this->repo->markProcessing($id, 2);
        $row = $this->repo->find($id);
        $this->assertSame('processing', $row['status']);
        $this->assertSame(2, (int) $row['attempts']);

        $this->repo->markFailed($id, 'err');
        $this->repo->resetToPending($id);
        $row = $this->repo->find($id);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['error_message']);
    }

    public function testGetStatusCountsByChannelGroups(): void
    {
        $this->makeDelivery('email', 'sent');
        $this->makeDelivery('email', 'sent');
        $this->makeDelivery('email', 'failed');
        $this->makeDelivery('telegram', 'sent');

        $map = $this->repo->getStatusCountsByChannel();

        $this->assertSame(2, $map['email']['sent']);
        $this->assertSame(1, $map['email']['failed']);
        $this->assertSame(1, $map['telegram']['sent']);
    }

    public function testGetDriverPayloadJoinsDispatch(): void
    {
        $this->insertRow('notification_dispatches', [
            'id' => 1, 'event_slug' => 'contacts.birthday', 'title' => 'Compleanno', 'body' => 'corpo',
        ]);
        $id = $this->makeDelivery();

        $payload = $this->repo->getDriverPayload($id);
        $this->assertNotNull($payload);
        $this->assertSame('contacts.birthday', $payload['event_slug']);
        $this->assertSame('Compleanno', $payload['dispatch_title']);
        $this->assertSame($id, (int) $payload['delivery_id']);
    }

    public function testGetDriverPayloadReturnsNullForUnknownDelivery(): void
    {
        $this->assertNull($this->repo->getDriverPayload(999));
    }
}
