<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\MailController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for MailController via the HTTP harness.
 * Focus on the DB-free validation branches (invalid template / invalid test email).
 */
class MailControllerTest extends ControllerTestCase
{
    public function testStoreWithEmptyDataRedirectsToCreateWithErrors(): void
    {
        $this->actingAsAdmin();

        // Empty POST → validateTemplate() fails on the required slug BEFORE any DB lookup.
        $result = $this->withPost([])->dispatch(MailController::class, 'store');

        $this->assertTrue($result->isRedirect(), 'Un template non valido deve reindirizzare al form');
        $this->assertSame('/admin.mail.templates.create', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_errors'] ?? [], 'Devono essere salvati gli errori di validazione');
    }

    public function testSendTestRejectsInvalidEmailAndRedirects(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['test_email' => 'not-an-email'])
            ->dispatch(MailController::class, 'sendTest');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.mail.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'), 'Email non valida → flash di errore');
    }

    public function testSendTestRejectsEmptyEmailAndRedirects(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['test_email' => '   '])
            ->dispatch(MailController::class, 'sendTest');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.mail.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }
}
