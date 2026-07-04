<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Services\AuthService;
use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

/**
 * Il flusso remember-me non deve aggirare l'MFA: flagMfaIfRequired() deve
 * impostare gli stessi flag di sessione del login POST, così la guardia 5
 * di AuthMiddleware blocca la navigazione fino alla verifica del TOTP.
 */
class AuthServiceMfaRememberMeTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(new Container());
        $_SESSION = [];

        $this->service = new class () extends AuthService {
            public function __construct()
            {
                // Bypass container dependencies — flagMfaIfRequired non le usa.
            }
        };
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function bindTotp(bool $enabled, bool $setupRequired): void
    {
        $fake = new class ($enabled, $setupRequired) extends TotpService {
            public function __construct(
                private bool $fakeEnabled,
                private bool $fakeSetupRequired,
            ) {
                // Bypass container dependencies (PDO/EncryptionService).
            }

            public function isEnabled(int $userId): bool
            {
                return $this->fakeEnabled;
            }

            public function isSetupRequired(int $userId): bool
            {
                return $this->fakeSetupRequired;
            }
        };

        Container::getInstance()->instance(TotpService::class, $fake);
    }

    public function testSetsMfaPendingFlagsWhenTotpIsEnabled(): void
    {
        $this->bindTotp(enabled: true, setupRequired: false);

        $this->service->flagMfaIfRequired(7);

        $this->assertTrue($_SESSION['_mfa_required']);
        $this->assertSame(7, $_SESSION['_mfa_pending_user_id']);
        $this->assertArrayNotHasKey('_mfa_forced_setup', $_SESSION);
        $this->assertArrayNotHasKey('_mfa_verified', $_SESSION);
    }

    public function testSetsForcedSetupFlagsWhenSetupIsRequired(): void
    {
        $this->bindTotp(enabled: false, setupRequired: true);

        $this->service->flagMfaIfRequired(7);

        $this->assertTrue($_SESSION['_mfa_required']);
        $this->assertTrue($_SESSION['_mfa_forced_setup']);
        $this->assertArrayNotHasKey('_mfa_pending_user_id', $_SESSION);
    }

    public function testLeavesSessionUntouchedWhenMfaIsNotConfigured(): void
    {
        $this->bindTotp(enabled: false, setupRequired: false);

        $this->service->flagMfaIfRequired(7);

        $this->assertArrayNotHasKey('_mfa_required', $_SESSION);
        $this->assertArrayNotHasKey('_mfa_pending_user_id', $_SESSION);
        $this->assertArrayNotHasKey('_mfa_forced_setup', $_SESSION);
    }

    public function testFailsOpenWithoutFlagsWhenTotpServiceIsUnavailable(): void
    {
        // Nessun binding: la risoluzione di TotpService (→ PDO) fallisce.
        // Comportamento allineato al login POST: non-fatal, nessun flag.
        $this->service->flagMfaIfRequired(7);

        $this->assertArrayNotHasKey('_mfa_required', $_SESSION);
    }
}
