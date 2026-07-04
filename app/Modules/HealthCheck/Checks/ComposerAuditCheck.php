<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * ISO 27001 A.8.1 — Vulnerabilità note (CVE) nelle dipendenze Composer.
 *
 * Check "deep": esegue `composer audit` via shell. In passato il metodo esisteva
 * ma non era mai richiamato (codice morto): ora entra nel flusso deep (CLI +
 * scansione approfondita).
 */
class ComposerAuditCheck extends AbstractHealthCheck
{
    protected string $depth = self::DEPTH_DEEP;

    public function key(): string
    {
        return 'composer_audit';
    }

    public function label(): string
    {
        return 'Vulnerabilità dipendenze';
    }

    public function description(): string
    {
        return 'Controllo CVE sulle librerie PHP (composer audit).';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $composerLock = realpath(BASE_PATH . '/composer.lock');
            if (!$composerLock || !file_exists($composerLock)) {
                $checks[] = $this->warn('Composer audit', 'composer.lock non trovato');
                return $checks;
            }

            $phpBin      = PHP_BINARY;
            $projectRoot = dirname($composerLock);

            if (file_exists($projectRoot . '/vendor/bin/composer')) {
                $cmd = escapeshellarg($phpBin) . ' '
                     . escapeshellarg($projectRoot . '/vendor/bin/composer')
                     . ' audit --format=json --no-interaction 2>&1';
            } else {
                $cmd = 'composer audit --format=json --working-dir=' . escapeshellarg($projectRoot) . ' --no-interaction 2>&1';
            }

            $output = shell_exec($cmd);
            if ($output === null) {
                $checks[] = $this->warn('Composer audit', 'Impossibile eseguire composer audit');
                return $checks;
            }

            $result     = json_decode($output, true);
            $advisories = is_array($result) ? ($result['advisories'] ?? []) : [];
            $total      = 0;
            foreach ($advisories as $pkgAdvisories) {
                $total += is_countable($pkgAdvisories) ? count($pkgAdvisories) : 0;
            }

            $checks[] = $total === 0
                ? $this->ok('Composer audit', 'Nessuna vulnerabilità nota rilevata')
                : $this->fail('Composer audit', "{$total} vulnerabilità nota/e rilevata/e — eseguire 'composer update'");
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Composer audit', 'Errore: ' . $e->getMessage());
        }

        return $checks;
    }
}
