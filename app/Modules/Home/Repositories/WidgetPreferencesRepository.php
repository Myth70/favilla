<?php

declare(strict_types=1);

namespace App\Modules\Home\Repositories;

use App\Repositories\BaseRepository;

class WidgetPreferencesRepository extends BaseRepository
{
    protected string $table = 'user_widget_preferences';

    /**
     * Get all widget preferences for a user, ordered by sort_order.
     *
     * @return array<int, array{widget_id: string, sort_order: int, visible: int}>
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT widget_id, sort_order, visible
             FROM {$this->table}
             WHERE user_id = ?
             ORDER BY sort_order ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Bulk upsert widget preferences for a user.
     *
     * @param array<int, array{widget_id: string, sort_order: int, visible: int}> $items
     */
    public function upsertBatch(int $userId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $sql = "INSERT INTO {$this->table} (user_id, widget_id, sort_order, visible)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), visible = VALUES(visible)";

        $stmt = $this->pdo->prepare($sql);

        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $stmt->execute([
                    $userId,
                    $item['widget_id'],
                    $item['sort_order'],
                    $item['visible'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Delete all widget preferences for a user (reset to defaults).
     */
    public function deleteByUserId(int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    /**
     * Atomically replace all widget preferences for a user:
     * deletes existing rows, then inserts the new set in one transaction.
     * Ensures no orphan entries survive after a layout save.
     *
     * @param array<int, array{widget_id: string, sort_order: int, visible: int}> $items
     */
    public function replaceAll(int $userId, array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
            $stmt->execute([$userId]);

            if (!empty($items)) {
                $sql = "INSERT INTO {$this->table} (user_id, widget_id, sort_order, visible) VALUES (?, ?, ?, ?)";
                $insert = $this->pdo->prepare($sql);
                foreach ($items as $item) {
                    $insert->execute([
                        $userId,
                        $item['widget_id'],
                        $item['sort_order'],
                        $item['visible'],
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
