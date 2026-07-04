<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\SecurityDashboardController;
use App\Modules\HealthCheck\Services\HealthCheckService;
use App\Services\KeyRotationService;
use App\Services\LogRotationService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for SecurityDashboardController via the HTTP harness.
 *
 * Services are resolved inside each action via app(), so they are mocked through
 * the container. assets() (shell_exec composer audit) and recordKeyRotation()
 * (raw header+exit) are intentionally left to the Integration suite.
 */
class SecurityDashboardControllerTest extends ControllerTestCase
{
    public function testKeysRendersStatusDashboard(): void
    {
        $service = $this->createMock(KeyRotationService::class);
        $service->method('getStatus')->willReturn([
            ['name' => 'APP_KEY', 'rotated_at' => null],
        ]);
        $this->bindInstance(KeyRotationService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(SecurityDashboardController::class, 'keys');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/security-keys', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['keys']);
    }

    public function testLogsStatusRendersDashboard(): void
    {
        $service = $this->createMock(LogRotationService::class);
        $service->method('getStatus')->willReturn(['files' => [['name' => 'app.log']]]);
        $service->method('verifyAll')->willReturn(['ok' => true]);
        $this->bindInstance(LogRotationService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(SecurityDashboardController::class, 'logsStatus');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/security-logs-status', $result->renderedTemplate());
        $this->assertSame(['files' => [['name' => 'app.log']]], $result->renderedData()['logStatus']);
        $this->assertSame(['ok' => true], $result->renderedData()['verification']);
    }

    public function testHardeningRendersChecksAndComputesSummary(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('checkPhpHardening')->willReturn([
            'label'       => 'Hardening PHP',
            'description' => '',
            'checks'      => [
                ['name' => 'expose_php', 'status' => 'ok', 'detail' => ''],
                ['name' => 'allow_url_include', 'status' => 'ok', 'detail' => ''],
                ['name' => 'open_basedir', 'status' => 'warn', 'detail' => ''],
                ['name' => 'allow_url_fopen', 'status' => 'fail', 'detail' => ''],
            ],
        ]);
        $this->bindInstance(HealthCheckService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(SecurityDashboardController::class, 'hardening');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/security-hardening', $result->renderedTemplate());
        $this->assertCount(4, $result->renderedData()['checks']);

        $summary = $result->renderedData()['summary'];
        $this->assertSame(2, $summary['ok']);
        $this->assertSame(1, $summary['warn']);
        $this->assertSame(1, $summary['fail']);
    }

    public function testRotateNowFlashesSuccessAndRedirects(): void
    {
        $service = $this->createMock(LogRotationService::class);
        $service->method('rotate')->willReturn([
            'rotated' => true,
            'file'    => 'app.log',
            'size'    => 2048,
        ]);
        $this->bindInstance(LogRotationService::class, $service);

        $this->actingAsAdmin();
        $result = $this->withPost([])->dispatch(SecurityDashboardController::class, 'rotateNow');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.security.logs', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('success'));
    }
}
