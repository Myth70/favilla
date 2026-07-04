<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ModuleUninstallService;
use Tests\ModuleTestCase;

/**
 * Tests for ModuleUninstallService — the DB-free guard branches of uninstall()
 * and the filesystem-driven getDependentModules().
 */
class ModuleUninstallServiceTest extends ModuleTestCase
{
    public function testUninstallRejectsInvalidModuleName(): void
    {
        $result = ModuleUninstallService::uninstall('not a valid name');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('non valido', (string) $result->error);
    }

    public function testUninstallFailsForUnknownModule(): void
    {
        // Valid CamelCase, non-core, no dependents, but the directory does not exist.
        $result = ModuleUninstallService::uninstall('Zzzphantommodule');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('non trovato', (string) $result->error);
    }

    public function testGetDependentModulesReturnsEmptyForUnknownModule(): void
    {
        $dependents = ModuleUninstallService::getDependentModules('Zzzphantommodule');

        $this->assertSame([], $dependents);
    }
}
