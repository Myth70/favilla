<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Modules\Home\Services\PreferencesService;
use App\Services\LocaleResolver;

/**
 * Public language switcher. Works for anonymous visitors (login/public pages)
 * and authenticated users alike: it always sets the session + cookie locale,
 * and additionally persists the stored preference when a user is logged in so
 * the choice survives the next login.
 *
 * GET is intentional — switching the UI language is a benign, idempotent,
 * user-scoped personalization (same class as theme switching), not a
 * security-sensitive mutation.
 */
class LocaleController extends Controller
{
    public function switch(string $code): void
    {
        $applied = app(LocaleResolver::class)->apply($code, true);

        $userId = auth()['id'] ?? null;
        if ($userId) {
            try {
                $stored = app(PreferencesService::class)->updateLanguage((int) $userId, $applied);
                $_SESSION['user_preferences']['language'] = $stored;
            } catch (\Throwable $e) {
                app_log('error', '[i18n] updateLanguage failed: ' . $e->getMessage());
            }
        }

        $target = $this->safeRedirectTarget();

        if (isset($_SERVER['HTTP_HX_REQUEST'])) {
            header('HX-Redirect: ' . $target);
            http_response_code(204);
            return;
        }

        $this->redirect($target);
    }

    /**
     * Redirect back to the originating page when it is same-host, else home/login.
     */
    private function safeRedirectTarget(): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $appHost = parse_url(rtrim((string) config('app.url', ''), '/'), PHP_URL_HOST);
            $refHost = parse_url($referer, PHP_URL_HOST);
            if ($refHost !== null && ($appHost === null || $appHost === '' || $refHost === $appHost)) {
                return $referer;
            }
        }

        try {
            return auth() ? route('home') : route('login');
        } catch (\Throwable) {
            return rtrim((string) config('app.base_path', ''), '/') . '/';
        }
    }
}
