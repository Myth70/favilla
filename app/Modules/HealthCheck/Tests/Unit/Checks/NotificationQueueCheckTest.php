<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Checks;

use App\Modules\HealthCheck\Checks\NotificationQueueCheck;
use App\Modules\Notifications\Repositories\NotificationQueueRepository;
use PHPUnit\Framework\TestCase;

class NotificationQueueCheckTest extends TestCase
{
    public function testEmptyQueueIsOk(): void
    {
        $repo = $this->createMock(NotificationQueueRepository::class);
        $repo->method('getBacklogSummary')->willReturn(['pending' => 0, 'oldest_pending_minutes' => null]);

        $checks = (new NotificationQueueCheck($repo))->run()['checks'];

        $this->assertSame('ok', $checks[0]['status']);
    }

    public function testRecentBacklogIsOk(): void
    {
        $repo = $this->createMock(NotificationQueueRepository::class);
        $repo->method('getBacklogSummary')->willReturn(['pending' => 5, 'oldest_pending_minutes' => 2]);

        $checks = (new NotificationQueueCheck($repo))->run()['checks'];

        $this->assertSame('ok', $checks[0]['status']);
    }

    public function testModerateBacklogWarns(): void
    {
        $repo = $this->createMock(NotificationQueueRepository::class);
        $repo->method('getBacklogSummary')->willReturn(['pending' => 40, 'oldest_pending_minutes' => 45]);

        $checks = (new NotificationQueueCheck($repo))->run()['checks'];

        $this->assertSame('warn', $checks[0]['status']);
    }

    public function testLargeBacklogFails(): void
    {
        $repo = $this->createMock(NotificationQueueRepository::class);
        $repo->method('getBacklogSummary')->willReturn(['pending' => 500, 'oldest_pending_minutes' => 180]);

        $checks = (new NotificationQueueCheck($repo))->run()['checks'];

        $this->assertSame('fail', $checks[0]['status']);
    }

    public function testRepositoryFailureWarnsGracefully(): void
    {
        $repo = $this->createMock(NotificationQueueRepository::class);
        $repo->method('getBacklogSummary')->willThrowException(new \RuntimeException('no table'));

        $checks = (new NotificationQueueCheck($repo))->run()['checks'];

        $this->assertSame('warn', $checks[0]['status']);
    }
}
