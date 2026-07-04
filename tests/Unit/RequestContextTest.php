<?php

namespace Tests\Unit;

use App\Support\RequestContext;
use PHPUnit\Framework\TestCase;

class RequestContextTest extends TestCase
{
    private array $savedServer;
    private ?string $savedTrustedProxies;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedTrustedProxies = $_ENV['TRUSTED_PROXIES'] ?? null;
        unset($_ENV['TRUSTED_PROXIES']);
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['REMOTE_ADDR']);
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

    public function testPlainHttpLanIsNotSecure(): void
    {
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $this->assertFalse(RequestContext::isSecure());
    }

    public function testDirectHttpsIsSecure(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(RequestContext::isSecure());
    }

    public function testHttpsOffIsNotSecure(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(RequestContext::isSecure());
    }

    public function testPort443IsSecure(): void
    {
        $_SERVER['SERVER_PORT'] = '443';
        $this->assertTrue(RequestContext::isSecure());
    }

    public function testForwardedProtoIgnoredWithoutTrustedProxy(): void
    {
        // Header spoofabile da chiunque sulla LAN → va ignorato senza proxy fidato.
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertFalse(RequestContext::isSecure());
    }

    public function testForwardedProtoHonoredBehindTrustedProxy(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(RequestContext::isSecure());
    }

    public function testForwardedProtoHttpBehindTrustedProxyIsNotSecure(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $this->assertFalse(RequestContext::isSecure());
    }

    public function testForwardedProtoListTakesFirstValue(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https, http';
        $this->assertTrue(RequestContext::isSecure());
    }
}
