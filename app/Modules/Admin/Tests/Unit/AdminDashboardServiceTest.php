<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\AdminDashboardService;
use Tests\ModuleTestCase;

/**
 * Le statistiche usano SQL MySQL-only (CURDATE(), DATE_SUB, SUM(success=1)) →
 * coperte in tests/Integration/AdminDashboardServiceIntegrationTest. Qui solo i
 * metodi portabili che non toccano quel SQL.
 */
class AdminDashboardServiceTest extends ModuleTestCase
{
    public function testGetSystemInfoReturnsRuntimeMetadata(): void
    {
        $info = (new AdminDashboardService())->getSystemInfo();

        $this->assertSame(PHP_VERSION, $info['php_version']);
        $this->assertArrayHasKey('environment', $info);
        $this->assertArrayHasKey('timezone', $info);
        $this->assertArrayHasKey('db_version', $info);
    }
}
