<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

class SettingsService
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadCache();

        if (!isset(self::$cache[$key])) {
            return $default;
        }

        return self::castValue(self::$cache[$key]['value'], self::$cache[$key]['type']);
    }

    public static function set(string $key, mixed $value): void
    {
        $repo = app(SettingsRepository::class);
        $repo->set($key, (string) $value);
        self::$cache = null;
    }

    public static function getByGroup(string $group): array
    {
        $repo = app(SettingsRepository::class);
        return $repo->getByGroup($group);
    }

    public static function all(): array
    {
        $repo = app(SettingsRepository::class);
        return $repo->all();
    }

    public static function bulkUpdate(array $settings): void
    {
        $repo = app(SettingsRepository::class);
        $repo->bulkUpdate($settings);
        self::$cache = null;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];
        try {
            $repo = app(SettingsRepository::class);
            $rows = $repo->all();
            foreach ($rows as $row) {
                self::$cache[$row['key']] = [
                    'value' => $row['value'],
                    'type'  => $row['type'] ?? 'string',
                ];
            }
        } catch (\Throwable $e) {
            // DB non disponibile — cache vuota
        }
    }

    private static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => (bool) (int) $value,
            'int'  => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
