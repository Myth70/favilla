<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * Execute a callback inside a database transaction.
     * Rolls back on any exception and re-throws.
     */
    protected function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get the PDO instance.
     */
    protected function getPdo(): PDO
    {
        return $this->pdo;
    }
}
