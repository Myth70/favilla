<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Modules\Home\Helpers\PatternHelper;
use App\Modules\Home\Repositories\PreferencesRepository;

/**
 * PreferencesService — persiste le preferenze utente nel DB.
 *
 * Contratto: ogni metodo ritorna il valore validato (con eventuale fallback).
 * Il Controller è responsabile di scrivere il valore in `$_SESSION['user_preferences']`,
 * cosi' che il Service rimanga puro e testabile senza superglobal.
 */
class PreferencesService
{
    public const ALLOWED_SKINS = ['default', 'soft', 'sharp', 'compact'];

    public const ALLOWED_SIDEBAR_STYLES = ['default', 'light', 'accent'];

    public const ALLOWED_FONTS = ['system', 'inter', 'plex', 'lora', 'jetbrains'];

    public const DEFAULT_COLOR = '#3b82f6';

    private PreferencesRepository $repo;

    public function __construct()
    {
        $this->repo = app(PreferencesRepository::class);
    }

    /**
     * Update theme preference (light/dark) and return the normalized value.
     */
    public function updateTheme(int $userId, string $theme): string
    {
        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }

        $this->repo->upsert($userId, ['theme' => $theme]);
        return $theme;
    }

    /**
     * Update accent color preference (hex) and return the normalized value.
     */
    public function updateColor(int $userId, string $color): string
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = self::DEFAULT_COLOR;
        }

        $this->repo->upsert($userId, ['primary_color' => $color]);
        return $color;
    }

    /**
     * Update sidebar collapsed state (0 or 1) and return the normalized value.
     */
    public function updateSidebar(int $userId, int $collapsed): int
    {
        $collapsed = $collapsed === 1 ? 1 : 0;

        $this->repo->upsert($userId, ['sidebar_collapsed' => $collapsed]);
        return $collapsed;
    }

    /**
     * Update sidebar skin preference (default|light|accent) and return the normalized value.
     */
    public function updateSidebarStyle(int $userId, string $style): string
    {
        if (!in_array($style, self::ALLOWED_SIDEBAR_STYLES, true)) {
            $style = 'default';
        }

        $this->repo->upsert($userId, ['sidebar_style' => $style]);
        return $style;
    }

    /**
     * Update visual theme skin preference and return the normalized value.
     */
    public function updateSkin(int $userId, string $skin): string
    {
        if (!in_array($skin, self::ALLOWED_SKINS, true)) {
            $skin = 'default';
        }

        $this->repo->upsert($userId, ['theme_skin' => $skin]);
        return $skin;
    }

    /**
     * Update hero background pattern preference and return the normalized value.
     */
    public function updatePattern(int $userId, string $pattern): string
    {
        if (!in_array($pattern, PatternHelper::allowed(), true)) {
            $pattern = PatternHelper::default();
        }

        $this->repo->upsert($userId, ['background_pattern' => $pattern]);
        return $pattern;
    }

    /**
     * Update font family preference and return the normalized value.
     */
    public function updateFont(int $userId, string $font): string
    {
        if (!in_array($font, self::ALLOWED_FONTS, true)) {
            $font = 'system';
        }

        $this->repo->upsert($userId, ['font_family' => $font]);
        return $font;
    }

    /**
     * Update language preference and return the normalized value. The whitelist
     * is the configured supported-locale list; unsupported codes fall back to
     * the configured default.
     */
    public function updateLanguage(int $userId, string $language): string
    {
        $supported = (array) config('localization.supported', ['it']);
        $default   = (string) config('localization.default', 'it');
        if (!in_array($language, $supported, true)) {
            $language = in_array($default, $supported, true) ? $default : ($supported[0] ?? 'it');
        }

        $this->repo->upsert($userId, ['language' => $language]);
        return $language;
    }
}
