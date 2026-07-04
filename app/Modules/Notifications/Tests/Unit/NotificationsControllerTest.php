<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Controllers\NotificationsController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for NotificationsController via the HTTP harness.
 * Covers the guest-guard echoes and the empty-selection redirects, all of which
 * are DB-free (the static NotificationService is never reached on these paths).
 */
class NotificationsControllerTest extends ControllerTestCase
{
    public function testUnreadCountForGuestEchoesHiddenBadge(): void
    {
        // No user_id in session → renders the inert, hidden placeholder badge.
        $result = $this->dispatch(NotificationsController::class, 'unreadCount');

        $this->assertStringContainsString('id="nt-badge-count"', $result->echoed);
        $this->assertStringContainsString('d-none', $result->echoed);
    }

    public function testDropdownForGuestEchoesUnavailable(): void
    {
        $result = $this->dispatch(NotificationsController::class, 'dropdown');

        $this->assertStringContainsString('dropdown-item', $result->echoed);
    }

    public function testMarkSelectedReadWithoutSelectionRedirectsWithError(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['notification_ids' => []])
            ->dispatch(NotificationsController::class, 'markSelectedRead');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/notifications.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testDestroySelectedWithoutSelectionRedirectsWithError(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['notification_ids' => []])
            ->dispatch(NotificationsController::class, 'destroySelected');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/notifications.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }
}
