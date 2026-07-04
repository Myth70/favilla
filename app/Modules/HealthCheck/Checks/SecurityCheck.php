<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\HealthCheck\Repositories\SystemDiagnosticsRepository;

/**
 * Variabili ambiente e impostazioni che impattano direttamente la sicurezza.
 */
class SecurityCheck extends AbstractHealthCheck
{
    private ?SystemDiagnosticsRepository $repo;

    public function __construct(?SystemDiagnosticsRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): SystemDiagnosticsRepository
    {
        return $this->repo ??= app(SystemDiagnosticsRepository::class);
    }

    public function key(): string
    {
        return 'security';
    }

    public function label(): string
    {
        return 'Sicurezza';
    }

    public function description(): string
    {
        return 'Variabili ambiente e impostazioni che impattano direttamente la sicurezza.';
    }

    protected function checks(): array
    {
        $checks = [];

        $env   = env('APP_ENV', 'development');
        $debug = env('APP_DEBUG', 'true');
        $key   = env('APP_KEY', '');

        // APP_ENV
        $checks[] = $env === 'production'
            ? $this->ok('Ambiente applicativo', 'production')
            : $this->ok('Ambiente applicativo', "{$env} (non produttivo)");

        // APP_DEBUG
        $isDebug = $debug === 'true' || $debug === '1';
        if (!$isDebug) {
            $checks[] = $this->ok('Modalita debug', 'disattivata');
        } elseif ($env === 'production') {
            $checks[] = $this->warn('Modalita debug', 'attiva — da disattivare in produzione');
        } else {
            $checks[] = $this->ok('Modalita debug', 'attiva in ambiente non produttivo');
        }

        // APP_KEY
        $checks[] = strlen((string) $key) >= 32
            ? $this->ok('Chiave applicativa', strlen((string) $key) . ' caratteri')
            : $this->fail('Chiave applicativa', 'assente o troppo corta');

        // HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || str_starts_with(env('APP_URL', ''), 'https');
        if ($env === 'production') {
            $checks[] = $isHttps
                ? $this->ok('HTTPS', 'attivo')
                : $this->warn('HTTPS', 'non attivo — consigliato in produzione');
        } else {
            $checks[] = $this->ok('HTTPS', $isHttps ? 'attivo' : 'non attivo in ambiente non produttivo');
        }

        // Session httponly
        // '0' è falsy: il solo controllo di verità copre disattivato/'0'/''.
        $httponly = ini_get('session.cookie_httponly');
        $checks[] = $httponly
            ? $this->ok('Cookie sessione HttpOnly', 'attivo')
            : $this->warn('Cookie sessione HttpOnly', 'disattivato');

        // Session cookie secure
        $secure = ini_get('session.cookie_secure');
        if ($env === 'production') {
            $checks[] = $secure
                ? $this->ok('Cookie sessione Secure', 'attivo')
                : $this->warn('Cookie sessione Secure', 'disattivato con ambiente production');
        }

        // Password admin non banale
        try {
            $hashes = $this->repo()->adminPasswordHashes(5);

            $banaleList = ['password', 'admin', '123456', 'admin123', 'qwerty', '12345678', 'letmein', 'welcome', 'favilla'];
            $foundBanale = false;
            foreach ($hashes as $hash) {
                foreach ($banaleList as $pw) {
                    if (password_verify($pw, $hash)) {
                        $foundBanale = true;
                        break 2;
                    }
                }
            }
            if (!empty($hashes)) {
                if ($foundBanale && $env === 'production') {
                    $checks[] = $this->fail('Credenziali amministratore', 'rilevata password debole');
                } elseif ($foundBanale) {
                    $checks[] = $this->warn('Credenziali amministratore', 'rilevata password debole (ammessa solo in sviluppo)');
                } else {
                    $checks[] = $this->ok('Credenziali amministratore', 'nessuna password banale rilevata');
                }
            }
        } catch (\Throwable) {
            // Silenzioso se la query fallisce
        }

        return $checks;
    }
}
