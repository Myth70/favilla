<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\UserController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for UserController via the HTTP harness.
 * Covers the DB-free branches: form validation, self-delete guard and the
 * bulk-action JSON endpoint.
 */
class UserControllerTest extends ControllerTestCase
{
    public function testStoreWithInvalidDataRedirectsToCreate(): void
    {
        $this->actingAsAdmin();

        // Empty POST → Validator fails on required fields BEFORE any uniqueness query.
        $result = $this->withPost([])->dispatch(UserController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.users.create', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_errors'] ?? [], 'Devono essere salvati gli errori di validazione');
    }

    public function testDestroyRejectsSelfDeletion(): void
    {
        // actingAsAdmin() authenticates user_id = 1.
        $this->actingAsAdmin(1);

        $result = $this->withPost([])->dispatch(UserController::class, 'destroy', ['1']);

        $this->assertTrue($result->isRedirect(), 'Un admin non puo\' eliminare se stesso');
        $this->assertSame('/admin.users.show?id=1', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testBulkRejectsEmptySelectionAsJson(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['action' => 'activate', 'user_ids' => []])
            ->dispatch(UserController::class, 'bulk');

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['success']);
    }

    public function testBulkRejectsUnknownActionAsJson(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['action' => 'nuke', 'user_ids' => ['2', '3']])
            ->dispatch(UserController::class, 'bulk');

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['success']);
    }
}
