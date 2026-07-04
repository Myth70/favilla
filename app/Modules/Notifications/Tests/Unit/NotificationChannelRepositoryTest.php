<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use Tests\ModuleTestCase;

class NotificationChannelRepositoryTest extends ModuleTestCase
{
    private NotificationChannelRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE notification_channels (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                slug        TEXT NOT NULL UNIQUE,
                name        TEXT NOT NULL,
                description TEXT NULL,
                is_enabled  INTEGER NOT NULL DEFAULT 1,
                sort_order  INTEGER NOT NULL DEFAULT 10,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->repo = new NotificationChannelRepository();
    }

    public function testGetAllOrderedReturnsChannelsBySortOrder(): void
    {
        $this->insertRow('notification_channels', ['slug' => 'email', 'name' => 'Email', 'sort_order' => 20]);
        $this->insertRow('notification_channels', ['slug' => 'inapp', 'name' => 'In-App', 'sort_order' => 10]);
        $this->insertRow('notification_channels', ['slug' => 'telegram', 'name' => 'Telegram', 'sort_order' => 30]);

        $rows = $this->repo->getAllOrdered();

        $this->assertSame(['inapp', 'email', 'telegram'], array_column($rows, 'slug'));
    }

    public function testGetAllOrderedReturnsEmptyArrayWhenNoChannels(): void
    {
        $this->assertSame([], $this->repo->getAllOrdered());
    }
}
