<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Checks;

use App\Modules\HealthCheck\Checks\SecurityIncidentsCheck;
use App\Services\SecurityIncidentService;
use PHPUnit\Framework\TestCase;

class SecurityIncidentsCheckTest extends TestCase
{
    public function testNoIncidentsIsOk(): void
    {
        $service = $this->createMock(SecurityIncidentService::class);
        $service->method('getSummary')->willReturn(['24h' => [], '7d' => [], '30d' => []]);

        $checks = (new SecurityIncidentsCheck($service))->run()['checks'];

        $this->assertSame('ok', $checks[0]['status']);
    }

    public function testFewIncidentsIsOk(): void
    {
        $service = $this->createMock(SecurityIncidentService::class);
        $service->method('getSummary')->willReturn([
            '24h' => [['type' => 'access_denied', 'severity' => 'low', 'cnt' => 3]],
            '7d' => [], '30d' => [],
        ]);

        $checks = (new SecurityIncidentsCheck($service))->run()['checks'];

        $this->assertSame('ok', $checks[0]['status']);
        $this->assertStringContainsString('access_denied: 3', $checks[0]['detail']);
    }

    public function testModerateIncidentsWarn(): void
    {
        $service = $this->createMock(SecurityIncidentService::class);
        $service->method('getSummary')->willReturn([
            '24h' => [
                ['type' => 'brute_force', 'severity' => 'high', 'cnt' => 4],
                ['type' => 'csrf_violation', 'severity' => 'medium', 'cnt' => 3],
            ],
            '7d' => [], '30d' => [],
        ]);

        $checks = (new SecurityIncidentsCheck($service))->run()['checks'];

        // totale 7 → warn (>5, <=20)
        $this->assertSame('warn', $checks[0]['status']);
    }

    public function testManyIncidentsFail(): void
    {
        $service = $this->createMock(SecurityIncidentService::class);
        $service->method('getSummary')->willReturn([
            '24h' => [['type' => 'brute_force', 'severity' => 'high', 'cnt' => 25]],
            '7d' => [], '30d' => [],
        ]);

        $checks = (new SecurityIncidentsCheck($service))->run()['checks'];

        $this->assertSame('fail', $checks[0]['status']);
    }

    public function testServiceFailureWarnsGracefully(): void
    {
        $service = $this->createMock(SecurityIncidentService::class);
        $service->method('getSummary')->willThrowException(new \RuntimeException('no table'));

        $checks = (new SecurityIncidentsCheck($service))->run()['checks'];

        $this->assertSame('warn', $checks[0]['status']);
    }
}
