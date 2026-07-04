<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\UninstallResult;
use PHPUnit\Framework\TestCase;

class UninstallResultTest extends TestCase
{
    public function testOkFactoryBuildsSuccessResult(): void
    {
        $r = UninstallResult::ok(['removed table x'], ['kept data']);

        $this->assertTrue($r->success);
        $this->assertSame(['removed table x'], $r->log);
        $this->assertSame(['kept data'], $r->warnings);
        $this->assertNull($r->error);
    }

    public function testFailFactoryBuildsFailureResult(): void
    {
        $r = UninstallResult::fail('cannot uninstall core', ['attempted']);

        $this->assertFalse($r->success);
        $this->assertSame('cannot uninstall core', $r->error);
        $this->assertSame(['attempted'], $r->log);
        $this->assertSame([], $r->warnings);
    }

    public function testConstructorDefaults(): void
    {
        $r = new UninstallResult(true);

        $this->assertTrue($r->success);
        $this->assertSame([], $r->log);
        $this->assertSame([], $r->warnings);
        $this->assertNull($r->error);
    }
}
