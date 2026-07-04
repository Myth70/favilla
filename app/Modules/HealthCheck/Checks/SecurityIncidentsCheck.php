<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Services\SecurityIncidentService;

/**
 * ISO 27001 A.16 — Riepilogo incidenti di sicurezza nelle ultime 24h.
 *
 * Passa dal servizio proprietario (SecurityIncidentService) invece di
 * interrogare direttamente la tabella security_incidents.
 */
class SecurityIncidentsCheck extends AbstractHealthCheck
{
    private ?SecurityIncidentService $incidentService;

    public function __construct(?SecurityIncidentService $incidentService = null)
    {
        $this->incidentService = $incidentService;
    }

    private function incidentService(): SecurityIncidentService
    {
        return $this->incidentService ??= app(SecurityIncidentService::class);
    }

    public function key(): string
    {
        return 'security_incidents';
    }

    public function label(): string
    {
        return 'Incidenti di sicurezza';
    }

    public function description(): string
    {
        return 'Riepilogo incidenti ISO 27001 A.16.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $summary = $this->incidentService()->getSummary();

            // Aggrega per tipo le righe (type, severity, cnt) delle ultime 24h.
            $byType = [];
            foreach ($summary['24h'] ?? [] as $row) {
                $type = (string) ($row['type'] ?? '');
                $byType[$type] = ($byType[$type] ?? 0) + (int) ($row['cnt'] ?? 0);
            }

            if (empty($byType)) {
                $checks[] = $this->ok('Incidenti ultime 24h', 'Nessun incidente rilevato');
            } else {
                $total  = array_sum($byType);
                $detail = [];
                foreach ($byType as $type => $count) {
                    $detail[] = "{$type}: {$count}";
                }

                $method = $total > 20 ? 'fail' : ($total > 5 ? 'warn' : 'ok');
                $checks[] = $this->{$method}('Incidenti ultime 24h', "{$total} — " . implode(', ', $detail));
            }
        } catch (\Throwable) {
            $checks[] = $this->warn('Incidenti sicurezza', 'Tabella security_incidents non disponibile');
        }

        return $checks;
    }
}
