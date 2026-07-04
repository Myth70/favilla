<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpException;
use App\Security\CsrfToken;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Verify CSRF token on POST/PUT/DELETE requests.
     * Accepts token from $_POST['_token'] or X-CSRF-Token header.
     */
    public function handle(callable $next): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Only verify on state-changing methods
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            $token = $this->getSubmittedToken();

            if (!CsrfToken::verify($token)) {
                throw new HttpException(403, [], null, null, 'CSRF token mismatch');
            }
        }

        $next();
    }

    /**
     * Get the token from POST body or X-CSRF-Token header.
     */
    private function getSubmittedToken(): ?string
    {
        // 1. POST body field
        if (!empty($_POST['_token'])) {
            return $_POST['_token'];
        }

        // 2. X-CSRF-Token header (for HTMX and AJAX requests)
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        return null;
    }
}
