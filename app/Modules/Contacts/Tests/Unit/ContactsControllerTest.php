<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Controllers\ContactsController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ContactsController via the HTTP harness.
 * Covers the DB-free validation branches of store() (which run before any
 * persistence).
 */
class ContactsControllerTest extends ControllerTestCase
{
    public function testStoreRejectsMissingNomeAndRedirects(): void
    {
        $this->actingAs(1);

        $result = $this->withPost([])->dispatch(ContactsController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/contacts.create', $result->redirectUrl());
        $this->assertArrayHasKey('nome', $_SESSION['_errors'] ?? []);
    }

    public function testStoreRejectsHalfFilledCoordinates(): void
    {
        $this->actingAs(1);

        // Valid nome but only latitude provided → coords-must-come-together error.
        $result = $this->withPost(['nome' => 'Mario', 'latitude' => '45.1', 'longitude' => ''])
            ->dispatch(ContactsController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/contacts.create', $result->redirectUrl());
        $errors = $_SESSION['_errors'] ?? [];
        $this->assertArrayHasKey('latitude', $errors);
        $this->assertArrayHasKey('longitude', $errors);
    }
}
