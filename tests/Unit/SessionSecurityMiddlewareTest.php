<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Exceptions\HttpException;
use App\Middleware\SessionSecurityMiddleware;
use App\Services\SecurityIncidentService;
use PHPUnit\Framework\TestCase;

/**
 * SessionSecurityMiddleware now audits 403 denials synchronously (catching the
 * HttpException thrown by RoleMiddleware) instead of via a shutdown function.
 * A capturing stub of SecurityIncidentService records what would be logged.
 */
class SessionSecurityMiddlewareTest extends TestCase
{
    private CapturingIncidentService $incidents;

    protected function setUp(): void
    {
        $container = new Container();
        Container::setInstance($container);
        $this->incidents = new CapturingIncidentService();
        $container->instance(SecurityIncidentService::class, $this->incidents);

        $_SESSION = ['user_id' => 7, 'user_name' => 'Tester', '_login_ip' => '1.2.3.4'];
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testLogsAccessDeniedOn403AndRethrows(): void
    {
        $middleware = new SessionSecurityMiddleware();

        try {
            $middleware->handle(function (): void {
                throw new HttpException(403);
            });
            $this->fail('The 403 must be re-thrown for the central handler');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame('access_denied', $this->incidents->lastType);
    }

    public function testDoesNotLogOnSuccess(): void
    {
        $middleware = new SessionSecurityMiddleware();
        $called = false;
        $middleware->handle(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertNull($this->incidents->lastType);
    }

    public function testDoesNotLogAccessDeniedForNon403(): void
    {
        $middleware = new SessionSecurityMiddleware();

        try {
            $middleware->handle(function (): void {
                throw new HttpException(404);
            });
            $this->fail('The 404 must be re-thrown');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $this->assertNull($this->incidents->lastType, 'only 403 denials are audited');
    }

    public function testSkipsForUnauthenticatedSession(): void
    {
        $_SESSION = [];
        $middleware = new SessionSecurityMiddleware();
        $called = false;
        $middleware->handle(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertNull($this->incidents->lastType);
    }
}

class CapturingIncidentService extends SecurityIncidentService
{
    public ?string $lastType = null;

    public function __construct()
    {
        // Skip the real (DB-backed) construction.
    }

    public function recordIncident(string $type, string $severity, ?string $details, ?string $ip): void
    {
        $this->lastType = $type;
    }
}
