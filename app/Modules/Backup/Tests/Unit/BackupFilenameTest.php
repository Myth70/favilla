<?php

namespace App\Modules\Backup\Tests\Unit;

use App\Modules\Backup\Services\BackupService;
use PHPUnit\Framework\TestCase;

/**
 * validateFilename deve accettare sia i set multi-DB (.zip) sia i backup legacy
 * (.sql.gz), e bloccare path traversal / estensioni arbitrarie.
 */
class BackupFilenameTest extends TestCase
{
    private BackupService $svc;

    protected function setUp(): void
    {
        $this->svc = new BackupService();
    }

    public function testAcceptsZipSet(): void
    {
        $this->assertTrue($this->svc->validateFilename('backup_20260530_164000.zip'));
    }

    public function testAcceptsLegacySqlGz(): void
    {
        $this->assertTrue($this->svc->validateFilename('backup_20260530_164000.sql.gz'));
    }

    public function testRejectsPathTraversal(): void
    {
        $this->assertFalse($this->svc->validateFilename('../etc/passwd'));
        $this->assertFalse($this->svc->validateFilename('backup_20260530_164000.zip/../x'));
        $this->assertFalse($this->svc->validateFilename('/abs/backup_20260530_164000.zip'));
    }

    public function testRejectsArbitraryExtensionOrPattern(): void
    {
        $this->assertFalse($this->svc->validateFilename('backup_20260530_164000.tar'));
        $this->assertFalse($this->svc->validateFilename('backup_20260530_164000.sql'));
        $this->assertFalse($this->svc->validateFilename('backup_2026_164000.zip'));
        $this->assertFalse($this->svc->validateFilename('dump_20260530_164000.zip'));
    }

    public function testIsZipBackup(): void
    {
        $this->assertTrue($this->svc->isZipBackup('backup_20260530_164000.zip'));
        $this->assertTrue($this->svc->isZipBackup('BACKUP_20260530_164000.ZIP'));
        $this->assertFalse($this->svc->isZipBackup('backup_20260530_164000.sql.gz'));
    }
}
