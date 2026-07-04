<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\StylePresetRepository;
use Tests\ModuleTestCase;

class StylePresetRepositoryTest extends ModuleTestCase
{
    private StylePresetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
            CREATE TABLE report_style_presets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                logo_path TEXT NULL,
                logo_secondary_path TEXT NULL,
                primary_color TEXT NULL,
                secondary_color TEXT NULL,
                accent_color TEXT NULL,
                header_bg_color TEXT NULL,
                header_text_color TEXT NULL,
                zebra_color TEXT NULL,
                font_family TEXT NULL,
                font_size_base INTEGER NULL,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE report_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                style_preset_id INTEGER NULL
            );
        ');

        $this->repo = new StylePresetRepository();
    }

    public function testListAllOrdersDefaultFirstThenByName(): void
    {
        $userId = $this->insertRow('users', ['name' => 'Designer']);

        $this->insertRow('report_style_presets', [
            'name' => 'Zeta',
            'is_default' => 0,
            'created_by' => $userId,
        ]);
        $this->insertRow('report_style_presets', [
            'name' => 'Alpha',
            'is_default' => 1,
            'created_by' => $userId,
        ]);

        $rows = $this->repo->listAll();

        $this->assertSame('Alpha', $rows[0]['name']);
        $this->assertSame('Designer', $rows[0]['creator_name']);
        $this->assertSame('Zeta', $rows[1]['name']);
    }

    public function testSetDefaultSwitchesFlagsAtomically(): void
    {
        $a = $this->insertRow('report_style_presets', ['name' => 'A', 'is_default' => 1]);
        $b = $this->insertRow('report_style_presets', ['name' => 'B', 'is_default' => 0]);

        $this->repo->setDefault($b);

        $this->assertNull($this->repo->getDefault()['id'] === $a ? 'unexpected' : null);
        $this->assertSame($b, (int) $this->repo->getDefault()['id']);
    }

    public function testCountTemplatesUsingReturnsLinkedTemplatesCount(): void
    {
        $preset = $this->insertRow('report_style_presets', ['name' => 'Default', 'is_default' => 1]);

        $this->insertRow('report_templates', ['name' => 'T1', 'style_preset_id' => $preset]);
        $this->insertRow('report_templates', ['name' => 'T2', 'style_preset_id' => $preset]);
        $this->insertRow('report_templates', ['name' => 'T3', 'style_preset_id' => null]);

        $this->assertSame(2, $this->repo->countTemplatesUsing($preset));
    }
}
