<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;

class NotificationsRepository extends BaseRepository
{
    protected string $table = 'notifications';

    /**
     * Count unread notifications for a user.
     */
    public function getUnreadCountForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get the latest unread (and recently read) notifications for the dropdown.
     * Returns up to $limit rows ordered by newest first.
     */
    public function getUnreadForUser(int $userId, int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name AS sender_name, u.avatar_path AS sender_avatar
             FROM notifications n
             LEFT JOIN users u ON u.id = n.created_by
             WHERE n.user_id = ?
             ORDER BY n.read_at IS NOT NULL, n.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get paginated notifications for the full list page.
     *
     * @param string|null $filter  'unread' | 'read' | null (all)
     * @return array{items: array, total: int, page: int, lastPage: int}
     */
    public function getPagedForUser(int $userId, int $page, int $perPage = 20, ?string $filter = null): array
    {
        $allowedFilters = ['unread', 'read'];

        $where  = 'WHERE n.user_id = ?';
        $params = [$userId];

        if ($filter === 'unread') {
            $where .= ' AND n.read_at IS NULL';
        } elseif ($filter === 'read') {
            $where .= ' AND n.read_at IS NOT NULL';
        }

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications n {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages  = max(1, (int) ceil($total / $perPage));
        $page   = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT n.*, u.name AS sender_name, u.avatar_path AS sender_avatar
             FROM notifications n
             LEFT JOIN users u ON u.id = n.created_by
             {$where}
             ORDER BY n.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [(int) $perPage, (int) $offset]));

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'lastPage' => $pages,
        ];
    }

    /**
     * Mark a single notification as read (only if it belongs to $userId).
     */
    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL'
        );
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Mark all unread notifications for a user as read.
     */
    public function markAllAsRead(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$userId]);
    }

    /**
     * Mark selected unread notifications as read for a user.
     * Returns the number of updated rows.
     */
    public function markManyAsRead(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE notifications
             SET read_at = NOW()
             WHERE user_id = ?
               AND read_at IS NULL
               AND id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$userId], $ids));

        return $stmt->rowCount();
    }

    /**
     * Mark all unread notifications with a specific link as read.
     */
    public function markAsReadByLink(int $userId, string $link): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND link = ? AND read_at IS NULL'
        );
        $stmt->execute([$userId, $link]);
        return $stmt->rowCount();
    }

    /**
     * Delete a single notification (only if it belongs to $userId).
     */
    public function deleteForUser(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notifications WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Delete selected notifications for a user.
     * Returns the number of deleted rows.
     */
    public function deleteManyForUser(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "DELETE FROM notifications
             WHERE user_id = ?
               AND id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$userId], $ids));

        return $stmt->rowCount();
    }

    /**
     * Delete read notifications older than $days days.
     * Returns the number of deleted rows.
     */
    public function deleteOlderThan(int $days): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notifications
             WHERE read_at IS NOT NULL
               AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
