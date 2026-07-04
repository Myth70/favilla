<?php

namespace Tests\Unit;

use App\Support\ConfigCache;
use PHPUnit\Framework\TestCase;

class ConfigCacheTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigCache::$data = [];
    }

    protected function tearDown(): void
    {
        ConfigCache::$data = [];
    }

    public function testStartsEmpty(): void
    {
        $this->assertSame([], ConfigCache::$data);
    }

    public function testCanStoreAndReadArbitraryData(): void
    {
        ConfigCache::$data['app'] = ['debug' => true, 'name' => 'Favilla'];
        $this->assertTrue(ConfigCache::$data['app']['debug']);
        $this->assertSame('Favilla', ConfigCache::$data['app']['name']);
    }

    public function testConfigFlushResetsCache(): void
    {
        ConfigCache::$data['x'] = ['k' => 1];
        config_flush();
        $this->assertSame([], ConfigCache::$data);
    }

    public function testConfigHelperUsesCache(): void
    {
        ConfigCache::$data['app'] = ['foo' => ['bar' => 'baz']];
        $this->assertSame('baz', config('app.foo.bar'));
        $this->assertSame('default', config('app.missing.key', 'default'));
    }

    public function testConfigReturnsDefaultForMissingFile(): void
    {
        $this->assertSame('fallback', config('nonexistent_file_xyz.key', 'fallback'));
    }
}
