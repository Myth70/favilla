<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Services\LogRotationService;

/**
 * ISO 27001 A.12.4 — Rotazione, retention e integrità dei log.
 */
class LogManagementCheck extends AbstractHealthCheck
{
    private ?LogRotationService $logService;

    public function __construct(?LogRotationService $logService = null)
    {
        $this->logService = $logService;
    }

    private function logService(): LogRotationService
    {
        return $this->logService ??= app(LogRotationService::class);
    }

    public function key(): string
    {
        return 'log_management';
    }

    public function label(): string
    {
        return 'Gestione Log ISO 27001';
    }

    public function description(): string
    {
        return 'Controllo A.12.4 — Rotazione, retention e integrità dei log.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            // getStatus() restituisce anche 'retention_days' a runtime, ma la sua
            // annotazione @return (in app/Services, superficie non modificabile) lo omette.
            /** @var array{active_size:int,rotated_count:int,retention_days:int} $status */
            $status = $this->logService()->getStatus();

            // Active log file size
            $activeMb = round($status['active_size'] / 1048576, 1);
            $checks[] = $status['active_size'] < 100 * 1048576
                ? $this->ok('Dimensione log attivo', $activeMb . ' MB')
                : $this->warn('Dimensione log attivo', $activeMb . ' MB — rotazione consigliata');

            // Rotated files count
            $checks[] = $status['rotated_count'] > 0
                ? $this->ok('File di log ruotati', $status['rotated_count'] . ' file archiviati')
                : $this->warn('File di log ruotati', 'Nessuno — configurare rotazione automatica');

            // Retention policy
            $checks[] = $status['retention_days'] >= 365
                ? $this->ok('Retention log', $status['retention_days'] . ' giorni')
                : $this->warn('Retention log', $status['retention_days'] . ' giorni — raccomandato almeno 365 per ISO 27001');

            // Integrity verification
            $verification = $this->logService()->verifyAll();
            if ($verification['total'] > 0) {
                $checks[] = $verification['invalid'] === 0
                    ? $this->ok('Integrità log ruotati', $verification['valid'] . '/' . $verification['total'] . ' file verificati')
                    : $this->fail('Integrità log ruotati', $verification['invalid'] . ' file con integrità compromessa!');
            }
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Gestione log', 'Servizio non disponibile: ' . $e->getMessage());
        }

        return $checks;
    }
}
