<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpException;

class RoleMiddleware implements MiddlewareInterface
{
    private string $permission;

    public function __construct(string $permission = '')
    {
        $this->permission = $permission;
    }

    /**
     * Verify that the authenticated user has the required permission.
     */
    public function handle(callable $next): void
    {
        if ($this->permission === '') {
            $next();
            return;
        }

        // Admin role bypasses all permission checks (super-admin)
        $roles = $_SESSION['user_roles'] ?? [];
        if (in_array('admin', $roles, true)) {
            $next();
            return;
        }

        $permissions = $_SESSION['user_permissions'] ?? [];

        if (!in_array($this->permission, $permissions, true)) {
            throw new HttpException(403, [], null, null, 'Missing permission: ' . $this->permission);
        }

        $next();
    }

    /**
     * Create a new instance with a specific permission requirement.
     * Used by the middleware resolver to pass parameters.
     */
    public static function withPermission(string $permission): self
    {
        return new self($permission);
    }
}
