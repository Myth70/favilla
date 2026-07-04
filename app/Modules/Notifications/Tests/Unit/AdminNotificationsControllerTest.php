<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Controllers\AdminNotificationsController;
use App\Modules\Notifications\Services\NotificationAdminService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AdminNotificationsController via the HTTP harness.
 * Covers the DB-free send() validation branches and the bot-validation redirect
 * (admin service mocked).
 */
class AdminNotificationsControllerTest extends ControllerTestCase
{
    public function testStoreRejectsEmptyRecipientAndTitle(): void
    {
        $this->actingAsAdmin();

        // Default send_mode 'user' with no user_id and no title → two errors,
        // resolved before any NotificationService call.
        $result = $this->withPost([])->dispatch(AdminNotificationsController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.notifications.send', $result->redirectUrl());
        $errors = $_SESSION['_errors'] ?? [];
        $this->assertArrayHasKey('user_id', $errors);
        $this->assertArrayHasKey('title', $errors);
    }

    public function testStoreRejectsRoleModeWithoutRole(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['send_mode' => 'role', 'role_slug' => '', 'title' => 'Hi'])
            ->dispatch(AdminNotificationsController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.notifications.send', $result->redirectUrl());
        $this->assertArrayHasKey('role_slug', $_SESSION['_errors'] ?? []);
    }

    public function testSaveBotRedirectsOnValidationErrors(): void
    {
        $admin = $this->createMock(NotificationAdminService::class);
        $admin->method('validateBot')->willReturn(['bot_token' => 'Token non valido']);
        $admin->expects($this->never())->method('saveBot');
        $this->bindInstance(NotificationAdminService::class, $admin);

        $this->actingAsAdmin();
        $result = $this->withPost(['bot_token' => 'bad'])
            ->dispatch(AdminNotificationsController::class, 'saveBot');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.notifications.settings#telegram-bot', $result->redirectUrl());
        $this->assertArrayHasKey('bot_token', $_SESSION['_errors'] ?? []);
    }
}
