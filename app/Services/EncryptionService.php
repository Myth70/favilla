<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ISO 27001 A.10.1.1 — Application-level field encryption (AES-256-GCM).
 *
 * Provides encrypt/decrypt for sensitive database fields (tokens, secrets, PII).
 * Uses the APP_KEY from .env as the master key.
 */
class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    private string $key;

    public function __construct()
    {
        $appKey = env('APP_KEY', '');
        if (strlen($appKey) < 32) {
            throw new \RuntimeException('APP_KEY deve essere almeno 32 caratteri per la crittografia AES-256.');
        }
        // Derive a dedicated encryption key from APP_KEY using HKDF
        $this->key = hash_hkdf('sha256', $appKey, 32, 'favilla-field-encryption');
    }

    /**
     * Encrypt a plaintext value. Returns a base64-encoded string (nonce + tag + ciphertext).
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Errore durante la crittografia del campo.');
        }

        // Pack: nonce (12 bytes) + tag (16 bytes) + ciphertext
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt a base64-encoded encrypted value.
     *
     * @throws \RuntimeException If decryption fails (tampered data or wrong key).
     */
    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new \RuntimeException('Dato crittografato non valido (base64 corrotto).');
        }

        $nonceLen = openssl_cipher_iv_length(self::CIPHER);
        $minLen = $nonceLen + self::TAG_LENGTH + 1;

        if (strlen($raw) < $minLen) {
            throw new \RuntimeException('Dato crittografato non valido (troppo corto).');
        }

        $nonce      = substr($raw, 0, $nonceLen);
        $tag        = substr($raw, $nonceLen, self::TAG_LENGTH);
        $ciphertext = substr($raw, $nonceLen + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Impossibile decifrare il campo. Chiave errata o dato manomesso.');
        }

        return $plaintext;
    }

    /**
     * Check if a string appears to be an encrypted value (base64 with minimum length).
     */
    public function isEncrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $raw = base64_decode($value, true);
        if ($raw === false) {
            return false;
        }

        $nonceLen = openssl_cipher_iv_length(self::CIPHER);
        return strlen($raw) >= ($nonceLen + self::TAG_LENGTH + 1);
    }

    /**
     * Encrypt a value only if it's not already encrypted. Safe for idempotent migrations.
     */
    public function encryptIfNeeded(string $value): string
    {
        if ($this->isEncrypted($value)) {
            return $value;
        }
        return $this->encrypt($value);
    }

    /**
     * Mask a sensitive value for audit/logging display (show first 4 and last 4 chars).
     */
    public static function mask(string $value, int $visibleChars = 4): string
    {
        $len = mb_strlen($value);
        if ($len <= $visibleChars * 2) {
            return str_repeat('*', $len);
        }
        return mb_substr($value, 0, $visibleChars)
            . str_repeat('*', $len - ($visibleChars * 2))
            . mb_substr($value, -$visibleChars);
    }

    /**
     * Encrypt a file in-place. Reads, encrypts with AES-256-GCM, writes back.
     * Prepends a magic header 'ENC1' to identify encrypted files.
     *
     * @return bool True on success
     */
    public function encryptFile(string $filePath): bool
    {
        if (!$this->isReadableFile($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Skip if already encrypted
        if (str_starts_with($content, 'ENC1')) {
            return true;
        }

        $nonce = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $ciphertext = openssl_encrypt(
            $content,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            return false;
        }

        // Format: 'ENC1' (4 bytes) + nonce (12) + tag (16) + ciphertext
        $encrypted = 'ENC1' . $nonce . $tag . $ciphertext;
        return file_put_contents($filePath, $encrypted) !== false;
    }

    /**
     * Decrypt a file in-place. Reads, decrypts, writes back.
     *
     * @return bool True on success
     */
    public function decryptFile(string $filePath): bool
    {
        if (!$this->isReadableFile($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false || !str_starts_with($content, 'ENC1')) {
            return false;
        }

        $raw = substr($content, 4); // Strip 'ENC1' header
        $nonceLen = openssl_cipher_iv_length(self::CIPHER);
        $nonce      = substr($raw, 0, $nonceLen);
        $tag        = substr($raw, $nonceLen, self::TAG_LENGTH);
        $ciphertext = substr($raw, $nonceLen + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            return false;
        }

        return file_put_contents($filePath, $plaintext) !== false;
    }

    /**
     * Decrypt a file to a temp path for streaming (returns temp path or null).
     *
     * @return string|null Temp file path (caller must unlink after use)
     */
    public function decryptFileToTemp(string $filePath): ?string
    {
        if (!$this->isReadableFile($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false || !str_starts_with($content, 'ENC1')) {
            return null; // Not encrypted, serve directly
        }

        $raw = substr($content, 4);
        $nonceLen = openssl_cipher_iv_length(self::CIPHER);
        $nonce      = substr($raw, 0, $nonceLen);
        $tag        = substr($raw, $nonceLen, self::TAG_LENGTH);
        $ciphertext = substr($raw, $nonceLen + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            return null;
        }

        $tmpPath = $this->resolveTempFilePath();
        if ($tmpPath !== null && file_put_contents($tmpPath, $plaintext) !== false) {
            // Plaintext di backup: restringi i permessi subito dopo la scrittura.
            @chmod($tmpPath, 0600);
            return $tmpPath;
        }
        return null;
    }

    /**
     * Check if a file is encrypted (has ENC1 header).
     */
    public function isFileEncrypted(string $filePath): bool
    {
        if (!$this->isReadableFile($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 4);
        fclose($handle);
        return $header === 'ENC1';
    }

    private function isReadableFile(string $filePath): bool
    {
        return @file_exists($filePath) && @is_readable($filePath);
    }

    private function resolveTempFilePath(): ?string
    {
        // Preferisci le directory di proprietà dell'app (create con permessi
        // ristretti) al temp di sistema condiviso, che su host multi-tenant è
        // world-traversable mentre il plaintext di backup vi risiede.
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dirs = [
            $base . '/storage/tmp',
            $base . '/storage/cache',
            sys_get_temp_dir(),
        ];

        foreach ($dirs as $dir) {
            if ($dir === '') {
                continue;
            }

            if (!@is_dir($dir) && !@mkdir($dir, 0700, true)) {
                continue;
            }

            if (!@is_writable($dir)) {
                continue;
            }

            return rtrim($dir, '/\\') . '/favilla_dec_' . bin2hex(random_bytes(8));
        }

        return null;
    }
}
