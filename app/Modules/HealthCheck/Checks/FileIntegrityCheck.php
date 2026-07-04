<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\Files\Services\FilesService;
use App\Services\SecurityIncidentService;

/**
 * ISO 27001 A.12.2 — Verifica checksum e protezione da manomissione.
 *
 * Niente SQL cross-modulo: la copertura checksum passa dal FilesService
 * (proprietario della tabella files) e le violazioni recenti dal
 * SecurityIncidentService (proprietario di security_incidents).
 */
class FileIntegrityCheck extends AbstractHealthCheck
{
    private ?FilesService $filesService;
    private ?SecurityIncidentService $incidentService;

    public function __construct(
        ?FilesService $filesService = null,
        ?SecurityIncidentService $incidentService = null
    ) {
        $this->filesService    = $filesService;
        $this->incidentService = $incidentService;
    }

    private function filesService(): FilesService
    {
        return $this->filesService ??= app(FilesService::class);
    }

    private function incidentService(): SecurityIncidentService
    {
        return $this->incidentService ??= app(SecurityIncidentService::class);
    }

    public function key(): string
    {
        return 'file_integrity';
    }

    public function label(): string
    {
        return 'Integrità File ISO 27001';
    }

    public function description(): string
    {
        return 'Controllo A.12.2 — Verifica checksum e protezione da manomissione.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $coverage     = $this->filesService()->checksumCoverage();
            $totalFiles   = (int) $coverage['total'];
            $checkedFiles = (int) $coverage['checked'];

            if ($totalFiles === 0) {
                $checks[] = $this->ok('Checksum file', 'Nessun file presente');
            } else {
                $percentage = round(($checkedFiles / $totalFiles) * 100, 1);
                $checks[] = $percentage >= 90
                    ? $this->ok('Copertura checksum', "{$checkedFiles}/{$totalFiles} file ({$percentage}%)")
                    : $this->warn('Copertura checksum', "{$checkedFiles}/{$totalFiles} file ({$percentage}%) — file caricati prima della migrazione non hanno checksum");
            }

            // Violazioni di integrità negli ultimi 30 giorni (via servizio proprietario)
            $integrityFailures = $this->countRecentIntegrityFailures();
            $checks[] = $integrityFailures === 0
                ? $this->ok('Integrità file recente', 'Nessuna violazione negli ultimi 30 giorni')
                : $this->fail('Integrità file recente', $integrityFailures . ' violazioni rilevate negli ultimi 30 giorni');
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Integrità file', 'Controllo non riuscito: ' . $e->getMessage());
        }

        return $checks;
    }

    private function countRecentIntegrityFailures(): int
    {
        $summary = $this->incidentService()->getSummary();
        $count = 0;
        foreach ($summary['30d'] ?? [] as $row) {
            if (($row['type'] ?? '') === 'file_integrity_failure') {
                $count += (int) ($row['cnt'] ?? 0);
            }
        }

        return $count;
    }
}
