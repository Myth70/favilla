<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;

class TelegramUserLinkRepository extends BaseRepository
{
    protected string $table = 'telegram_user_links';
    protected array $fillable = [
        'user_id',
        'bot_id',
        'telegram_user_id',
        'chat_id',
        'telegram_username',
        'link_token',
        'status',
        'linked_at',
        'last_seen_at',
        'metadata_json',
    ];
    protected bool $timestamps = true;

    public function findLinkedByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM telegram_user_links
             WHERE user_id = ? AND status = ?
             ORDER BY linked_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId, 'linked']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPendingByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM telegram_user_links
             WHERE user_id = ? AND status = ?
             ORDER BY updated_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId, 'pending']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByToken(int $botId, string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM telegram_user_links
             WHERE bot_id = ? AND link_token = ?
             LIMIT 1'
        );
        $stmt->execute([$botId, $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUserAndBot(int $userId, int $botId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM telegram_user_links
             WHERE user_id = ? AND bot_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $botId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function ensurePendingLink(int $userId, int $botId, string $token): array
    {
        $linked = $this->findLinkedByUserId($userId);
        if ($linked && (int) $linked['bot_id'] === $botId) {
            return $linked;
        }

        $pending = $this->findPendingByUserId($userId);
        if ($pending) {
            $this->update((int) $pending['id'], [
                'bot_id'       => $botId,
                'link_token'   => $token,
                'status'       => 'pending',
                'chat_id'      => null,
                'telegram_user_id' => null,
                'telegram_username' => null,
                'linked_at'    => null,
                'metadata_json' => null,
            ]);
            return $this->find((int) $pending['id']) ?? $pending;
        }

        $existing = $this->findByUserAndBot($userId, $botId);
        if ($existing) {
            $this->update((int) $existing['id'], [
                'link_token'        => $token,
                'status'            => 'pending',
                'telegram_user_id'  => null,
                'chat_id'           => null,
                'telegram_username' => null,
                'linked_at'         => null,
                'last_seen_at'      => null,
                'metadata_json'     => null,
            ]);
            return $this->find((int) $existing['id']) ?? $existing;
        }

        $id = $this->create([
            'user_id'           => $userId,
            'bot_id'            => $botId,
            'telegram_user_id'  => null,
            'chat_id'           => null,
            'telegram_username' => null,
            'link_token'        => $token,
            'status'            => 'pending',
            'linked_at'         => null,
            'last_seen_at'      => null,
            'metadata_json'     => null,
        ]);

        return $this->find($id) ?? [];
    }

    public function markLinked(int $id, array $data): void
    {
        $this->update($id, [
            'telegram_user_id'  => $data['telegram_user_id'] ?? null,
            'chat_id'           => $data['chat_id'] ?? null,
            'telegram_username' => $data['telegram_username'] ?? null,
            'status'            => 'linked',
            'linked_at'         => date('Y-m-d H:i:s'),
            'last_seen_at'      => date('Y-m-d H:i:s'),
            'metadata_json'     => !empty($data['metadata_json']) ? $data['metadata_json'] : null,
        ]);
    }

    public function touchLastSeen(int $id): void
    {
        $this->update($id, [
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function revokeByUserId(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE telegram_user_links
             SET status = ?, updated_at = NOW()
             WHERE user_id = ? AND status IN (?, ?)'
        );
        $stmt->execute(['revoked', $userId, 'pending', 'linked']);
        return $stmt->rowCount() > 0;
    }
}
