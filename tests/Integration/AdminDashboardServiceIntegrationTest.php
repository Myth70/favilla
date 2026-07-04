<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Admin\Services\AdminDashboardService;

/**
 * getStats()/getLoginSecurityChartData() usano CURDATE(), DATE_SUB(...INTERVAL),
 * SUM(success=1) — sintassi MySQL non parsabile su SQLite: verificate su MariaDB.
 */
class AdminDashboardServiceIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testGetStatsCountsSeededData(): void
    {
        $this->insertRow('users', ['name' => 'A', 'email' => 'a@x.test', 'username' => 'a', 'password' => 'x', 'is_active' => 1]);
        $this->insertRow('users', ['name' => 'B', 'email' => 'b@x.test', 'username' => 'b', 'password' => 'x', 'is_active' => 0]);

        $stats = (new AdminDashboardService())->getStats();

        $this->assertSame(2, $stats['total_users']);
        $this->assertSame(1, $stats['active_users']);
        $this->assertSame(1, $stats['inactive_users']);
        $this->assertArrayHasKey('modules_count', $stats);
    }

    public function testGetLoginSecurityChartDataFillsAllDays(): void
    {
        $data = (new AdminDashboardService())->getLoginSecurityChartData(7);

        $this->assertCount(7, $data['labels']);
        $this->assertCount(7, $data['ok_values']);
        $this->assertCount(7, $data['fail_values']);
    }

    public function testGetAuditTypeDistributionRunsOnMariaDb(): void
    {
        $dist = (new AdminDashboardService())->getAuditTypeDistribution(7);
        $this->assertArrayHasKey('labels', $dist);
        $this->assertArrayHasKey('values', $dist);
    }
}
