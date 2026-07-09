<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Controllers\PushController;
use App\Modules\Notifications\Repositories\PushSubscriptionRepository;
use App\Modules\Notifications\Services\VapidKeyService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests per PushController via l'harness HTTP. Repository e
 * VapidKeyService sono mockati: si verificano gli status JSON dei percorsi di
 * guardia (guest, VAPID assente, endpoint non valido) e il flusso felice
 * subscribe/unsubscribe.
 */
class PushControllerTest extends ControllerTestCase
{
    private PushSubscriptionRepository $subRepo;
    private VapidKeyService $vapid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subRepo = $this->createMock(PushSubscriptionRepository::class);
        $this->vapid = $this->createMock(VapidKeyService::class);
        $this->bindInstance(PushSubscriptionRepository::class, $this->subRepo);
        $this->bindInstance(VapidKeyService::class, $this->vapid);
    }

    private function validPayload(): array
    {
        return [
            'endpoint' => 'https://push.example/endpoint-abc',
            'p256dh'   => str_repeat('A', 40),
            'auth'     => str_repeat('B', 22),
        ];
    }

    public function testSubscribeAsGuestReturns401(): void
    {
        $result = $this->withPost($this->validPayload())
            ->dispatch(PushController::class, 'subscribe');

        $this->assertTrue($result->isJson());
        $this->assertSame(401, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['ok']);
    }

    public function testSubscribeWithoutVapidReturns409(): void
    {
        $this->actingAs(1);
        $this->vapid->method('isConfigured')->willReturn(false);

        $result = $this->withPost($this->validPayload())
            ->dispatch(PushController::class, 'subscribe');

        $this->assertSame(409, $result->jsonStatus());
        $this->assertSame('not_configured', $result->jsonPayload()['error']);
    }

    public function testSubscribeWithNonHttpsEndpointReturns422(): void
    {
        $this->actingAs(1);
        $this->vapid->method('isConfigured')->willReturn(true);
        $this->subRepo->expects($this->never())->method('upsertForDevice');

        $payload = $this->validPayload();
        $payload['endpoint'] = 'http://push.example/insecure';

        $result = $this->withPost($payload)
            ->dispatch(PushController::class, 'subscribe');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame('invalid_subscription', $result->jsonPayload()['error']);
    }

    public function testSubscribeValidPersistsAndReturnsDeviceCount(): void
    {
        $this->actingAs(1);
        $this->vapid->method('isConfigured')->willReturn(true);
        $this->subRepo->expects($this->once())
            ->method('upsertForDevice')
            ->with(1, 'https://push.example/endpoint-abc', $this->anything(), $this->anything(), 'aes128gcm', $this->anything());
        $this->subRepo->method('countForUser')->willReturn(2);

        $result = $this->withPost($this->validPayload())
            ->dispatch(PushController::class, 'subscribe');

        $this->assertSame(200, $result->jsonStatus());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertSame(2, $result->jsonPayload()['device_count']);
    }

    public function testUnsubscribeRemovesEndpoint(): void
    {
        $this->actingAs(1);
        $this->subRepo->expects($this->once())
            ->method('deleteForUserByEndpoint')
            ->with(1, 'https://push.example/endpoint-abc')
            ->willReturn(true);
        $this->subRepo->method('countForUser')->willReturn(0);

        $result = $this->withPost(['endpoint' => 'https://push.example/endpoint-abc'])
            ->dispatch(PushController::class, 'unsubscribe');

        $this->assertSame(200, $result->jsonStatus());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertSame(0, $result->jsonPayload()['device_count']);
    }

    public function testUnsubscribeWithoutEndpointReturns422(): void
    {
        $this->actingAs(1);

        $result = $this->withPost([])
            ->dispatch(PushController::class, 'unsubscribe');

        $this->assertSame(422, $result->jsonStatus());
    }
}
