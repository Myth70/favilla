<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\OidcController;
use App\Modules\Auth\Services\OidcService;
use App\Modules\Auth\Services\OidcTransactionStore;
use App\Security\RateLimiter;
use App\Services\AuthService;
use App\Services\EncryptionService;
use App\Services\ExternalIdentityService;
use Tests\ControllerTestCase;

/**
 * Flusso HTTP di start/callback OIDC: guard di abilitazione, transazione
 * single-use, mismatch di state, errore IdP, happy path con interstitial
 * (mai redirect HTTP dal callback: cookie di sessione SameSite=Strict).
 */
final class OidcControllerTest extends ControllerTestCase
{
    private OidcService $oidc;
    private ExternalIdentityService $identities;
    private AuthService $auth;
    private RateLimiter $limiter;
    private OidcTransactionStore $txn;

    protected function setUp(): void
    {
        parent::setUp();

        // audit_logs per AuditService (i fallimenti sono comunque ingoiati)
        $this->migrate('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER, action TEXT, entity TEXT, entity_id INTEGER,
            old_value TEXT, new_value TEXT, ip TEXT, created_at TEXT)');

        $this->oidc       = $this->createMock(OidcService::class);
        $this->identities = $this->createMock(ExternalIdentityService::class);
        $this->auth       = $this->createMock(AuthService::class);
        $this->limiter    = $this->createMock(RateLimiter::class);
        $this->txn        = new OidcTransactionStore(new EncryptionService());
        $this->txn->clear();

        $this->bindInstance(OidcService::class, $this->oidc);
        $this->bindInstance(ExternalIdentityService::class, $this->identities);
        $this->bindInstance(AuthService::class, $this->auth);
        $this->bindInstance(RateLimiter::class, $this->limiter);
        $this->bindInstance(OidcTransactionStore::class, $this->txn);
    }

    protected function tearDown(): void
    {
        $this->txn->clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // /auth/oidc/start
    // ------------------------------------------------------------------

    public function testStartRedirectsToLoginWhenDisabled(): void
    {
        $this->oidc->method('isEnabled')->willReturn(false);

        $result = $this->dispatch(OidcController::class, 'start');

        $this->assertTrue($result->isRedirect());
        $this->assertSame(route('login'), $result->redirectUrl());
    }

    public function testStartStoresTransactionAndRedirectsToIdp(): void
    {
        $this->oidc->method('isEnabled')->willReturn(true);
        $this->oidc->method('buildAuthorizationUrl')->willReturn('https://idp.test/authorize?x=1');
        $_SESSION['_intended_url'] = '/tasks';

        $result = $this->dispatch(OidcController::class, 'start');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('https://idp.test/authorize?x=1', $result->redirectUrl());

        $txn = $this->txn->take();
        $this->assertNotNull($txn);
        $this->assertNotEmpty($txn['state']);
        $this->assertNotEmpty($txn['nonce']);
        $this->assertNotEmpty($txn['verifier']);
        $this->assertSame('/tasks', $txn['redirect']);
    }

    // ------------------------------------------------------------------
    // /auth/oidc/callback
    // ------------------------------------------------------------------

    public function testCallbackWithoutTransactionShowsGenericFailure(): void
    {
        $this->limiter->method('isLimited')->willReturn(false);

        $result = $this->dispatch(OidcController::class, 'callback');

        $this->assertTrue($result->didRender());
        $this->assertStringContainsString('oidc-interstitial', (string) $result->renderedTemplate());
        $this->assertSame(route('login'), $result->renderedData()['targetUrl']);
        $this->assertNotEmpty($_SESSION['_login_error'] ?? '');
    }

    public function testCallbackStateMismatchFails(): void
    {
        $this->limiter->method('isLimited')->willReturn(false);
        $this->txn->put(['state' => 'expected', 'nonce' => 'n', 'verifier' => 'v', 'redirect' => '']);

        $result = $this->withGet(['state' => 'DIVERSO', 'code' => 'abc'])
            ->dispatch(OidcController::class, 'callback');

        $this->assertStringContainsString('oidc-interstitial', (string) $result->renderedTemplate());
        $this->assertSame(route('login'), $result->renderedData()['targetUrl']);
        $this->assertNotEmpty($_SESSION['_login_error'] ?? '');
        // La transazione è single-use: consumata anche in caso di fallimento.
        $this->assertNull($this->txn->take());
    }

    public function testCallbackIdpErrorFailsWithoutLeakingDetails(): void
    {
        $this->limiter->method('isLimited')->willReturn(false);
        $this->txn->put(['state' => 's', 'nonce' => 'n', 'verifier' => 'v', 'redirect' => '']);

        $result = $this->withGet(['error' => 'access_denied', 'error_description' => 'internal secret detail'])
            ->dispatch(OidcController::class, 'callback');

        $this->assertStringContainsString('oidc-interstitial', (string) $result->renderedTemplate());
        $this->assertStringNotContainsString('internal secret detail', (string) ($_SESSION['_login_error'] ?? ''));
    }

    public function testCallbackRateLimitedFailsBeforeProcessing(): void
    {
        $this->limiter->method('isLimited')->willReturn(true);
        $this->oidc->expects($this->never())->method('exchangeCode');

        $result = $this->dispatch(OidcController::class, 'callback');

        $this->assertStringContainsString('oidc-interstitial', (string) $result->renderedTemplate());
    }

    public function testCallbackHappyPathLogsInAndRendersInterstitialToIntendedTarget(): void
    {
        $this->limiter->method('isLimited')->willReturn(false);
        $this->txn->put(['state' => 'st', 'nonce' => 'no', 'verifier' => 've', 'redirect' => '/tasks']);

        $claims = ['sub' => 'sub-1', 'email' => 'anna@example.test', 'name' => 'Anna'];
        $identity = [
            'provider' => 'oidc', 'issuer' => 'https://idp.test', 'subject' => 'sub-1',
            'email' => 'anna@example.test', 'email_verified' => true, 'name' => 'Anna',
            'preferred_username' => 'anna',
        ];
        $user = ['id' => 5, 'name' => 'Anna', 'email' => 'anna@example.test', 'username' => 'anna', 'must_change_password' => 0];

        $this->oidc->method('exchangeCode')->with('code-1', 've')
            ->willReturn(['id_token' => 'jwt', 'access_token' => 'at']);
        $this->oidc->method('validateIdToken')->with('jwt', 'no')->willReturn($claims);
        $this->oidc->method('identityFromClaims')->willReturn($identity);
        $this->oidc->expects($this->never())->method('fetchUserinfo');
        $this->identities->method('resolveUser')->with($identity)->willReturn($user);
        $this->auth->expects($this->once())->method('loginExternal')->with($user, $this->anything(), $this->anything());

        $result = $this->withGet(['state' => 'st', 'code' => 'code-1'])
            ->dispatch(OidcController::class, 'callback');

        $this->assertTrue($result->didRender());
        $this->assertStringContainsString('oidc-interstitial', (string) $result->renderedTemplate());
        $this->assertSame('/tasks', $result->renderedData()['targetUrl']);
        $this->assertEmpty($_SESSION['_login_error'] ?? '');

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'sso_login'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testCallbackDeniedIdentityShowsPolicyMessage(): void
    {
        $this->limiter->method('isLimited')->willReturn(false);
        $this->txn->put(['state' => 'st', 'nonce' => 'no', 'verifier' => 've', 'redirect' => '']);

        $this->oidc->method('exchangeCode')->willReturn(['id_token' => 'jwt']);
        $this->oidc->method('validateIdToken')->willReturn(['sub' => 's', 'email' => 'x@y.z', 'name' => 'X']);
        $this->oidc->method('identityFromClaims')->willReturn([
            'provider' => 'oidc', 'issuer' => 'i', 'subject' => 's',
            'email' => 'x@y.z', 'email_verified' => true, 'name' => 'X', 'preferred_username' => null,
        ]);
        $this->identities->method('resolveUser')
            ->willThrowException(new \App\Exceptions\ExternalLoginDeniedException(
                \App\Exceptions\ExternalLoginDeniedException::NO_LOCAL_ACCOUNT
            ));
        $this->auth->expects($this->never())->method('loginExternal');

        $result = $this->withGet(['state' => 'st', 'code' => 'c'])
            ->dispatch(OidcController::class, 'callback');

        $this->assertStringContainsString('oidc-interstitial', (string) $result->renderedTemplate());
        $this->assertSame(t('auth.errors.sso_denied'), $_SESSION['_login_error'] ?? '');
    }
}
