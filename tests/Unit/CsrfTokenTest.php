<?php

namespace Tests\Unit;

use App\Security\CsrfToken;
use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        // Simulate session and env for tests
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_ENV['APP_KEY'] = 'test-key-for-unit-tests-only-32bytes00';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testGenerateReturnsNonEmptyString(): void
    {
        $token = CsrfToken::generate();

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testGenerateStoresTokenInSession(): void
    {
        CsrfToken::generate();

        $this->assertArrayHasKey('_csrf_token', $_SESSION);
        $this->assertNotEmpty($_SESSION['_csrf_token']);
    }

    public function testGetReturnsSameTokenWithoutRegeneration(): void
    {
        $token1 = CsrfToken::get();
        $token2 = CsrfToken::get();

        $this->assertSame($token1, $token2);
    }

    public function testVerifyAcceptsValidToken(): void
    {
        $token = CsrfToken::generate();

        $this->assertTrue(CsrfToken::verify($token));
    }

    public function testVerifyRejectsInvalidToken(): void
    {
        CsrfToken::generate();

        $this->assertFalse(CsrfToken::verify('invalid-token-value'));
    }

    public function testVerifyRejectsNullToken(): void
    {
        CsrfToken::generate();

        $this->assertFalse(CsrfToken::verify(null));
    }

    public function testVerifyRejectsEmptyToken(): void
    {
        CsrfToken::generate();

        $this->assertFalse(CsrfToken::verify(''));
    }

    public function testVerifyFailsWithNoSessionToken(): void
    {
        // No generate() called — no session token
        $this->assertFalse(CsrfToken::verify('some-token'));
    }

    public function testRegenerateProducesNewToken(): void
    {
        $token1 = CsrfToken::generate();
        $token2 = CsrfToken::regenerate();

        // The signed output may differ because the raw token changed
        // The session raw token should have changed
        $this->assertIsString($token2);
        $this->assertNotEmpty($token2);
    }

    public function testTokenIsHmacSigned(): void
    {
        $token = CsrfToken::generate();

        // HMAC-SHA256 produces 64 hex chars
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGetKeepsTokenJustBeforeTtlExpiry(): void
    {
        // TTL = 3600s. A un secondo dalla scadenza il token deve restare valido.
        CsrfToken::generate();
        $rawBefore = $_SESSION['_csrf_token'];
        $_SESSION['_csrf_token_created_at'] = time() - 3599;

        CsrfToken::get();

        $this->assertSame($rawBefore, $_SESSION['_csrf_token'], 'Token non deve rigenerarsi prima della scadenza');
    }

    public function testGetRegeneratesTokenAtTtlExpiry(): void
    {
        // Raggiunta la TTL (>= 3600s) il token deve essere rigenerato.
        CsrfToken::generate();
        $rawBefore = $_SESSION['_csrf_token'];
        $_SESSION['_csrf_token_created_at'] = time() - 3600;

        CsrfToken::get();

        $this->assertNotSame($rawBefore, $_SESSION['_csrf_token'], 'Token scaduto deve essere rigenerato');
    }

    public function testExpiredTokenNoLongerVerifies(): void
    {
        $signed = CsrfToken::generate();
        $_SESSION['_csrf_token_created_at'] = time() - 7200;

        // get() rigenera il raw token in sessione...
        CsrfToken::get();

        // ...quindi il vecchio token firmato non deve più verificare.
        $this->assertFalse(CsrfToken::verify($signed));
    }

    public function testRegenerateProducesDifferentRawTokenEachCall(): void
    {
        CsrfToken::regenerate();
        $first = $_SESSION['_csrf_token'];
        CsrfToken::regenerate();
        $second = $_SESSION['_csrf_token'];

        $this->assertNotSame($first, $second, 'regenerate() deve produrre un nuovo raw token');
    }
}
