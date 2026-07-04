<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\ImpersonationController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ImpersonationController via the HTTP harness.
 * Exercises the DB-free guard branches (cannot impersonate self / nothing to revert).
 */
class ImpersonationControllerTest extends ControllerTestCase
{
    public function testStartRejectsImpersonatingSelf(): void
    {
        // Authenticated as user 1; impersonating user 1 is rejected before any DB lookup.
        $this->actingAsAdmin(1);

        $result = $this->withPost([])->dispatch(ImpersonationController::class, 'start', ['1']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.users.show?id=1', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testRevertWithoutActiveImpersonationRedirectsHome(): void
    {
        $this->actingAsAdmin();

        // No _impersonator_id in session → service->revert() returns null.
        $result = $this->withPost([])->dispatch(ImpersonationController::class, 'revert');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/home', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }
}
