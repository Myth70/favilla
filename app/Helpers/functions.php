<?php

/**
 * Helpers globali — popolati progressivamente nelle sessioni successive.
 * Sessione 1: e(), config(), env(), app()
 * Sessione 2: csrf_field(), csrf_token()
 * Sessione 4: auth()
 * Sessione 5: asset(), route()
 * (route() already added in Session 2 since Router was ready)
 */

if (!function_exists('e')) {
    /**
     * Escape HTML output to prevent XSS.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_log')) {
    /**
     * Write to the application log (Monolog) with a safe fallback.
     *
     * Routes through the container's ErrorHandler logger so log lines land in
     * storage/logs/app.log with structured context. If the container/logger is
     * not available (early bootstrap, some CLI paths, tests), it degrades to
     * error_log() — a logging failure must never break the request.
     *
     * @param 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency' $level
     * @param array<string,mixed> $context
     */
    function app_log(string $level, string $message, array $context = []): void
    {
        try {
            $container = \App\Core\Container::getInstance();
            if ($container->has(\App\Core\ErrorHandler::class)) {
                $container->make(\App\Core\ErrorHandler::class)->getLogger()->log($level, $message, $context);
                return;
            }
        } catch (\Throwable) {
            // fall through to the primitive fallback
        }
        error_log($message);
    }
}

if (!function_exists('flash')) {
    /**
     * Queue a one-shot flash message, shown on the next rendered page and then
     * cleared. Stored as $_SESSION['_flash_<type>'] — the layouts read 'success'
     * and 'error'.
     */
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash_' . $type] = $message;
    }
}

if (!function_exists('flash_success')) {
    function flash_success(string $message): void
    {
        flash('success', $message);
    }
}

if (!function_exists('flash_error')) {
    function flash_error(string $message): void
    {
        flash('error', $message);
    }
}

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // Cast common string values to native types
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Get a configuration value using dot notation.
     * Example: config('database.host') reads app/Config/database.php['host']
     *
     * The cache lives in ConfigCache::$data so config_flush() can clear it cleanly.
     */
    function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!isset(\App\Support\ConfigCache::$data[$file])) {
            $path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
                  . '/app/Config/' . $file . '.php';

            if (!file_exists($path)) {
                return $default;
            }
            \App\Support\ConfigCache::$data[$file] = require $path;
        }

        $value = \App\Support\ConfigCache::$data[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('config_flush')) {
    /**
     * Flush the config() cache.
     * Use in test tearDown to prevent cross-test contamination.
     */
    function config_flush(): void
    {
        \App\Support\ConfigCache::$data = [];
    }
}

if (!function_exists('app')) {
    /**
     * Get the Container instance, or resolve a class from it.
     */
    function app(?string $abstract = null): mixed
    {
        $container = \App\Core\Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract);
    }
}

// --- Sessione 2: CSRF helpers ---

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token value.
     */
    function csrf_token(): string
    {
        return \App\Security\CsrfToken::get();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden input field with the CSRF token.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route.
     */
    function route(string $name, array $params = []): string
    {
        $router = app(App\Core\Router::class);
        return $router->url($name, $params);
    }
}

// --- Sessione 5: asset() helper ---

if (!function_exists('asset')) {
    /**
     * Generate a URL to an asset in public/assets/.
     * Appends file mtime as cache-busting query string in production.
     */
    function asset(string $path): string
    {
        // External absolute URLs must pass through unchanged.
        // This prevents local filesystem checks (file_exists) on CDN assets.
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $basePath = rtrim(config('app.base_path', ''), '/');
        $baseUrl  = rtrim(config('app.url', ''), '/') . $basePath . '/assets/';
        $url      = $baseUrl . ltrim($path, '/');

        // Cache-busting: append file modification time
        $absPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
                 . '/public/assets/' . ltrim($path, '/');
        $mtime = file_exists($absPath) ? filemtime($absPath) : false;
        if ($mtime) {
            $url .= '?v=' . $mtime;
        }

        return $url;
    }
}

// --- Sessione 4: Auth helper ---

if (!function_exists('auth')) {
    /**
     * Get the authenticated user data from session, or null if not logged in.
     */
    function auth(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'          => $_SESSION['user_id'],
            'name'        => $_SESSION['user_name'] ?? '',
            'email'       => $_SESSION['user_email'] ?? '',
            'username'    => $_SESSION['user_username'] ?? '',
            'roles'       => $_SESSION['user_roles'] ?? [],
            'permissions' => $_SESSION['user_permissions'] ?? [],
        ];
    }
}

if (!function_exists('has_permission')) {
    /**
     * Check whether the authenticated user has a given permission slug.
     * Users with the 'admin' role always pass (super-admin bypass).
     */
    function has_permission(string $slug): bool
    {
        $roles = $_SESSION['user_roles'] ?? [];
        if (in_array('admin', $roles, true)) {
            return true;
        }
        return in_array($slug, $_SESSION['user_permissions'] ?? [], true);
    }
}

if (!function_exists('is_admin')) {
    /**
     * True se l'utente corrente ha il ruolo super-admin.
     *
     * Da usare come override "manage-all" per scopare un'azione al solo
     * proprietario + admin quando il modulo NON ha un permesso elevato
     * dedicato (es. Calendario non ha calendario.manage_all).
     *
     * ATTENZIONE: NON usare has_permission($slug) come override di ownership.
     * has_permission() ritorna true per gli admin su QUALSIASI slug, ma se lo
     * $slug è lo stesso permesso che già protegge la rotta, il check
     * "created_by !== userId || has_permission($slug)" diventa codice morto
     * (chiunque raggiunga la rotta lo possiede). Per il check di proprietà
     * serve un check di RUOLO esplicito: questo helper.
     */
    function is_admin(): bool
    {
        return in_array('admin', $_SESSION['user_roles'] ?? [], true);
    }
}

// --- Internationalization (i18n) helpers ---

if (!function_exists('translator')) {
    /**
     * Resolve the shared Translator singleton, lazily registering one if the
     * web bootstrap hasn't (CLI / tests / early bootstrap). Mirrors app_log()'s
     * defensive resolution so a missing container never fatals.
     */
    function translator(): \App\Services\Translator
    {
        $container = \App\Core\Container::getInstance();
        if (!$container->has(\App\Services\Translator::class)) {
            $container->instance(\App\Services\Translator::class, new \App\Services\Translator());
        }
        return $container->make(\App\Services\Translator::class);
    }
}

if (!function_exists('t')) {
    /**
     * Translate a symbolic dot-key for the active (or given) locale.
     * Returns the key unchanged when no translation exists — never fatals.
     * NOTE: like every other source of untrusted text, wrap with e() when
     * echoing into HTML: e(t('contacts.title')).
     *
     * @param array<string,scalar> $replace Placeholder replacements (:name / {{name}}).
     */
    function t(string $key, array $replace = [], ?string $locale = null): string
    {
        try {
            return translator()->get($key, $replace, $locale);
        } catch (\Throwable) {
            return $key;
        }
    }
}

if (!function_exists('__')) {
    /**
     * Alias of t().
     *
     * @param array<string,scalar> $replace
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return t($key, $replace, $locale);
    }
}

if (!function_exists('tc')) {
    /**
     * Pluralized translation. The lang value holds "singular|plural"; the
     * `:count` placeholder is auto-filled.
     *
     * @param array<string,scalar> $replace
     */
    function tc(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        try {
            return translator()->choice($key, $number, $replace, $locale);
        } catch (\Throwable) {
            return $key;
        }
    }
}

if (!function_exists('t_line')) {
    /**
     * Flat overlay translation: look up an exact slug/route-id key inside an
     * overlay namespace (nav / permissions / admin_panel / notifications),
     * falling back to $default (the canonical Italian from module.json) on miss.
     * Keeps module.json the source of truth while letting locales override.
     */
    function t_line(string $namespace, string $key, string $default = '', ?string $locale = null): string
    {
        try {
            return translator()->line($namespace, $key, $default, $locale) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('locale')) {
    /**
     * Current active locale code (e.g. 'it', 'en').
     */
    function locale(): string
    {
        try {
            return translator()->getLocale();
        } catch (\Throwable) {
            return 'it';
        }
    }
}

if (!function_exists('set_locale')) {
    /**
     * Set the active locale (no-op if unsupported).
     */
    function set_locale(string $locale): void
    {
        try {
            translator()->setLocale($locale);
        } catch (\Throwable) {
            // non-fatal
        }
    }
}

if (!function_exists('lang_flush')) {
    /**
     * Flush the lang-file cache. Use in test tearDown, mirrors config_flush().
     */
    function lang_flush(): void
    {
        \App\Support\LangCache::$data = [];
        \App\Support\LangCache::$missing = [];
    }
}

if (!function_exists('js_i18n_dict')) {
    /**
     * Flat "dot.key" => translated string map for the `js` lang namespace,
     * exposed to the browser as window.__I18N (layouts/main.php + auth.php)
     * and consumed via the JS t(key, fallback) helper in public/assets/js/app.js.
     * Keyed off the `it` baseline shape (resources/lang/it/js.php) and resolved
     * per-key via t(), so each locale falls back to Italian. Keys that still
     * resolve to their own literal (missing in both locale and fallback) are
     * omitted so the JS-side fallback argument applies instead of leaking a
     * raw key into the UI.
     *
     * @return array<string,string>
     */
    function js_i18n_dict(): array
    {
        $flatten = static function (array $arr, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($arr as $k => $v) {
                $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
                $out = is_array($v) ? array_merge($out, $flatten($v, $path)) : array_merge($out, [$path]);
            }
            return $out;
        };

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $path = $base . '/resources/lang/it/js.php';
        $keys = is_file($path) ? $flatten((array) require $path) : [];

        $dict = [];
        foreach ($keys as $key) {
            $value = t('js.' . $key);
            if ($value !== 'js.' . $key) {
                $dict['js.' . $key] = $value;
            }
        }
        return $dict;
    }
}

// --- Shared date/number formatting helpers (locale-aware) ---

if (!function_exists('format_date')) {
    /**
     * Format a datetime string for display in the active (or given) locale.
     *
     * Numeric date layout stays dd/mm/yyyy across locales (the app is
     * euro-area); only the textual month/day/relative labels are localized,
     * sourced from resources/lang/<locale>/datetime.php with an Italian
     * fallback. Modes: 'time' | 'short' | 'compact' | 'relative' | 'long'.
     *
     * Returns '' for null/empty input.
     */
    function format_date(?string $datetime, string $mode = 'compact', ?string $locale = null): string
    {
        if (!$datetime) {
            return '';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }

        $loc = $locale ?? locale();

        $monthsShort = translator()->getArray('datetime.months_short', $loc);
        $monthsLong  = translator()->getArray('datetime.months_long', $loc);
        $daysLong    = translator()->getArray('datetime.days_long', $loc);

        // Hard fallback to Italian arrays if a datetime file is missing/partial.
        if (count($monthsShort) < 12) {
            $monthsShort = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
        }
        if (count($monthsLong) < 12) {
            $monthsLong = ['gennaio','febbraio','marzo','aprile','maggio','giugno',
                           'luglio','agosto','settembre','ottobre','novembre','dicembre'];
        }
        if (count($daysLong) < 7) {
            $daysLong = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
        }

        $todayLabel     = t('datetime.today', [], $loc);
        $yesterdayLabel = t('datetime.yesterday', [], $loc);

        $today     = strtotime('today');
        $yesterday = strtotime('yesterday');

        return match ($mode) {
            'time'  => date('H:i', $ts),
            'short' => date('d/m/Y', $ts),
            'compact' => match (true) {
                $ts >= $today     => date('H:i', $ts),
                $ts >= $yesterday => $yesterdayLabel,
                date('Y', $ts) === date('Y') => (int)date('d', $ts) . ' ' . ($monthsShort[(int)date('n', $ts) - 1] ?? ''),
                default           => date('d/m/y', $ts),
            },
            'relative' => match (true) {
                $ts >= $today     => $todayLabel,
                $ts >= $yesterday => $yesterdayLabel,
                default           => ($daysLong[(int)date('w', $ts)] ?? '') . ' ' .
                                     (int)date('d', $ts) . ' ' .
                                     ($monthsLong[(int)date('n', $ts) - 1] ?? '') . ' ' .
                                     date('Y', $ts),
            },
            'long' => ($daysLong[(int)date('w', $ts)] ?? '') . ' ' .
                      (int)date('d', $ts) . ' ' .
                      ($monthsLong[(int)date('n', $ts) - 1] ?? '') . ' ' .
                      date('Y', $ts) . ' ' . date('H:i', $ts),
            default => date('d/m/Y H:i', $ts),
        };
    }
}

if (!function_exists('format_date_it')) {
    /**
     * Back-compat shim: Italian date formatting via format_date().
     * Existing call sites keep working byte-for-byte; new code should call
     * format_date() so it follows the active locale.
     */
    function format_date_it(?string $datetime, string $mode = 'compact'): string
    {
        return format_date($datetime, $mode, 'it');
    }
}

if (!function_exists('format_number')) {
    /**
     * Locale-aware number formatting (uses ext-intl when available).
     */
    function format_number(float|int $value, int $decimals = 0, ?string $locale = null): string
    {
        $loc = $locale ?? locale();

        if (config('localization.intl') && class_exists('\\NumberFormatter')) {
            $icu = config('localization.intl_locale.' . $loc, 'it_IT');
            $fmt = new \NumberFormatter($icu, \NumberFormatter::DECIMAL);
            $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
            $out = $fmt->format($value);
            if ($out !== false) {
                return $out;
            }
        }

        // Fallback: Italian/EU convention (1.234,56).
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (!function_exists('format_currency')) {
    /**
     * Locale-aware currency formatting (uses ext-intl when available).
     */
    function format_currency(float|int $value, ?string $currency = null, ?string $locale = null): string
    {
        $loc = $locale ?? locale();
        $currency ??= (string) config('localization.currency.' . $loc, 'EUR');

        if (config('localization.intl') && class_exists('\\NumberFormatter')) {
            $icu = config('localization.intl_locale.' . $loc, 'it_IT');
            $fmt = new \NumberFormatter($icu, \NumberFormatter::CURRENCY);
            $out = $fmt->formatCurrency((float) $value, $currency);
            if ($out !== false) {
                return $out;
            }
        }

        return number_format((float) $value, 2, ',', '.') . ' €';
    }
}

if (!function_exists('isModuleEnabled')) {
    /**
     * Check whether a module is enabled (both in config and after DB overrides).
     * Result is cached statically for the lifetime of the request.
     *
     * Usage: isModuleEnabled('Notifications'), isModuleEnabled('Files')
     */
    function isModuleEnabled(string $moduleName): bool
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            foreach (app(\App\Core\ModuleLoader::class)->getModules() as $m) {
                $cache[$m['name']] = (bool) ($m['enabled'] ?? true);
            }
        }
        return $cache[$moduleName] ?? false;
    }
}

if (!function_exists('navigation')) {
    /**
     * Ritorna le voci di navigazione per una specifica superficie della UI,
     * filtrate per i permessi dell'utente corrente.
     *
     * Surfaces supportate: 'sidebar', 'user_menu', 'radial', 'quick_access'.
     *
     * Ogni entry ha la forma:
     *   [
     *     'id'         => string,   // identificatore stabile (es. 'contacts.index')
     *     'label'      => string,
     *     'icon'       => string,   // classe Font Awesome (es. 'fa-address-book')
     *     'route'      => string,   // nome della rotta
     *     'permission' => ?string,  // slug richiesto o null
     *     'order'      => int,
     *     'surfaces'   => string[], // superfici su cui appare
     *     'module'     => string,
     *     'children'   => array,
     *   ]
     */
    function navigation(string $surface): array
    {
        $registry = app(\App\Services\NavigationRegistry::class);
        $perms = $_SESSION['user_permissions'] ?? null;
        $roles = $_SESSION['user_roles'] ?? [];
        return $registry->forSurface($surface, $perms, $roles);
    }
}

if (!function_exists('sort_link')) {
    /**
     * Genera un anchor sortable per thead di tabelle HTMX.
     *
     * Produce <a href="..." hx-get="..." hx-target="..." hx-push-url="..." class="...">
     * con query string che preserva i $filters esistenti, sovrascrive sort/dir
     * e resetta la pagina a 1. I valori stringa vuota nei filtri vengono rimossi;
     * i valori 0 e false sono conservati.
     * Il separatore &amp; rende l'output sicuro negli attributi HTML.
     *
     * NON passare il risultato attraverso e(): contiene già entità &amp; corrette.
     *
     * @param string $col     Nome colonna (valore del param `sort`).
     * @param string $label   Testo del link (viene escaped).
     * @param string $sort    Colonna attualmente ordinata.
     * @param string $dir     Direzione attuale ('ASC'|'DESC', case-insensitive).
     * @param array  $filters Filtri attivi da preservare nell'URL.
     * @param string $url     URL base per href e hx-get (es. route('items.index')).
     * @param string $target  Selettore CSS per hx-target (es. '#items-table').
     * @param array  $options {
     *   push_url bool   Default true. False emette hx-push-url="false".
     *   class    string Default 'text-decoration-none text-body'.
     *   page_key string Default 'page'. Passa '' per non resettare la pagina.
     *   extra    array  Parametri aggiuntivi mergiati per ultimi nel query string.
     * }
     * @return string HTML dell'anchor (già safe — non ripassare per e()).
     */
    function sort_link(
        string $col,
        string $label,
        string $sort,
        string $dir,
        array  $filters,
        string $url,
        string $target,
        array  $options = []
    ): string {
        $dirUpper = strtoupper($dir);
        $newDir   = ($sort === $col && $dirUpper === 'ASC') ? 'DESC' : 'ASC';

        $icon = '';
        if ($sort === $col) {
            $arrow = $dirUpper === 'ASC' ? 'up' : 'down';
            $icon  = ' <i class="fa-solid fa-sort-' . $arrow . ' text-primary"></i>';
        }

        // Rimuove solo stringhe vuote; 0, false, null restano
        $cleanFilters = array_filter(
            $filters,
            static fn (mixed $v): bool => $v !== ''
        );

        $params = array_merge($cleanFilters, ['sort' => $col, 'dir' => $newDir]);

        $pageKey = $options['page_key'] ?? 'page';
        if ($pageKey !== '') {
            $params[$pageKey] = 1;
        }

        if (!empty($options['extra']) && is_array($options['extra'])) {
            $params = array_merge($params, $options['extra']);
        }

        // &amp; come separatore → safe nei valori di attributo HTML
        $qs   = http_build_query($params, '', '&amp;');
        // $qs contiene già &amp; letterali: NON applicare e() ulteriormente
        $href = e(rtrim($url, '?')) . '?' . $qs;

        $pushUrl = $options['push_url'] ?? true;
        $class   = $options['class']    ?? 'text-decoration-none text-body';

        $attrs  = ' href="'         . $href                          . '"';
        $attrs .= ' hx-get="'      . $href                          . '"';
        $attrs .= ' hx-target="'   . e($target)                     . '"';
        $attrs .= ' hx-push-url="' . ($pushUrl ? 'true' : 'false')  . '"';
        $attrs .= ' class="'       . e($class)                      . '"';

        return '<a' . $attrs . '>' . e($label) . $icon . '</a>';
    }
}

if (!function_exists('sort_context')) {
    /**
     * Restituisce una closure pre-configurata per sort_link().
     *
     * Elimina il boilerplate della closure $sortLink locale in ogni partial.
     * La closure restituita è static (nessuna cattura di $this) e idempotente.
     *
     * Uso:
     *   $sh = sort_context($sortBy, $sortDir, $filters, route('items.index'), '#items-table');
     *   echo '<th>' . $sh('title',      'Titolo') . '</th>';
     *   echo '<th>' . $sh('created_at', 'Data')   . '</th>';
     *
     * Con extra params (es. Files — mantiene view=list):
     *   $sh = sort_context(
     *       $filters['sort'], $filters['dir'], $filters,
     *       route('files.index'), '#files-container',
     *       ['extra' => ['view' => 'list']]
     *   );
     *
     * @param string $sort    Colonna attualmente ordinata.
     * @param string $dir     Direzione attuale ('ASC'|'DESC').
     * @param array  $filters Filtri da preservare.
     * @param string $url     URL base per href e hx-get.
     * @param string $target  Selettore CSS per hx-target.
     * @param array  $options Forwarded a sort_link() (vedi relativo docblock).
     * @return \Closure(string $col, string $label): string
     */
    function sort_context(
        string $sort,
        string $dir,
        array  $filters,
        string $url,
        string $target,
        array  $options = []
    ): \Closure {
        return static function (string $col, string $label) use (
            $sort,
            $dir,
            $filters,
            $url,
            $target,
            $options
        ): string {
            return sort_link($col, $label, $sort, $dir, $filters, $url, $target, $options);
        };
    }
}

if (!function_exists('app_version')) {
    /**
     * Returns the latest published app version string from the changelogs table.
     * Result is cached statically for the lifetime of the request.
     * Returns null if no published release exists or the table is unavailable.
     */
    function app_version(): ?string
    {
        static $version = false;
        if ($version === false) {
            try {
                $pdo  = app(\PDO::class);
                $stmt = $pdo->query(
                    'SELECT version FROM changelogs WHERE is_published = 1
                     ORDER BY release_date DESC, id DESC LIMIT 1'
                );
                $row     = $stmt->fetch(\PDO::FETCH_ASSOC);
                $version = $row ? $row['version'] : null;
            } catch (\Throwable $e) {
                $version = null;
            }
        }
        return $version;
    }
}

if (!function_exists('setting')) {
    /**
     * Get a setting value from the database (app_settings table).
     * SettingsService gestisce già una cache static per tutti i settings
     * (una singola query SELECT * al primo accesso), quindi qui non
     * aggiungiamo un livello ulteriore per restare compatibili con clearCache().
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Services\SettingsService::get($key, $default);
    }
}

if (!function_exists('csp_nonce')) {
    /**
     * Get the CSP nonce for the current request.
     */
    function csp_nonce(): string
    {
        return app(\App\Services\NonceService::class)->getNonce();
    }
}

if (!function_exists('cover_url')) {
    /**
     * Costruisce l'URL pubblico di un'immagine.
     *
     * Gestisce due formati per il campo cover_image / avatar_path:
     *  - basename puro (es. "cover_abc123.jpg")   → cerca in uploads/$directory/
     *  - path relativo con '/' (es. "files/img.jpg") → file proveniente dalla
     *    libreria Files, url= uploads/files/img.jpg
     *
     * Usato da Auth (avatar_path via AvatarHelper) e
     * da qualsiasi modulo che integri il File Picker.
     *
     * @param string|null $path             Valore memorizzato nel DB.
     * @param string      $defaultDirectory Directory di upload diretta (es. 'files', 'avatars').
     */
    function cover_url(?string $path, string $defaultDirectory): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (str_contains($path, '/')) {
            // Path relativo dalla root uploads/ (file dalla libreria)
            return asset('uploads/' . $path);
        }
        return \App\Services\FileUploadService::url($path, $defaultDirectory);
    }
}

if (!function_exists('edition')) {
    /**
     * Risolve l'edizione corrente: APP_EDITION (env) > setting('app_edition') > default.
     * Un valore che non corrisponde a un profilo noto ricade su 'developer'.
     * Niente cache statica: setting() ha già la sua in SettingsService.
     */
    function edition(): string
    {
        $profiles = array_keys(config('editions.profiles', []));

        $fromEnv = env('APP_EDITION');
        if (is_string($fromEnv) && in_array($fromEnv, $profiles, true)) {
            return $fromEnv;
        }

        $fromSetting = setting('app_edition');
        if (is_string($fromSetting) && in_array($fromSetting, $profiles, true)) {
            return $fromSetting;
        }

        $default = config('editions.default', 'developer');
        return in_array($default, $profiles, true) ? $default : 'developer';
    }
}

if (!function_exists('edition_profile')) {
    /**
     * Configurazione del profilo edizione corrente (label, single_user, sidebar_hidden_modules).
     */
    function edition_profile(): array
    {
        return config('editions.profiles.' . edition(), []);
    }
}

if (!function_exists('is_single_user')) {
    /**
     * True se l'edizione corrente è pensata per un solo utente (Personal).
     */
    function is_single_user(): bool
    {
        return (bool) (edition_profile()['single_user'] ?? false);
    }
}
