<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Exceptions\ExternalLoginDeniedException;
use App\Modules\Auth\Services\OidcService;
use App\Modules\Auth\Services\OidcTransactionStore;
use App\Security\RateLimiter;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\ExternalIdentityService;
use App\Support\ClientIp;
use App\Traits\ControllerHelpers;

/**
 * SSO OIDC: avvio del flusso authorization-code+PKCE e callback dall'IdP.
 *
 * Il cookie di sessione è SameSite=Strict, quindi il callback (navigazione
 * cross-site) arriva SENZA sessione: lo stato viaggia nel cookie di
 * transazione (OidcTransactionStore) e la risposta del callback non è mai un
 * 302 — è una pagina interstitial same-origin che naviga via client, così il
 * cookie della sessione appena creata viaggia dal passo successivo in poi.
 */
class OidcController extends Controller
{
    use ControllerHelpers;

    private const RATE_BUCKET = '__oidc__';

    public function __construct(
        private readonly OidcService $oidc,
        private readonly OidcTransactionStore $txnStore,
        private readonly ExternalIdentityService $identities,
        private readonly AuthService $authService,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function start(): void
    {
        if (!$this->oidc->isEnabled()) {
            $this->redirect(route('login'));

            return;
        }
        if (!empty($_SESSION['user_id'])) {
            $this->redirect(route('home'));

            return;
        }

        // Target post-login: qui la sessione è leggibile (navigazione same-site).
        $intended = (string) ($_SESSION['_intended_url'] ?? '');
        if (!AuthController::isSafeRedirectTarget($intended)) {
            $intended = '';
        }

        $state    = bin2hex(random_bytes(32));
        $nonce    = bin2hex(random_bytes(32));
        $verifier = OidcService::generateCodeVerifier();

        try {
            $authorizeUrl = $this->oidc->buildAuthorizationUrl(
                $state,
                $nonce,
                OidcService::codeChallenge($verifier)
            );
        } catch (\Throwable $e) {
            app_log('error', '[Oidc] start fallito: ' . $e->getMessage());
            $_SESSION['_login_error'] = t('auth.errors.sso_failed');
            $this->redirect(route('login'));

            return;
        }

        $this->txnStore->put([
            'state'    => $state,
            'nonce'    => $nonce,
            'verifier' => $verifier,
            'redirect' => $intended,
        ]);

        $this->redirect($authorizeUrl);
    }

    public function callback(): void
    {
        $ip = ClientIp::resolve();

        if ($this->rateLimiter->isLimited($ip, self::RATE_BUCKET)) {
            $this->failToLogin(t('auth.errors.too_many_attempts'), 'rate_limited');

            return;
        }

        // Single-use: letta e cancellata subito, qualunque cosa accada dopo.
        $txn = $this->txnStore->take();
        if ($txn === null) {
            $this->recordAttempt($ip, false);
            $this->failToLogin(t('auth.errors.sso_failed'), 'txn_missing_or_expired');

            return;
        }

        if (isset($_GET['error'])) {
            // Errore dell'IdP (es. access_denied): dettaglio nei log, mai in pagina.
            app_log('error', '[Oidc] IdP error al callback: ' . substr((string) $_GET['error'], 0, 100));
            $this->recordAttempt($ip, false);
            $this->failToLogin(t('auth.errors.sso_failed'), 'idp_error');

            return;
        }

        $state = (string) ($_GET['state'] ?? '');
        $code  = (string) ($_GET['code'] ?? '');
        if ($state === '' || $code === '' || !hash_equals((string) $txn['state'], $state)) {
            $this->recordAttempt($ip, false);
            $this->failToLogin(t('auth.errors.sso_failed'), 'state_mismatch');

            return;
        }

        try {
            $tokens = $this->oidc->exchangeCode($code, (string) $txn['verifier']);
            $claims = $this->oidc->validateIdToken((string) $tokens['id_token'], (string) $txn['nonce']);

            $userinfo = null;
            if (!isset($claims['email']) || !isset($claims['name'])) {
                $userinfo = $this->oidc->fetchUserinfo(
                    (string) ($tokens['access_token'] ?? ''),
                    (string) $claims['sub']
                );
            }

            $identity = $this->oidc->identityFromClaims($claims, $userinfo);
            $user     = $this->identities->resolveUser($identity);
        } catch (ExternalLoginDeniedException $e) {
            // resolveUser è l'unica sorgente di questa eccezione: $identity esiste.
            AuditService::log('sso_login_failed', 'auth', null, null, [
                'reason' => $e->reason(),
                'email'  => (string) ($identity['email'] ?? ''),
            ]);
            $this->recordAttempt($ip, false);
            $this->failToLogin(t('auth.errors.sso_denied'), $e->reason(), audit: false);

            return;
        } catch (\Throwable $e) {
            app_log('error', '[Oidc] callback fallito: ' . $e->getMessage());
            $this->recordAttempt($ip, false);
            $this->failToLogin(t('auth.errors.sso_failed'), 'protocol_error');

            return;
        }

        $this->authService->loginExternal($user, $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $this->recordAttempt($ip, true);
        AuditService::log('sso_login', 'auth', (int) $user['id'], null, [
            'provider' => $identity['provider'],
            'issuer'   => $identity['issuer'],
        ]);

        $target = (string) ($txn['redirect'] ?? '');
        if ($target === '' || !AuthController::isSafeRedirectTarget($target)) {
            $target = route('home');
        }
        unset($_SESSION['_intended_url']);

        $this->renderInterstitial($target);
    }

    // ------------------------------------------------------------------

    private function recordAttempt(string $ip, bool $success): void
    {
        try {
            $this->rateLimiter->record(self::RATE_BUCKET, $ip, $success);
        } catch (\Throwable $e) {
            app_log('error', '[Oidc] rate limiter record fallito: ' . $e->getMessage());
        }
    }

    private function failToLogin(string $userMessage, string $reason, bool $audit = true): void
    {
        if ($audit) {
            AuditService::log('sso_login_failed', 'auth', null, null, ['reason' => $reason]);
        }
        $_SESSION['_login_error'] = $userMessage;
        $this->renderInterstitial(route('login'));
    }

    /**
     * Risposta 200 same-origin che naviga via client: l'unico modo perché il
     * cookie di sessione Strict venga inviato al passo successivo.
     */
    private function renderInterstitial(string $targetUrl): void
    {
        $this->render('Auth/Views/oidc-interstitial', [
            'layout'    => 'auth',
            'authPage'  => true,
            'targetUrl' => $targetUrl,
            'pageTitle' => t('auth.login.sso_redirecting'),
        ]);
    }
}
