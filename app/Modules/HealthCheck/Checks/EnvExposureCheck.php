<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * Verifica che il file .env non sia raggiungibile via web.
 *
 * Check "deep": esegue una richiesta HTTP remota verso APP_URL/.env, quindi non
 * va eseguito ad ogni refresh del dashboard.
 */
class EnvExposureCheck extends AbstractHealthCheck
{
    protected string $depth = self::DEPTH_DEEP;

    public function key(): string
    {
        return 'env_exposure';
    }

    public function label(): string
    {
        return 'Esposizione .env';
    }

    public function description(): string
    {
        return 'Verifica che il file di configurazione .env non sia accessibile dal web.';
    }

    protected function checks(): array
    {
        $appUrl = env('APP_URL', '');
        if (!$appUrl) {
            return [$this->warn('Protezione file .env', 'verifica remota saltata: APP_URL non configurato')];
        }

        try {
            $envUrl = rtrim($appUrl, '/') . '/.env';
            $ctx    = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
            $resp   = @file_get_contents($envUrl, false, $ctx);
            $code   = isset($http_response_header[0]) ? (int) explode(' ', $http_response_header[0])[1] : 0;

            return [($resp === false || $code === 403 || $code === 404 || $code === 0)
                ? $this->ok('Protezione file .env', 'non accessibile dal web')
                : $this->warn('Protezione file .env', 'potenzialmente accessibile via web')];
        } catch (\Throwable) {
            return [$this->warn('Protezione file .env', 'verifica remota non conclusiva')];
        }
    }
}
