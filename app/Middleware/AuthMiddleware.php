<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpRedirectException;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Verify that the user is authenticated.
     * Redirects to login page if not.
     */
    public function handle(callable $next): void
    {
        $loginUrl = route('login');

        // Redirect compatibile con HTMX: usa HX-Redirect per le richieste HTMX
        // in modo che HTMX esegua una navigazione full-page invece di iniettare
        // l'HTML della pagina di login/redirect nel target dell'elemento corrente.
        $isHtmx = ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
        $redirect = function (string $url) use ($isHtmx): never {
            throw new HttpRedirectException($url, $isHtmx);
        };

        // Guard 1: utente non autenticato
        if (empty($_SESSION['user_id'])) {
            $_SESSION['_intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $redirect($loginUrl);
        }

        // Guard 2: timeout sessione PHP
        $lifetime     = (int) config('app.session.lifetime', 480) * 60;
        $lastActivity = $_SESSION['_last_activity'] ?? 0;

        if ($lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $p['path'],
                    $p['domain'],
                    $p['secure'],
                    $p['httponly']
                );
            }
            session_destroy();
            $redirect($loginUrl);
        }

        // Guard 3: sessione revocata lato DB
        // Il check avviene al massimo ogni 60 secondi per non colpire il DB ad ogni request.
        $dbSessionId      = $_SESSION['_db_session_id'] ?? null;
        $lastDbCheck      = $_SESSION['_session_db_checked_at'] ?? 0;
        $dbCheckInterval  = 60; // secondi

        if ($dbSessionId && (time() - $lastDbCheck) >= $dbCheckInterval) {
            $pdo  = \App\Core\Container::getInstance()->make(\PDO::class);
            $stmt = $pdo->prepare(
                'SELECT token_hash FROM sessions WHERE id = ? AND expires_at > NOW()'
            );
            $stmt->execute([$dbSessionId]);
            $row = $stmt->fetch();
            $expectedHash = hash('sha256', session_id());
            if (!$row || !hash_equals((string) $row['token_hash'], $expectedHash)) {
                $_SESSION = [];
                session_destroy();
                $redirect($loginUrl);
            }
            $_SESSION['_session_db_checked_at'] = time();
        }

        // Guard 3.5: staleness permessi
        // Se i permessi dichiarati dai moduli sono stati risincronizzati dopo
        // l'ultimo caricamento della sessione, rigeneriamo user_permissions senza
        // richiedere logout/login. Throttled a 60s per non interrogare il DB ad ogni request.
        $this->refreshStalePermissions();

        // Guard 3.7: impersonazione scaduta — il timeout impostato da
        // ImpersonationService::start() viene applicato qui: revert automatico
        // alla sessione admin originale.
        if (\App\Modules\Admin\Services\ImpersonationService::sessionIsExpired($_SESSION)) {
            try {
                $impersonation = \App\Core\Container::getInstance()
                    ->make(\App\Modules\Admin\Services\ImpersonationService::class);
                $payload = $impersonation->revert($_SESSION);
            } catch (\Throwable $e) {
                app_log('error', self::class . '::handle impersonation revert failed: ' . $e->getMessage());
                $payload = null;
            }

            if ($payload !== null) {
                foreach (array_keys($_SESSION) as $key) {
                    unset($_SESSION[$key]);
                }
                foreach ($payload['session_replace'] as $key => $value) {
                    $_SESSION[$key] = $value;
                }
                $cookie = $payload['cookie'];
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);

                flash_error('Sessione di impersonazione scaduta: sei tornato al tuo account.');
                $redirect(route('admin.dashboard'));
            }

            // Fail-closed: se il revert non è possibile l'identità impersonata
            // non deve proseguire oltre la scadenza.
            $_SESSION = [];
            session_destroy();
            $redirect($loginUrl);
        }

        // Aggiornamento timestamp
        $_SESSION['_last_activity'] = time();

        // Aggiorna last_activity nel DB ogni 5 minuti
        if ($dbSessionId) {
            $lastSync = $_SESSION['_last_db_sync'] ?? 0;
            if ((time() - $lastSync) > 300) {
                $pdo = $pdo ?? \App\Core\Container::getInstance()->make(\PDO::class);
                $pdo->prepare('UPDATE sessions SET last_activity = NOW() WHERE id = ?')
                    ->execute([$dbSessionId]);
                $_SESSION['_last_db_sync'] = time();
            }
        }

        // Guard 4: cambio password forzato
        if (!empty($_SESSION['must_change_password'])) {
            $changeUrl  = route('password.change');
            $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $changeUri  = parse_url($changeUrl, PHP_URL_PATH);
            if ($currentUri !== $changeUri) {
                $redirect($changeUrl);
            }
        }

        // Guard 5: ISO 27001 A.9.4.2 — MFA pending verification
        if (!empty($_SESSION['_mfa_required']) && empty($_SESSION['_mfa_verified'])) {
            $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $allowedPaths = [
                parse_url(route('mfa.challenge'), PHP_URL_PATH),
                parse_url(route('mfa.challenge.verify'), PHP_URL_PATH),
                parse_url(route('mfa.setup.forced'), PHP_URL_PATH),
                parse_url(route('mfa.setup.forced.verify'), PHP_URL_PATH),
                parse_url(route('mfa.backup.show'), PHP_URL_PATH),
                parse_url(route('password.change'), PHP_URL_PATH),
                parse_url(route('logout'), PHP_URL_PATH),
            ];
            if (!in_array($currentUri, $allowedPaths, true)) {
                $setupRequired = false;
                try {
                    $totp = \App\Core\Container::getInstance()->make(\App\Services\TotpService::class);
                    $setupRequired = !$totp->isEnabled($_SESSION['user_id'])
                                  && $totp->isSetupRequired($_SESSION['user_id']);
                } catch (\Throwable $e) {
                    app_log('error', self::class . '::handle TOTP probe failed: ' . $e->getMessage());
                }

                $mfaUrl = $setupRequired ? route('mfa.setup.forced') : route('mfa.challenge');
                $redirect($mfaUrl);
            }
        }

        $next();
    }

    /**
     * Rinfresca $_SESSION['user_permissions'] se un sync globale dei permessi
     * e' avvenuto dopo l'ultimo caricamento. Throttled a 60s per non tassare il DB.
     */
    private function refreshStalePermissions(): void
    {
        $lastCheck = $_SESSION['_permissions_staleness_checked_at'] ?? 0;
        if ((time() - $lastCheck) < 60) {
            return;
        }
        $_SESSION['_permissions_staleness_checked_at'] = time();

        $loadedAt = (int) ($_SESSION['_permissions_loaded_at'] ?? 0);
        if ($loadedAt === 0) {
            // Sessione pre-esistente senza marker: impostiamo ora per non ricaricare ogni request.
            $_SESSION['_permissions_loaded_at'] = time();
            return;
        }

        try {
            $pdo  = \App\Core\Container::getInstance()->make(\PDO::class);
            $stmt = $pdo->prepare('SELECT `value` FROM app_settings WHERE `key` = ?');
            $stmt->execute(['permissions_last_sync_at']);
            $syncAtStr = $stmt->fetchColumn();

            if ($syncAtStr === false || $syncAtStr === null) {
                return;
            }

            $syncAt = strtotime((string) $syncAtStr);
            if ($syncAt === false || $syncAt <= $loadedAt) {
                return;
            }

            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                return;
            }

            $authService = \App\Core\Container::getInstance()->make(\App\Services\AuthService::class);
            $authService->refreshPermissions($userId);
        } catch (\Throwable) {
            // Silent-fail: lo staleness check non deve mai bloccare una request autenticata.
        }
    }
}
