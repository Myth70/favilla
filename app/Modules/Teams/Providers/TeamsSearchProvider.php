<?php

declare(strict_types=1);

namespace App\Modules\Teams\Providers;

use App\Contracts\SearchableModule;
use PDO;

class TeamsSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        if (!has_permission('teams.view')) {
            return [];
        }

        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        $stmt = $pdo->prepare(
            "SELECT m.id, m.body, m.created_at, m.conversation_id,
                    c.name AS conv_name, c.type AS conv_type, u.name AS author_name
             FROM teams_messages m
             JOIN teams_conversations c ON c.id = m.conversation_id
             JOIN teams_conversation_members cm ON cm.conversation_id = c.id
                  AND cm.user_id = ? AND cm.left_at IS NULL
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.deleted_at IS NULL AND m.type = 'text'
               AND m.body LIKE ?
             ORDER BY m.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $like, $limit]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $convLabel = $row['conv_type'] === 'group'
                ? ($row['conv_name'] ?? t('teams.exception.default_group_name'))
                : ($row['author_name'] ?? t('teams.search.default_chat_name'));
            $results[] = [
                'title'    => mb_substr(strip_tags($row['body']), 0, 80),
                'subtitle' => $convLabel . ' — ' . ($row['author_name'] ?? ''),
                'url'      => route('teams.show', ['id' => $row['conversation_id']]),
                'icon'     => 'fa-comment',
                'badge'    => null,
            ];
        }
        return $results;
    }

    public function getSearchLabel(): string
    {
        return t('teams.title');
    }

    public function getSearchIcon(): string
    {
        return 'fa-comments';
    }
}
