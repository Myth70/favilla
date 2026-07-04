<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Support;

/**
 * Conversioni di dimensioni in byte usate dai check (memory_limit, upload, ecc.).
 *
 * Estratto dalla vecchia HealthCheckService::parseBytes() per essere riusabile
 * e testabile in isolamento.
 */
final class Bytes
{
    /**
     * Converte un valore tipo "128M", "2G", "512K" o un numero grezzo in byte.
     */
    public static function parse(string $val): int
    {
        $val  = trim($val);
        if ($val === '') {
            return 0;
        }

        $last = strtolower($val[-1]);
        $num  = (int) $val;

        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    /**
     * Formatta un numero di byte in stringa leggibile (es. "1.5 GB").
     */
    public static function human(int $bytes, int $decimals = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $pow   = min($pow, count($units) - 1);
        $value = $bytes / (1024 ** $pow);

        return round($value, $decimals) . ' ' . $units[$pow];
    }
}
