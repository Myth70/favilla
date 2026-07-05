<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Core\Container;
use App\Core\Router;
use App\Modules\Auth\Services\OidcHttpClient;
use App\Modules\Auth\Services\OidcService;
use App\Services\EncryptionService;
use Firebase\JWT\JWT;
use Tests\ModuleTestCase;
use Tests\Support\FakeRouter;

/**
 * Matrice di validazione del protocollo OIDC: firma JWKS (chiavi RSA generate
 * nel test), iss/aud/azp/nonce/exp, alg whitelist, retry su rotazione chiavi,
 * userinfo sub-binding, PKCE.
 */
final class OidcServiceTest extends ModuleTestCase
{
    private string $issuer;
    /** @var resource|\OpenSSLAsymmetricKey */
    private $keyPair;
    private string $privatePem;
    /** @var array<string,mixed> */
    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();

        Container::getInstance()->instance(Router::class, new FakeRouter());

        // Issuer unico per test: le cache su file non collidono mai tra run.
        $this->issuer = 'https://idp.test/' . bin2hex(random_bytes(6));

        $this->migrate('CREATE TABLE app_settings (`key` TEXT PRIMARY KEY, `value` TEXT, `type` TEXT, `group` TEXT, `label` TEXT, updated_at TEXT)');
        $insert = $this->pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`, `type`, `group`, `label`) VALUES (?, ?, ?, \'sso\', ?)'
        );
        foreach ([
            ['sso_oidc_enabled', '1', 'bool'],
            ['sso_oidc_issuer', $this->issuer, 'string'],
            ['sso_oidc_client_id', 'favilla-client', 'string'],
            ['sso_oidc_client_secret', '', 'string'],
            ['sso_oidc_scopes', 'openid profile email', 'string'],
        ] as [$key, $value, $type]) {
            $insert->execute([$key, $value, $type, $key]);
        }
        \App\Services\SettingsService::clearCache();

        // Coppia RSA reale: firma nel test, verifica nel service via JWKS.
        // Su Windows/XAMPP openssl richiede un openssl.cnf esistente (e i path
        // di sistema sono fuori open_basedir): usiamo la fixture minimale del
        // repo. Su Linux/CI il primo tentativo senza config va a buon fine.
        $args = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $exportArgs = null;
        $key = openssl_pkey_new($args);
        if ($key === false) {
            $cnf = BASE_PATH . '/tests/Support/openssl.cnf';
            $key = openssl_pkey_new($args + ['config' => $cnf]);
            $exportArgs = ['config' => $cnf];
        }
        if ($key === false) {
            $this->markTestSkipped('openssl_pkey_new non disponibile in questo ambiente.');
        }
        $this->keyPair = $key;
        $pem = '';
        openssl_pkey_export($key, $pem, null, $exportArgs);
        $this->privatePem = $pem;
        $details = openssl_pkey_get_details($this->keyPair);
        $b64u = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $this->jwks = ['keys' => [[
            'kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'alg' => 'RS256',
            'n' => $b64u($details['rsa']['n']), 'e' => $b64u($details['rsa']['e']),
        ]]];
    }

    protected function tearDown(): void
    {
        foreach (glob(BASE_PATH . '/storage/cache/oidc_*.json') ?: [] as $file) {
            @unlink($file);
        }
        \App\Services\SettingsService::clearCache();
        parent::tearDown();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function discoveryDoc(array $overrides = []): array
    {
        return array_merge([
            'issuer'                 => $this->issuer,
            'authorization_endpoint' => $this->issuer . '/authorize',
            'token_endpoint'         => $this->issuer . '/token',
            'jwks_uri'               => $this->issuer . '/jwks',
            'userinfo_endpoint'      => $this->issuer . '/userinfo',
        ], $overrides);
    }

    private function serviceWithHttp(OidcHttpClient $http): OidcService
    {
        return new OidcService($http, new EncryptionService());
    }

    /**
     * Client HTTP finto: discovery e JWKS serviti dai fixture.
     */
    private function httpReturning(?array $discovery = null, ?array $jwks = null): OidcHttpClient
    {
        $discovery ??= $this->discoveryDoc();
        $jwks ??= $this->jwks;

        $http = $this->createMock(OidcHttpClient::class);
        $http->method('getJson')->willReturnCallback(
            static function (string $url) use ($discovery, $jwks): ?array {
                if (str_contains($url, '.well-known')) {
                    return $discovery;
                }
                if (str_contains($url, '/jwks')) {
                    return $jwks;
                }

                return null;
            }
        );

        return $http;
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function signedToken(array $overrides = [], string $kid = 'test-key', string $alg = 'RS256'): string
    {
        $claims = array_merge([
            'iss'   => $this->issuer,
            'aud'   => 'favilla-client',
            'sub'   => 'user-123',
            'nonce' => 'nonce-abc',
            'iat'   => time(),
            'exp'   => time() + 300,
            'email' => 'anna@example.test',
        ], $overrides);

        return JWT::encode($claims, $this->privatePem, $alg, $kid);
    }

    // ------------------------------------------------------------------

    public function testValidTokenReturnsClaims(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $claims  = $service->validateIdToken($this->signedToken(), 'nonce-abc');

        $this->assertSame('user-123', $claims['sub']);
        $this->assertSame('anna@example.test', $claims['email']);
    }

    public function testWrongIssuerRejected(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/iss mismatch/');
        $service->validateIdToken($this->signedToken(['iss' => 'https://evil.test']), 'nonce-abc');
    }

    public function testWrongAudienceRejected(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/aud mismatch/');
        $service->validateIdToken($this->signedToken(['aud' => 'other-client']), 'nonce-abc');
    }

    public function testMultiAudienceWithoutAzpRejected(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/azp/');
        $service->validateIdToken(
            $this->signedToken(['aud' => ['favilla-client', 'other']]),
            'nonce-abc'
        );
    }

    public function testMultiAudienceWithAzpAccepted(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $claims  = $service->validateIdToken(
            $this->signedToken(['aud' => ['favilla-client', 'other'], 'azp' => 'favilla-client']),
            'nonce-abc'
        );
        $this->assertSame('user-123', $claims['sub']);
    }

    public function testWrongNonceRejected(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/nonce/');
        $service->validateIdToken($this->signedToken(), 'different-nonce');
    }

    public function testExpiredTokenRejectedBeyondLeeway(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/scaduto/');
        $service->validateIdToken(
            $this->signedToken(['iat' => time() - 600, 'exp' => time() - 300]),
            'nonce-abc'
        );
    }

    public function testAlgNoneRejectedBeforeAnyDecode(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $b64u = static fn (array $d): string => rtrim(strtr(base64_encode((string) json_encode($d)), '+/', '-_'), '=');
        $unsigned = $b64u(['alg' => 'none', 'typ' => 'JWT']) . '.' . $b64u(['sub' => 'user-123']) . '.';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/alg non consentito/');
        $service->validateIdToken($unsigned, 'nonce-abc');
    }

    public function testHs256Rejected(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $token = JWT::encode(['sub' => 'user-123'], str_repeat('shared-secret-', 4), 'HS256');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/alg non consentito/');
        $service->validateIdToken($token, 'nonce-abc');
    }

    public function testUnknownKidTriggersExactlyOneJwksRefetch(): void
    {
        $discovery = $this->discoveryDoc();
        $jwks      = $this->jwks;
        $jwksCalls = 0;

        $http = $this->createMock(OidcHttpClient::class);
        $http->method('getJson')->willReturnCallback(
            static function (string $url) use ($discovery, $jwks, &$jwksCalls): ?array {
                if (str_contains($url, '.well-known')) {
                    return $discovery;
                }
                if (str_contains($url, '/jwks')) {
                    $jwksCalls++;

                    return $jwks;
                }

                return null;
            }
        );
        $service = $this->serviceWithHttp($http);

        try {
            $service->validateIdToken($this->signedToken(kid: 'rotated-key'), 'nonce-abc');
            $this->fail('Token con kid ignoto accettato');
        } catch (\RuntimeException) {
            // atteso
        }

        $this->assertSame(2, $jwksCalls, 'Attesi esattamente 2 fetch JWKS (iniziale + un retry)');
    }

    public function testDiscoveryIssuerMismatchRejected(): void
    {
        $service = $this->serviceWithHttp(
            $this->httpReturning($this->discoveryDoc(['issuer' => 'https://other.test']))
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/issuer mismatch/i');
        $service->discovery();
    }

    public function testUserinfoSubMismatchIgnored(): void
    {
        $discovery = $this->discoveryDoc();
        $http = $this->createMock(OidcHttpClient::class);
        $http->method('getJson')->willReturnCallback(
            static function (string $url) use ($discovery): ?array {
                if (str_contains($url, '.well-known')) {
                    return $discovery;
                }
                if (str_contains($url, '/userinfo')) {
                    return ['sub' => 'ALTRO-utente', 'email' => 'evil@example.test'];
                }

                return null;
            }
        );
        $service = $this->serviceWithHttp($http);

        $this->assertNull($service->fetchUserinfo('access-token', 'user-123'));
    }

    public function testPkceChallengeIsS256OfVerifier(): void
    {
        $verifier = OidcService::generateCodeVerifier();
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $this->assertSame($expected, OidcService::codeChallenge($verifier));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43,128}$/', $verifier);
    }

    public function testAuthorizationUrlContainsAllParams(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());
        $url = $service->buildAuthorizationUrl('state-1', 'nonce-1', 'challenge-1');

        $this->assertStringStartsWith($this->issuer . '/authorize?', $url);
        foreach (['response_type=code', 'client_id=favilla-client', 'state=state-1', 'nonce=nonce-1',
            'code_challenge=challenge-1', 'code_challenge_method=S256'] as $fragment) {
            $this->assertStringContainsString($fragment, $url);
        }
    }

    public function testIdentityFromClaimsMergesUserinfoAndNormalizesEmailVerified(): void
    {
        $service = $this->serviceWithHttp($this->httpReturning());

        $identity = $service->identityFromClaims(
            ['sub' => 'user-123', 'email_verified' => 'true'],
            ['email' => 'anna@example.test', 'name' => 'Anna', 'preferred_username' => 'anna']
        );

        $this->assertSame('oidc', $identity['provider']);
        $this->assertSame($this->issuer, $identity['issuer']);
        $this->assertSame('anna@example.test', $identity['email']);
        $this->assertTrue($identity['email_verified']);
        $this->assertSame('anna', $identity['preferred_username']);
    }
}
