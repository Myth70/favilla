<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\RegistrazioneController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for RegistrazioneController via the HTTP harness.
 * Covers the DB-free branches: guest-only guards and required-field validation
 * (which short-circuits before any uniqueness query).
 */
class RegistrazioneControllerTest extends ControllerTestCase
{
    private array $savedEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedEnv = $_ENV;
        unset($_ENV['APP_EDITION']);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        parent::tearDown();
    }

    public function testShowFormRedirectsWhenLoggedIn(): void
    {
        $this->actingAs(1);

        $result = $this->dispatch(RegistrazioneController::class, 'showForm');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/home', $result->redirectUrl());
    }

    public function testShowFormRendersForGuest(): void
    {
        $result = $this->dispatch(RegistrazioneController::class, 'showForm');

        $this->assertTrue($result->didRender());
        $this->assertSame('Auth/Views/registrazione', $result->renderedTemplate());
    }

    public function testRegisterWithEmptyDataRedirectsWithErrors(): void
    {
        // Empty fields fail the required checks before isUsernameTaken/isEmailTaken run.
        $result = $this->withPost([])->dispatch(RegistrazioneController::class, 'register');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/registrazione', $result->redirectUrl());
        $errors = $_SESSION['_reg_errors'] ?? [];
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testShowFormRedirectsToLoginWhenSingleUser(): void
    {
        $_ENV['APP_EDITION'] = 'personal';

        $result = $this->dispatch(RegistrazioneController::class, 'showForm');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/login', $result->redirectUrl());
    }

    public function testRegisterDoesNotCreateUserWhenSingleUser(): void
    {
        $_ENV['APP_EDITION'] = 'personal';

        // Dati di registrazione validi: se il guard non intervenisse per primo,
        // l'esecuzione proseguirebbe fino a UserService::createInactiveUser(),
        // che qui fallirebbe rumorosamente (nessuna tabella `users` nello SQLite
        // di test) invece di essere silenziosamente ignorata.
        $result = $this->withPost([
            'name'             => 'Mario Rossi',
            'username'         => 'mariorossi',
            'email'            => 'mario@example.test',
            'email_confirm'    => 'mario@example.test',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
        ])->dispatch(RegistrazioneController::class, 'register');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/login', $result->redirectUrl());
    }
}
