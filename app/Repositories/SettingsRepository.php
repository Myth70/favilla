<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    public function get(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `value`, `type` FROM `app_settings` WHERE `key` = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `app_settings` SET `value` = ? WHERE `key` = ?'
        );
        $stmt->execute([$value, $key]);
    }

    public function getByGroup(string $group): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `app_settings` WHERE `group` = ? ORDER BY `key`'
        );
        $stmt->execute([$group]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM `app_settings` ORDER BY `group`, `key`');
        return $stmt->fetchAll();
    }

    public function bulkUpdate(array $settings): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE `app_settings` SET `value` = ? WHERE `key` = ?'
            );
            foreach ($settings as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
