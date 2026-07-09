<?php

declare(strict_types=1);

namespace App\Modules\Api\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpException;
use App\Modules\Api\Support\ApiRequestContext;
use App\Support\ClientIp;
use PDO;

/**
 * Rate limiting per-token dell'API v1. Sliding window sulla tabella condivisa
 * rate_limits, con chiave api-token:<id> (fallback IP se il contesto non è
 * autenticato). Il limite si legge da app_settings (api_rate_limit_per_minute).
 * Deve girare DOPO ApiTokenMiddleware, che popola ApiRequestContext.
 */
class ApiRateLimitMiddleware implements MiddlewareInterface
{
    private const WINDOW_SECONDS = 60;

    public function handle(callable $next): void
    {
        $context = app(ApiRequestContext::class);
        $tokenId = $context->tokenId();
        $key = $tokenId !== null ? "api-token:{$tokenId}" : 'api-ip:' . ClientIp::resolve();

        $max = max(1, (int) setting('api_rate_limit_per_minute', 120));
        $endpoint = 'api/v1';

        $pdo = app(PDO::class);

        if (random_int(1, 50) === 1) {
            $this->cleanup($pdo);
        }

        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND endpoint = ? AND created_at > ?'
        );
        $stmt->execute([$key, $endpoint, $cutoff]);
        $count = (int) $stmt->fetchColumn();

        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $max);
            header('X-RateLimit-Remaining: ' . max(0, $max - $count - 1));
            header('X-RateLimit-Reset: ' . (time() + self::WINDOW_SECONDS));
        }

        if ($count >= $max) {
            $body = json_encode([
                'error' => [
                    'code'        => 'rate_limited',
                    'message'     => 'Troppe richieste. Riprova tra poco.',
                    'retry_after' => self::WINDOW_SECONDS,
                ],
            ], JSON_UNESCAPED_UNICODE) ?: '{"error":{"code":"rate_limited"}}';

            throw new HttpException(
                429,
                ['Retry-After' => (string) self::WINDOW_SECONDS],
                $body,
                'application/json; charset=utf-8',
                'API rate limit exceeded'
            );
        }

        $ip = ClientIp::resolve();
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (rate_key, endpoint, ip_address, user_id, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$key, $endpoint, $ip, $context->isAuthenticated() ? $context->userId() : null]);

        $next();
    }

    private function cleanup(PDO $pdo): void
    {
        try {
            $pdo->exec('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        } catch (\Throwable) {
            // Non-blocking
        }
    }
}
