<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\HealthCheck\Support\Bytes;

/**
 * Runtime e configurazione PHP necessaria al funzionamento.
 */
class PhpCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'php';
    }

    public function label(): string
    {
        return 'PHP';
    }

    public function description(): string
    {
        return 'Runtime e configurazione PHP necessaria al funzionamento.';
    }

    protected function checks(): array
    {
        $checks = [];
        $isProduction = $this->isProduction();

        // Versione PHP
        $ver = PHP_VERSION;
        $checks[] = version_compare($ver, '8.2.0', '>=')
            ? $this->ok('Versione runtime PHP', $ver)
            : $this->fail('Versione runtime PHP', $ver . ' — richiesta almeno 8.2');

        // Estensioni obbligatorie
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo'] as $ext) {
            $checks[] = extension_loaded($ext)
                ? $this->ok("Estensione obbligatoria: {$ext}", 'disponibile')
                : $this->fail("Estensione obbligatoria: {$ext}", 'non disponibile');
        }

        // Estensioni consigliate
        foreach (['intl', 'gd', 'zip'] as $ext) {
            $checks[] = extension_loaded($ext)
                ? $this->ok("Estensione consigliata: {$ext}", 'disponibile')
                : $this->warn("Estensione consigliata: {$ext}", 'non disponibile');
        }

        // memory_limit
        $mem = Bytes::parse((string) ini_get('memory_limit'));
        $checks[] = $mem >= 128 * 1024 * 1024
            ? $this->ok('Limite memoria PHP', (string) ini_get('memory_limit'))
            : $this->warn('Limite memoria PHP', ini_get('memory_limit') . ' — consigliato almeno 128M');

        // upload_max_filesize
        $upload = Bytes::parse((string) ini_get('upload_max_filesize'));
        $checks[] = $upload >= 8 * 1024 * 1024
            ? $this->ok('Dimensione upload massima', (string) ini_get('upload_max_filesize'))
            : $this->warn('Dimensione upload massima', ini_get('upload_max_filesize') . ' — consigliato almeno 8M');

        // display_errors
        // Nota: la stringa '0' è falsy in PHP, quindi !$de copre già '0'/''/false.
        $de = ini_get('display_errors');
        if (!$de || $de === 'Off') {
            $checks[] = $this->ok('Visibilita errori a schermo', 'disattivata');
        } elseif ($isProduction) {
            $checks[] = $this->warn('Visibilita errori a schermo', 'attiva — da disattivare in produzione');
        } else {
            $checks[] = $this->ok('Visibilita errori a schermo', 'attiva in ambiente non produttivo');
        }

        return $checks;
    }
}
