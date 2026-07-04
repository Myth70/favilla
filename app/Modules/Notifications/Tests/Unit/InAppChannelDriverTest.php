<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\InAppChannelDriver;
use Tests\ModuleTestCase;

class InAppChannelDriverTest extends ModuleTestCase
{
    private InAppChannelDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                title      TEXT NULL,
                body       TEXT NULL,
                type       TEXT NULL,
                icon       TEXT NULL,
                color      TEXT NULL,
                link       TEXT NULL,
                created_by INTEGER NULL
            );
        ');
        $this->driver = new InAppChannelDriver();
    }

    public function testChannelSlug(): void
    {
        $this->assertSame('in_app', $this->driver->channel());
    }

    public function testSendInsertsNotificationAndReturnsSent(): void
    {
        $result = $this->driver->send([
            'user_id'          => 5,
            'delivery_subject' => 'Oggetto consegna',
            'delivery_body'    => 'Corpo',
            'dispatch_type'    => 'warning',
            'delivery_icon'    => 'fa-bell',
            'delivery_color'   => 'amber',
            'delivery_link'    => '/x',
            'created_by'       => 2,
        ]);

        $this->assertSame('sent', $result['status']);
        $this->assertNotEmpty($result['provider_message_id']);
        $this->assertNull($result['error_message']);

        $row = $this->pdo->query('SELECT * FROM notifications')->fetch();
        $this->assertSame(5, (int) $row['user_id']);
        $this->assertSame('Oggetto consegna', $row['title']);
        $this->assertSame('warning', $row['type']);
    }

    public function testSendFallsBackToDispatchFieldsWhenDeliveryAbsent(): void
    {
        // subject usa `??` (fallback solo su chiave assente), body usa `?:`
        // (fallback anche su stringa vuota): chiave subject assente + body vuoto.
        $this->driver->send([
            'user_id'        => 1,
            'dispatch_title' => 'Titolo dispatch',
            'delivery_body'  => '',
            'dispatch_body'  => 'Corpo dispatch',
            'delivery_icon'  => '',
            'delivery_color' => '',
            'delivery_link'  => '',
        ]);

        $row = $this->pdo->query('SELECT * FROM notifications')->fetch();
        $this->assertSame('Titolo dispatch', $row['title']);
        $this->assertSame('Corpo dispatch', $row['body']);
        // type assente → default 'info'.
        $this->assertSame('info', $row['type']);
    }
}
