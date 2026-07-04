<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Support;

use App\Modules\HealthCheck\Support\Bytes;
use PHPUnit\Framework\TestCase;

class BytesTest extends TestCase
{
    public function testParseMegabytes(): void
    {
        $this->assertSame(128 * 1024 * 1024, Bytes::parse('128M'));
    }

    public function testParseGigabytes(): void
    {
        $this->assertSame(2 * 1024 * 1024 * 1024, Bytes::parse('2G'));
    }

    public function testParseKilobytes(): void
    {
        $this->assertSame(512 * 1024, Bytes::parse('512K'));
    }

    public function testParsePlainNumber(): void
    {
        $this->assertSame(1024, Bytes::parse('1024'));
    }

    public function testParseIsCaseInsensitive(): void
    {
        $this->assertSame(64 * 1024 * 1024, Bytes::parse('64m'));
    }

    public function testParseEmptyStringIsZero(): void
    {
        $this->assertSame(0, Bytes::parse('   '));
    }

    public function testHumanReadable(): void
    {
        $this->assertSame('1 KB', Bytes::human(1024));
        $this->assertSame('1.5 MB', Bytes::human((int) (1.5 * 1024 * 1024)));
        $this->assertSame('0 B', Bytes::human(0));
    }
}
