<?php

declare(strict_types=1);

namespace App\Modules\Auth\Helpers;

class AvatarHelper
{
    /**
     * Restituisce l'URL pubblico dell'avatar o null se non presente.
     */
    public static function url(?string $avatarPath): ?string
    {
        if (empty($avatarPath)) {
            return null;
        }

        // Path legacy con '/' (libreria Files, es. "files/stored_name.jpg"):
        // quella directory non è più servita da Apache. Fallback alle iniziali;
        // reimpostando l'avatar dal profilo il file viene copiato in avatars/.
        if (str_contains($avatarPath, '/')) {
            return null;
        }

        return self::uploadsBaseUrl() . '/avatars/' . basename($avatarPath);
    }

    /**
     * Base URL pubblico della directory uploads, rispettando app.url e app.base_path.
     */
    private static function uploadsBaseUrl(): string
    {
        $baseUrl  = rtrim((string) config('app.url', ''), '/');
        $basePath = trim((string) config('app.base_path', ''), '/');

        if ($basePath !== '') {
            $pathPart = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
            if ($pathPart !== $basePath && !str_ends_with($pathPart, '/' . $basePath)) {
                $baseUrl .= '/' . $basePath;
            }
        }

        return $baseUrl . '/uploads';
    }

    /**
     * Calcola le iniziali dal nome utente (massimo 2 caratteri).
     */
    public static function initials(string $name): string
    {
        $parts    = explode(' ', $name ?: 'U');
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (isset($parts[1]) && $parts[1] !== '') {
            $initials .= mb_strtoupper(mb_substr($parts[1], 0, 1));
        }
        return $initials;
    }

    /**
     * Percorso fisico assoluto della directory di upload sul filesystem.
     */
    public static function uploadDir(): string
    {
        return defined('BASE_PATH')
            ? BASE_PATH . '/public/uploads/avatars/'
            : dirname(__DIR__, 4) . '/public/uploads/avatars/';
    }
}
