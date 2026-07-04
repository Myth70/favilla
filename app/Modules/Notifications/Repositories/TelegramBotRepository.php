<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;

class TelegramBotRepository extends BaseRepository
{
    protected string $table = 'telegram_bots';
    protected array $fillable = [
        'name',
        'bot_username',
        'bot_token',
        'webhook_secret',
        'is_enabled',
        'is_default',
        'created_by',
    ];
    protected bool $timestamps = true;

    public function findDefaultEnabled(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM telegram_bots
             WHERE is_enabled = 1
             ORDER BY is_default DESC, id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findDefault(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM telegram_bots
             WHERE is_default = 1
             ORDER BY id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM telegram_bots
             ORDER BY is_default DESC, is_enabled DESC, id ASC'
        );

        return $stmt->fetchAll();
    }

    public function clearDefault(?int $exceptId = null): void
    {
        if ($exceptId !== null) {
            $stmt = $this->pdo->prepare('UPDATE telegram_bots SET is_default = 0 WHERE id <> ?');
            $stmt->execute([$exceptId]);
            return;
        }

        $this->pdo->exec('UPDATE telegram_bots SET is_default = 0');
    }
}
