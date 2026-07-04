<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Manages session startup with configurable driver.
 *
 * Supports 'file' (PHP default) and 'database' drivers.
 * When driver is 'database', registers DatabaseSessionHandler
 * before calling session_start().
 */
class SessionManager
{
    public function __construct(
        private DatabaseSessionHandler $dbHandler,
        private string $driver = 'file'
    ) {
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function start(): void
    {
        if ($this->driver === 'database') {
            session_set_save_handler($this->dbHandler, true);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
