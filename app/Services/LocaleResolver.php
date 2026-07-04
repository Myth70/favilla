<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\RequestContext;
use PDO;

/**
 * Resolves the active locale for the current request and applies it to the
 * Translator. Invoked once from bootstrap/app.php (after the session is
 * started), because the core Application exposes no global-middleware slot.
 *
 * Priority (first match wins):
 *   1. ?lang= query param        — explicit switch, persisted to session+cookie
 *   2. $_SESSION['user_language'] — primed at login / by a previous switch
 *   3. language cookie           — survives across sessions / before login
 *   4. logged-in user's stored preference (for sessions predating this feature)
 *   5. Accept-Language header
 *   6. config('localization.default')
 */
class LocaleResolver
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * Resolve, apply, and return the active locale code.
     */
    public function resolve(): string
    {
        $param      = (string) config('localization.query_param', 'lang');
        $cookieName = (string) config('localization.cookie_name', 'favilla_lang');

        // 1) Explicit query switch — persist it.
        $query = (isset($_GET[$param]) && is_string($_GET[$param])) ? $_GET[$param] : '';
        if ($query !== '' && ($code = $this->translator->canonical($query)) !== null) {
            $this->apply($code, true);
            return $code;
        }

        // 2) Session.
        $sessionLang = $_SESSION['user_language'] ?? null;
        if (is_string($sessionLang) && ($code = $this->translator->canonical($sessionLang)) !== null) {
            $this->translator->setLocale($code);
            return $code;
        }

        // 3) Cookie.
        $cookieLang = $_COOKIE[$cookieName] ?? null;
        if (is_string($cookieLang) && ($code = $this->translator->canonical($cookieLang)) !== null) {
            $_SESSION['user_language'] = $code;
            $this->translator->setLocale($code);
            return $code;
        }

        // 4) Stored preference for already-logged-in users (no session lang yet).
        $dbLang = $this->fromUserPreference();
        if ($dbLang !== null) {
            $_SESSION['user_language'] = $dbLang;
            $this->translator->setLocale($dbLang);
            return $dbLang;
        }

        // 5) Accept-Language header.
        $headerLang = $this->fromAcceptLanguage((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($headerLang !== null) {
            $this->translator->setLocale($headerLang);
            return $headerLang;
        }

        // 6) Default (already the Translator's locale).
        return $this->translator->getLocale();
    }

    /**
     * Apply a locale and store it in the session, optionally persisting a cookie.
     * Used by the explicit switcher endpoint. Returns the canonical code applied
     * (or the current locale when $locale is unsupported).
     */
    public function apply(string $locale, bool $persistCookie = false): string
    {
        $code = $this->translator->canonical($locale);
        if ($code === null) {
            return $this->translator->getLocale();
        }

        $this->translator->setLocale($code);
        $_SESSION['user_language'] = $code;

        if ($persistCookie) {
            $this->setCookie($code);
        }

        return $code;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function setCookie(string $code): void
    {
        if (headers_sent()) {
            return;
        }
        $cookieName = (string) config('localization.cookie_name', 'favilla_lang');
        $days       = (int) config('localization.cookie_days', 365);

        setcookie($cookieName, $code, [
            'expires'  => time() + ($days * 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => RequestContext::isSecure(),
        ]);
        $_COOKIE[$cookieName] = $code;
    }

    private function fromUserPreference(): ?string
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (empty($userId)) {
            return null;
        }

        try {
            $pdo  = app(PDO::class);
            $stmt = $pdo->prepare('SELECT language FROM user_preferences WHERE user_id = ?');
            $stmt->execute([$userId]);
            $lang = $stmt->fetchColumn();
            if (is_string($lang)) {
                return $this->translator->canonical($lang);
            }
        } catch (\Throwable) {
            // Column/table may not exist yet (pre-migration) — fall through.
        }
        return null;
    }

    /**
     * Pick the highest-q supported locale from an Accept-Language header.
     */
    private function fromAcceptLanguage(string $header): ?string
    {
        if (trim($header) === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $bits = explode(';', trim($part));
            $code = trim($bits[0]);
            if ($code === '') {
                continue;
            }
            $q = 1.0;
            if (isset($bits[1]) && str_starts_with(trim($bits[1]), 'q=')) {
                $q = (float) substr(trim($bits[1]), 2);
            }
            $candidates[] = [$code, $q];
        }

        usort($candidates, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        foreach ($candidates as [$code, $q]) {
            $canon = $this->translator->canonical($code);
            if ($canon !== null) {
                return $canon;
            }
        }
        return null;
    }
}
