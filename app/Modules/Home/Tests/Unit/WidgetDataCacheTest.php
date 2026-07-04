<?php

declare(strict_types=1);

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Services\WidgetDataCache;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression guard for the WidgetDataCache hardening:
 *  - cache files are read with allowed_classes:false (no object injection);
 *  - the key can never escape the cache directory (path traversal).
 */
class WidgetDataCacheTest extends TestCase
{
    private WidgetDataCache $cache;
    private string $dir;

    protected function setUp(): void
    {
        $this->cache = new WidgetDataCache();
        $this->dir = BASE_PATH . '/storage/cache/widgets';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/wdtest_*.cache') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function testArrayPayloadRoundTrips(): void
    {
        $this->cache->put('wdtest_ok', ['temp' => 21, 'sky' => 'clear'], 60);
        $this->assertSame(['temp' => 21, 'sky' => 'clear'], $this->cache->get('wdtest_ok'));
    }

    public function testSerializedObjectPayloadIsRejected(): void
    {
        // Simulate a poisoned cache file containing a serialized object.
        @mkdir($this->dir, 0775, true);
        file_put_contents(
            $this->dir . '/wdtest_obj.cache',
            serialize(new \ArrayObject(['expires' => PHP_INT_MAX, 'value' => ['x' => 1]]))
        );

        // allowed_classes:false → the object is never instantiated; treated as a miss.
        $this->assertFalse($this->cache->get('wdtest_obj'));
    }

    public function testKeyCannotEscapeCacheDirectory(): void
    {
        $path = new ReflectionMethod($this->cache, 'path');
        $path->setAccessible(true);

        $resolved = str_replace('\\', '/', (string) $path->invoke($this->cache, '../../../etc/passwd'));

        $this->assertStringContainsString('/storage/cache/widgets/', $resolved);
        $this->assertStringNotContainsString('..', $resolved);
    }
}
