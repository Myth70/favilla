<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

/**
 * Backup-specific encryption at rest, extracted from BackupService to keep the
 * crypto in one focused, testable place.
 *
 * New format: AES-256-GCM, wire layout HEADER(4) || IV(16) || TAG(16) || CIPHERTEXT.
 * Reads also fall back to the legacy AES-256-CBC layout IV(16) || CIPHERTEXT.
 * Key: BACKUP_ENCRYPTION_KEY, hashed to 32 bytes.
 */
class BackupEncryptionService
{
    private const ENC_CIPHER        = 'aes-256-gcm';
    private const ENC_LEGACY_CIPHER = 'AES-256-CBC';
    private const ENC_HEADER        = 'PMT2';
    private const ENC_IV_SIZE       = 16;
    private const ENC_TAG_SIZE      = 16;
    // Oltre questo limite la cifratura in-memory è bloccata (richiede di caricare
    // l'intero file in RAM) salvo override esplicito BACKUP_ALLOW_UNENCRYPTED_LARGE.
    private const ENC_MAX_BYTES     = 200 * 1024 * 1024; // 200 MB

    /**
     * Cifra il file di backup in-place con AES-256-GCM se BACKUP_ENCRYPTION_KEY è
     * configurata. Formato: HEADER(4) || IV(16) || TAG(16) || CIPHERTEXT.
     *
     * @throws \RuntimeException Se la cifratura fallisce
     */
    public function encryptInPlace(string $path): void
    {
        $rawKey = env('BACKUP_ENCRYPTION_KEY', '');
        if (empty($rawKey)) {
            return;
        }

        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize === 0) {
            return;
        }

        if ($fileSize > self::ENC_MAX_BYTES) {
            $allowPlainLarge = filter_var(
                (string) env('BACKUP_ALLOW_UNENCRYPTED_LARGE', false),
                FILTER_VALIDATE_BOOL
            );

            if ($allowPlainLarge) {
                // Override esplicito: comportamento legacy con warning operativo.
                app_log('warning', "[Backup] AVVERTIMENTO: file troppo grande per cifratura ({$fileSize} byte). Backup salvato non cifrato (override attivo). ");
                return;
            }

            throw new \RuntimeException(
                'Backup troppo grande per la cifratura in-memory. Operazione bloccata per evitare backup non cifrati. '
                . 'Ridurre dimensione dump o attivare BACKUP_ALLOW_UNENCRYPTED_LARGE=true solo in casi eccezionali.'
            );
        }

        $key       = hash('sha256', $rawKey, true); // 32 byte
        $iv        = random_bytes(self::ENC_IV_SIZE);
        $plaintext = file_get_contents($path);

        if ($plaintext === false) {
            throw new \RuntimeException('Impossibile leggere il file di backup per la cifratura.');
        }

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ENC_CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::ENC_TAG_SIZE
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Cifratura backup fallita: ' . openssl_error_string());
        }

        $payload = self::ENC_HEADER . $iv . $tag . $ciphertext;
        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Impossibile sovrascrivere il file di backup cifrato.');
        }

        // Pulisci variabili sensibili
        $plaintext = '';
        $ciphertext = '';
    }

    /**
     * Legge il contenuto di un backup, decifrando automaticamente se necessario.
     * Ritorna il contenuto gzip pronto per il download.
     *
     * @throws \RuntimeException Se la decifratura fallisce
     */
    public function readDecrypted(string $path): string
    {
        $rawKey = env('BACKUP_ENCRYPTION_KEY', '');

        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException('Impossibile leggere il file di backup.');
        }

        if (empty($rawKey)) {
            return $data;
        }

        // Se i magic bytes sono gzip (1F 8B), il file non è cifrato.
        if (strlen($data) >= 2 && ord($data[0]) === 0x1F && ord($data[1]) === 0x8B) {
            return $data;
        }

        // Nuovo formato GCM autenticato
        if (str_starts_with($data, self::ENC_HEADER)) {
            $minLen = strlen(self::ENC_HEADER) + self::ENC_IV_SIZE + self::ENC_TAG_SIZE + 1;
            if (strlen($data) < $minLen) {
                throw new \RuntimeException('File di backup corrotto o non valido.');
            }

            $offset = strlen(self::ENC_HEADER);
            $iv = substr($data, $offset, self::ENC_IV_SIZE);
            $offset += self::ENC_IV_SIZE;
            $tag = substr($data, $offset, self::ENC_TAG_SIZE);
            $offset += self::ENC_TAG_SIZE;
            $ciphertext = substr($data, $offset);

            $key = hash('sha256', $rawKey, true);
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::ENC_CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                ''
            );
            if ($plaintext === false) {
                throw new \RuntimeException('Decifratura backup fallita. Verificare BACKUP_ENCRYPTION_KEY.');
            }

            return $plaintext;
        }

        // Legacy format fallback: IV(16) || CIPHERTEXT (AES-256-CBC)
        if (strlen($data) <= self::ENC_IV_SIZE) {
            throw new \RuntimeException('File di backup corrotto o non valido.');
        }

        $key        = hash('sha256', $rawKey, true);
        $iv         = substr($data, 0, self::ENC_IV_SIZE);
        $ciphertext = substr($data, self::ENC_IV_SIZE);

        $plaintext = openssl_decrypt($ciphertext, self::ENC_LEGACY_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Decifratura backup fallita. Verificare BACKUP_ENCRYPTION_KEY.');
        }

        return $plaintext;
    }
}
