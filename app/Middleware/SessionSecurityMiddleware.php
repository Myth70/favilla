<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpException;
use App\Services\SecurityIncidentService;
use App\Support\ClientIp;

/**
 * ISO 27001 A.9.4.3 — Session IP consistency check.
 * ISO 27001 A.12.4.1 — Failed authorization audit logging.
 *
 * Detects IP address changes during an active session and records them as
 * security incidents. Also audits 403 authorization denials: RoleMiddleware
 * (inner) denies by throwing HttpException(403), so we catch it synchronously
 * as it propagates up, log the incident, and re-throw for the central handler.
 *
 * Must be placed AFTER AuthMiddleware in the middleware chain.
 */
class SessionSecurityMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        // Only run for authenticated sessions
        if (empty($_SESSION['user_id'])) {
            $next();
            return;
        }

        $currentIp = ClientIp::resolve();

        // Store login IP on first encounter; detect changes afterwards.
        if (!isset($_SESSION['_login_ip'])) {
            $_SESSION['_login_ip'] = $currentIp;
        } elseif ($currentIp !== $_SESSION['_login_ip']) {
            $this->maybeLogIpChange($currentIp, (string) $_SESSION['_login_ip']);
        }

        try {
            $next();
        } catch (HttpException $e) {
            // ISO 27001 A.12.4.1 — a 403 from an inner middleware (RoleMiddleware)
            // is an authorization denial worth auditing.
            if ($e->getStatusCode() === 403) {
                $this->logAccessDenied();
            }
            throw $e;
        }
    }

    /**
     * Record an IP-change incident, throttled to once per 60 seconds.
     */
    private function maybeLogIpChange(string $currentIp, string $loginIp): void
    {
        $lastIpCheck = $_SESSION['_ip_change_logged_at'] ?? 0;
        if ((time() - $lastIpCheck) <= 60) {
            return;
        }
        $_SESSION['_ip_change_logged_at'] = time();

        try {
            app(SecurityIncidentService::class)->recordIncident('ip_change', 'medium', json_encode([
                'user_id'    => $_SESSION['user_id'],
                'login_ip'   => $loginIp,
                'current_ip' => $currentIp,
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            ], JSON_THROW_ON_ERROR), $currentIp);
        } catch (\Throwable) {
            // Never break the request for a logging failure
        }
    }

    /**
     * Record a failed-authorization (403) incident for the current session.
     */
    private function logAccessDenied(): void
    {
        try {
            app(SecurityIncidentService::class)->recordIncident('access_denied', 'medium', json_encode([
                'user_id'    => $_SESSION['user_id'] ?? null,
                'user_name'  => $_SESSION['user_name'] ?? '',
                'method'     => $_SERVER['REQUEST_METHOD'] ?? '',
                'uri'        => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            ], JSON_THROW_ON_ERROR), ClientIp::resolve());
        } catch (\Throwable) {
            // Never break the response for a logging failure
        }
    }
}
