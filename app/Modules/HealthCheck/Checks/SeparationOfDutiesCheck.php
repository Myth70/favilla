<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Services\RoleConstraintService;

/**
 * ISO 27001 A.6.1.2 — Vincoli di incompatibilità tra ruoli.
 *
 * Usa il RoleConstraintService (proprietario di role_constraints) invece di
 * reimplementare i JOIN: la vecchia god class duplicava findViolations()/getStats().
 */
class SeparationOfDutiesCheck extends AbstractHealthCheck
{
    private ?RoleConstraintService $constraintService;

    public function __construct(?RoleConstraintService $constraintService = null)
    {
        $this->constraintService = $constraintService;
    }

    private function constraintService(): RoleConstraintService
    {
        return $this->constraintService ??= app(RoleConstraintService::class);
    }

    public function key(): string
    {
        return 'separation_of_duties';
    }

    public function label(): string
    {
        return 'Separazione dei Compiti';
    }

    public function description(): string
    {
        return 'Controllo A.6.1.2 — Vincoli di incompatibilità tra ruoli.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $stats = $this->constraintService()->getStats();

            $checks[] = $stats['active'] > 0
                ? $this->ok('Vincoli attivi', $stats['active'] . ' regole di incompatibilità configurate')
                : $this->warn('Vincoli attivi', 'Nessun vincolo configurato');

            if ($stats['violations'] === 0) {
                $checks[] = $this->ok('Violazioni SoD', 'Nessuna violazione rilevata');
            } else {
                $violations = $this->constraintService()->findViolations();
                $details = [];
                foreach (array_slice($violations, 0, 3) as $v) {
                    $details[] = ($v['user_name'] ?? '') . ': ' . ($v['role1_name'] ?? '') . ' + ' . ($v['role2_name'] ?? '');
                }
                $extra = count($violations) > 3 ? ' (+' . (count($violations) - 3) . ' altre)' : '';
                $checks[] = $this->fail('Violazioni SoD', count($violations) . ' violazioni: ' . implode('; ', $details) . $extra);
            }
        } catch (\Throwable) {
            $checks[] = $this->warn('Tabella vincoli ruoli', 'role_constraints non disponibile — SoD non configurato');
        }

        return $checks;
    }
}
