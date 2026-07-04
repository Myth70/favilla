<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpException;
use PDO;

/**
 * ISO 27001 A.13.1 — Per-endpoint rate limiting middleware.
 *
 * Uses a sliding-window counter stored in DB table `rate_limits`.
 * Configure via static factory: RateLimitMiddleware::perMinute(60)
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;

    private function __construct(int $maxRequests, int $windowSeconds)
    {
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Factory: N requests per minute.
     */
    public static function perMinute(int $max): self
    {
        return new self($max, 60);
    }

    /**
     * Factory: N requests per hour.
     */
    public static function perHour(int $max): self
    {
        return new self($max, 3600);
    }

    /**
     * Factory: N requests per custom window (seconds).
     */
    public static function make(int $max, int $windowSeconds): self
    {
        return new self($max, $windowSeconds);
    }

    public function handle(callable $next): void
    {
        $ip    = \App\Support\ClientIp::resolve();
        $user  = $_SESSION['user_id'] ?? null;
        $route = $_SERVER['REQUEST_URI'] ?? '/';

        // Key: per-user if authenticated, per-IP otherwise
        $key = $user ? "user:{$user}" : "ip:{$ip}";
        // Normalize route: strip query string
        $endpoint = strtok($route, '?');

        $pdo = app(PDO::class);

        // Cleanup expired entries (probabilistic — 2% chance)
        if (random_int(1, 50) === 1) {
            $this->cleanup($pdo);
        }

        // Count requests within window
        $cutoff = date('Y-m-d H:i:s', time() - $this->windowSeconds);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits
             WHERE rate_key = ? AND endpoint = ? AND created_at > ?'
        );
        $stmt->execute([$key, $endpoint, $cutoff]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $this->maxRequests) {
            $retryAfter = $this->windowSeconds;
            throw new HttpException(
                429,
                ['Retry-After' => (string) $retryAfter],
                json_encode([
                    'error'       => 'Troppe richieste. Riprova tra poco.',
                    'retry_after' => $retryAfter,
                ], JSON_UNESCAPED_UNICODE) ?: '',
                'application/json; charset=utf-8',
                'Rate limit exceeded'
            );
        }

        // Record this request
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (rate_key, endpoint, ip_address, user_id, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$key, $endpoint, $ip, $user]);

        // Set rate limit headers
        $remaining = max(0, $this->maxRequests - $count - 1);
        header("X-RateLimit-Limit: {$this->maxRequests}");
        header("X-RateLimit-Remaining: {$remaining}");
        header('X-RateLimit-Reset: ' . (time() + $this->windowSeconds));

        $next();
    }

    private function cleanup(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
        } catch (\Throwable) {
            // Non-blocking
        }
    }
}
