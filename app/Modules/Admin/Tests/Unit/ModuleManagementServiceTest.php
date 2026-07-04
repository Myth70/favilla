<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ModuleManagementService;
use Tests\ModuleTestCase;

/**
 * Tests for ModuleManagementService — the config/filesystem-driven methods that
 * do not need the module_states table (getCoreModules / scanPermissions).
 */
class ModuleManagementServiceTest extends ModuleTestCase
{
    public function testGetCoreModulesReturnsOnlyCoreModules(): void
    {
        $service = new ModuleManagementService();

        $core = $service->getCoreModules();

        $this->assertNotEmpty($core, 'La configurazione dichiara dei moduli core');
        foreach ($core as $module) {
            $this->assertTrue(($module['core'] ?? false) === true, "Il modulo {$module['name']} deve essere core");
        }
    }

    public function testScanPermissionsReturnsArray(): void
    {
        $service = new ModuleManagementService();

        // Reads app/Modules/Admin/permissions.php from disk (no DB involved).
        $permissions = $service->scanPermissions('Admin');

        $this->assertIsArray($permissions);
    }
}
