<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Factory for independent module database connections.
 *
 * Modules with "database": "independent" in module.json use this
 * to obtain a PDO instance connected to their own database.
 *
 * Environment variables: {PREFIX}_DB_HOST, {PREFIX}_DB_PORT,
 * {PREFIX}_DB_NAME, {PREFIX}_DB_USER, {PREFIX}_DB_PASS.
 */
class ModulePdoFactory
{
    /** @var array<string, PDO> Cached connections keyed by env prefix */
    private static array $connections = [];

    /**
     * Get or create a PDO connection for the given environment prefix.
     *
     * @param string $envPrefix e.g. 'CRM' reads CRM_DB_HOST, CRM_DB_NAME, etc.
     * @throws \RuntimeException if required env vars are missing
     */
    public static function get(string $envPrefix): PDO
    {
        if (isset(self::$connections[$envPrefix])) {
            return self::$connections[$envPrefix];
        }

        $host = env("{$envPrefix}_DB_HOST", 'localhost');
        $port = env("{$envPrefix}_DB_PORT", '3306');
        $name = env("{$envPrefix}_DB_NAME");
        $user = env("{$envPrefix}_DB_USER", 'root');
        $pass = env("{$envPrefix}_DB_PASS", '');

        if (!$name) {
            throw new \RuntimeException(
                "Variabile {$envPrefix}_DB_NAME non configurata in .env. "
                . 'Questo modulo richiede un database indipendente.'
            );
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        self::$connections[$envPrefix] = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$connections[$envPrefix];
    }

    /**
     * Check whether environment variables are configured for this prefix.
     */
    public static function hasConfig(string $envPrefix): bool
    {
        return (bool) env("{$envPrefix}_DB_NAME");
    }

    /**
     * Close and remove a cached connection (useful for testing/cleanup).
     */
    public static function close(string $envPrefix): void
    {
        unset(self::$connections[$envPrefix]);
    }

    /**
     * Close all cached connections.
     */
    public static function closeAll(): void
    {
        self::$connections = [];
    }
}
