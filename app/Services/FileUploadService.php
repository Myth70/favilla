<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Centralises file-upload validation and storage.
 *
 * Usage:
 *   $filename = FileUploadService::uploadImage($_FILES['avatar'], 'profiles', 'avatar_123_');
 *   FileUploadService::delete($oldFilename, 'profiles');
 *   $url = FileUploadService::url($filename, 'profiles');
 */
class FileUploadService
{
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE   => 'Il file supera la dimensione massima consentita.',
        UPLOAD_ERR_FORM_SIZE  => 'Il file supera la dimensione massima consentita.',
        UPLOAD_ERR_PARTIAL    => 'Upload interrotto: file ricevuto solo parzialmente. Riprova.',
        UPLOAD_ERR_NO_FILE    => 'Nessun file selezionato.',
        UPLOAD_ERR_NO_TMP_DIR => 'Errore di configurazione server: cartella temporanea mancante.',
        UPLOAD_ERR_CANT_WRITE => 'Errore di scrittura: impossibile salvare il file sul server.',
    ];

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and store an uploaded image.
     *
     * @param array  $file          Entry from $_FILES (e.g. $_FILES['avatar']).
     * @param string $directory     Subdirectory inside public/uploads/ ('profiles', 'avatars', …).
     * @param string $prefix        Filename prefix including any IDs ('avatar_123_', 'file_45_').
     * @param int    $maxBytes      Maximum allowed size in bytes (default 2 MB).
     * @param int    $maxDimension  Maximum pixel width or height (default 2000 px).
     * @param array  $allowedMimes  Allowed MIME types.
     * @return string               Stored basename (e.g. 'avatar_123_a1b2c3d4.jpg').
     * @throws \RuntimeException    On validation failure or I/O error.
     */
    public static function uploadImage(
        array  $file,
        string $directory,
        string $prefix,
        int    $maxBytes     = 2097152,
        int    $maxDimension = 2000,
        array  $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    ): string {
        // 1. PHP upload error check
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new \RuntimeException(
                self::UPLOAD_ERRORS[$code] ?? 'Errore durante l\'upload (codice ' . $code . '). Riprova.'
            );
        }

        // 2. File size
        if ($file['size'] > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            throw new \RuntimeException("Il file supera il limite di {$maxMb}MB.");
        }

        // 3. MIME type via magic bytes (do not trust the browser-supplied type)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \RuntimeException('Formato non supportato. Usa JPG, PNG, GIF o WebP.');
        }

        // 4. Image dimensions
        $imageInfo = self::callSilently(static fn () => getimagesize($file['tmp_name']));
        if ($imageInfo === false) {
            throw new \RuntimeException('Impossibile leggere l\'immagine. Il file potrebbe essere corrotto.');
        }
        [$width, $height] = $imageInfo;
        if ($width > $maxDimension || $height > $maxDimension) {
            throw new \RuntimeException("L'immagine non può superare {$maxDimension}×{$maxDimension} pixel.");
        }

        // 5. Build a safe, collision-free filename
        $ext      = self::MIME_EXTENSIONS[$mimeType] ?? 'bin';
        $prefix   = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = self::resolveUploadDir($directory) . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Errore durante il salvataggio del file. Riprova.');
        }

        return $filename;
    }

    /**
     * Delete a stored file from an upload directory.
     *
     * @param string|null $filename  Basename or relative path of the file to remove.
     * @param string      $directory Subdirectory inside public/uploads/.
     */
    public static function delete(?string $filename, string $directory): void
    {
        if (empty($filename)) {
            return;
        }
        $fullPath = self::resolveUploadDir($directory) . basename($filename);
        self::deleteIfExists($fullPath);
    }

    /**
     * Return the public URL for a stored file, or a fallback value.
     *
     * @param string|null $filename  Basename of the stored file.
     * @param string      $directory Subdirectory inside public/uploads/.
     * @param string|null $fallback  Returned when $filename is empty.
     */
    public static function url(?string $filename, string $directory, ?string $fallback = null): ?string
    {
        if (empty($filename)) {
            return $fallback;
        }

        $baseUrl  = rtrim((string) config('app.url', ''), '/');
        $basePath = trim((string) config('app.base_path', ''), '/');

        if ($basePath !== '') {
            $pathPart = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
            if ($pathPart !== $basePath && !str_ends_with($pathPart, '/' . $basePath)) {
                $baseUrl .= '/' . $basePath;
            }
        }

        return $baseUrl . '/uploads/' . trim($directory, '/') . '/' . basename($filename);
    }

    /**
     * Resolve (and create if needed) the absolute filesystem path for an upload subdirectory.
     *
     * @param string $directory Subdirectory name inside public/uploads/.
     * @return string           Absolute path with trailing slash.
     */
    public static function resolveUploadDir(string $directory): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $directory = basename(trim($directory, '/'));
        $dir      = $basePath . '/public/uploads/' . $directory . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    // ── Generic file upload (non-image types supported) ───────────────────

    private const FILE_MIME_EXTENSIONS = [
        // Documents
        'application/pdf'                                                              => 'pdf',
        'application/msword'                                                           => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'     => 'docx',
        'application/vnd.ms-excel'                                                    => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'          => 'xlsx',
        'application/vnd.ms-powerpoint'                                               => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'  => 'pptx',
        'application/vnd.oasis.opendocument.text'                                     => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet'                              => 'ods',
        // Archives
        'application/zip'              => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/x-7z-compressed'  => '7z',
        'application/gzip'             => 'gz',
        // Text
        'text/plain' => 'txt',
        'text/csv'   => 'csv',
        // Images (same types as uploadImage)
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and store any uploaded file (not just images).
     *
     * Unlike uploadImage(), this method does NOT check pixel dimensions.
     * Returns an array with metadata needed for DB tracking.
     *
     * @param array  $file          Entry from $_FILES (e.g. $_FILES['file']).
     * @param string $directory     Subdirectory inside public/uploads/ ('files', …).
     * @param string $prefix        Filename prefix ('file_').
     * @param int    $maxBytes      Maximum allowed size in bytes (default 20 MB).
     * @param array  $allowedMimes  Allowed MIME types; empty = all FILE_MIME_EXTENSIONS.
     * @return array{filename: string, mime: string, size: int, extension: string}
     * @throws \RuntimeException    On validation failure or I/O error.
     */
    public static function uploadFile(
        array  $file,
        string $directory,
        string $prefix       = 'file_',
        int    $maxBytes     = 20971520,
        array  $allowedMimes = []
    ): array {
        // 1. PHP upload error check
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new \RuntimeException(
                self::UPLOAD_ERRORS[$code] ?? 'Errore durante l\'upload (codice ' . $code . '). Riprova.'
            );
        }

        // 2. File size
        if ($file['size'] > $maxBytes) {
            $maxMb = round($maxBytes / 1048576, 1);
            throw new \RuntimeException("Il file supera il limite di {$maxMb} MB.");
        }

        // 3. MIME type via magic bytes
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        // 4. Allowed-MIME check
        $supported = empty($allowedMimes) ? array_keys(self::FILE_MIME_EXTENSIONS) : $allowedMimes;
        if (!in_array($mimeType, $supported, true)) {
            throw new \RuntimeException('Formato non supportato: ' . htmlspecialchars($mimeType, ENT_QUOTES, 'UTF-8') . '. Verifica i formati accettati.');
        }

        // 5. Derive extension from MIME map (fallback to sanitised original extension)
        $ext = self::FILE_MIME_EXTENSIONS[$mimeType]
            ?? strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION))
            ?: 'bin';

        // 6. Safe, collision-free filename
        $prefix   = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = self::resolveUploadDir($directory) . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Errore durante il salvataggio del file. Riprova.');
        }

        return [
            'filename'  => $filename,
            'mime'      => $mimeType,
            'size'      => (int) $file['size'],
            'extension' => $ext,
        ];
    }

    private static function deleteIfExists(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        return (bool) self::callSilently(static fn () => unlink($path));
    }

    // ── Cropped avatar (GD resize to exact square) ────────────────────

    /**
     * Validate and store a pre-cropped avatar image, resizing to exact dimensions via GD.
     *
     * @param string $tmpPath   Path to the uploaded temp file (canvas blob).
     * @param string $directory Subdirectory inside public/uploads/ ('avatars').
     * @param string $prefix    Filename prefix ('avatar_5_', 'team_3_').
     * @param int    $size      Output square dimension in pixels (default 256).
     * @return string           Stored basename (e.g. 'avatar_5_a1b2c3d4.png').
     * @throws \RuntimeException On validation failure or GD error.
     */
    public static function saveCroppedAvatar(
        string $tmpPath,
        string $directory,
        string $prefix,
        int    $size = 256,
        int    $maxSourceDimension = 6000
    ): string {
        // 1. Validate MIME via magic bytes
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \RuntimeException('Formato non supportato. Usa PNG, JPG o WebP.');
        }

        // 2. Validate it's a real image
        $imageInfo = self::callSilently(static fn () => getimagesize($tmpPath));
        if ($imageInfo === false) {
            throw new \RuntimeException('Impossibile leggere l\'immagine. Il file potrebbe essere corrotto.');
        }

        // 3. Guard against decompression bombs: reject oversized source dimensions
        //    before GD allocates the full bitmap. A small file can decode to a huge
        //    in-memory image (e.g. 30000×30000) and exhaust server memory.
        [$srcWidth, $srcHeight] = $imageInfo;
        if ($srcWidth > $maxSourceDimension || $srcHeight > $maxSourceDimension) {
            throw new \RuntimeException("L'immagine sorgente non può superare {$maxSourceDimension}×{$maxSourceDimension} pixel.");
        }

        // 4. File size guard (cropped blob should be small)
        if (filesize($tmpPath) > 2097152) {
            throw new \RuntimeException('Il file supera il limite di 2 MB.');
        }

        // 5. Load source with GD
        $src = self::callSilently(static fn () => imagecreatefromstring(file_get_contents($tmpPath)));
        if (!$src) {
            throw new \RuntimeException('Errore nella lettura dell\'immagine.');
        }

        // 6. Create square canvas and resample
        $dst = imagecreatetruecolor($size, $size);
        // Preserve transparency
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size - 1, $size - 1, $transparent);
        imagealphablending($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

        // 7. Save as PNG
        $prefix   = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        $filename = $prefix . bin2hex(random_bytes(8)) . '.png';
        $destPath = self::resolveUploadDir($directory) . $filename;
        imagepng($dst, $destPath, 9);

        imagedestroy($src);
        imagedestroy($dst);

        return $filename;
    }

    private static function callSilently(callable $callback): mixed
    {
        set_error_handler(static fn () => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
