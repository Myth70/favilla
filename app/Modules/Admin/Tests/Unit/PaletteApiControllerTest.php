<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\PaletteApiController;
use Tests\ControllerTestCase;

/**
 * Controller-level test for the command-palette JSON endpoint via the harness.
 * Exercises the json() terminal-response path (HaltResponse::JSON).
 */
class PaletteApiControllerTest extends ControllerTestCase
{
    public function testIndexReturnsCachedCatalogAsJson(): void
    {
        $this->actingAsAdmin();

        // Pre-seed the per-session catalog cache so the action short-circuits to
        // json() without touching AdminIndexService / the database.
        $catalog = [
            ['id' => 'home',  'label' => 'Home',   'url' => '/home'],
            ['id' => 'users', 'label' => 'Utenti', 'url' => '/admin/users'],
        ];
        $_SESSION['_palette_catalog'] = $catalog;

        $result = $this->dispatch(PaletteApiController::class, 'index');

        $this->assertTrue($result->isJson(), 'La palette deve rispondere in JSON');
        $this->assertSame(200, $result->jsonStatus());
        $this->assertSame($catalog, $result->jsonPayload());
    }
}
