<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ISO 27001 A.9.4.2 — TOTP (RFC 6238) and MFA management.
 *
 * Implements TOTP generation/verification per RFC 6238 (HMAC-SHA1, 6 digits, 30s).
 * Secrets are encrypted at rest via EncryptionService (AES-256-GCM).
 * Backup codes use bcrypt hashing (one-time use).
 */
class TotpService
{
    private const ALGORITHM = 'sha1';
    private const DIGITS    = 6;
    private const PERIOD    = 30;
    private const WINDOW    = 1; // Accept ±1 time step (±30s tolerance)
    private const SECRET_LENGTH = 20; // 160-bit secret (RFC 4226 recommended)

    private PDO $pdo;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->pdo        = app(PDO::class);
        $this->encryption = app(EncryptionService::class);
    }

    // ------------------------------------------------------------------
    // Secret management
    // ------------------------------------------------------------------

    /**
     * Generate a new TOTP secret for a user (not yet enabled).
     * If one exists, replaces it (re-setup).
     *
     * @return string Base32-encoded secret (for QR code / manual entry)
     */
    public function generateSecret(int $userId): string
    {
        $rawSecret = random_bytes(self::SECRET_LENGTH);
        $base32    = self::base32Encode($rawSecret);

        $encrypted = $this->encryption->encrypt($base32);

        // Upsert: replace if exists
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_totp_secrets (user_id, secret, algorithm, digits, period, enabled, verified_at)
             VALUES (?, ?, ?, ?, ?, 0, NULL)
             ON DUPLICATE KEY UPDATE secret = VALUES(secret), enabled = 0, verified_at = NULL, updated_at = NOW()'
        );
        $stmt->execute([$userId, $encrypted, self::ALGORITHM, self::DIGITS, self::PERIOD]);

        // Clear any existing backup codes (will regenerate after verification)
        $this->pdo->prepare('DELETE FROM user_totp_backup_codes WHERE user_id = ?')->execute([$userId]);

        return $base32;
    }

    /**
     * Build the otpauth:// URI for QR code generation.
     */
    public function getProvisioningUri(string $base32Secret, string $userEmail): string
    {
        $issuer = config('app.name', 'Favilla');
        $label  = rawurlencode($issuer . ':' . $userEmail);
        $params = http_build_query([
            'secret'    => $base32Secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code during setup (first-time confirmation).
     * Enables MFA and generates backup codes if valid.
     *
     * @return array{valid: bool, backup_codes?: string[]}
     */
    public function verifySetup(int $userId, string $code): array
    {
        $record = $this->getRecord($userId);
        if (!$record) {
            return ['valid' => false];
        }

        $secret   = $this->decryptSecret($record['secret']);
        $timestep = $secret ? $this->matchTimestep($secret, $code) : null;
        if ($timestep === null) {
            return ['valid' => false];
        }

        // Enable TOTP
        $this->pdo->prepare(
            'UPDATE user_totp_secrets SET enabled = 1, verified_at = NOW() WHERE user_id = ?'
        )->execute([$userId]);

        // Il codice usato per il setup non deve essere riutilizzabile al login.
        $this->storeLastUsedTimestep($userId, $timestep);

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes($userId);

        AuditService::log('mfa_enabled', 'user', $userId, null, ['method' => 'totp']);

        return ['valid' => true, 'backup_codes' => $backupCodes];
    }

    /**
     * Verify a TOTP code during login.
     */
    public function verifyLogin(int $userId, string $code): bool
    {
        // First try TOTP code
        $record = $this->getRecord($userId);
        if (!$record || !$record['enabled']) {
            return false;
        }

        $secret = $this->decryptSecret($record['secret']);
        if ($secret) {
            $timestep = $this->matchTimestep($secret, $code);
            if ($timestep !== null) {
                // Anti-replay (RFC 6238 §5.2): un codice già consumato non è
                // riutilizzabile entro la finestra di tolleranza.
                $lastUsed = $record['last_used_timestep'] ?? null;
                if ($lastUsed !== null && $timestep <= (int) $lastUsed) {
                    return false;
                }
                $this->storeLastUsedTimestep($userId, $timestep);
                return true;
            }
        }

        // Try backup code (strips spaces/dashes from input)
        $cleanCode = preg_replace('/[\s\-]/', '', $code);
        if (strlen($cleanCode) >= 8) {
            return $this->consumeBackupCode($userId, $cleanCode);
        }

        return false;
    }

    /**
     * Persiste l'ultimo timestep consumato (best effort: la colonna esiste
     * dalla migration 040; senza, l'anti-replay resta inattivo ma il login
     * non si rompe).
     */
    private function storeLastUsedTimestep(int $userId, int $timestep): void
    {
        try {
            $this->pdo->prepare(
                'UPDATE user_totp_secrets SET last_used_timestep = ? WHERE user_id = ?'
            )->execute([$timestep, $userId]);
        } catch (\Throwable $e) {
            // Colonna assente (DB legacy non migrato) → l'anti-replay TOTP è
            // DISATTIVO. Log esplicito e azionabile invece di degradare in
            // silenzio: la colonna esiste in schema.sql, quindi serve applicare
            // le migrazioni del DB.
            app_log('warning', '[TotpService] Anti-replay TOTP DEGRADATO: impossibile salvare last_used_timestep ('
                . $e->getMessage() . '). Applica le migrazioni (colonna user_totp_secrets.last_used_timestep).');
        }
    }

    /**
     * Check if a user has TOTP enabled and verified.
     */
    public function isEnabled(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT enabled FROM user_totp_secrets WHERE user_id = ? AND enabled = 1 AND verified_at IS NOT NULL'
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Disable TOTP for a user (admin or self).
     */
    public function disable(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM user_totp_secrets WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('DELETE FROM user_totp_backup_codes WHERE user_id = ?')->execute([$userId]);

        AuditService::log('mfa_disabled', 'user', $userId);
    }

    /**
     * Check if user is required to set up MFA (based on role + policy).
     */
    public function isSetupRequired(int $userId): bool
    {
        // Is TOTP globally enabled?
        if (!(bool) setting('mfa_totp_enabled', true)) {
            return false;
        }

        // Already enabled?
        if ($this->isEnabled($userId)) {
            return false;
        }

        // Admin role forced?
        if ((bool) setting('mfa_required_for_admin', false)) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM user_role ur
                 JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ? AND r.slug = 'admin'"
            );
            $stmt->execute([$userId]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }

        // Grace period expired?
        $graceDays = (int) setting('mfa_setup_grace_period_days', 0);
        if ($graceDays > 0) {
            $stmt = $this->pdo->prepare('SELECT created_at FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $createdAt = $stmt->fetchColumn();
            if ($createdAt) {
                $deadline = strtotime($createdAt) + ($graceDays * 86400);
                if (time() > $deadline) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get count of remaining (unused) backup codes.
     */
    public function getRemainingBackupCodesCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_totp_backup_codes WHERE user_id = ? AND used_at IS NULL'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Regenerate backup codes (destroys old ones).
     *
     * @return string[] Plain-text backup codes (show once, then discard)
     */
    public function regenerateBackupCodes(int $userId): array
    {
        if (!$this->isEnabled($userId)) {
            throw new \RuntimeException('TOTP non abilitato per questo utente.');
        }

        $codes = $this->generateBackupCodes($userId);
        AuditService::log('mfa_backup_codes_regenerated', 'user', $userId);
        return $codes;
    }

    // ------------------------------------------------------------------
    // TOTP algorithm (RFC 6238 / RFC 4226)
    // ------------------------------------------------------------------

    /**
     * Restituisce il timestep (counter RFC 6238) che matcha il codice entro
     * la finestra di tolleranza, o null se nessuno corrisponde.
     */
    private function matchTimestep(string $base32Secret, string $inputCode): ?int
    {
        $inputCode = preg_replace('/\s/', '', $inputCode);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $inputCode)) {
            return null;
        }

        $now     = time();
        $counter = intdiv($now, self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $rawSecret = self::base32Decode($base32Secret);
            $expected  = self::hotp($rawSecret, $counter + $i, self::DIGITS);
            if (hash_equals($expected, $inputCode)) {
                return $counter + $i;
            }
        }

        return null;
    }

    /**
     * HOTP (RFC 4226): HMAC-based One-Time Password.
     */
    private static function hotp(string $key, int $counter, int $digits): string
    {
        // Counter as 8-byte big-endian
        $msg = pack('N*', 0, $counter);

        $hash = hash_hmac('sha1', $msg, $key, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $code   = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            (ord($hash[$offset + 3])  & 0xFF)
        ) % (10 ** $digits);

        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Backup codes
    // ------------------------------------------------------------------

    /**
     * Generate and store backup codes for a user.
     *
     * @return string[] Plain-text codes (display once)
     */
    private function generateBackupCodes(int $userId): array
    {
        // Delete old codes
        $this->pdo->prepare('DELETE FROM user_totp_backup_codes WHERE user_id = ?')->execute([$userId]);

        $count = (int) setting('mfa_backup_codes_count', 10);
        $codes = [];

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_totp_backup_codes (user_id, code_hash) VALUES (?, ?)'
        );

        for ($i = 0; $i < $count; $i++) {
            // 8-char alphanumeric code (case-insensitive)
            $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            // Format as XXXX-XXXX for readability
            $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4);
            $codes[]   = $formatted;
            $stmt->execute([$userId, password_hash($code, PASSWORD_BCRYPT)]);
        }

        return $codes;
    }

    /**
     * Try to consume a backup code.
     */
    private function consumeBackupCode(int $userId, string $code): bool
    {
        $code = strtoupper(preg_replace('/[\s\-]/', '', $code));

        $stmt = $this->pdo->prepare(
            'SELECT id, code_hash FROM user_totp_backup_codes
             WHERE user_id = ? AND used_at IS NULL'
        );
        $stmt->execute([$userId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($code, $row['code_hash'])) {
                $this->pdo->prepare(
                    'UPDATE user_totp_backup_codes SET used_at = NOW() WHERE id = ?'
                )->execute([$row['id']]);

                AuditService::log('mfa_backup_code_used', 'user', $userId, null, [
                    'remaining' => $this->getRemainingBackupCodesCount($userId),
                ]);

                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function getRecord(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_totp_secrets WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function decryptSecret(string $encrypted): ?string
    {
        try {
            return $this->encryption->decrypt($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Base32 encoding/decoding (RFC 4648)
    // ------------------------------------------------------------------

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary   = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk   = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= $alphabet[bindec($chunk)];
        }

        return $result;
    }

    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data     = strtoupper(rtrim($data, '='));
        $binary   = '';

        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $result .= chr(bindec($byte));
        }

        return $result;
    }
}
