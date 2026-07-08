<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Services;

use App\Modules\HealthCheck\Checks\AbstractHealthCheck;
use App\Modules\HealthCheck\Checks\ComposerAuditCheck;
use App\Modules\HealthCheck\Checks\DatabaseCheck;
use App\Modules\HealthCheck\Checks\EmailSecurityCheck;
use App\Modules\HealthCheck\Checks\EnvExposureCheck;
use App\Modules\HealthCheck\Checks\FileIntegrityCheck;
use App\Modules\HealthCheck\Checks\FilesystemCheck;
use App\Modules\HealthCheck\Checks\HealthCheck;
use App\Modules\HealthCheck\Checks\KeyRotationCheck;
use App\Modules\HealthCheck\Checks\LogManagementCheck;
use App\Modules\HealthCheck\Checks\ModulesCheck;
use App\Modules\HealthCheck\Checks\NotificationQueueCheck;
use App\Modules\HealthCheck\Checks\PasswordPolicyCheck;
use App\Modules\HealthCheck\Checks\PerformanceCheck;
use App\Modules\HealthCheck\Checks\PhpCheck;
use App\Modules\HealthCheck\Checks\PhpHardeningCheck;
use App\Modules\HealthCheck\Checks\SchedulerCheck;
use App\Modules\HealthCheck\Checks\SecurityCheck;
use App\Modules\HealthCheck\Checks\SecurityIncidentsCheck;
use App\Modules\HealthCheck\Checks\SeparationOfDutiesCheck;
use App\Modules\HealthCheck\Repositories\HealthCheckRepository;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Orchestratore dei controlli di salute del sistema.
 *
 * Non contiene più la logica dei singoli check (spostata in App\Modules\HealthCheck\Checks):
 * tiene il registro dei check, li esegue (fast/all), aggrega i risultati, li
 * persiste nello storico e invia le notifiche.
 */
class HealthCheckService
{
    /** @var HealthCheck[] */
    private array $checks;

    /**
     * @param HealthCheck[]|null $checks  Iniettabile nei test; default = registro completo.
     */
    public function __construct(?array $checks = null)
    {
        $this->checks = $checks ?? self::defaultChecks();
    }

    /**
     * Registro ordinato dei check. I check "deep" (rete/shell) sono in coda.
     *
     * @return HealthCheck[]
     */
    public static function defaultChecks(): array
    {
        return [
            new PhpCheck(),
            new DatabaseCheck(),
            new FilesystemCheck(),
            new ModulesCheck(),
            new SecurityCheck(),
            new PhpHardeningCheck(),
            new PerformanceCheck(),
            new SchedulerCheck(),
            new NotificationQueueCheck(),
            new PasswordPolicyCheck(),
            new SecurityIncidentsCheck(),
            new LogManagementCheck(),
            new FileIntegrityCheck(),
            new KeyRotationCheck(),
            new SeparationOfDutiesCheck(),
            // Deep (eseguiti solo da CLI / scansione approfondita)
            new EnvExposureCheck(),
            new EmailSecurityCheck(),
            new ComposerAuditCheck(),
        ];
    }

    /**
     * Esegue solo i check rapidi (per il dashboard).
     *
     * @return array<string,array{label:string,description:string,checks:array}>
     */
    public function runFast(): array
    {
        return $this->run(AbstractHealthCheck::DEPTH_FAST);
    }

    /**
     * Esegue tutti i check, inclusi i deep (CLI / scansione approfondita / export).
     *
     * @return array<string,array{label:string,description:string,checks:array}>
     */
    public function runAll(): array
    {
        return $this->run('all');
    }

    /**
     * Esegue il solo controllo di hardening PHP (configurazione php.ini),
     * usato dalla dashboard Admin "Hardening PHP". È indipendente dal registro
     * iniettato perché la pagina riguarda specificamente l'hardening di PHP.
     *
     * @return array{label:string,description:string,checks:array<int,array{name:string,status:string,detail:string}>}
     */
    public function checkPhpHardening(): array
    {
        return (new PhpHardeningCheck())->run();
    }

    /**
     * @param string $depth 'fast' per i soli check rapidi, qualsiasi altro valore = tutti.
     * @return array<string,array{label:string,description:string,checks:array}>
     */
    private function run(string $depth): array
    {
        $results = [];
        foreach ($this->checks as $check) {
            if ($depth === AbstractHealthCheck::DEPTH_FAST && $check->depth() !== AbstractHealthCheck::DEPTH_FAST) {
                continue;
            }
            $results[$check->key()] = $check->run();
        }

        return $results;
    }

    /**
     * Storico esecuzioni health check paginato.
     */
    public function getHistory(int $perPage, int $page): array
    {
        return app(HealthCheckRepository::class)->getHistory($perPage, $page);
    }

    /**
     * Conta i check per status (ok/warn/fail).
     *
     * @return array{ok:int,warn:int,fail:int}
     */
    public function summary(array $results): array
    {
        $counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($results as $group) {
            foreach ($group['checks'] as $check) {
                $status = $check['status'];
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Ordina i gruppi per severità ed estrae i check azionabili (warn/fail).
     *
     * @return array<int,array<string,mixed>>
     */
    public function prioritizeResults(array $results): array
    {
        $groups = [];

        foreach ($results as $key => $group) {
            $statusCounts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
            $actionableChecks = [];
            foreach ($group['checks'] as $check) {
                $status = $check['status'];
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                if ($status !== 'ok') {
                    $actionableChecks[] = $check;
                }
            }

            $groups[] = [
                'key'         => $key,
                'label'       => $group['label'],
                'description' => $group['description'] ?? '',
                'status'      => $statusCounts['fail'] > 0 ? 'fail' : ($statusCounts['warn'] > 0 ? 'warn' : 'ok'),
                'counts'      => $statusCounts,
                'checks'      => $group['checks'],
                'highlights'  => array_slice($actionableChecks, 0, 3),
            ];
        }

        usort($groups, static function (array $a, array $b): int {
            $weight = ['fail' => 0, 'warn' => 1, 'ok' => 2];
            $statusCompare = ($weight[$a['status']] ?? 9) <=> ($weight[$b['status']] ?? 9);
            if ($statusCompare !== 0) {
                return $statusCompare;
            }

            return (($b['counts']['fail'] + $b['counts']['warn']) <=> ($a['counts']['fail'] + $a['counts']['warn']));
        });

        return $groups;
    }

    /**
     * Trasforma i risultati in righe flat per export CSV.
     *
     * @return array<int,array{Categoria:string,Check:string,Stato:string,Dettaglio:string}>
     */
    public function toExportRows(array $results): array
    {
        $rows = [];
        foreach ($results as $group) {
            foreach ($group['checks'] as $check) {
                $rows[] = [
                    'Categoria' => $group['label'],
                    'Check'     => $check['name'],
                    'Stato'     => strtoupper($check['status']),
                    'Dettaglio' => $check['detail'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Salva un run nello storico e ritorna l'ID.
     *
     * @param array{ok:int,warn:int,fail:int} $summary
     */
    public function saveRun(array $results, array $summary): int
    {
        return app(HealthCheckRepository::class)->create([
            'total_ok'   => $summary['ok'],
            'total_warn' => $summary['warn'],
            'total_fail' => $summary['fail'],
            'data'       => json_encode($results, JSON_UNESCAPED_UNICODE),
            'created_by' => auth()['id'] ?? null,
        ]);
    }

    /**
     * Invia notifica agli admin se ci sono check falliti.
     *
     * @param array{ok:int,warn:int,fail:int} $summary
     */
    public function notifyOnFailures(array $results, array $summary): void
    {
        if ($summary['fail'] <= 0) {
            return;
        }

        $failNames = [];
        foreach ($results as $group) {
            foreach ($group['checks'] as $check) {
                if ($check['status'] === 'fail') {
                    $failNames[] = $check['name'];
                }
            }
        }

        $list = implode(', ', array_slice($failNames, 0, 5));
        if (count($failNames) > 5) {
            $list .= '...';
        }

        NotificationService::dispatchEventToRole(
            'health_check.failures_detected',
            'HealthCheck',
            'admin',
            [
                'failure_count'      => $summary['fail'],
                'failed_checks'      => $failNames,
                'failed_checks_text' => $list,
            ],
            route('healthcheck.index'),
            auth()['id'] ?? null
        );
    }
}
