<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Checks;

use App\Modules\HealthCheck\Checks\SeparationOfDutiesCheck;
use App\Services\RoleConstraintService;
use PHPUnit\Framework\TestCase;

/**
 * Prima del refactor questa logica era SQL grezzo dentro la god class e non
 * testabile. Ora il check passa dal RoleConstraintService, mockabile.
 */
class SeparationOfDutiesCheckTest extends TestCase
{
    public function testNoConstraintsConfiguredWarns(): void
    {
        $service = $this->createMock(RoleConstraintService::class);
        $service->method('getStats')->willReturn(['total' => 0, 'active' => 0, 'violations' => 0]);

        $checks = (new SeparationOfDutiesCheck($service))->run()['checks'];

        $this->assertSame('warn', $this->statusOf($checks, 'Vincoli attivi'));
    }

    public function testActiveConstraintsNoViolationsIsOk(): void
    {
        $service = $this->createMock(RoleConstraintService::class);
        $service->method('getStats')->willReturn(['total' => 3, 'active' => 3, 'violations' => 0]);

        $checks = (new SeparationOfDutiesCheck($service))->run()['checks'];

        $this->assertSame('ok', $this->statusOf($checks, 'Vincoli attivi'));
        $this->assertSame('ok', $this->statusOf($checks, 'Violazioni SoD'));
    }

    public function testViolationsFail(): void
    {
        $service = $this->createMock(RoleConstraintService::class);
        $service->method('getStats')->willReturn(['total' => 1, 'active' => 1, 'violations' => 2]);
        $service->method('findViolations')->willReturn([
            ['user_name' => 'Mario', 'role1_name' => 'Admin', 'role2_name' => 'Auditor', 'reason' => 'x'],
            ['user_name' => 'Luigi', 'role1_name' => 'Admin', 'role2_name' => 'Auditor', 'reason' => 'x'],
        ]);

        $checks = (new SeparationOfDutiesCheck($service))->run()['checks'];
        $detail = $this->detailOf($checks, 'Violazioni SoD');

        $this->assertSame('fail', $this->statusOf($checks, 'Violazioni SoD'));
        $this->assertStringContainsString('Mario', $detail);
    }

    public function testServiceFailureWarnsGracefully(): void
    {
        $service = $this->createMock(RoleConstraintService::class);
        $service->method('getStats')->willThrowException(new \RuntimeException('no table'));

        $checks = (new SeparationOfDutiesCheck($service))->run()['checks'];

        $this->assertSame('warn', $this->statusOf($checks, 'Tabella vincoli ruoli'));
    }

    /** @param array<int,array{name:string,status:string,detail:string}> $checks */
    private function statusOf(array $checks, string $name): ?string
    {
        foreach ($checks as $c) {
            if ($c['name'] === $name) {
                return $c['status'];
            }
        }
        return null;
    }

    /** @param array<int,array{name:string,status:string,detail:string}> $checks */
    private function detailOf(array $checks, string $name): string
    {
        foreach ($checks as $c) {
            if ($c['name'] === $name) {
                return $c['detail'];
            }
        }
        return '';
    }
}
