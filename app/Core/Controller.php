<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected ?View $view = null;

    /**
     * Render a full view with layout.
     * Injects shared data: user, cssVars, currentTheme, currentSkin.
     */
    protected function render(string $template, array $data = []): void
    {
        if ($this->view !== null) {
            $this->prepareSharedData($data);
            $this->view->render($template, $data);
            return;
        }

        // Fallback: basic output (should not reach here after Session 5)
        extract($data, EXTR_SKIP);
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $file = $basePath . '/app/Views/' . $template . '.php';

        if (!file_exists($file)) {
            $file = $basePath . '/app/Modules/' . $template . '.php';
        }

        if (file_exists($file)) {
            include $file;
        } else {
            echo '<p>View not found: ' . e($template) . '</p>';
        }
    }

    /**
     * Render a partial template (no layout) — for HTMX responses.
     */
    protected function renderPartial(string $template, array $data = []): void
    {
        if ($this->view !== null) {
            $this->view->renderPartial($template, $data);
            return;
        }

        extract($data, EXTR_SKIP);
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $file = $basePath . '/app/Views/' . $template . '.php';

        if (!file_exists($file)) {
            $file = $basePath . '/app/Modules/' . $template . '.php';
        }

        if (file_exists($file)) {
            include $file;
        }
    }

    /**
     * Check if this is an HTMX request.
     */
    protected function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']);
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url): void
    {
        // Test seam: under PHPUnit (FAVILLA_TESTING is defined in tests/bootstrap.php)
        // throw a catchable signal instead of calling exit, so controller actions stay
        // unit-testable. The guard is never defined in production → behaviour unchanged.
        if (defined('FAVILLA_TESTING')) {
            throw new Testing\HaltResponse(Testing\HaltResponse::REDIRECT, $url);
        }
        header('Location: ' . $url);
        exit;
    }

    /**
     * Send a JSON response (for internal use only — API endpoints are out of scope).
     */
    protected function json(array $data, int $status = 200): void
    {
        if (defined('FAVILLA_TESTING')) {
            throw new Testing\HaltResponse(Testing\HaltResponse::JSON, null, $status, $data);
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        echo $encoded;
        exit;
    }

    /**
     * Set the View instance (injected by Application before dispatch).
     */
    public function setView(View $view): void
    {
        $this->view = $view;
    }

    /**
     * Prepare shared data for every rendered view.
     * Reads user preferences for CSS vars and theme, builds menu items.
     */
    private function prepareSharedData(array &$data): void
    {
        // User data from session
        $user = auth();
        if (!array_key_exists('user', $data)) {
            $data['user'] = $user;
        }

        // User preferences → CSS vars (validated whitelist)
        $prefs = $this->getUserPreferences();

        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $prefs['primary_color'] ?? '')
            ? $prefs['primary_color']
            : '#3b82f6';
        $theme = in_array($prefs['theme'] ?? '', ['light', 'dark'], true)
            ? $prefs['theme']
            : 'light';

        $allowedSkins = ['default', 'soft', 'sharp', 'compact'];
        $skin = in_array($prefs['theme_skin'] ?? '', $allowedSkins, true)
            ? $prefs['theme_skin']
            : 'default';

        $allowedFonts = ['system', 'inter', 'plex', 'lora', 'jetbrains'];
        $font = in_array($prefs['font_family'] ?? '', $allowedFonts, true)
            ? $prefs['font_family']
            : 'system';

        $allowedSidebarStyles = ['default', 'light', 'accent'];
        $sidebarStyle = in_array($prefs['sidebar_style'] ?? '', $allowedSidebarStyles, true)
            ? $prefs['sidebar_style']
            : 'default';

        // Map font slug to CSS font stack. 'system' must emit an explicit stack
        // (identico al default di app.css) per sovrascrivere le skin che ridefiniscono
        // --font-family-base/heading via [data-theme-skin="..."].
        $fontStacks = [
            'system'    => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            'inter'     => '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            'plex'      => '"IBM Plex Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            'lora'      => '"Lora", Georgia, Cambria, "Times New Roman", serif',
            'jetbrains' => '"JetBrains Mono", ui-monospace, Menlo, Consolas, "Courier New", monospace',
        ];

        $stack = $fontStacks[$font] ?? $fontStacks['system'];
        $fontCss = " --font-family-base: {$stack}; --font-family-heading: {$stack};";

        if (!isset($data['cssVars'])) {
            $data['cssVars'] = "--accent: {$color}; --theme: {$theme};{$fontCss}";
        }
        if (!isset($data['currentTheme'])) {
            $data['currentTheme'] = $theme;
        }
        if (!isset($data['currentSkin'])) {
            $data['currentSkin'] = $skin;
        }
        if (!isset($data['currentFont'])) {
            $data['currentFont'] = $font;
        }
        if (!isset($data['currentSidebarStyle'])) {
            $data['currentSidebarStyle'] = $sidebarStyle;
        }

        // Active locale for <html lang> and the language switcher. This is the
        // locale actually resolved/applied for the request (LocaleResolver),
        // which already accounts for the stored preference, cookie and ?lang.
        if (!isset($data['currentLocale'])) {
            $data['currentLocale'] = locale();
        }

        // Default page title
        if (!isset($data['pageTitle'])) {
            $data['pageTitle'] = 'Home';
        }

        // Default breadcrumbs
        if (!isset($data['breadcrumbs'])) {
            $data['breadcrumbs'] = [];
        }
    }

    /**
     * Get user preferences from session cache or DB.
     */
    private function getUserPreferences(): array
    {
        $defaultPreferences = [
            'theme' => 'light',
            'primary_color' => '#3b82f6',
            'sidebar_collapsed' => 0,
            'sidebar_style' => 'default',
            'background_pattern' => 'circles',
            'theme_skin' => 'default',
            'font_family' => 'system',
            'language' => (string) config('localization.default', 'it'),
        ];

        // Use cached preferences from session
        if (!empty($_SESSION['user_preferences'])) {
            $cachedPreferences = (array) $_SESSION['user_preferences'];
            $requiredKeys = array_keys($defaultPreferences);
            $hasAllRequiredKeys = count(array_intersect($requiredKeys, array_keys($cachedPreferences))) === count($requiredKeys);

            if ($hasAllRequiredKeys) {
                return array_replace($defaultPreferences, $cachedPreferences);
            }
        }

        // Fetch from DB if user is logged in
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return $defaultPreferences;
        }

        $pdo = app(\PDO::class);
        $stmt = $pdo->prepare('SELECT theme, primary_color, sidebar_collapsed, sidebar_style, background_pattern, theme_skin, font_family, language FROM user_preferences WHERE user_id = ?');
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(\PDO::FETCH_ASSOC);

        $prefs = is_array($prefs) ? array_replace($defaultPreferences, $prefs) : $defaultPreferences;

        $_SESSION['user_preferences'] = $prefs;
        return $prefs;
    }
}
