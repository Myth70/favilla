<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\ResetPasswordController;
use App\Security\RateLimiter;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ResetPasswordController via the HTTP harness.
 * The rate limiter is mocked so the DB-free validation branches of reset() and
 * the rate-limit guard of showForm() can be exercised.
 */
class ResetPasswordControllerTest extends ControllerTestCase
{
    private function bindLimiter(bool $limited): void
    {
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->method('isLimitedForIpAndAccount')->willReturn($limited);
        $this->bindInstance(RateLimiter::class, $limiter);
    }

    public function testResetRejectsEmptyPassword(): void
    {
        $this->bindLimiter(false);

        $result = $this->withPost(['password' => '', 'password_confirmation' => ''])
            ->dispatch(ResetPasswordController::class, 'reset', ['tok123']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/password.reset.form?token=tok123', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_reset_error'] ?? '');
    }

    public function testResetRejectsMismatchedPasswords(): void
    {
        $this->bindLimiter(false);

        $result = $this->withPost([
            'password'              => 'abcdefgh',
            'password_confirmation' => 'different1',
        ])->dispatch(ResetPasswordController::class, 'reset', ['tok123']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/password.reset.form?token=tok123', $result->redirectUrl());
        $this->assertStringContainsString('non corrispondono', $_SESSION['_reset_error'] ?? '');
    }

    public function testShowFormRedirectsWhenRateLimited(): void
    {
        $this->bindLimiter(true);

        $result = $this->dispatch(ResetPasswordController::class, 'showForm', ['tok123']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/password.forgot', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_reset_error'] ?? '');
    }
}
