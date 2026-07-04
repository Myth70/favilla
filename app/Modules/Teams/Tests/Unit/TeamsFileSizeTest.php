<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Support\TeamsFileSize;
use PHPUnit\Framework\TestCase;

/**
 * Unit test (puri, no DB) per TeamsFileSize::format.
 * Verifica boundary 1024 e formattazione decimali.
 */
class TeamsFileSizeTest extends TestCase
{
    public function testZeroBytes(): void
    {
        $this->assertSame('0 B', TeamsFileSize::format(0));
    }

    public function testNegativeIsClampedToZero(): void
    {
        $this->assertSame('0 B', TeamsFileSize::format(-100));
    }

    public function testSubKilobyteShowsBytes(): void
    {
        $this->assertSame('456 B', TeamsFileSize::format(456));
        $this->assertSame('1023 B', TeamsFileSize::format(1023));
    }

    public function testExactKilobyteIsInteger(): void
    {
        // Limite inferiore: round trip 1024 / 1024 = 1.0 → "1 KB" (no decimali)
        $this->assertSame('1 KB', TeamsFileSize::format(1024));
    }

    public function testKilobyteWithDecimal(): void
    {
        // 1536 / 1024 = 1.5 → "1.5 KB"
        $this->assertSame('1.5 KB', TeamsFileSize::format(1536));
    }

    public function testExactMegabyteIsInteger(): void
    {
        $this->assertSame('1 MB', TeamsFileSize::format(1048576));
    }

    public function testMegabyteWithDecimal(): void
    {
        // 1.5 MB ≈ 1572864
        $this->assertSame('1.5 MB', TeamsFileSize::format(1572864));
    }

    public function testGigabyteScale(): void
    {
        $this->assertSame('1 GB', TeamsFileSize::format(1073741824));
        $this->assertSame('2.5 GB', TeamsFileSize::format((int) (2.5 * 1073741824)));
    }

    public function testDecimalsParamControlsPrecision(): void
    {
        // 1.234... KB con decimals=2 → "1.23 KB"
        $bytes = (int) (1.234 * 1024);
        $this->assertSame('1.2 KB', TeamsFileSize::format($bytes, 1));
        $this->assertSame('1.23 KB', TeamsFileSize::format($bytes, 2));
    }

    public function testDecimalsZeroRoundsToInteger(): void
    {
        // 1.7 KB con decimals=0 → "2 KB"
        $bytes = (int) (1.7 * 1024);
        $this->assertSame('2 KB', TeamsFileSize::format($bytes, 0));
    }
}
