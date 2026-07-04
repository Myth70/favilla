<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminMimeController;
use Tests\ControllerTestCase;

class AdminMimeControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS app_settings (
                `key`        TEXT PRIMARY KEY,
                `value`      TEXT NULL,
                `type`       TEXT NULL,
                `group`      TEXT NULL,
                updated_at   TEXT NULL
            );
        ');

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersAllMimeTypesEnabledByDefault(): void
    {
        $result = $this->dispatch(AdminMimeController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $rows = $result->renderedData()['mimeTypes'];
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertTrue($row['abilitato']);
        }
    }

    public function testToggleDisablesThenReenablesMime(): void
    {
        $result = $this->dispatch(AdminMimeController::class, 'toggle', ['application%2Fpdf']);
        $this->assertTrue($result->isRedirect());

        $row = $this->pdo->query("SELECT value FROM app_settings WHERE `key` = 'documenti.mime.disabled'")->fetch();
        $this->assertContains('application/pdf', json_decode($row['value'], true));

        $this->dispatch(AdminMimeController::class, 'toggle', ['application%2Fpdf']);
        $row2 = $this->pdo->query("SELECT value FROM app_settings WHERE `key` = 'documenti.mime.disabled'")->fetch();
        $this->assertNotContains('application/pdf', json_decode($row2['value'], true));
    }

    public function testToggleReturnsPartialOnHtmxRequest(): void
    {
        $result = $this->asHtmx()->dispatch(AdminMimeController::class, 'toggle', ['image%2Fpng']);

        $this->assertTrue($result->didRender());
    }
}
