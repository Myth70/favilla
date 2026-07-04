<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Modules\Notifications\Services\NotificationAdminService;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Tests for NotificationAdminService::validateBot() — the bot form validation,
 * including the DB-free new-bot path and the existing-bot path (where the token
 * becomes optional), with the bot repository mocked.
 */
class NotificationAdminServiceTest extends ModuleTestCase
{
    use MakesContainer;

    public function testValidateBotFlagsMissingNameAndTokenForNewBot(): void
    {
        $errors = (new NotificationAdminService())->validateBot([]);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('bot_token', $errors);
    }

    public function testValidateBotAcceptsCompleteNewBot(): void
    {
        $errors = (new NotificationAdminService())->validateBot([
            'name'      => 'Bot Notifiche',
            'bot_token' => '123456:ABCDEF',
        ]);

        $this->assertSame([], $errors);
    }

    public function testValidateBotMakesTokenOptionalForExistingBot(): void
    {
        $botRepo = $this->createMock(TelegramBotRepository::class);
        $botRepo->method('find')->willReturn(['id' => 5, 'name' => 'Existing']);
        $this->bindInstance(TelegramBotRepository::class, $botRepo);

        $errors = (new NotificationAdminService())->validateBot([
            'bot_id' => '5',
            'name'   => 'Existing',
            // no bot_token — accepted because the bot already exists
        ]);

        $this->assertArrayNotHasKey('bot_token', $errors);
        $this->assertSame([], $errors);
    }
}
