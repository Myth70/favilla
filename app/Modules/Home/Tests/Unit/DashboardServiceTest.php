<?php

namespace App\Modules\Home\Tests\Unit;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Home\Services\DashboardColorPalette;
use App\Modules\Home\Services\DashboardService;
use App\Modules\Home\Services\WidgetDataCache;
use App\Modules\Home\Services\WidgetPreferencesService;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use Tests\ModuleTestCase;

class DashboardServiceTest extends ModuleTestCase
{
    private DashboardService $service;
    /** @var WidgetPreferencesService&MockObject */
    private WidgetPreferencesService $prefsService;
    /** @var DashboardColorPalette&MockObject */
    private DashboardColorPalette $colorPalette;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prefsService = $this->getMockBuilder(WidgetPreferencesService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserPrefs'])
            ->getMock();

        $this->colorPalette = $this->getMockBuilder(DashboardColorPalette::class)
            ->onlyMethods(['resolveWidgetThemeColor', 'resolveDisplayColor', 'getTone'])
            ->getMock();

        $this->colorPalette->method('resolveWidgetThemeColor')
            ->willReturnCallback(static function (array $widget): ?string {
                return ($widget['id'] ?? null) === 'tasks.open' ? 'purple' : null;
            });

        $this->colorPalette->method('resolveDisplayColor')
            ->willReturnCallback(static function (?string $assignedColor, ?string $themeColor): string {
                if ($themeColor !== null && in_array($assignedColor, ['primary', 'secondary', 'info', 'dark', 'light'], true)) {
                    return $themeColor;
                }

                return $assignedColor ?? 'primary';
            });

        $this->colorPalette->method('getTone')
            ->willReturnCallback(static function (string $color): array {
                return match ($color) {
                    'purple' => ['value' => '#6f42c1', 'rgb' => '111,66,193'],
                    'warning' => ['value' => 'var(--bs-warning)', 'rgb' => 'var(--bs-warning-rgb)'],
                    default => ['value' => 'var(--bs-primary)', 'rgb' => 'var(--bs-primary-rgb)'],
                };
            });

        // Bypass the on-disk cache: always run the callback so tests are isolated.
        $widgetCache = $this->getMockBuilder(WidgetDataCache::class)
            ->onlyMethods(['remember'])
            ->getMock();
        $widgetCache->method('remember')
            ->willReturnCallback(static fn (string $key, int $ttl, callable $cb) => $cb());

        app()->instance(WidgetPreferencesService::class, $this->prefsService);
        app()->instance(DashboardColorPalette::class, $this->colorPalette);
        app()->instance(WidgetDataCache::class, $widgetCache);

        $this->service = new DashboardService();
        $_SESSION['user_permissions'] = ['tasks.view'];

        $provider = new class () implements DashboardWidgetProvider {
            public function getWidgets(int $userId): array
            {
                return [
                    ['id' => 'tasks.open', 'type' => 'stat', 'label' => 'Task aperti', 'icon' => 'fa-list-check', 'size' => 3, 'permission' => 'tasks.view'],
                    ['id' => 'admin.secret', 'type' => 'stat', 'label' => 'Segreto', 'icon' => 'fa-lock', 'size' => 3, 'permission' => 'admin.secret'],
                    ['id' => 'tasks.empty', 'type' => 'chart', 'label' => 'Vuoto', 'icon' => 'fa-chart-line', 'size' => 6, 'permission' => 'tasks.view'],
                    ['id' => 'weather.x', 'type' => 'stat', 'label' => 'Meteo', 'icon' => 'fa-cloud', 'size' => 3, 'permission' => null, 'lazy' => true],
                ];
            }

            public function getWidgetData(int $userId, string $widgetId): ?array
            {
                return match ($widgetId) {
                    'tasks.open'   => ['data' => ['value' => 8, 'subtitle' => 'In corso', 'link' => '/tasks', 'color' => 'primary']],
                    'admin.secret' => ['data' => ['value' => 1, 'subtitle' => 'N/D', 'link' => '#', 'color' => 'danger']],
                    'weather.x'    => ['data' => ['value' => '20°', 'subtitle' => 'sereno', 'link' => '#', 'color' => 'info']],
                    'tasks.empty'  => null, // nothing to show → widget hidden
                    default        => null,
                };
            }
        };

        $prop = new ReflectionProperty(DashboardService::class, 'providers');
        $prop->setAccessible(true);
        $prop->setValue($this->service, [$provider]);
    }

    public function testGetWidgetCatalogFiltersByPermissionAndReturnsMetadataOnly(): void
    {
        $this->prefsService->expects($this->once())
            ->method('getUserPrefs')
            ->with(4)
            ->willReturn([]);

        $catalog = $this->service->getWidgetCatalog(4);

        // admin.secret filtered out (no permission); metadata only, no data computed.
        $this->assertCount(3, $catalog);
        $this->assertSame('tasks.open', $catalog[0]['id']);
        $this->assertSame('tasks.empty', $catalog[1]['id']);
        $this->assertSame('weather.x', $catalog[2]['id']);
        $this->assertArrayNotHasKey('data', $catalog[0]);
    }

    public function testGetWidgetCatalogAppliesUserPreferences(): void
    {
        $this->prefsService->expects($this->once())
            ->method('getUserPrefs')
            ->with(4)
            ->willReturn([
                'tasks.open'  => ['sort_order' => 0, 'visible' => true],
                'tasks.empty' => ['sort_order' => 1, 'visible' => false],
            ]);

        $catalog = $this->service->getWidgetCatalog(4);

        // tasks.empty hidden; tasks.open first by order; weather.x visible by default.
        $this->assertCount(2, $catalog);
        $this->assertSame('tasks.open', $catalog[0]['id']);
        $this->assertSame('weather.x', $catalog[1]['id']);
    }

    public function testBuildDashboardRendersFastWidgetsInlineAndKeepsSlowOnesLazy(): void
    {
        $this->prefsService->expects($this->once())
            ->method('getUserPrefs')
            ->with(4)
            ->willReturn([]);

        $items = $this->service->buildDashboard(4);

        // tasks.open → ready inline; tasks.empty → null (omitted); weather.x → lazy.
        $this->assertCount(2, $items);

        $this->assertFalse($items[0]['lazy']);
        $this->assertSame('tasks.open', $items[0]['widget']['id']);
        $this->assertSame(8, $items[0]['widget']['data']['value']);
        $this->assertSame('purple', $items[0]['widget']['_displayColor']);

        $this->assertTrue($items[1]['lazy']);
        $this->assertSame('weather.x', $items[1]['meta']['id']);
        // Lazy widgets are NOT computed during the batch.
        $this->assertArrayNotHasKey('widget', $items[1]);
    }

    public function testRenderWidgetComputesDataAndDecoratesColor(): void
    {
        $widget = $this->service->renderWidget(4, 'tasks.open');

        $this->assertNotNull($widget);
        $this->assertSame('tasks.open', $widget['id']);
        $this->assertSame(8, $widget['data']['value']);
        $this->assertSame('purple', $widget['_displayColor']);
    }

    public function testRenderWidgetReturnsNullWhenForbidden(): void
    {
        $this->assertNull($this->service->renderWidget(4, 'admin.secret'));
    }

    public function testRenderWidgetReturnsNullWhenWidgetHasNoData(): void
    {
        $this->assertNull($this->service->renderWidget(4, 'tasks.empty'));
    }

    public function testRenderWidgetReturnsNullForUnknownWidget(): void
    {
        $this->assertNull($this->service->renderWidget(4, 'does.not.exist'));
    }

    public function testRenderWidgetComputesLazyWidgetOnDemand(): void
    {
        // Lazy widgets are skipped in the batch but still render via the endpoint.
        $widget = $this->service->renderWidget(4, 'weather.x');

        $this->assertNotNull($widget);
        $this->assertSame('weather.x', $widget['id']);
        $this->assertSame('20°', $widget['data']['value']);
        $this->assertArrayNotHasKey('lazy', $widget);
    }

    public function testGetAllAvailableWidgetsIncludesHiddenWithVisibilityMetadata(): void
    {
        $this->prefsService->expects($this->once())
            ->method('getUserPrefs')
            ->with(9)
            ->willReturn([
                'tasks.empty' => ['sort_order' => 0, 'visible' => false],
                'tasks.open'  => ['sort_order' => 1, 'visible' => true],
            ]);

        $widgets = $this->service->getAllAvailableWidgets(9);

        // All permitted widgets listed (admin.secret excluded); hidden ones too.
        $this->assertCount(3, $widgets);
        $this->assertSame('tasks.empty', $widgets[0]['id']);
        $this->assertFalse($widgets[0]['_visible']);
        $this->assertSame(0, $widgets[0]['_sort_order']);
        $this->assertSame('tasks.open', $widgets[1]['id']);
        $this->assertSame('weather.x', $widgets[2]['id']);
    }
}
