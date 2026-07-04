<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;
use App\Modules\Notifications\Repositories\NotificationPreferenceRepository;
use App\Modules\Notifications\Services\NotificationModuleCatalogService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Tests for NotificationPreferenceService::updatePreferences() — the aggregation
 * of submitted module/event toggles into upsert calls and the returned counters.
 * Collaborators are mocked through the container so no notification schema is
 * required.
 */
class NotificationPreferenceServiceTest extends ModuleTestCase
{
    use MakesContainer;

    public function testUpdatePreferencesCountsModuleChannelToggles(): void
    {
        $channelRepo = $this->createMock(NotificationChannelRepository::class);
        $channelRepo->method('getAllOrdered')->willReturn([
            ['slug' => 'inapp', 'name' => 'In-app'],
        ]);
        $this->bindInstance(NotificationChannelRepository::class, $channelRepo);

        $catalog = $this->createMock(NotificationModuleCatalogService::class);
        $catalog->method('getBaselineModules')->willReturn([
            ['slug' => 'contacts', 'name' => 'Contatti'],
        ]);
        $this->bindInstance(NotificationModuleCatalogService::class, $catalog);

        $eventRepo = $this->createMock(NotificationEventRepository::class);
        $eventRepo->method('getExplicitEventCatalog')->willReturn([]);
        $this->bindInstance(NotificationEventRepository::class, $eventRepo);

        $prefRepo = $this->createMock(NotificationPreferenceRepository::class);
        $prefRepo->expects($this->once())
            ->method('upsertPreference')
            ->with(7, 'contacts', '', 'inapp', true);
        $this->bindInstance(NotificationPreferenceRepository::class, $prefRepo);

        $result = (new NotificationPreferenceService())->updatePreferences(
            7,
            ['contacts' => ['inapp' => 'on']],
            []
        );

        $this->assertSame(1, $result['module_updates']);
        $this->assertSame(0, $result['event_updates']);
        $this->assertSame(0, $result['event_cleared']);
    }
}
