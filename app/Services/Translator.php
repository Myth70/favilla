<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\LangCache;

/**
 * Minimal, dependency-free translation engine.
 *
 * Keys are symbolic dot-notation: the first segment is the lang-file namespace
 * (one PHP file per module + cross-cutting `common`, `validation`, `errors`,
 * `auth`, `nav`, `datetime`, and the module.json overlays), the rest is the
 * nested path inside that file. Example:
 *
 *   t('contacts.form.label.company')
 *     -> resources/lang/<locale>/contacts.php ['form']['label']['company']
 *
 * Lookups fall back active-locale -> fallback-locale -> the key itself, so a
 * missing translation degrades to readable text and never fatals. Placeholders
 * use `:name` (Validator style) and `{{name}}` (mail-template style).
 *
 * Registered as a singleton in bootstrap/app.php; the t()/__() helpers also
 * lazily register an instance so CLI and tests work without the web bootstrap.
 */
class Translator
{
    /** @var list<string> */
    private array $supported;
    private string $locale;
    private string $fallback;

    /** @var array<string,mixed> Raw localization config */
    private array $config;

    /**
     * @param array<string,mixed>|null $config Defaults to config('localization').
     */
    public function __construct(?array $config = null)
    {
        $this->config    = $config ?? (array) config('localization', []);
        $supported       = $this->config['supported'] ?? ['it'];
        $this->supported = array_values(array_map(
            fn ($l): string => $this->normalize((string) $l),
            is_array($supported) ? $supported : ['it']
        ));
        $this->fallback  = $this->normalize((string) ($this->config['fallback'] ?? 'it'));

        $default = $this->normalize((string) ($this->config['default'] ?? 'it'));
        $this->locale = in_array($default, $this->supported, true)
            ? $default
            : ($this->supported[0] ?? 'it');
    }

    // ------------------------------------------------------------------
    // Locale state
    // ------------------------------------------------------------------

    /**
     * Set the active locale. Ignored (current kept) if not supported.
     */
    public function setLocale(string $locale): void
    {
        $locale = $this->normalize($locale);
        if (in_array($locale, $this->supported, true)) {
            $this->locale = $locale;
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    /** @return list<string> */
    public function getSupported(): array
    {
        return $this->supported;
    }

    public function isSupported(string $locale): bool
    {
        return in_array($this->normalize($locale), $this->supported, true);
    }

    /**
     * Normalize a raw locale to its canonical supported code, or null.
     * "en_GB" -> "en"; unsupported -> null. Does not mutate the active locale.
     */
    public function canonical(string $locale): ?string
    {
        $normalized = $this->normalize($locale);
        return in_array($normalized, $this->supported, true) ? $normalized : null;
    }

    /** @return array<string,string> code => endonym */
    public function names(): array
    {
        $names = $this->config['names'] ?? [];
        return is_array($names) ? $names : [];
    }

    // ------------------------------------------------------------------
    // Translation
    // ------------------------------------------------------------------

    /**
     * Translate a key. Returns the key unchanged if no translation exists.
     *
     * @param array<string,scalar> $replace Placeholder replacements.
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale !== null ? $this->normalize($locale) : $this->locale;

        $value = $this->lookup($key, $locale);
        if ($value === null && $locale !== $this->fallback) {
            $value = $this->lookup($key, $this->fallback);
        }

        if (!is_string($value)) {
            // Missing, or the key points to a sub-array rather than a leaf string.
            LangCache::$missing[$locale . ':' . $key] = true;
            return $key;
        }

        return $this->interpolate($value, $replace);
    }

    /**
     * Pluralization: the lang value holds "singular|plural" (or
     * "zero|one|many"); the segment is chosen by $number. The `:count`
     * placeholder is auto-filled.
     *
     * @param array<string,scalar> $replace
     */
    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale !== null ? $this->normalize($locale) : $this->locale;

        $line = $this->lookup($key, $locale);
        if ($line === null && $locale !== $this->fallback) {
            $line = $this->lookup($key, $this->fallback);
        }
        if (!is_string($line)) {
            LangCache::$missing[$locale . ':' . $key] = true;
            return $key;
        }

        $parts = explode('|', $line);
        if (count($parts) === 3) {
            $segment = $number === 0 ? $parts[0] : ($number === 1 ? $parts[1] : $parts[2]);
        } else {
            $segment = $number === 1 ? ($parts[0] ?? $line) : ($parts[1] ?? $parts[0] ?? $line);
        }

        return $this->interpolate($segment, $replace + ['count' => $number]);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale !== null ? $this->normalize($locale) : $this->locale;
        return is_string($this->lookup($key, $locale))
            || ($locale !== $this->fallback && is_string($this->lookup($key, $this->fallback)));
    }

    /**
     * Flat lookup: fetch one exact key from a namespace file WITHOUT splitting on
     * dots. Used by the module.json overlays (nav / permissions / admin_panel /
     * notifications) whose keys are slugs/route-ids that contain dots. Returns
     * $default (typically the canonical Italian from module.json) on miss, so
     * untranslated entries degrade gracefully.
     */
    public function line(string $namespace, string $key, ?string $default = null, ?string $locale = null): ?string
    {
        $locale = $locale !== null ? $this->normalize($locale) : $this->locale;

        $items = $this->load($locale, $namespace);
        if (array_key_exists($key, $items) && is_string($items[$key])) {
            return $items[$key];
        }

        if ($locale !== $this->fallback) {
            $items = $this->load($this->fallback, $namespace);
            if (array_key_exists($key, $items) && is_string($items[$key])) {
                return $items[$key];
            }
        }

        return $default;
    }

    /**
     * Return a key that resolves to an array node (e.g. datetime.months_long),
     * with the same locale -> fallback resolution. Empty array on miss.
     *
     * @return array<int|string,mixed>
     */
    public function getArray(string $key, ?string $locale = null): array
    {
        $locale = $locale !== null ? $this->normalize($locale) : $this->locale;
        $value = $this->lookup($key, $locale);
        if (!is_array($value) && $locale !== $this->fallback) {
            $value = $this->lookup($key, $this->fallback);
        }
        return is_array($value) ? $value : [];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Resolve a dot-key inside one locale. Returns null when absent.
     */
    private function lookup(string $key, string $locale): mixed
    {
        $segments = explode('.', $key);
        $namespace = array_shift($segments);
        if ($namespace === '' || $segments === []) {
            return null;
        }

        $items = $this->load($locale, $namespace);
        $value = $items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /**
     * Load (and cache) one namespace file for one locale.
     *
     * @return array<string,mixed>
     */
    private function load(string $locale, string $namespace): array
    {
        if (!preg_match('/^[a-z0-9_\-]+$/i', $namespace)) {
            return [];
        }

        $cacheKey = $locale . ':' . $namespace;
        if (isset(LangCache::$data[$cacheKey])) {
            return LangCache::$data[$cacheKey];
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $path = $base . '/resources/lang/' . $locale . '/' . $namespace . '.php';

        $data = is_file($path) ? require $path : [];
        if (!is_array($data)) {
            $data = [];
        }

        LangCache::$data[$cacheKey] = $data;
        return $data;
    }

    /**
     * Replace `:name` and `{{name}}` placeholders.
     *
     * @param array<string,scalar> $replace
     */
    private function interpolate(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        $map = [];
        foreach ($replace as $name => $value) {
            $value = (string) $value;
            $map[':' . $name]      = $value;
            $map['{{' . $name . '}}']   = $value;
            $map['{{ ' . $name . ' }}'] = $value;
        }
        return strtr($line, $map);
    }

    /**
     * Normalize "en_GB" / "en-GB" / "EN" -> "en".
     */
    private function normalize(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $locale = str_replace('_', '-', $locale);
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale, 2)[0];
        }
        return substr($locale, 0, 5);
    }
}
