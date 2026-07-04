<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Modules\Notifications\Repositories\TelegramUserLinkRepository;
use App\Modules\Notifications\Services\TelegramLinkService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * Si testano i rami che NON effettuano chiamate di rete (sendTelegramMessage):
 * wizard senza bot, webhook con secret errato/senza messaggio, disconnect.
 */
class TelegramLinkServiceTest extends TestCase
{
    use MakesContainer;

    private TelegramBotRepository $botRepo;
    private TelegramUserLinkRepository $linkRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botRepo = $this->createMock(TelegramBotRepository::class);
        $this->linkRepo = $this->createMock(TelegramUserLinkRepository::class);
    }

    private function service(): TelegramLinkService
    {
        $this->freshContainer();
        $this->bindInstance(TelegramBotRepository::class, $this->botRepo);
        $this->bindInstance(TelegramUserLinkRepository::class, $this->linkRepo);
        return new TelegramLinkService();
    }

    public function testWizardDataWhenNoBotConfigured(): void
    {
        $this->botRepo->method('findDefaultEnabled')->willReturn(null);
        $this->linkRepo->method('findLinkedByUserId')->willReturn(null);

        $data = $this->service()->getWizardData(1);

        $this->assertFalse($data['available']);
        $this->assertFalse($data['linked']);
        $this->assertNull($data['deep_link']);
    }

    public function testWizardDataBuildsDeepLinkForPendingLink(): void
    {
        $this->botRepo->method('findDefaultEnabled')->willReturn([
            'id' => 1, 'name' => 'Bot', 'bot_username' => 'favilla_bot', 'webhook_secret' => 's',
        ]);
        $this->linkRepo->method('findLinkedByUserId')->willReturn(null);
        $this->linkRepo->method('ensurePendingLink')->willReturn(['id' => 9, 'link_token' => 'tok123456']);

        $data = $this->service()->getWizardData(1);

        $this->assertTrue($data['available']);
        $this->assertFalse($data['linked']);
        $this->assertSame('https://t.me/favilla_bot?start=tok123456', $data['deep_link']);
    }

    public function testHandleWebhookRejectsInvalidSecret(): void
    {
        $this->botRepo->method('findDefault')->willReturn(['id' => 1, 'webhook_secret' => 'right', 'bot_token' => 't']);

        $result = $this->service()->handleWebhook('wrong', ['message' => ['text' => 'x']]);

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['status']);
    }

    public function testHandleWebhookIgnoresUpdateWithoutMessage(): void
    {
        $this->botRepo->method('findDefault')->willReturn(['id' => 1, 'webhook_secret' => 'right', 'bot_token' => 't']);

        $result = $this->service()->handleWebhook('right', ['update_id' => 5]);

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
    }

    public function testDisconnectRevokesLink(): void
    {
        $this->linkRepo->expects($this->once())->method('revokeByUserId')->with(7)->willReturn(true);

        $this->service()->disconnect(7);
    }
}
