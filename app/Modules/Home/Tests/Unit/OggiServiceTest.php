<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Services\OggiService;
use Tests\ModuleTestCase;

/**
 * OggiService aggrega feed da Tasks/Calendar/Contacts/Notifications. I rami di
 * raccolta sono protetti da has_permission(): con una sessione senza permessi
 * restano vuoti, così si può verificare in modo deterministico la struttura del
 * feed e l'aggregazione (counts/stats) senza dipendere dai moduli applicativi.
 */
class OggiServiceTest extends ModuleTestCase
{
    private OggiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // collectNotificationItems è l'unico ramo non protetto da permesso:
        // tabella vuota → getUnread() ritorna [].
        $this->createUsersTable();
        $this->migrate('
            CREATE TABLE notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                created_by INTEGER NULL,
                type       TEXT NULL,
                title      TEXT NULL,
                body       TEXT NULL,
                link       TEXT NULL,
                read_at    TEXT NULL,
                created_at TEXT NULL
            );
        ');
        $_SESSION['user_roles'] = [];
        $_SESSION['user_permissions'] = [];
        $this->service = new OggiService();
    }

    public function testBuildFeedReturnsEmptySkeletonWithoutPermissions(): void
    {
        $feed = $this->service->buildFeed(1);

        $this->assertSame([], $feed['items']);
        $this->assertSame(
            ['tasks' => 0, 'calendar' => 0, 'contacts' => 0, 'notifications' => 0],
            $feed['counts']
        );
        $this->assertArrayHasKey('stats', $feed);
        $this->assertSame(0, $feed['stats']['total_items']);
        $this->assertNull($feed['stats']['next_event']);
        $this->assertArrayHasKey('generated_at', $feed);
    }

    public function testGetCompletedTodayListReturnsEmptyWithoutPermission(): void
    {
        $this->assertSame([], $this->service->getCompletedTodayList(1));
    }
}
