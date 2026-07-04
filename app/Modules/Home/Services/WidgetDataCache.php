<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

/**
 * Small file-based TTL cache for dashboard widget payloads.
 *
 * Widgets load via one HTTP request each (in parallel) and auto-refresh every
 * 120s, so a short cross-request cache prevents re-running queries / external
 * HTTP calls (notably the weather widget) on every hit. Per user + widget.
 *
 * Stored under storage/cache/widgets/ as serialized payloads; expired or
 * unreadable files are treated as a miss and pruned opportunistically.
 */
class WidgetDataCache
{
    private string $dir;

    public function __construct()
    {
        $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $this->dir = $root . '/storage/cache/widgets';
    }

    /**
     * Return the cached payload for $key, or compute it via $callback, store it
     * for $ttl seconds and return it. A $ttl <= 0 bypasses the cache entirely.
     *
     * @param  callable():(array<string,mixed>|null) $callback
     * @return array<string,mixed>|null
     */
    public function remember(string $key, int $ttl, callable $callback): ?array
    {
        if ($ttl <= 0) {
            return $callback();
        }

        $cached = $this->get($key);
        if ($cached !== false) {
            return $cached;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * @return array<string,mixed>|null|false  false = miss; null = cached null
     */
    public function get(string $key)
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return false;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }

        // allowed_classes:false → the payload is plain data; never instantiate
        // objects from cache files (defense-in-depth against object injection).
        $entry = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($entry) || !isset($entry['expires'])) {
            @unlink($file);
            return false;
        }

        if ($entry['expires'] < time()) {
            @unlink($file);
            return false;
        }

        return $entry['value'];
    }

    /**
     * @param array<string,mixed>|null $value
     */
    public function put(string $key, ?array $value, int $ttl): void
    {
        if (!$this->ensureDir()) {
            return;
        }

        $entry = ['expires' => time() + $ttl, 'value' => $value];
        @file_put_contents($this->path($key), serialize($entry), LOCK_EX);
    }

    public function forget(string $key): void
    {
        $file = $this->path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Drop every cached widget payload for a user (e.g. after layout changes).
     */
    public function flushUser(int $userId): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/u' . $userId . '_*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function path(string $key): string
    {
        // Defense-in-depth: callers already sanitise the key, but never let it
        // escape the cache dir. Keep only [A-Za-z0-9._-] and neutralise "..".
        $safe = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $key);
        $safe = str_replace('..', '_', $safe);
        return $this->dir . '/' . $safe . '.cache';
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dir)) {
            return true;
        }
        return @mkdir($this->dir, 0775, true) || is_dir($this->dir);
    }
}
