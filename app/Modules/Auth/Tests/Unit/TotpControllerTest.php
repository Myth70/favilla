<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\TotpController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for TotpController (MFA) via the HTTP harness.
 * Covers the DB-free session-state guards and empty-code validation branches.
 */
class TotpControllerTest extends ControllerTestCase
{
    public function testShowChallengeRedirectsWithoutPendingUser(): void
    {
        $result = $this->dispatch(TotpController::class, 'showChallenge');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/login', $result->redirectUrl());
    }

    public function testVerifyChallengeRejectsEmptyCode(): void
    {
        $_SESSION['_mfa_pending_user_id'] = 7;

        $result = $this->withPost(['totp_code' => ''])
            ->dispatch(TotpController::class, 'verifyChallenge');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/mfa.challenge', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_mfa_error'] ?? '');
    }

    public function testVerifyForcedSetupRedirectsWhenNotLoggedIn(): void
    {
        $result = $this->withPost(['totp_code' => '123456'])
            ->dispatch(TotpController::class, 'verifyForcedSetup');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/login', $result->redirectUrl());
    }

    public function testVerifySetupRejectsEmptyCode(): void
    {
        $this->actingAs(7);

        $result = $this->withPost(['totp_code' => '  '])
            ->dispatch(TotpController::class, 'verifySetup');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/mfa.setup', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_mfa_setup_error'] ?? '');
    }

    public function testShowBackupCodesRedirectsWhenNoCodes(): void
    {
        $this->actingAs(7);

        $result = $this->dispatch(TotpController::class, 'showBackupCodes');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/profile', $result->redirectUrl());
    }
}
