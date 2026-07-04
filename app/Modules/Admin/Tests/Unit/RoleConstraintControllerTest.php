<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\RoleConstraintController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for the Separation-of-Duties admin (RoleConstraintController).
 * Exercises the DB-free input-validation branches of store().
 */
class RoleConstraintControllerTest extends ControllerTestCase
{
    public function testStoreRejectsIdenticalRolesAndRedirects(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['role_id_1' => '5', 'role_id_2' => '5', 'reason' => 'x'])
            ->dispatch(RoleConstraintController::class, 'store');

        $this->assertTrue($result->isRedirect(), 'Due ruoli identici devono essere rifiutati');
        $this->assertSame('/admin.security.sod', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testStoreRejectsMissingRoleAndRedirects(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['role_id_1' => '0', 'role_id_2' => '3', 'reason' => 'x'])
            ->dispatch(RoleConstraintController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.security.sod', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testStoreRejectsEmptyReasonAndRedirects(): void
    {
        $this->actingAsAdmin();

        // Roles are valid and distinct, but the reason is empty → reason-required branch.
        $result = $this->withPost(['role_id_1' => '2', 'role_id_2' => '3', 'reason' => '   '])
            ->dispatch(RoleConstraintController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.security.sod', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }
}
