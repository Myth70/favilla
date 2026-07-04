<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ImportResult;
use PHPUnit\Framework\TestCase;

class ImportResultTest extends TestCase
{
    public function testOkFactoryBuildsSuccessResult(): void
    {
        $r = ImportResult::ok('Contacts', ['step1'], ['warn1']);

        $this->assertTrue($r->success);
        $this->assertSame('Contacts', $r->moduleName);
        $this->assertSame(['step1'], $r->log);
        $this->assertSame(['warn1'], $r->warnings);
        $this->assertNull($r->error);
    }

    public function testFailFactoryBuildsFailureResult(): void
    {
        $r = ImportResult::fail('boom', 'Contacts', ['step1'], ['warn1']);

        $this->assertFalse($r->success);
        $this->assertSame('boom', $r->error);
        $this->assertSame('Contacts', $r->moduleName);
        $this->assertSame(['step1'], $r->log);
        $this->assertSame(['warn1'], $r->warnings);
    }

    public function testConstructorDefaults(): void
    {
        $r = new ImportResult(true);

        $this->assertTrue($r->success);
        $this->assertSame('', $r->moduleName);
        $this->assertSame([], $r->log);
        $this->assertSame([], $r->warnings);
        $this->assertNull($r->error);
    }
}
