<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Notifications\Repositories\NotificationDispatchRepository;

/**
 * findWithSummary() usa SUM(nd.status = "sent") — espressione booleana MySQL non
 * portabile su SQLite in modo affidabile: verificata sul dialetto reale.
 */
class NotificationDispatchRepositoryIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testFindWithSummaryAggregatesDeliveryStatuses(): void
    {
        // Soddisfa i vincoli FK reali (user, channel).
        $userId = $this->insertRow('users', [
            'name' => 'U', 'email' => 'nd@example.test', 'username' => 'nd_user', 'password' => 'x',
        ]);
        $this->insertRow('notification_channels', ['slug' => 'in_app', 'name' => 'In-App']);

        $repo = new NotificationDispatchRepository();
        $dispatchId = $repo->createDispatch([
            'source_module' => 'Contacts', 'title' => 'Compleanno', 'event_slug' => 'contacts.birthday',
        ]);

        foreach (['sent', 'sent', 'failed', 'skipped'] as $status) {
            $this->insertRow('notification_deliveries', [
                'dispatch_id' => $dispatchId, 'user_id' => $userId, 'channel_slug' => 'in_app', 'status' => $status,
            ]);
        }

        $row = $repo->findWithSummary($dispatchId);
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['sent_count']);
        $this->assertSame(1, (int) $row['failed_count']);
        $this->assertSame(1, (int) $row['skipped_count']);
    }

    public function testFindWithSummaryReturnsNullForUnknownDispatch(): void
    {
        $this->assertNull((new NotificationDispatchRepository())->findWithSummary(999999));
    }
}
