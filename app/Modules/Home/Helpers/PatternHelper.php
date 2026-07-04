<?php

declare(strict_types=1);

namespace App\Modules\Home\Helpers;

use App\Modules\Home\Repositories\PreferencesRepository;

class PatternHelper
{
    /**
     * Chiavi tecniche ammesse (es. 'circles', 'triangles', …).
     */
    public static function allowed(): array
    {
        return array_keys(config('patterns.items', []));
    }

    /**
     * Mappa [chiave_tecnica => label visualizzata].
     */
    public static function labels(): array
    {
        return config('patterns.items', []);
    }

    /**
     * Pattern di default.
     */
    public static function default(): string
    {
        return config('patterns.default', 'circles');
    }

    /**
     * Prefisso CSS (es. 'pf-pattern-').
     */
    public static function cssPrefix(): string
    {
        return config('patterns.css_prefix', 'pf-pattern-');
    }

    /**
     * Risolve il pattern corrente dell'utente e restituisce la classe CSS.
     * Logica: sessione → fallback DB → validazione → default.
     * Popola $_SESSION al primo accesso.
     */
    public static function resolveClass(): string
    {
        return static::cssPrefix() . static::resolveKey();
    }

    /**
     * Risolve la chiave tecnica del pattern corrente (senza prefisso CSS).
     * Mai lancia eccezioni: ritorna il default se DB, sessione o config non sono disponibili.
     */
    public static function resolveKey(): string
    {
        try {
            $pattern = $_SESSION['user_preferences']['background_pattern'] ?? null;

            if ($pattern === null && !empty(auth()['id'])) {
                $prefRepo = app(PreferencesRepository::class);
                $dbPrefs  = $prefRepo->getByUserId((int) auth()['id']);
                $pattern  = $dbPrefs['background_pattern'] ?? static::default();
                $_SESSION['user_preferences']['background_pattern'] = $pattern;
            }

            $allowed = static::allowed();
            if (empty($allowed) || !in_array($pattern, $allowed, true)) {
                $pattern = static::default();
            }

            return $pattern;
        } catch (\Throwable) {
            return 'circles';
        }
    }

    /**
     * Lista chiavi in JSON (per attributi data- nel DOM).
     */
    public static function toJson(): string
    {
        return json_encode(static::allowed());
    }
}
