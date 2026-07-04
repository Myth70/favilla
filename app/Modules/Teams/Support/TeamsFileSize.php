<?php

declare(strict_types=1);

namespace App\Modules\Teams\Support;

/**
 * Formattatore di dimensioni file (binario, base 1024) per il tab File
 * dell'offcanvas gruppo e le anteprime allegati.
 */
class TeamsFileSize
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    /**
     * Restituisce una stringa human-readable: "0 B", "456 B", "1.2 MB", "3.4 GB".
     */
    public static function format(int $bytes, int $decimals = 1): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $unit  = 0;
        $value = (float) $bytes;
        $max   = count(self::UNITS) - 1;
        while ($value >= 1024 && $unit < $max) {
            $value /= 1024;
            $unit++;
        }

        $decimals = max(0, $decimals);
        $rounded  = round($value, $decimals);
        // Se è intero esatto, evita ".0"
        if ($rounded == (int) $rounded) {
            return ((int) $rounded) . ' ' . self::UNITS[$unit];
        }
        return number_format($rounded, $decimals, '.', '') . ' ' . self::UNITS[$unit];
    }
}
