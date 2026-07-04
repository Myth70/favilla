<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class NotificationChannelRepository extends BaseRepository
{
    protected string $table = 'notification_channels';

    /**
     * All channels ordered by sort_order.
     *
     * @return array<int, array{slug:string,name:string,description:?string,is_enabled:int,sort_order:int}>
     */
    public function getAllOrdered(): array
    {
        $stmt = $this->pdo->query(
            'SELECT slug, name, description, is_enabled, sort_order
             FROM notification_channels
             ORDER BY sort_order ASC, id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
