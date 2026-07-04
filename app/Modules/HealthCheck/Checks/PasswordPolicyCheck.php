<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * ISO 27001 A.9.4.3 — Verifica configurazione policy password.
 */
class PasswordPolicyCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'password_policy';
    }

    public function label(): string
    {
        return 'Policy password ISO 27001';
    }

    public function description(): string
    {
        return 'Verifica configurazione A.9.4.3.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $enabled = (bool) setting('password_policy_enabled', true);
            $checks[] = $enabled
                ? $this->ok('Policy password', 'Abilitata')
                : $this->warn('Policy password', 'Disabilitata — non conforme ISO 27001');

            if ($enabled) {
                $minLen = (int) setting('password_min_length', 12);
                $checks[] = $minLen >= 12
                    ? $this->ok('Lunghezza minima', "{$minLen} caratteri")
                    : $this->warn('Lunghezza minima', "{$minLen} — raccomandati almeno 12 per ISO 27001");

                $maxAge = (int) setting('password_max_age_days', 90);
                $checks[] = $maxAge > 0 && $maxAge <= 90
                    ? $this->ok('Scadenza password', "{$maxAge} giorni")
                    : $this->warn('Scadenza password', $maxAge <= 0 ? 'Disabilitata' : "{$maxAge} giorni — raccomandati max 90");

                $history = (int) setting('password_history_count', 5);
                $checks[] = $history >= 5
                    ? $this->ok('Storico password', "Ultime {$history} non riutilizzabili")
                    : $this->warn('Storico password', "Ultime {$history} — raccomandato almeno 5");
            }
        } catch (\Throwable) {
            $checks[] = $this->warn('Policy password', 'Impostazioni non disponibili');
        }

        return $checks;
    }
}
