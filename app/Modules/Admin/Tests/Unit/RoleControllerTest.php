<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\RoleController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for RoleController via the HTTP harness.
 * Covers the DB-free validation branches of store() and the legacy
 * permissions() redirect shim.
 */
class RoleControllerTest extends ControllerTestCase
{
    public function testStoreRejectsMissingFieldsAndRedirects(): void
    {
        $this->actingAsAdmin();

        // Empty name + slug → validation fails before service->create().
        $result = $this->withPost([])->dispatch(RoleController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.roles.create', $result->redirectUrl());
        $this->assertArrayHasKey('name', $_SESSION['_errors'] ?? []);
        $this->assertArrayHasKey('slug', $_SESSION['_errors'] ?? []);
    }

    public function testStoreRejectsInvalidSlugFormat(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['name' => 'Editor', 'slug' => 'Not Valid Slug!'])
            ->dispatch(RoleController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.roles.create', $result->redirectUrl());
        $this->assertArrayHasKey('slug', $_SESSION['_errors'] ?? []);
    }

    public function testPermissionsRedirectsToEditAnchor(): void
    {
        $this->actingAsAdmin();

        $result = $this->dispatch(RoleController::class, 'permissions', ['7']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.roles.edit?id=7#permissions', $result->redirectUrl());
    }
}
