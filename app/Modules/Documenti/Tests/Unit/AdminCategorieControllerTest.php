<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\Admin\AdminCategorieController;
use Tests\ControllerTestCase;

class AdminCategorieControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER NULL,
                nome TEXT NOT NULL,
                codice TEXT NOT NULL,
                path TEXT NULL,
                ordine INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                categoria_id INTEGER NOT NULL,
                deleted_at TEXT NULL
            );
        ');

        $this->actingAs(1, ['documenti.admin']);
    }

    public function testIndexRendersCategoryTreeReadOnly(): void
    {
        $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'codice' => 'GEN']);

        $result = $this->dispatch(AdminCategorieController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['categorie']);
        $this->assertCount(1, $result->renderedData()['flat']);
    }
}
