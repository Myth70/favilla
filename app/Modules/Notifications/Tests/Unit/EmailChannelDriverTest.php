<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\EmailChannelDriver;
use App\Services\MailService;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Tests for EmailChannelDriver: channel id, the skipped path when the recipient
 * has no email, and the sent/failed mapping of the mail service result.
 */
class EmailChannelDriverTest extends ModuleTestCase
{
    use MakesContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT NOT NULL,
                email TEXT
            )
        ');
    }

    public function testChannelIsEmail(): void
    {
        $this->bindInstance(MailService::class, $this->createMock(MailService::class));

        $this->assertSame('email', (new EmailChannelDriver())->channel());
    }

    public function testSendSkipsWhenRecipientHasNoEmail(): void
    {
        $mail = $this->createMock(MailService::class);
        $mail->expects($this->never())->method('send');
        $this->bindInstance(MailService::class, $mail);

        // No matching user row → recipient cannot be resolved.
        $result = (new EmailChannelDriver())->send(['user_id' => 999]);

        $this->assertSame('skipped', $result['status']);
    }

    public function testSendReportsSentWhenMailServiceSucceeds(): void
    {
        $this->insertRow('users', ['name' => 'Bruno', 'email' => 'bruno@test.it']);

        $mail = $this->createMock(MailService::class);
        $mail->method('send')->willReturn(true);
        $this->bindInstance(MailService::class, $mail);

        $result = (new EmailChannelDriver())->send([
            'user_id'        => 1,
            'dispatch_title' => 'Hello',
            'delivery_body'  => '<p>Body</p>',
        ]);

        $this->assertSame('sent', $result['status']);
    }

    public function testSendReportsFailedWhenMailServiceFails(): void
    {
        $this->insertRow('users', ['name' => 'Bruno', 'email' => 'bruno@test.it']);

        $mail = $this->createMock(MailService::class);
        $mail->method('send')->willReturn(false);
        $this->bindInstance(MailService::class, $mail);

        $result = (new EmailChannelDriver())->send([
            'user_id'        => 1,
            'dispatch_title' => 'Hello',
            'delivery_body'  => 'Plain body',
        ]);

        $this->assertSame('failed', $result['status']);
    }
}
