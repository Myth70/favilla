<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

class RateLimiter
{
    private PDO $pdo;
    private int $maxAttempts;
    private int $windowMinutes;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
        $this->maxAttempts = (int) config('app.rate_limit.login_max', 5);
        $this->windowMinutes = (int) config('app.rate_limit.login_window', 15);
    }

    /**
     * Check if the login attempt is rate-limited.
     *
     * Doppio bucket: per IP (come prima) e, se fornito, per account ($login).
     * Il bucket per account chiude l'evasione via rotazione di IP; il lockout
     * dura al massimo la finestra configurata (default 15 min).
     */
    public function isLimited(string $ip, ?string $login = null): bool
    {
        // Pulizia probabilistica: evita una DELETE su ogni check di login
        if (random_int(1, 100) === 1) {
            $this->cleanup();
        }

        if ($this->countFailedAttempts($ip) >= $this->maxAttempts) {
            return true;
        }

        return $login !== null && $login !== ''
            && $this->countFailedAttemptsForLogin($login) >= $this->maxAttempts;
    }

    /**
     * Record a login attempt.
     */
    public function record(string $email, string $ip, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $ip, $success ? 1 : 0]);
    }

    /**
     * Limite per la combinazione (IP + chiave account), entro la finestra
     * configurata. Usato da flussi non-login che registrano i tentativi in
     * login_attempts con una pseudo-email dedicata (es. reset password).
     */
    public function isLimitedForIpAndAccount(string $ip, string $email, int $maxAttempts): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND email = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $email, $this->windowMinutes]);

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    /**
     * Get remaining attempts (il più restrittivo tra bucket IP e bucket account).
     */
    public function remainingAttempts(string $ip, ?string $login = null): int
    {
        $remaining = $this->maxAttempts - $this->countFailedAttempts($ip);
        if ($login !== null && $login !== '') {
            $remaining = min($remaining, $this->maxAttempts - $this->countFailedAttemptsForLogin($login));
        }
        return max(0, $remaining);
    }

    /**
     * Count recent failed login attempts for an IP within the rate-limit window.
     */
    private function countFailedAttempts(string $ip): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $this->windowMinutes]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count recent failed login attempts for an account within the window.
     */
    private function countFailedAttemptsForLogin(string $login): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$login, $this->windowMinutes]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cleanup old login attempts (> 30 days).
     */
    private function cleanup(): void
    {
        $this->pdo->exec(
            'DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
    }
}
