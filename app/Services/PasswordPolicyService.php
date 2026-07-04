<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ISO 27001 A.9.4.3 — Password policy enforcement.
 *
 * Validates password complexity, rotation, and history.
 * All thresholds are configurable via app_settings (group: security).
 */
class PasswordPolicyService
{
    private PDO $pdo;

    /** Default policy values (overridden by app_settings). */
    private const DEFAULTS = [
        'password_min_length'       => 12,
        'password_require_upper'    => true,
        'password_require_lower'    => true,
        'password_require_digit'    => true,
        'password_require_special'  => true,
        'password_max_age_days'     => 90,
        'password_history_count'    => 5,
        'password_policy_enabled'   => true,
    ];

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    // ------------------------------------------------------------------
    // Configuration helpers
    // ------------------------------------------------------------------

    /**
     * Get a policy setting from app_settings or fall back to default.
     */
    private function setting(string $key): mixed
    {
        return setting($key, self::DEFAULTS[$key] ?? null);
    }

    /**
     * Whether the password policy is globally enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->setting('password_policy_enabled');
    }

    // ------------------------------------------------------------------
    // Complexity validation
    // ------------------------------------------------------------------

    /**
     * Validate a plaintext password against the policy.
     *
     * @return string[] Array of error messages (empty = valid).
     */
    public function validate(string $password, ?int $userId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $errors = [];
        $minLen = (int) $this->setting('password_min_length');

        if (mb_strlen($password) < $minLen) {
            $errors[] = "La password deve contenere almeno {$minLen} caratteri.";
        }

        if ($this->setting('password_require_upper') && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La password deve contenere almeno una lettera maiuscola.';
        }

        if ($this->setting('password_require_lower') && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'La password deve contenere almeno una lettera minuscola.';
        }

        if ($this->setting('password_require_digit') && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'La password deve contenere almeno un numero.';
        }

        if ($this->setting('password_require_special') && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'La password deve contenere almeno un carattere speciale.';
        }

        // History check
        if ($userId !== null) {
            $historyErrors = $this->checkHistory($password, $userId);
            if ($historyErrors) {
                $errors[] = $historyErrors;
            }
        }

        return $errors;
    }

    /**
     * Return a human-readable summary of the current policy rules (for UI display).
     */
    public function getRulesDescription(): array
    {
        if (!$this->isEnabled()) {
            return ['Almeno 8 caratteri.'];
        }

        $rules = [];
        $rules[] = 'Almeno ' . (int) $this->setting('password_min_length') . ' caratteri.';

        if ($this->setting('password_require_upper')) {
            $rules[] = 'Almeno una lettera maiuscola.';
        }
        if ($this->setting('password_require_lower')) {
            $rules[] = 'Almeno una lettera minuscola.';
        }
        if ($this->setting('password_require_digit')) {
            $rules[] = 'Almeno un numero.';
        }
        if ($this->setting('password_require_special')) {
            $rules[] = 'Almeno un carattere speciale (!@#$%...).';
        }

        $historyCount = (int) $this->setting('password_history_count');
        if ($historyCount > 0) {
            $rules[] = "Non può essere uguale alle ultime {$historyCount} password.";
        }

        return $rules;
    }

    // ------------------------------------------------------------------
    // Password history
    // ------------------------------------------------------------------

    /**
     * Check if the new password was recently used.
     *
     * @return string|null Error message or null if OK.
     */
    private function checkHistory(string $newPassword, int $userId): ?string
    {
        $historyCount = (int) $this->setting('password_history_count');
        if ($historyCount <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT password_hash FROM password_history
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $historyCount]);
        $hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Also check the current password in users table
        $stmtCurrent = $this->pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmtCurrent->execute([$userId]);
        $currentHash = $stmtCurrent->fetchColumn();
        if ($currentHash) {
            array_unshift($hashes, $currentHash);
        }

        foreach ($hashes as $hash) {
            if (password_verify($newPassword, $hash)) {
                return "La password non può essere uguale alle ultime {$historyCount} password utilizzate.";
            }
        }

        return null;
    }

    /**
     * Record a password hash in the history table. Call AFTER successful password change.
     */
    public function recordInHistory(int $userId, string $hashedPassword): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_history (user_id, password_hash, created_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$userId, $hashedPassword]);

        // Prune old entries beyond the configured count
        $keep = (int) $this->setting('password_history_count');
        if ($keep > 0) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM password_history
                 WHERE user_id = ? AND id NOT IN (
                     SELECT id FROM (
                         SELECT id FROM password_history
                         WHERE user_id = ?
                         ORDER BY created_at DESC
                         LIMIT ?
                     ) AS keep_rows
                 )'
            );
            $stmt->execute([$userId, $userId, $keep]);
        }
    }

    /**
     * Update the password_changed_at timestamp. Call AFTER successful password change.
     */
    public function touchPasswordChangedAt(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE users SET password_changed_at = NOW() WHERE id = ?'
        )->execute([$userId]);
    }

    // ------------------------------------------------------------------
    // Password rotation (expiry)
    // ------------------------------------------------------------------

    /**
     * Check if a user's password has expired.
     */
    public function isPasswordExpired(int $userId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $maxAgeDays = (int) $this->setting('password_max_age_days');
        if ($maxAgeDays <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT password_changed_at FROM users WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$userId]);
        $changedAt = $stmt->fetchColumn();

        // If never set, the password has effectively expired (force change)
        if (!$changedAt) {
            return true;
        }

        $expiryDate = (new \DateTimeImmutable($changedAt))->modify("+{$maxAgeDays} days");
        return new \DateTimeImmutable() > $expiryDate;
    }

    /**
     * Days until password expires (for UI warnings). Returns null if rotation disabled.
     */
    public function daysUntilExpiry(int $userId): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $maxAgeDays = (int) $this->setting('password_max_age_days');
        if ($maxAgeDays <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT password_changed_at FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $changedAt = $stmt->fetchColumn();

        if (!$changedAt) {
            return 0;
        }

        $expiryDate = (new \DateTimeImmutable($changedAt))->modify("+{$maxAgeDays} days");
        $diff = (new \DateTimeImmutable())->diff($expiryDate);

        return $diff->invert ? 0 : $diff->days;
    }
}
