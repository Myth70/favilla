<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Events\UserLoggedIn;
use App\Repositories\UserRepository;
use App\Security\RateLimiter;
use App\Support\ClientIp;
use PDO;

class AuthService
{
    private UserRepository $userRepo;
    private RateLimiter $rateLimiter;
    private PDO $pdo;

    public function __construct()
    {
        $this->userRepo = app(UserRepository::class);
        $this->rateLimiter = app(RateLimiter::class);
        $this->pdo = app(PDO::class);
    }

    /**
     * Attempt login. Returns ['success' => bool, 'user' => ?array, 'error' => ?string].
     */
    public function attempt(string $login, string $password, string $ip, string $userAgent): array
    {
        // Rate limiting: bucket per IP + bucket per account (anti IP-rotation)
        if ($this->rateLimiter->isLimited($ip, $login)) {
            return [
                'success' => false,
                'user' => null,
                'error' => t('auth.errors.too_many_attempts'),
            ];
        }

        // Find user — always run password_verify to prevent timing-based user enumeration
        $user = $this->userRepo->findByLogin($login);
        $hash = $user['password'] ?? '$argon2id$v=19$m=65536,t=4,p=1$dW5rbm93bg$dW5rbm93bg';
        $passwordValid = password_verify($password, $hash);

        if (!$user || !$passwordValid) {
            $this->rateLimiter->record($login, $ip, false);
            $remaining = $this->rateLimiter->remainingAttempts($ip, $login);

            // ISO 27001 A.16.1 — Check for brute-force incident
            try {
                $incidentService = app(SecurityIncidentService::class);
                $incidentService->checkBruteForce($ip, $login);
            } catch (\Throwable) {
                // Non-fatal
            }

            return [
                'success' => false,
                'user' => null,
                'error' => t('auth.errors.invalid_credentials', ['remaining' => $remaining]),
            ];
        }

        // Success
        $this->rateLimiter->record($login, $ip, true);
        $this->createSession($user, $ip, $userAgent);

        // ISO 27001 A.9.4.3 — Enforce concurrent session limit
        try {
            $sessionLimiter = app(SessionLimiterService::class);
            $dbSessionId = $_SESSION['_db_session_id'] ?? 0;
            $sessionLimiter->enforce($user['id'], $dbSessionId);
        } catch (\Throwable $e) {
            app_log('error', '[AuthService] SessionLimiter fallito: ' . $e->getMessage());
        }

        // Fire event via dispatcher
        $event = new UserLoggedIn($user['id'], $ip, $userAgent);
        EventDispatcher::getInstance()->dispatch($event);

        return [
            'success' => true,
            'user' => $user,
            'error' => null,
        ];
    }

    /**
     * Stabilisce la sessione per un utente autenticato ESTERNAMENTE (OIDC/LDAP).
     * Stesso percorso di attempt() dopo la verifica password, ma senza
     * rate-limiter (nessuna superficie di guessing) e senza flag MFA locale:
     * il secondo fattore è delegato all'Identity Provider.
     */
    public function loginExternal(array $user, string $ip, string $userAgent): void
    {
        $this->createSession($user, $ip, $userAgent);

        // ISO 27001 A.9.4.3 — Enforce concurrent session limit
        try {
            $sessionLimiter = app(SessionLimiterService::class);
            $sessionLimiter->enforce($user['id'], $_SESSION['_db_session_id'] ?? 0);
        } catch (\Throwable $e) {
            app_log('error', '[AuthService] SessionLimiter fallito: ' . $e->getMessage());
        }

        EventDispatcher::getInstance()->dispatch(new UserLoggedIn($user['id'], $ip, $userAgent));
    }

    /**
     * Create PHP session and DB session record.
     */
    private function createSession(array $user, string $ip, string $userAgent): void
    {
        // Pulizia probabilistica (1% dei login) delle sessioni scadute da più di 1 giorno
        if (random_int(1, 100) === 1) {
            $this->pdo->exec(
                'DELETE FROM sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
            );
        }

        // Regenerate session ID and CSRF token to prevent fixation
        session_regenerate_id(true);
        \App\Security\CsrfToken::regenerate();

        // Load user with permissions
        $fullUser = $this->userRepo->findWithPermissions($user['id']);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_roles'] = array_column($fullUser['roles'] ?? [], 'slug');
        $_SESSION['user_permissions'] = $fullUser['permissions'] ?? [];
        $_SESSION['must_change_password'] = (bool) $user['must_change_password'];
        $_SESSION['user_avatar'] = $fullUser['avatar_path'] ?? null;
        $_SESSION['user_language'] = $this->resolveUserLanguage((int) $user['id']);
        $_SESSION['_last_activity'] = time();
        $_SESSION['_login_ip'] = $ip;
        $_SESSION['_permissions_loaded_at'] = time();

        // Write DB session record
        $tokenHash = hash('sha256', session_id());
        $lifetime = (int) config('app.session.lifetime', 480);
        $expiresAt = date('Y-m-d H:i:s', time() + ($lifetime * 60));

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (user_id, token_hash, ip, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $tokenHash, $ip, mb_substr($userAgent, 0, 500), $expiresAt]);
        $_SESSION['_db_session_id'] = (int) $this->pdo->lastInsertId();
    }

    /**
     * Resolve the user's stored language preference for session priming.
     * Falls back to the configured default when unset/unsupported. A cookie
     * chosen before login still wins later via LocaleResolver (cookie step).
     */
    private function resolveUserLanguage(int $userId): string
    {
        $supported = (array) config('localization.supported', ['it']);
        $default   = (string) config('localization.default', 'it');
        $fallback  = in_array($default, $supported, true) ? $default : ($supported[0] ?? 'it');

        try {
            $stmt = $this->pdo->prepare('SELECT language FROM user_preferences WHERE user_id = ?');
            $stmt->execute([$userId]);
            $lang = $stmt->fetchColumn();
            if (is_string($lang) && in_array($lang, $supported, true)) {
                return $lang;
            }
        } catch (\Throwable) {
            // Column may not exist yet (pre-migration) — use the default.
        }

        return $fallback;
    }

    /**
     * Ricarica dal DB ruoli e permessi dell'utente corrente e aggiorna la sessione,
     * senza richiedere logout/login. Da invocare quando i permessi vengono risincronizzati
     * (es. post `context:generate` o import modulo).
     *
     * Ritorna true se la sessione e' stata aggiornata, false se utente non esiste piu'.
     */
    public function refreshPermissions(int $userId): bool
    {
        $fullUser = $this->userRepo->findWithPermissions($userId);
        if (!$fullUser) {
            return false;
        }

        $_SESSION['user_roles']             = array_column($fullUser['roles'] ?? [], 'slug');
        $_SESSION['user_permissions']       = $fullUser['permissions'] ?? [];
        $_SESSION['_permissions_loaded_at'] = time();

        return true;
    }

    /**
     * Logout: destroy PHP session, remove DB session record, clear remember token.
     */
    public function logout(): void
    {
        $dbSessionId = $_SESSION['_db_session_id'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;

        // Clear remember token
        if ($userId) {
            $this->clearRememberToken($userId);
        }

        // Log the logout
        if ($userId) {
            $ip = ClientIp::resolve();
            $stmt = $this->pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                 VALUES (?, 'logout', 'user', ?, ?)"
            );
            $stmt->execute([$userId, $userId, $ip]);
        }

        // Delete DB session record
        if ($dbSessionId) {
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->execute([$dbSessionId]);
        }

        // Destroy PHP session
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        // Rimuovi cookie impersonazione se presente
        if (isset($_COOKIE['favilla_impersonating'])) {
            $basePath = env('APP_BASE_PATH', '') ?: '/';
            $secure = \App\Support\RequestContext::isSecure();
            setcookie('favilla_impersonating', '', [
                'expires'  => time() - 3600,
                'path'     => $basePath,
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Strict',
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Remember Me
    // ------------------------------------------------------------------

    /**
     * Set a remember-me token cookie + DB hash.
     */
    public function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->pdo->prepare('UPDATE users SET remember_token = ? WHERE id = ?')
            ->execute([$tokenHash, $userId]);

        $expires = time() + (30 * 24 * 60 * 60); // 30 days
        $secure = \App\Support\RequestContext::isSecure();

        setcookie('remember_token', $userId . '|' . $token, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
    }

    /**
     * Try to auto-login from remember-me cookie. Returns user array or null.
     */
    public function loginFromRememberToken(): ?array
    {
        $cookie = $_COOKIE['remember_token'] ?? null;
        if (!$cookie) {
            return null;
        }

        $parts = explode('|', $cookie, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$userId, $token] = $parts;
        $userId = (int) $userId;
        $tokenHash = hash('sha256', $token);

        $user = $this->userRepo->find($userId);
        if (!$user || !$user['is_active'] || $user['deleted_at']) {
            $this->clearRememberCookie();
            return null;
        }

        if (empty($user['remember_token']) || !hash_equals($user['remember_token'], $tokenHash)) {
            $this->clearRememberCookie();
            return null;
        }

        // Valid! Create session
        $ip = ClientIp::resolve();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->createSession($user, $ip, $ua);

        // ISO 27001 A.9.4.2 — il remember-me non deve aggirare l'MFA: imposta gli
        // stessi flag del login POST così la guardia in AuthMiddleware blocca la
        // navigazione finché il secondo fattore non è verificato.
        $this->flagMfaIfRequired((int) $user['id']);

        // Fire login event
        $event = new UserLoggedIn($user['id'], $ip, $ua);
        EventDispatcher::getInstance()->dispatch($event);

        // Rotate token for security
        $this->setRememberToken($user['id']);

        return $user;
    }

    /**
     * ISO 27001 A.9.4.2 — Imposta i flag di sessione della guardia MFA se
     * l'utente ha il TOTP attivo (o il setup obbligatorio in sospeso).
     * Da chiamare in ogni flusso di login alternativo al POST (remember-me)
     * per non aggirare il secondo fattore.
     */
    public function flagMfaIfRequired(int $userId): void
    {
        try {
            $totpService = app(TotpService::class);
            if ($totpService->isEnabled($userId)) {
                $_SESSION['_mfa_required'] = true;
                $_SESSION['_mfa_pending_user_id'] = $userId;
            } elseif ($totpService->isSetupRequired($userId)) {
                $_SESSION['_mfa_required'] = true;
                $_SESSION['_mfa_forced_setup'] = true;
            }
        } catch (\Throwable $e) {
            // Non-fatal: TOTP tables may not exist yet — log per diagnostica
            app_log('error', '[AuthService] MFA check su remember-me fallito: ' . $e->getMessage());
        }
    }

    /**
     * Clear remember token from DB.
     */
    public function clearRememberToken(int $userId): void
    {
        $this->pdo->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')
            ->execute([$userId]);

        $this->clearRememberCookie();
    }

    /**
     * Clear the remember cookie.
     */
    private function clearRememberCookie(): void
    {
        $secure = \App\Support\RequestContext::isSecure();
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
    }

    /**
     * Genera token reset e invia email reset se l'utente esiste.
     */
    public function processForgotPassword(string $email): void
    {
        $user = $this->userRepo->findByEmail($email);
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        // Invalida eventuali token precedenti non usati per evitare concorrenza.
        $this->pdo->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
        )->execute([(int) $user['id']]);

        $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
        )->execute([(int) $user['id'], $tokenHash]);

        $this->pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
             VALUES (?, 'password_forgot_reset', 'user', ?, ?)"
        )->execute([(int) $user['id'], (int) $user['id'], ClientIp::resolve()]);

        $resetLink = route('password.reset.form', ['token' => $token]);

        $mailService = app(MailService::class);
        $mailService->sendFromTemplate($email, 'password-reset', [
            'name'   => $user['name'],
            'link'   => $resetLink,
            'expiry' => '1440',
        ]);
    }

    /**
     * Verifica se un token reset è valido e non scaduto.
     *
     * @return array{id:int,user_id:int,email:string,name:string}|null
     */
    public function validatePasswordResetToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            'SELECT pr.id, pr.user_id, u.email, u.name
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ?
               AND pr.used_at IS NULL
               AND pr.expires_at >= NOW()
               AND u.deleted_at IS NULL
               AND u.is_active = 1
             ORDER BY pr.id DESC
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Consuma un token reset e imposta una nuova password.
     */
    public function consumePasswordResetToken(string $token, string $newPassword): bool
    {
        $reset = $this->validatePasswordResetToken($token);
        if (!$reset) {
            return false;
        }

        $policy = app(PasswordPolicyService::class);
        $errors = $policy->validate($newPassword, (int) $reset['user_id']);
        if (!empty($errors)) {
            return false;
        }

        $userService = app(UserService::class);

        $this->pdo->beginTransaction();
        try {
            $ok = $userService->changePassword((int) $reset['user_id'], $newPassword);
            if (!$ok) {
                $this->pdo->rollBack();
                return false;
            }

            // Marca esplicitamente il token corrente come usato.
            $this->pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                ->execute([(int) $reset['id']]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }
}
