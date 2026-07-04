<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * Scrittura directory operative, spazio disco e presenza file sensibili.
 *
 * La verifica di esposizione web del file .env (che richiede una richiesta HTTP
 * remota) è separata nel check "deep" EnvExposureCheck.
 */
class FilesystemCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'filesystem';
    }

    public function label(): string
    {
        return 'Filesystem';
    }

    public function description(): string
    {
        return 'Scrittura directory operative, spazio disco e presenza file sensibili.';
    }

    protected function checks(): array
    {
        $checks = [];
        $base   = BASE_PATH;

        $writableDirs = [
            'Upload pubblici'    => 'public/uploads/',
            'Storage principale' => 'storage/',
            'Log applicativi'    => 'storage/logs/',
            'Sessioni'           => 'storage/sessions/',
            'Temporanei'         => 'storage/tmp/',
            'Backup'             => 'storage/backups/',
            'Report'             => 'storage/reports/',
            'Export moduli'      => 'storage/module_exports/',
        ];

        $missingDirs     = [];
        $notWritableDirs = [];
        foreach ($writableDirs as $label => $relativePath) {
            $path = $base . '/' . $relativePath;
            if (!is_dir($path)) {
                $missingDirs[] = $label;
                continue;
            }
            if (!is_writable($path)) {
                $notWritableDirs[] = $label;
            }
        }

        $checks[] = empty($missingDirs)
            ? $this->ok('Directory operative', count($writableDirs) . ' directory presenti')
            : $this->fail('Directory operative', 'Mancano: ' . implode(', ', $missingDirs));

        $checks[] = empty($notWritableDirs)
            ? $this->ok('Permessi scrittura storage', 'tutte le directory richieste sono scrivibili')
            : $this->fail('Permessi scrittura storage', 'Non scrivibili: ' . implode(', ', $notWritableDirs));

        // Spazio disco disponibile
        $freeBytes = @disk_free_space($base);
        if ($freeBytes !== false) {
            $freeGb = round($freeBytes / (1024 * 1024 * 1024), 1);
            if ($freeBytes < 100 * 1024 * 1024) {
                $checks[] = $this->fail('Spazio disco disponibile', "{$freeGb} GB — soglia critica");
            } elseif ($freeBytes < 1024 * 1024 * 1024) {
                $checks[] = $this->warn('Spazio disco disponibile', "{$freeGb} GB — sotto 1 GB");
            } else {
                $checks[] = $this->ok('Spazio disco disponibile', "{$freeGb} GB disponibili");
            }
        }

        // .env presente
        $checks[] = file_exists($base . '/.env')
            ? $this->ok('Configurazione ambiente', 'file .env presente')
            : $this->fail('Configurazione ambiente', 'file .env mancante');

        return $checks;
    }
}
