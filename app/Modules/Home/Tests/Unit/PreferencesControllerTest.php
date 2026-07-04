<?php

declare(strict_types=1);

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Controllers\PreferencesController;
use App\Modules\Home\Services\PreferencesService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for PreferencesController via the HTTP harness.
 * Each setter syncs the resolved value back into the session cache; the
 * guest guard short-circuits before the service is touched.
 */
class PreferencesControllerTest extends ControllerTestCase
{
    public function testUpdateThemeSyncsSessionCache(): void
    {
        $service = $this->createMock(PreferencesService::class);
        $service->method('updateTheme')->willReturn('dark');
        $this->bindInstance(PreferencesService::class, $service);

        $this->actingAs(1);
        $this->withPost(['theme' => 'dark'])->dispatch(PreferencesController::class, 'updateTheme');

        $this->assertSame('dark', $_SESSION['user_preferences']['theme']);
    }

    public function testUpdateColorSyncsSessionCache(): void
    {
        $service = $this->createMock(PreferencesService::class);
        $service->method('updateColor')->willReturn('#aabbcc');
        $this->bindInstance(PreferencesService::class, $service);

        $this->actingAs(1);
        $this->withPost(['color' => '#aabbcc'])->dispatch(PreferencesController::class, 'updateColor');

        $this->assertSame('#aabbcc', $_SESSION['user_preferences']['primary_color']);
    }

    public function testUpdateThemeForGuestDoesNotTouchService(): void
    {
        $service = $this->createMock(PreferencesService::class);
        $service->expects($this->never())->method('updateTheme');
        $this->bindInstance(PreferencesService::class, $service);

        // No actingAs() → auth() is null → 401 before any service call.
        $result = $this->withPost(['theme' => 'dark'])->dispatch(PreferencesController::class, 'updateTheme');

        $this->assertFalse($result->didRender());
        $this->assertFalse($result->isRedirect());
    }
}
