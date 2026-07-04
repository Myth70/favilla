<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Services\DashboardColorPalette;
use ReflectionMethod;
use Tests\ModuleTestCase;

class DashboardColorPaletteTest extends ModuleTestCase
{
    public function testResolveDisplayColorAlwaysPrefersThemePaletteWhenAvailable(): void
    {
        $palette = new DashboardColorPalette();

        $this->assertSame('purple', $palette->resolveDisplayColor('success', 'purple'));
        $this->assertSame('danger', $palette->resolveDisplayColor('warning', 'danger'));
        $this->assertSame('secondary', $palette->resolveDisplayColor('secondary', 'secondary'));
    }

    public function testExtractWidgetKeysPrefersLinkedModuleBeforeHomePrefix(): void
    {
        $palette = new DashboardColorPalette();
        $method = new ReflectionMethod(DashboardColorPalette::class, 'extractWidgetKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($palette, [
            'id' => 'home.weekly_timeline',
            'permission' => null,
            'data' => [
                'link' => 'http://localhost/calendar',
            ],
        ]);

        $this->assertSame(['calendar', 'home'], $keys);
    }
}
