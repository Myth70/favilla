<?php

namespace Tests\Unit;

use App\Support\ClientIp;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
    private array $savedServer;
    private ?string $savedTrustedProxies;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedTrustedProxies = $_ENV['TRUSTED_PROXIES'] ?? null;
        unset($_ENV['TRUSTED_PROXIES']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        if ($this->savedTrustedProxies === null) {
            unset($_ENV['TRUSTED_PROXIES']);
        } else {
            $_ENV['TRUSTED_PROXIES'] = $this->savedTrustedProxies;
        }
    }

    public function testReturnsRemoteAddrWhenNoTrustedProxiesConfigured(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $this->assertSame('203.0.113.1', ClientIp::resolve());
    }

    public function testReturnsDefaultIpWhenRemoteAddrMissing(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame('127.0.0.1', ClientIp::resolve());
    }

    public function testIgnoresXffWhenRemoteAddrIsNotTrusted(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1,10.0.0.2';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $this->assertSame('203.0.113.1', ClientIp::resolve());
    }

    public function testReturnsClientFromXffWhenProxyIsTrusted(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
        $this->assertSame('198.51.100.7', ClientIp::resolve());
    }

    public function testSkipsTrustedProxiesInXffChain(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1,10.0.0.2';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.2';
        $this->assertSame('198.51.100.7', ClientIp::resolve());
    }

    public function testRejectsEntireXffWhenAnyEntryIsNotIp(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, not-an-ip';
        $this->assertSame('10.0.0.1', ClientIp::resolve());
    }

    public function testFallsBackToRemoteAddrWhenXffMissing(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('10.0.0.1', ClientIp::resolve());
    }
}
