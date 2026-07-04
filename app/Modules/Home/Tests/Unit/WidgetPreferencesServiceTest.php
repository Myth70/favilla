<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Repositories\WidgetPreferencesRepository;
use App\Modules\Home\Services\WidgetPreferencesService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\ModuleTestCase;

class WidgetPreferencesServiceTest extends ModuleTestCase
{
    /** @var WidgetPreferencesRepository&MockObject */
    private WidgetPreferencesRepository $repo;
    private WidgetPreferencesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->getMockBuilder(WidgetPreferencesRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getByUserId', 'replaceAll', 'upsertBatch', 'deleteByUserId'])
            ->getMock();

        app()->instance(WidgetPreferencesRepository::class, $this->repo);
        $this->service = new WidgetPreferencesService();
    }

    public function testGetUserPrefsMapsRowsAndUsesPerRequestCache(): void
    {
        $this->repo->expects($this->once())
            ->method('getByUserId')
            ->with(10)
            ->willReturn([
                ['widget_id' => 'w.stats', 'sort_order' => '0', 'visible' => 1],
                ['widget_id' => 'w.tasks', 'sort_order' => '3', 'visible' => 0],
            ]);

        $first = $this->service->getUserPrefs(10);
        $second = $this->service->getUserPrefs(10);

        $this->assertSame([
            'w.stats' => ['sort_order' => 0, 'visible' => true],
            'w.tasks' => ['sort_order' => 3, 'visible' => false],
        ], $first);
        $this->assertSame($first, $second);
    }

    public function testSaveLayoutReplacesAllAndRefreshesCache(): void
    {
        $this->repo->expects($this->once())
            ->method('replaceAll')
            ->with(
                7,
                [
                    ['widget_id' => 'alpha', 'sort_order' => 0, 'visible' => 1],
                    ['widget_id' => 'beta', 'sort_order' => 1, 'visible' => 0],
                ]
            );

        $this->repo->expects($this->once())
            ->method('getByUserId')
            ->with(7)
            ->willReturn([]);

        $this->service->saveLayout(7, [
            ['id' => 'alpha', 'visible' => true],
            ['id' => 'beta', 'visible' => false],
        ]);

        // After saveLayout, getUserPrefs should hit the repo only on first call
        // (already counted above as the refreshCache call).
        $this->assertSame([], $this->service->getUserPrefs(7));
    }

    public function testToggleWidgetKeepsExistingSortOrder(): void
    {
        $this->repo->expects($this->exactly(2))
            ->method('getByUserId')
            ->with(12)
            ->willReturnOnConsecutiveCalls(
                [['widget_id' => 'existing.widget', 'sort_order' => 4, 'visible' => 1]],
                [['widget_id' => 'existing.widget', 'sort_order' => 4, 'visible' => 0]],
            );

        $this->repo->expects($this->once())
            ->method('upsertBatch')
            ->with(
                12,
                [
                    ['widget_id' => 'existing.widget', 'sort_order' => 4, 'visible' => 0],
                ]
            );

        $this->service->toggleWidget(12, 'existing.widget', false);

        $this->assertFalse($this->service->getUserPrefs(12)['existing.widget']['visible']);
    }

    public function testToggleWidgetPlacesNewWidgetAtEnd(): void
    {
        $this->repo->expects($this->exactly(2))
            ->method('getByUserId')
            ->with(15)
            ->willReturnOnConsecutiveCalls(
                [
                    ['widget_id' => 'a', 'sort_order' => 1, 'visible' => 1],
                    ['widget_id' => 'b', 'sort_order' => 8, 'visible' => 1],
                ],
                [
                    ['widget_id' => 'a', 'sort_order' => 1, 'visible' => 1],
                    ['widget_id' => 'b', 'sort_order' => 8, 'visible' => 1],
                    ['widget_id' => 'new.widget', 'sort_order' => 9, 'visible' => 1],
                ],
            );

        $this->repo->expects($this->once())
            ->method('upsertBatch')
            ->with(
                15,
                [
                    ['widget_id' => 'new.widget', 'sort_order' => 9, 'visible' => 1],
                ]
            );

        $this->service->toggleWidget(15, 'new.widget', true);

        $this->assertSame(9, $this->service->getUserPrefs(15)['new.widget']['sort_order']);
    }

    public function testResetToDefaultsDeletesRowsAndClearsCache(): void
    {
        // Seed cache via first read so we can verify it is cleared.
        $this->repo->expects($this->exactly(2))
            ->method('getByUserId')
            ->with(22)
            ->willReturnOnConsecutiveCalls(
                [['widget_id' => 'legacy', 'sort_order' => 0, 'visible' => 1]],
                [],
            );

        $this->service->getUserPrefs(22);

        $this->repo->expects($this->once())
            ->method('deleteByUserId')
            ->with(22);

        $this->service->resetToDefaults(22);

        $this->assertSame([], $this->service->getUserPrefs(22));
    }
}
