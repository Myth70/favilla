<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Checks;

use App\Modules\HealthCheck\Checks\DatabaseCheck;
use App\Modules\HealthCheck\Repositories\SystemDiagnosticsRepository;
use PHPUnit\Framework\TestCase;

class DatabaseCheckTest extends TestCase
{
    public function testHealthyDatabase(): void
    {
        $repo = $this->repoMock('10.11.6-MariaDB', [], ['active' => 10, 'max' => 100]);

        $checks = (new DatabaseCheck($repo))->run()['checks'];

        $this->assertSame('ok', $this->statusOf($checks, 'Connessione database'));
        $this->assertSame('ok', $this->statusOf($checks, 'Versione database'));
        $this->assertSame('ok', $this->statusOf($checks, 'Compatibilita MariaDB'));
        $this->assertSame('ok', $this->statusOf($checks, 'Collation tabelle'));
        $this->assertSame('ok', $this->statusOf($checks, 'Carico connessioni database'));
    }

    public function testOldMariaDbVersionWarns(): void
    {
        $repo = $this->repoMock('10.2.1-MariaDB', [], null);

        $checks = (new DatabaseCheck($repo))->run()['checks'];

        $this->assertSame('warn', $this->statusOf($checks, 'Compatibilita MariaDB'));
    }

    public function testNonUtf8mb4TablesWarn(): void
    {
        $repo = $this->repoMock('10.11.6-MariaDB', ['legacy_a', 'legacy_b'], null);

        $checks = (new DatabaseCheck($repo))->run()['checks'];

        $this->assertSame('warn', $this->statusOf($checks, 'Collation tabelle'));
        $this->assertStringContainsString('legacy_a', $this->detailOf($checks, 'Collation tabelle'));
    }

    public function testHighConnectionLoadWarns(): void
    {
        $repo = $this->repoMock('10.11.6-MariaDB', [], ['active' => 95, 'max' => 100]);

        $checks = (new DatabaseCheck($repo))->run()['checks'];

        $this->assertSame('warn', $this->statusOf($checks, 'Carico connessioni database'));
    }

    public function testConnectionFailureFails(): void
    {
        $repo = $this->createMock(SystemDiagnosticsRepository::class);
        $repo->method('databaseVersion')->willThrowException(new \RuntimeException('connection refused'));

        $checks = (new DatabaseCheck($repo))->run()['checks'];

        $this->assertSame('fail', $this->statusOf($checks, 'Connessione database'));
    }

    /**
     * @param string[]                    $nonUtf8
     * @param array{active:int,max:int}|null $load
     */
    private function repoMock(string $version, array $nonUtf8, ?array $load): SystemDiagnosticsRepository
    {
        $repo = $this->createMock(SystemDiagnosticsRepository::class);
        $repo->method('databaseVersion')->willReturn($version);
        $repo->method('tablesNotUtf8mb4')->willReturn($nonUtf8);
        $repo->method('connectionLoad')->willReturn($load);
        $repo->method('executedCoreMigrations')->willReturn([]);
        $repo->method('executedModuleMigrations')->willReturn([]);

        return $repo;
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
