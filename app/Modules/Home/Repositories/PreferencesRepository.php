<?php

declare(strict_types=1);

namespace App\Modules\Home\Repositories;

use App\Repositories\BaseRepository;

class PreferencesRepository extends BaseRepository
{
    protected string $table = 'user_preferences';

    /**
     * Insert or update user preferences.
     * Only the provided fields are updated (partial upsert).
     */
    public function upsert(int $userId, array $data): void
    {
        $data['user_id'] = $userId;

        $columns      = implode(', ', array_keys($data));
        $placeholders  = implode(', ', array_fill(0, count($data), '?'));

        // Build SET clause for ON DUPLICATE KEY UPDATE (skip user_id)
        $updateParts = [];
        foreach ($data as $col => $val) {
            if ($col !== 'user_id') {
                $updateParts[] = "{$col} = VALUES({$col})";
            }
        }
        $updateClause = implode(', ', $updateParts);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$updateClause}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
    }

    /**
     * Get preferences for a user.
     */
    public function getByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }
}
