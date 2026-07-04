<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\EmailChannelDriver;
use App\Modules\Notifications\Services\NotificationChannelDriverInterface;
use App\Modules\Notifications\Services\TelegramChannelDriver;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for NotificationChannelDriverInterface: it must declare the
 * channel()/send() methods and the shipped drivers must implement it (so the
 * queue processor can resolve any channel through the same surface).
 */
class NotificationChannelDriverInterfaceTest extends TestCase
{
    public function testInterfaceDeclaresChannelAndSend(): void
    {
        $reflection = new \ReflectionClass(NotificationChannelDriverInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('channel'));
        $this->assertTrue($reflection->hasMethod('send'));
    }

    public function testConcreteDriversImplementTheInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(EmailChannelDriver::class, NotificationChannelDriverInterface::class)
        );
        $this->assertTrue(
            is_subclass_of(TelegramChannelDriver::class, NotificationChannelDriverInterface::class)
        );
    }
}
