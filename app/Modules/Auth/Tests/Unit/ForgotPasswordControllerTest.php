<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\ForgotPasswordController;
use App\Security\RateLimiter;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ForgotPasswordController via the HTTP harness.
 * The rate limiter is mocked for sendReset(); the other branches are DB-free.
 */
class ForgotPasswordControllerTest extends ControllerTestCase
{
    public function testShowFormRedirectsWhenAlreadyLoggedIn(): void
    {
        $this->actingAs(1);

        $result = $this->dispatch(ForgotPasswordController::class, 'showForm');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/home', $result->redirectUrl());
    }

    public function testShowFormRendersForGuest(): void
    {
        $result = $this->dispatch(ForgotPasswordController::class, 'showForm');

        $this->assertTrue($result->didRender());
        $this->assertSame('Auth/Views/forgot-password', $result->renderedTemplate());
    }

    public function testSendResetRejectsInvalidEmail(): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->method('isLimited')->willReturn(false);
        $this->bindInstance(RateLimiter::class, $limiter);

        $result = $this->withPost(['email' => 'not-an-email'])
            ->dispatch(ForgotPasswordController::class, 'sendReset');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/password.forgot', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_forgot_error'] ?? '');
    }
}
