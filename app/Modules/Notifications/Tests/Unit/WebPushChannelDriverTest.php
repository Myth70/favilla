<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\PushSubscriptionRepository;
use App\Modules\Notifications\Services\VapidKeyService;
use App\Modules\Notifications\Services\WebPushChannelDriver;
use App\Modules\Notifications\Services\WebPushSender;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Driver Web Push con repository, VapidKeyService e WebPushSender mockati (nessun
 * accesso a rete o crypto reale). Copre le guardie di skip, l'esito "sent" con
 * almeno un dispositivo raggiunto, il pruning delle subscription scadute (410) e
 * il fallimento totale.
 */
class WebPushChannelDriverTest extends ModuleTestCase
{
    use MakesContainer;

    private PushSubscriptionRepository $subRepo;
    private VapidKeyService $vapid;
    private WebPushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subRepo = $this->createMock(PushSubscriptionRepository::class);
        $this->vapid = $this->createMock(VapidKeyService::class);
        $this->sender = $this->createMock(WebPushSender::class);

        $this->bindInstance(PushSubscriptionRepository::class, $this->subRepo);
        $this->bindInstance(VapidKeyService::class, $this->vapid);
        $this->bindInstance(WebPushSender::class, $this->sender);
    }

    private function configureVapid(bool $configured): void
    {
        $this->vapid->method('isConfigured')->willReturn($configured);
        $this->vapid->method('publicKey')->willReturn('pub');
        $this->vapid->method('privateKey')->willReturn('priv');
        $this->vapid->method('subject')->willReturn('mailto:admin@example.test');
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(int $id, string $endpoint): array
    {
        return [
            'id'               => $id,
            'endpoint'         => $endpoint,
            'endpoint_hash'    => hash('sha256', $endpoint),
            'p256dh'           => 'p256dh-' . $id,
            'auth'             => 'auth-' . $id,
            'content_encoding' => 'aes128gcm',
        ];
    }

    public function testChannelIsWebPush(): void
    {
        $this->assertSame('web_push', (new WebPushChannelDriver())->channel());
    }

    public function testSkipsWhenVapidNotConfigured(): void
    {
        $this->configureVapid(false);
        $this->subRepo->expects($this->never())->method('activeForUser');

        $result = (new WebPushChannelDriver())->send(['user_id' => 1]);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('VAPID', (string) $result['error_message']);
    }

    public function testSkipsWhenNoSubscriptions(): void
    {
        $this->configureVapid(true);
        $this->subRepo->method('activeForUser')->willReturn([]);

        $result = (new WebPushChannelDriver())->send(['user_id' => 1]);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('subscription', strtolower((string) $result['error_message']));
    }

    public function testSentWhenAtLeastOneDeliverySucceeds(): void
    {
        $this->configureVapid(true);
        $sub = $this->subscription(10, 'https://push.example/ok');
        $this->subRepo->method('activeForUser')->willReturn([$sub]);
        $this->subRepo->expects($this->once())->method('touchLastUsed')->with([10]);
        $this->subRepo->expects($this->never())->method('deleteByEndpointHash');

        $this->sender->method('send')->willReturn([
            $sub['endpoint_hash'] => ['success' => true, 'status' => 201, 'expired' => false, 'error' => null],
        ]);

        $result = (new WebPushChannelDriver())->send([
            'user_id' => 1,
            'delivery_id' => 55,
            'delivery_subject' => 'Titolo',
            'delivery_body' => '<p>Corpo</p>',
            'delivery_link' => 'https://app.example/x',
        ]);

        $this->assertSame('sent', $result['status']);
    }

    public function testExpiredSubscriptionsGetPrunedAndReportSkipped(): void
    {
        $this->configureVapid(true);
        $sub = $this->subscription(11, 'https://push.example/gone');
        $this->subRepo->method('activeForUser')->willReturn([$sub]);
        $this->subRepo->expects($this->once())
            ->method('deleteByEndpointHash')
            ->with($sub['endpoint_hash']);

        $this->sender->method('send')->willReturn([
            $sub['endpoint_hash'] => ['success' => false, 'status' => 410, 'expired' => true, 'error' => null],
        ]);

        $result = (new WebPushChannelDriver())->send(['user_id' => 1, 'delivery_id' => 1]);

        $this->assertSame('skipped', $result['status']);
    }

    public function testBuildPayloadStripsHtmlTruncatesAndSetsTag(): void
    {
        $this->configureVapid(true);
        $sub = $this->subscription(20, 'https://push.example/p');
        $this->subRepo->method('activeForUser')->willReturn([$sub]);

        $captured = null;
        $this->sender->method('send')->willReturnCallback(
            function ($subs, $payload) use (&$captured, $sub) {
                $captured = $payload;
                return [$sub['endpoint_hash'] => ['success' => true, 'status' => 201, 'expired' => false, 'error' => null]];
            }
        );

        (new WebPushChannelDriver())->send([
            'user_id'          => 1,
            'delivery_id'      => 77,
            'delivery_subject' => '  Titolo  ',
            'delivery_body'    => '<p><b>Ciao</b> ' . str_repeat('a', 600) . '</p>',
            'delivery_link'    => 'https://app.example/z',
        ]);

        $data = json_decode((string) $captured, true);
        $this->assertSame('Titolo', $data['title']);
        $this->assertStringNotContainsString('<', (string) $data['body']);
        $this->assertLessThanOrEqual(500, mb_strlen((string) $data['body']));
        $this->assertStringEndsWith('…', (string) $data['body']);
        $this->assertSame('https://app.example/z', $data['url']);
        $this->assertSame('favilla-77', $data['tag']);
    }

    public function testBuildPayloadFallsBackToDefaultTitle(): void
    {
        $this->configureVapid(true);
        $sub = $this->subscription(21, 'https://push.example/q');
        $this->subRepo->method('activeForUser')->willReturn([$sub]);

        $captured = null;
        $this->sender->method('send')->willReturnCallback(
            function ($subs, $payload) use (&$captured, $sub) {
                $captured = $payload;
                return [$sub['endpoint_hash'] => ['success' => true, 'status' => 201, 'expired' => false, 'error' => null]];
            }
        );

        (new WebPushChannelDriver())->send(['user_id' => 1, 'delivery_id' => 1, 'delivery_body' => 'x']);

        $data = json_decode((string) $captured, true);
        $this->assertSame('Notifica', $data['title']);
    }

    public function testFailedWhenAllDeliveriesFail(): void
    {
        $this->configureVapid(true);
        $sub = $this->subscription(12, 'https://push.example/err');
        $this->subRepo->method('activeForUser')->willReturn([$sub]);
        $this->subRepo->expects($this->never())->method('touchLastUsed');

        $this->sender->method('send')->willReturn([
            $sub['endpoint_hash'] => ['success' => false, 'status' => 500, 'expired' => false, 'error' => 'HTTP 500'],
        ]);

        $result = (new WebPushChannelDriver())->send(['user_id' => 1, 'delivery_id' => 1]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('500', (string) $result['error_message']);
    }
}
