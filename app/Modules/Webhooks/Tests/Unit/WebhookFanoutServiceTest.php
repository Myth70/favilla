<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Tests\Unit;

use App\Modules\Webhooks\Repositories\WebhookDeliveryRepository;
use App\Modules\Webhooks\Services\WebhookFanoutService;
use Tests\ModuleTestCase;

/**
 * Fan-out: accoda una delivery per ogni endpoint attivo sottoscritto all'evento,
 * saltando endpoint inattivi e non sottoscritti. Repository reali su SQLite.
 */
class WebhookFanoutServiceTest extends ModuleTestCase
{
    private WebhookDeliveryRepository $deliveryRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE webhook_endpoints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                secret TEXT NOT NULL,
                event_types TEXT NOT NULL,
                description TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE webhook_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                endpoint_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                attempts INTEGER NOT NULL DEFAULT 0,
                response_code INTEGER NULL,
                last_error TEXT NULL,
                next_retry_at TEXT NULL,
                delivered_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->deliveryRepo = new WebhookDeliveryRepository();
    }

    private function seedEndpoint(array $events, int $active = 1, ?string $deletedAt = null): int
    {
        return $this->insertRow('webhook_endpoints', [
            'url'         => 'https://hooks.example.com/' . uniqid(),
            'secret'      => 'sekret',
            'event_types' => json_encode($events),
            'is_active'   => $active,
            'deleted_at'  => $deletedAt,
        ]);
    }

    public function testEnqueuesOnlyForSubscribedActiveEndpoints(): void
    {
        $subscribed = $this->seedEndpoint(['tasks.task_overdue', 'blog.published']);
        $this->seedEndpoint(['calendar.event_reminder']);          // non sottoscritto
        $this->seedEndpoint(['tasks.task_overdue'], active: 0);     // inattivo
        $this->seedEndpoint(['tasks.task_overdue'], deletedAt: '2020-01-01 00:00:00'); // soft-deleted

        $service = new WebhookFanoutService();
        $count = $service->enqueueForEvent('tasks.task_overdue', 'Tasks', [
            'title' => 'Scaduta', 'body' => 'x', 'link' => null, 'context' => ['task_id' => 5],
        ]);

        $this->assertSame(1, $count);
        $rows = $this->deliveryRepo->recentForEndpoint($subscribed);
        $this->assertCount(1, $rows);
        $this->assertSame('tasks.task_overdue', $rows[0]['event_type']);

        $payload = json_decode((string) $rows[0]['payload'], true);
        $this->assertSame('Tasks', $payload['module']);
        $this->assertSame(5, $payload['context']['task_id']);
    }

    public function testNoEndpointsMeansNoDeliveries(): void
    {
        $service = new WebhookFanoutService();
        $this->assertSame(0, $service->enqueueForEvent('nothing.subscribed', 'X', ['title' => 'a', 'body' => 'b']));
    }
}
