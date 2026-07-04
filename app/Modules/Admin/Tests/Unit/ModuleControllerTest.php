<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\ModuleController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ModuleController via the HTTP harness.
 * Covers the DB-free upload-validation branches of import() and the static
 * importForm() render. The download (export) and uninstall paths use raw
 * header()+exit / streaming and belong to the Integration suite.
 */
class ModuleControllerTest extends ControllerTestCase
{
    public function testImportWithoutFileRedirectsWithError(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost([])->dispatch(ModuleController::class, 'import');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.modules.import', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testImportRejectsNonZipExtension(): void
    {
        $this->actingAsAdmin();
        $this->withPost([]);
        $_FILES['module_zip'] = [
            'name'     => 'payload.txt',
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => '/tmp/whatever',
        ];

        $result = $this->dispatch(ModuleController::class, 'import');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.modules.import', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testImportRejectsInvalidDbOverride(): void
    {
        $this->actingAsAdmin();
        $this->withPost(['db_name_override' => '1nvalid-name']);
        $_FILES['module_zip'] = [
            'name'     => 'mod.zip',
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => '/tmp/whatever',
        ];

        $result = $this->dispatch(ModuleController::class, 'import');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.modules.import', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testImportFormRenders(): void
    {
        $this->actingAsAdmin();

        $result = $this->dispatch(ModuleController::class, 'importForm');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/modules/import', $result->renderedTemplate());
        $this->assertNull($result->renderedData()['result']);
    }
}
