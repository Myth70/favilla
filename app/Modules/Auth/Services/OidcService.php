<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Services\EncryptionService;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;

/**
 * Protocollo OIDC (authorization code + PKCE, provider singolo):
 * discovery con cache su file, costruzione authorize URL, scambio del code,
 * validazione completa dell'ID token (firma via JWKS con retry su rotazione
 * chiavi, iss/aud/azp/nonce/exp), fallback userinfo.
 *
 * Gli errori di protocollo sollevano RuntimeException con dettagli PER I LOG;
 * il controller mostra all'utente solo messaggi generici t().
 */
class OidcService
{
    private const CACHE_TTL   = 3600;
    private const ALLOWED_ALG = ['RS256', 'ES256'];
    private const JWT_LEEWAY  = 60;

    public function __construct(
        private readonly OidcHttpClient $http,
        private readonly EncryptionService $encryption,
    ) {
    }

    // ------------------------------------------------------------------
    // Configurazione
    // ------------------------------------------------------------------

    public function isEnabled(): bool
    {
        return (bool) setting('sso_oidc_enabled', false)
            && !is_single_user()
            && $this->issuer() !== ''
            && (string) setting('sso_oidc_client_id', '') !== '';
    }

    public function issuer(): string
    {
        return rtrim(trim((string) setting('sso_oidc_issuer', '')), '/');
    }

    private function clientId(): string
    {
        return (string) setting('sso_oidc_client_id', '');
    }

    private function clientSecret(): string
    {
        $stored = (string) setting('sso_oidc_client_secret', '');
        if ($stored === '') {
            return '';
        }
        if ($this->encryption->isEncrypted($stored)) {
            try {
                return $this->encryption->decrypt($stored);
            } catch (\Throwable $e) {
                app_log('error', '[Oidc] Decifratura client secret fallita (APP_KEY cambiata?): ' . $e->getMessage());

                return '';
            }
        }

        return $stored;
    }

    // ------------------------------------------------------------------
    // PKCE
    // ------------------------------------------------------------------

    public static function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    public static function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    // ------------------------------------------------------------------
    // Discovery + JWKS (cache su file, pattern WeatherService)
    // ------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    public function discovery(): array
    {
        $issuer = $this->issuer();
        if ($issuer === '') {
            throw new \RuntimeException('Issuer OIDC non configurato');
        }

        $cached = $this->cacheRead('oidc_discovery_' . md5($issuer));
        if ($cached !== null) {
            return $cached;
        }

        $doc = $this->http->getJson($issuer . '/.well-known/openid-configuration');
        if ($doc === null) {
            throw new \RuntimeException('Discovery OIDC non raggiungibile: ' . $issuer);
        }

        // Difesa mix-up: l'issuer dichiarato dal documento deve combaciare.
        $docIssuer = rtrim((string) ($doc['issuer'] ?? ''), '/');
        if ($docIssuer !== $issuer) {
            throw new \RuntimeException("Discovery issuer mismatch: atteso {$issuer}, ricevuto {$docIssuer}");
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $required) {
            if (empty($doc[$required])) {
                throw new \RuntimeException("Discovery OIDC incompleto: manca {$required}");
            }
        }

        $this->cacheWrite('oidc_discovery_' . md5($issuer), $doc);

        return $doc;
    }

    /**
     * @return array<string,mixed>
     */
    private function jwks(bool $bustCache = false): array
    {
        $jwksUri  = (string) $this->discovery()['jwks_uri'];
        $cacheKey = 'oidc_jwks_' . md5($jwksUri);

        if ($bustCache) {
            $this->cacheDelete($cacheKey);
        } else {
            $cached = $this->cacheRead($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $jwks = $this->http->getJson($jwksUri);
        if ($jwks === null || empty($jwks['keys'])) {
            throw new \RuntimeException('JWKS OIDC non raggiungibile o vuoto: ' . $jwksUri);
        }

        $this->cacheWrite($cacheKey, $jwks);

        return $jwks;
    }

    // ------------------------------------------------------------------
    // Flusso
    // ------------------------------------------------------------------

    public function buildAuthorizationUrl(string $state, string $nonce, string $codeChallenge): string
    {
        $params = [
            'response_type'         => 'code',
            'client_id'             => $this->clientId(),
            'redirect_uri'          => route('oidc.callback'),
            'scope'                 => (string) setting('sso_oidc_scopes', 'openid profile email'),
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        $endpoint = (string) $this->discovery()['authorization_endpoint'];
        $glue     = str_contains($endpoint, '?') ? '&' : '?';

        return $endpoint . $glue . http_build_query($params);
    }

    /**
     * Scambia l'authorization code: client_secret_basic con retry
     * client_secret_post su 400/401 (IdP permalosi).
     *
     * @return array<string,mixed> token response (id_token garantito presente)
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $endpoint = (string) $this->discovery()['token_endpoint'];
        $fields   = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => route('oidc.callback'),
            'code_verifier' => $codeVerifier,
        ];

        $result = $this->http->postForm($endpoint, $fields, [$this->clientId(), $this->clientSecret()]);

        if (in_array($result['status'], [400, 401], true)) {
            $fields['client_id']     = $this->clientId();
            $fields['client_secret'] = $this->clientSecret();
            $result = $this->http->postForm($endpoint, $fields);
        }

        $body = $result['body'];
        if ($result['status'] !== 200 || !is_array($body) || empty($body['id_token'])) {
            $detail = is_array($body) ? (string) ($body['error'] ?? 'risposta non valida') : 'nessun corpo';
            throw new \RuntimeException("Scambio code fallito (HTTP {$result['status']}): {$detail}");
        }

        return $body;
    }

    /**
     * Validazione completa dell'ID token. Ritorna i claims come array.
     *
     * @return array<string,mixed>
     */
    public function validateIdToken(string $idToken, string $expectedNonce): array
    {
        // 1. alg whitelist PRIMA di qualunque decode (rifiuta none/HS*).
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \RuntimeException('ID token malformato');
        }
        $header = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        $alg    = is_array($header) ? (string) ($header['alg'] ?? '') : '';
        if (!in_array($alg, self::ALLOWED_ALG, true)) {
            throw new \RuntimeException("ID token con alg non consentito: '{$alg}'");
        }

        // 2. Firma + exp/nbf/iat (leeway per clock skew); retry singolo con
        //    cache JWKS invalidata per coprire la rotazione delle chiavi.
        //    ExpiredException (sottoclasse di UnexpectedValueException) non è
        //    un problema di chiavi: niente retry.
        JWT::$leeway = self::JWT_LEEWAY;
        try {
            $claims = JWT::decode($idToken, JWK::parseKeySet($this->jwks(), 'RS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new \RuntimeException('ID token scaduto: ' . $e->getMessage());
        } catch (SignatureInvalidException | \UnexpectedValueException $first) {
            try {
                $claims = JWT::decode($idToken, JWK::parseKeySet($this->jwks(true), 'RS256'));
            } catch (\Throwable) {
                throw new \RuntimeException('Verifica firma ID token fallita: ' . $first->getMessage());
            }
        }

        $claims = (array) json_decode((string) json_encode($claims), true);

        // 3. iss: identico all'issuer configurato (già ≡ discovery.issuer).
        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $this->issuer()) {
            throw new \RuntimeException('ID token iss mismatch: ' . (string) ($claims['iss'] ?? '(vuoto)'));
        }

        // 4. aud contiene il client_id; se multi-audience serve azp.
        $aud = $claims['aud'] ?? [];
        $aud = is_array($aud) ? $aud : [$aud];
        if (!in_array($this->clientId(), $aud, true)) {
            throw new \RuntimeException('ID token aud mismatch');
        }
        if (count($aud) > 1 && (string) ($claims['azp'] ?? '') !== $this->clientId()) {
            throw new \RuntimeException('ID token multi-audience senza azp valido');
        }

        // 5. nonce: binding alla transazione del browser che ha iniziato il flusso.
        if (!hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new \RuntimeException('ID token nonce mismatch');
        }

        // 6. sub obbligatorio.
        if (trim((string) ($claims['sub'] ?? '')) === '') {
            throw new \RuntimeException('ID token senza sub');
        }

        return $claims;
    }

    /**
     * Userinfo endpoint per integrare claims mancanti; il sub DEVE combaciare
     * con quello dell'ID token (OIDC Core §5.3.2), altrimenti si ignora tutto.
     *
     * @return array<string,mixed>|null
     */
    public function fetchUserinfo(string $accessToken, string $expectedSub): ?array
    {
        $endpoint = (string) ($this->discovery()['userinfo_endpoint'] ?? '');
        if ($endpoint === '' || $accessToken === '') {
            return null;
        }

        $info = $this->http->getJson($endpoint, ['Authorization: Bearer ' . $accessToken]);
        if ($info === null) {
            return null;
        }
        if ((string) ($info['sub'] ?? '') !== $expectedSub) {
            app_log('error', '[Oidc] userinfo sub mismatch — risposta ignorata');

            return null;
        }

        return $info;
    }

    /**
     * Identità normalizzata per ExternalIdentityService: i claims dell'ID
     * token vincono, userinfo riempie solo i buchi.
     *
     * @param array<string,mixed> $claims
     * @param array<string,mixed>|null $userinfo
     * @return array{provider:string, issuer:string, subject:string,
     *               email:?string, email_verified:?bool, name:?string,
     *               preferred_username:?string}
     */
    public function identityFromClaims(array $claims, ?array $userinfo): array
    {
        $pick = static function (string $key) use ($claims, $userinfo): mixed {
            return $claims[$key] ?? ($userinfo[$key] ?? null);
        };

        $emailVerified = $pick('email_verified');
        if (is_string($emailVerified)) { // alcuni IdP lo serializzano come stringa
            $emailVerified = $emailVerified === 'true' || $emailVerified === '1';
        }

        return [
            'provider'           => 'oidc',
            'issuer'             => $this->issuer(),
            'subject'            => (string) $claims['sub'],
            'email'              => is_string($pick('email')) ? $pick('email') : null,
            'email_verified'     => is_bool($emailVerified) ? $emailVerified : null,
            'name'               => is_string($pick('name')) ? $pick('name') : null,
            'preferred_username' => is_string($pick('preferred_username')) ? $pick('preferred_username') : null,
        ];
    }

    /**
     * Verifica di configurazione per il pannello Admin: discovery raggiungibile,
     * endpoints presenti, alg compatibili. Non richiede il client secret.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(?string $issuerOverride = null): array
    {
        $issuer = rtrim(trim($issuerOverride ?? $this->issuer()), '/');
        if ($issuer === '') {
            return ['ok' => false, 'message' => t('admin.settings.sso_test_no_issuer')];
        }

        $doc = $this->http->getJson($issuer . '/.well-known/openid-configuration');
        if ($doc === null) {
            return ['ok' => false, 'message' => t('admin.settings.sso_test_unreachable')];
        }
        if (rtrim((string) ($doc['issuer'] ?? ''), '/') !== $issuer) {
            return ['ok' => false, 'message' => t('admin.settings.sso_test_issuer_mismatch')];
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $required) {
            if (empty($doc[$required])) {
                return ['ok' => false, 'message' => t('admin.settings.sso_test_missing_endpoint', ['endpoint' => $required])];
            }
        }
        $algs = (array) ($doc['id_token_signing_alg_values_supported'] ?? ['RS256']);
        if (array_intersect(self::ALLOWED_ALG, $algs) === []) {
            return ['ok' => false, 'message' => t('admin.settings.sso_test_no_supported_alg')];
        }

        return ['ok' => true, 'message' => t('admin.settings.sso_test_ok_detail')];
    }

    // ------------------------------------------------------------------
    // Cache su file (storage/cache)
    // ------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    private function cacheRead(string $key): ?array
    {
        $path = $this->cachePath($key);
        if (!is_file($path) || (time() - (int) filemtime($path)) > self::CACHE_TTL) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function cacheWrite(string $key, array $data): void
    {
        $dir = dirname($this->cachePath($key));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->cachePath($key) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) !== false) {
            @rename($tmp, $this->cachePath($key)); // write atomico
        }
    }

    private function cacheDelete(string $key): void
    {
        $path = $this->cachePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function cachePath(string $key): string
    {
        return BASE_PATH . '/storage/cache/' . $key . '.json';
    }
}
