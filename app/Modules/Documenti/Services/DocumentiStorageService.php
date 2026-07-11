<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoFileRepository;

/**
 * Storage dei file del modulo Documenti.
 *
 * Path: storage/uploads/documenti/Y/m/ (FUORI webroot).
 * I file NON sono raggiungibili via URL diretto: l'accesso passa SEMPRE
 * dal VersioniController che applica controllo permessi e visibility.
 *
 * Override path via env DOCUMENTI_STORAGE_PATH (assoluto) o
 * config('app.documenti.storage_path').
 */
class DocumentiStorageService
{
    private DocumentoFileRepository $fileRepo;

    public function __construct()
    {
        $this->fileRepo = app(DocumentoFileRepository::class);
    }

    /**
     * Root assoluta del filesystem dove i documenti vengono salvati.
     * Fuori webroot per default.
     */
    public static function baseDir(): string
    {
        $custom = getenv('DOCUMENTI_STORAGE_PATH') ?: config('app.documenti.storage_path');
        if (is_string($custom) && $custom !== '') {
            return rtrim($custom, '/\\');
        }
        return rtrim(BASE_PATH, '/\\') . '/storage/uploads/documenti';
    }

    /**
     * Subdirectory relativa per il bucket dell'anno/mese corrente (Y/m).
     * Usata come "directory" nel record documenti_files (non comprende baseDir).
     */
    public static function currentBucket(): string
    {
        return date('Y/m');
    }

    /**
     * Validazione + upload + record DB.
     *
     * @param  array $uploadedFile  Entry da $_FILES
     * @param  int   $userId        ID utente caricante
     * @return int                  ID del record documenti_files
     * @throws \RuntimeException    Se upload o salvataggio fallisce
     */
    public function store(array $uploadedFile, int $userId): int
    {
        if (!isset($uploadedFile['error']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(t('documenti.exception.upload_fallito'));
        }

        $maxBytes = (int) config('app.documenti.max_upload_bytes', 52428800); // 50 MB default
        if (($uploadedFile['size'] ?? 0) > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            throw new \RuntimeException(t('documenti.exception.file_troppo_grande', ['size' => $maxMb]));
        }

        // MIME via magic bytes (non fidarsi del browser)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($uploadedFile['tmp_name']) ?: 'application/octet-stream';

        $allowedMimes = array_keys(DocumentiMimeRegistry::activeMimes());
        if (!in_array($mime, $allowedMimes, true)) {
            throw new \RuntimeException(t('documenti.exception.formato_non_consentito', ['mime' => $mime]));
        }

        $extension = DocumentiMimeRegistry::MIMES[$mime]
            ?? strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION))
            ?: 'bin';

        // Filename collision-free
        $storedName = 'doc_' . bin2hex(random_bytes(8)) . '.' . $extension;

        $bucket = self::currentBucket();
        $absDir = self::baseDir() . '/' . $bucket;
        if (!is_dir($absDir) && !@mkdir($absDir, 0750, true) && !is_dir($absDir)) {
            throw new \RuntimeException(t('documenti.exception.directory_non_creata'));
        }

        $destPath = $absDir . '/' . $storedName;
        if (!move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
            throw new \RuntimeException(t('documenti.exception.salvataggio_fallito'));
        }

        $checksum = hash_file('sha256', $destPath) ?: null;

        $fileId = $this->fileRepo->create([
            'original_name'   => $uploadedFile['name'] ?? $storedName,
            'stored_name'     => $storedName,
            'directory'       => $bucket, // relativa a baseDir()
            'mime_type'       => $mime,
            'extension'       => $extension,
            'size_bytes'      => (int) $uploadedFile['size'],
            'checksum_sha256' => $checksum,
            'created_by'      => $userId,
        ]);

        return $fileId;
    }

    /**
     * Restituisce il path fisico assoluto di un file dato il record DB.
     */
    public function physicalPath(array $fileRecord): string
    {
        $directory = trim((string) ($fileRecord['directory'] ?? ''), '/\\');
        $stored    = basename((string) ($fileRecord['stored_name'] ?? ''));

        // Backwards compat: vecchi record possono avere directory che include "documenti/..."
        if (str_starts_with($directory, 'documenti/')) {
            $directory = substr($directory, strlen('documenti/'));
        }

        // Difensivo: `directory` è generato lato server (bucket Y/m), ma neutralizza
        // comunque eventuali segmenti di traversal in record corrotti/migrati male,
        // preservando la struttura multi-segmento legittima.
        $segments  = preg_split('#[/\\\\]+#', $directory) ?: [];
        $segments  = array_filter(
            $segments,
            static fn (string $s): bool => $s !== '' && $s !== '.' && $s !== '..'
        );
        $directory = implode('/', $segments);

        return self::baseDir() . '/' . $directory . '/' . $stored;
    }

    /**
     * Verifica l'integrità di un file confrontando l'hash su disco con quello atteso.
     * Funzione pura (nessuna dipendenza DB) per essere facilmente testabile.
     *
     * @return string  'ok' | 'mismatch' | 'missing' | 'no_checksum'
     */
    public static function verifyChecksum(string $physicalPath, ?string $expected): string
    {
        if (!is_file($physicalPath)) {
            return 'missing';
        }
        if ($expected === null || $expected === '') {
            return 'no_checksum';
        }
        $actual = hash_file('sha256', $physicalPath);
        if ($actual === false) {
            return 'missing';
        }
        return hash_equals(strtolower($expected), strtolower($actual)) ? 'ok' : 'mismatch';
    }

    /**
     * Rimuove file fisico e record DB. Usato su rollback di transazione e cleanup orfani.
     */
    public function cleanup(int $fileId): void
    {
        $fileRecord = $this->fileRepo->find($fileId);
        if (!$fileRecord) {
            return;
        }
        $path = $this->physicalPath($fileRecord);
        if (is_file($path)) {
            @unlink($path);
        }
        // Hard delete: il record sta uscendo dal sistema con il file
        $this->fileRepo->forceDelete($fileId);
    }
}
