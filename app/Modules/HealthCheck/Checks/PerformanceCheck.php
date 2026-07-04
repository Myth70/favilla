<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\HealthCheck\Support\Bytes;

/**
 * Configurazioni e accumuli che possono degradare il sistema nel tempo.
 */
class PerformanceCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'performance';
    }

    public function label(): string
    {
        return 'Prestazioni';
    }

    public function description(): string
    {
        return 'Configurazioni e accumuli che possono degradare il sistema nel tempo.';
    }

    protected function checks(): array
    {
        $checks = [];
        $base   = BASE_PATH;
        $isProduction = $this->isProduction();

        // OPcache
        $opcache = function_exists('opcache_get_status') && @opcache_get_status(false) !== false;
        if ($opcache) {
            $checks[] = $this->ok('Cache opcode PHP', 'attiva');
        } elseif ($isProduction) {
            $checks[] = $this->warn('Cache opcode PHP', 'non attiva');
        } else {
            $checks[] = $this->ok('Cache opcode PHP', 'non attiva in ambiente non produttivo');
        }

        // post_max_size vs upload_max_filesize
        $postMax   = Bytes::parse((string) ini_get('post_max_size'));
        $uploadMax = Bytes::parse((string) ini_get('upload_max_filesize'));
        if ($postMax > 0 && $uploadMax > $postMax) {
            $checks[] = $this->warn(
                'Coerenza limiti upload',
                ini_get('post_max_size') . ' inferiore a upload_max_filesize (' . ini_get('upload_max_filesize') . ')'
            );
        } else {
            $checks[] = $this->ok('Coerenza limiti upload', 'configurazione coerente');
        }

        // Dimensione app.log
        $logFile = $base . '/storage/logs/app.log';
        if (file_exists($logFile)) {
            $size = (int) filesize($logFile);
            $mb   = round($size / 1048576, 1);
            $checks[] = $size < 50 * 1048576
                ? $this->ok('Dimensione log applicativo', $mb . ' MB')
                : $this->warn('Dimensione log applicativo', $mb . ' MB — consigliata rotazione');
        } else {
            $checks[] = $this->ok('Dimensione log applicativo', 'log non ancora creato');
        }

        // Dimensione error_log PHP
        $errorLog = ini_get('error_log');
        if ($errorLog && @file_exists($errorLog)) {
            $size = @filesize($errorLog);
            if ($size !== false) {
                $mb = round($size / 1048576, 1);
                $checks[] = $size < 100 * 1048576
                    ? $this->ok('Dimensione error log PHP', $mb . ' MB')
                    : $this->warn('Dimensione error log PHP', $mb . ' MB — consigliata rotazione');
            }
        }

        // Numero file sessioni
        $sessDir = $base . '/storage/sessions/';
        if (is_dir($sessDir)) {
            $count = count(glob($sessDir . '*') ?: []);
            $checks[] = $count < 1000
                ? $this->ok('Accumulo file sessione', $count . ' file')
                : $this->warn('Accumulo file sessione', $count . ' file — utile una pulizia');
        }

        return $checks;
    }
}
