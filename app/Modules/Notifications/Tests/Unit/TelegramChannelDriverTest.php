<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Modules\Notifications\Repositories\TelegramUserLinkRepository;
use App\Modules\Notifications\Services\TelegramChannelDriver;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Tests for TelegramChannelDriver: channel id and the two skip guards that run
 * before any network call (no active bot / user not linked). The repositories
 * are mocked so no telegram schema is required.
 */
class TelegramChannelDriverTest extends ModuleTestCase
{
    use MakesContainer;

    private function bindRepos(?array $bot, ?array $link): void
    {
        $botRepo = $this->createMock(TelegramBotRepository::class);
        $botRepo->method('findDefaultEnabled')->willReturn($bot);
        $this->bindInstance(TelegramBotRepository::class, $botRepo);

        $linkRepo = $this->createMock(TelegramUserLinkRepository::class);
        $linkRepo->method('findLinkedByUserId')->willReturn($link);
        $this->bindInstance(TelegramUserLinkRepository::class, $linkRepo);
    }

    public function testChannelIsTelegram(): void
    {
        $this->bindRepos(null, null);

        $this->assertSame('telegram', (new TelegramChannelDriver())->channel());
    }

    public function testSendSkipsWhenNoBotConfigured(): void
    {
        $this->bindRepos(null, null);

        $result = (new TelegramChannelDriver())->send(['user_id' => 1]);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('bot', strtolower((string) $result['error_message']));
    }

    public function testSendSkipsWhenUserNotLinked(): void
    {
        $this->bindRepos(['bot_token' => 'abc', 'name' => 'Bot'], null);

        $result = (new TelegramChannelDriver())->send(['user_id' => 1]);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('Telegram', (string) $result['error_message']);
    }
}
