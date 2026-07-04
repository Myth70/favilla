<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationDeliveryRepository;
use App\Modules\Notifications\Repositories\NotificationDispatchRepository;
use App\Modules\Notifications\Repositories\NotificationQueueRepository;
use App\Modules\Notifications\Services\NotificationQueueProcessorService;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Tests for NotificationQueueProcessorService::process() — the queue loop and
 * stats aggregation, with the repositories mocked so no notification schema is
 * required. The unknown-channel job exercises the "no driver" failure branch
 * without invoking any real channel driver (no network).
 */
class NotificationQueueProcessorServiceTest extends ModuleTestCase
{
    use MakesContainer;

    private function bindRepos(NotificationQueueRepository $queueRepo): void
    {
        $this->bindInstance(NotificationQueueRepository::class, $queueRepo);
        $this->bindInstance(NotificationDeliveryRepository::class, $this->createMock(NotificationDeliveryRepository::class));
        $this->bindInstance(NotificationDispatchRepository::class, $this->createMock(NotificationDispatchRepository::class));
    }

    public function testProcessReturnsZeroedStatsWhenQueueEmpty(): void
    {
        $queueRepo = $this->createMock(NotificationQueueRepository::class);
        $queueRepo->method('claimPending')->willReturn([]);
        $this->bindRepos($queueRepo);

        $stats = (new NotificationQueueProcessorService())->process();

        $this->assertSame(
            ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'released' => 0],
            $stats
        );
    }

    public function testProcessFailsJobsWithUnknownChannel(): void
    {
        $queueRepo = $this->createMock(NotificationQueueRepository::class);
        $queueRepo->method('claimPending')->willReturn([[
            'queue_id'       => 10,
            'delivery_id'    => 20,
            'queue_attempts' => 0,
            'max_attempts'   => 3,
            'channel_slug'   => 'ghost',
            'dispatch_id'    => 30,
        ]]);
        $queueRepo->expects($this->once())->method('markFailed');
        $this->bindRepos($queueRepo);

        $stats = (new NotificationQueueProcessorService())->process();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['failed']);
    }
}
