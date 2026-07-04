<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Services\KeyRotationService;

/**
 * ISO 27001 A.10.1.2 — Rotazione periodica delle chiavi crittografiche.
 */
class KeyRotationCheck extends AbstractHealthCheck
{
    private ?KeyRotationService $keyService;

    public function __construct(?KeyRotationService $keyService = null)
    {
        $this->keyService = $keyService;
    }

    private function keyService(): KeyRotationService
    {
        return $this->keyService ??= app(KeyRotationService::class);
    }

    public function key(): string
    {
        return 'key_rotation';
    }

    public function label(): string
    {
        return 'Rotazione Chiavi ISO 27001';
    }

    public function description(): string
    {
        return 'Controllo A.10.1.2 — Rotazione periodica delle chiavi crittografiche.';
    }

    protected function checks(): array
    {
        $checks = [];
        $isProduction = $this->isProduction();

        try {
            $keys = $this->keyService()->getStatus();

            foreach ($keys as $k) {
                if (!$k['present']) {
                    $checks[] = $this->warn("Chiave {$k['key']}", 'Non configurata nel file .env');
                    continue;
                }

                if ($k['last_rotated'] === null) {
                    $checks[] = $this->warn(
                        "Chiave {$k['key']}",
                        'Rotazione mai registrata — registrare dopo il primo deploy'
                    );
                } elseif ($k['overdue']) {
                    $checks[] = $isProduction
                        ? $this->fail(
                            "Chiave {$k['key']}",
                            "Ultima rotazione: {$k['age_days']} giorni fa (limite: {$k['max_age_days']})"
                        )
                        : $this->warn(
                            "Chiave {$k['key']}",
                            "Rotazione oltre soglia in ambiente non produttivo ({$k['age_days']} giorni)"
                        );
                } else {
                    $checks[] = $this->ok(
                        "Chiave {$k['key']}",
                        "Ruotata {$k['age_days']} giorni fa (limite: {$k['max_age_days']})"
                    );
                }
            }
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Rotazione chiavi', 'Controllo non riuscito: ' . $e->getMessage());
        }

        return $checks;
    }
}
