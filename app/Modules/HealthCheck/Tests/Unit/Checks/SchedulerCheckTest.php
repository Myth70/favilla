<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Checks;

use App\Modules\HealthCheck\Checks\SchedulerCheck;
use App\Modules\Scheduler\Services\SchedulerService;
use PHPUnit\Framework\TestCase;

class SchedulerCheckTest extends TestCase
{
    public function testNoEnabledJobsWarns(): void
    {
        $service = $this->createMock(SchedulerService::class);
        $service->method('getJobs')->willReturn([
            ['enabled' => false, 'last_run_at' => null, 'interval_minutes' => 5],
        ]);

        $checks = (new SchedulerCheck($service))->run()['checks'];

        $this->assertSame('warn', $checks[0]['status']);
    }

    public function testJobsWithinIntervalAreOk(): void
    {
        $service = $this->createMock(SchedulerService::class);
        $service->method('getJobs')->willReturn([
            ['enabled' => true, 'name' => 'Coda notifiche', 'last_run_at' => date('Y-m-d H:i:s', time() - 60), 'interval_minutes' => 5],
        ]);

        $checks = (new SchedulerCheck($service))->run()['checks'];

        $this->assertSame('ok', $checks[0]['status']);
    }

    public function testJobNeverRunIsIgnored(): void
    {
        $service = $this->createMock(SchedulerService::class);
        $service->method('getJobs')->willReturn([
            ['enabled' => true, 'name' => 'Nuovo job', 'last_run_at' => null, 'interval_minutes' => 5],
        ]);

        $checks = (new SchedulerCheck($service))->run()['checks'];

        $this->assertSame('ok', $checks[0]['status']);
    }

    public function testModeratelyOverdueJobWarns(): void
    {
        $service = $this->createMock(SchedulerService::class);
        $service->method('getJobs')->willReturn([
            // intervallo 5 min, ultima esecuzione 30 min fa -> 25 min di ritardo (> grazia 15, <= fail 60)
            ['enabled' => true, 'name' => 'Coda notifiche', 'last_run_at' => date('Y-m-d H:i:s', time() - 1800), 'interval_minutes' => 5],
        ]);

        $checks = (new SchedulerCheck($service))->run()['checks'];

        $this->assertSame('warn', $checks[0]['status']);
        $this->assertStringContainsString('Coda notifiche', $checks[0]['detail']);
    }

    public function testSeverelyOverdueJobFails(): void
    {
        $service = $this->createMock(SchedulerService::class);
        $service->method('getJobs')->willReturn([
            // intervallo 5 min, ultima esecuzione 2h fa -> ben oltre la soglia di fail
            ['enabled' => true, 'name' => 'Coda notifiche', 'last_run_at' => date('Y-m-d H:i:s', time() - 7200), 'interval_minutes' => 5],
        ]);

        $checks = (new SchedulerCheck($service))->run()['checks'];

        $this->assertSame('fail', $checks[0]['status']);
    }

    public function testServiceFailureWarnsGracefully(): void
    {
        $service = $this->createMock(SchedulerService::class);
        $service->method('getJobs')->willThrowException(new \RuntimeException('no table'));

        $checks = (new SchedulerCheck($service))->run()['checks'];

        $this->assertSame('warn', $checks[0]['status']);
    }
}
