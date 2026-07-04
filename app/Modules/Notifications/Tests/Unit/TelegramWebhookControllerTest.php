<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Controllers\TelegramWebhookController;
use App\Modules\Notifications\Services\TelegramLinkService;
use App\Security\RateLimiter;
use Tests\ControllerTestCase;

/**
 * Controller-level test for the Telegram webhook endpoint via the HTTP harness.
 *
 * The action reads the raw request body from php://input, which is empty under
 * PHPUnit, so the invalid-payload guard is what we can deterministically assert:
 * it must respond 400 and never reach the link service.
 *
 * The rate limiter is DB-backed with a MySQL-only query, so it is injected as a
 * permissive/hostile mock to exercise the controller branches in isolation.
 */
class TelegramWebhookControllerTest extends ControllerTestCase
{
    public function testWebhookRejectsInvalidPayload(): void
    {
        $link = $this->createMock(TelegramLinkService::class);
        $link->expects($this->never())->method('handleWebhook');
        $this->bindInstance(TelegramLinkService::class, $link);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->method('isLimitedForIpAndAccount')->willReturn(false);
        $this->bindInstance(RateLimiter::class, $rateLimiter);

        $result = $this->dispatch(TelegramWebhookController::class, 'webhook', ['s3cr3t']);

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['ok']);
    }

    public function testWebhookRejectsWhenRateLimited(): void
    {
        $link = $this->createMock(TelegramLinkService::class);
        $link->expects($this->never())->method('handleWebhook');
        $this->bindInstance(TelegramLinkService::class, $link);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->method('isLimitedForIpAndAccount')->willReturn(true);
        $this->bindInstance(RateLimiter::class, $rateLimiter);

        $result = $this->dispatch(TelegramWebhookController::class, 'webhook', ['s3cr3t']);

        $this->assertTrue($result->isJson());
        $this->assertSame(429, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['ok']);
    }
}
