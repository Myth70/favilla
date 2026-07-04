<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationDispatchRepository;
use Tests\ModuleTestCase;

/**
 * findWithSummary() usa SUM(status = "...") (espressione booleana MySQL) → testato
 * in tests/Integration/NotificationDispatchRepositoryIntegrationTest. Qui le parti
 * portabili: create, conteggi, refreshStatus, estrazione placeholder.
 */
class NotificationDispatchRepositoryTest extends ModuleTestCase
{
    private NotificationDispatchRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE notification_dispatches (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                event_slug       TEXT NULL,
                source_module    TEXT NOT NULL DEFAULT "test",
                title            TEXT NOT NULL DEFAULT "t",
                body             TEXT NULL,
                type             TEXT NOT NULL DEFAULT "info",
                payload_json     TEXT NULL,
                status           TEXT NOT NULL DEFAULT "pending",
                total_recipients INTEGER NOT NULL DEFAULT 0,
                total_deliveries INTEGER NOT NULL DEFAULT 0,
                created_at       TEXT NULL
            );
            CREATE TABLE notification_deliveries (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                dispatch_id INTEGER NOT NULL,
                status      TEXT NOT NULL DEFAULT "pending"
            );
        ');
        $this->repo = new NotificationDispatchRepository();
    }

    public function testCreateDispatchPersists(): void
    {
        $id = $this->repo->createDispatch([
            'event_slug' => 'contacts.birthday', 'source_module' => 'Contacts', 'title' => 'X',
        ]);
        $this->assertNotNull($this->repo->find($id));
    }

    public function testGetStatusCountsGroupsByStatus(): void
    {
        $this->insertRow('notification_dispatches', ['title' => 'a', 'status' => 'sent']);
        $this->insertRow('notification_dispatches', ['title' => 'b', 'status' => 'sent']);
        $this->insertRow('notification_dispatches', ['title' => 'c', 'status' => 'failed']);

        $counts = $this->repo->getStatusCounts();
        $this->assertSame(2, $counts['sent']);
        $this->assertSame(1, $counts['failed']);
    }

    public function testRefreshStatusDerivesQueuedWhenPendingDeliveriesExist(): void
    {
        $dispatchId = $this->insertRow('notification_dispatches', ['title' => 'x', 'status' => 'pending']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'queued']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'sent']);

        $this->repo->refreshStatus($dispatchId);

        $row = $this->repo->find($dispatchId);
        $this->assertSame('queued', $row['status']);
        $this->assertSame(2, (int) $row['total_deliveries']);
    }

    public function testRefreshStatusDerivesSentWhenAllSent(): void
    {
        $dispatchId = $this->insertRow('notification_dispatches', ['title' => 'x', 'status' => 'pending']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'sent']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'sent']);

        $this->repo->refreshStatus($dispatchId);
        $this->assertSame('sent', $this->repo->find($dispatchId)['status']);
    }

    public function testRefreshStatusDerivesFailedWhenOnlyFailures(): void
    {
        $dispatchId = $this->insertRow('notification_dispatches', ['title' => 'x', 'status' => 'pending']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'failed']);

        $this->repo->refreshStatus($dispatchId);
        $this->assertSame('failed', $this->repo->find($dispatchId)['status']);
    }

    public function testRefreshStatusDerivesPartialWhenMixedSentAndFailed(): void
    {
        $dispatchId = $this->insertRow('notification_dispatches', ['title' => 'x', 'status' => 'pending']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'sent']);
        $this->insertRow('notification_deliveries', ['dispatch_id' => $dispatchId, 'status' => 'failed']);

        $this->repo->refreshStatus($dispatchId);
        $this->assertSame('partial', $this->repo->find($dispatchId)['status']);
    }

    public function testGetPayloadPlaceholderHintsExtractsKeys(): void
    {
        $this->insertRow('notification_dispatches', [
            'title' => 'a', 'event_slug' => 'contacts.birthday',
            'payload_json' => json_encode(['nome' => 'Mario', 'meta' => ['eta' => 40]]),
        ]);
        $this->insertRow('notification_dispatches', [
            'title' => 'b', 'event_slug' => 'tasks.due',
            'payload_json' => json_encode(['titolo' => 'T']),
        ]);
        // Payload vuoti/non validi ignorati.
        $this->insertRow('notification_dispatches', ['title' => 'c', 'payload_json' => '{}']);

        $hints = $this->repo->getPayloadPlaceholderHints();

        $this->assertSame(2, $hints['sampled_dispatches']);
        $this->assertContains('nome', $hints['global']);
        $this->assertContains('meta.eta', $hints['global']); // chiave annidata
        // per_event raccoglie le chiavi del solo evento, ordinate (inclusa la
        // chiave padre 'meta' oltre alla annidata 'meta.eta').
        $this->assertSame(['meta', 'meta.eta', 'nome'], $hints['per_event']['contacts.birthday']);
    }
}
