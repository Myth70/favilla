<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\TemplateRepository;
use Tests\ModuleTestCase;

class TemplateRepositoryTest extends ModuleTestCase
{
    private TemplateRepository $repo;

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
                name TEXT NOT NULL
            );
            CREATE TABLE report_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                module TEXT NOT NULL,
                source_key TEXT NOT NULL,
                output_format TEXT NOT NULL,
                source_type TEXT NOT NULL,
                filters_config TEXT DEFAULT NULL,
                sorting_config TEXT DEFAULT NULL,
                style_preset_id INTEGER DEFAULT NULL,
                visibility TEXT NOT NULL,
                visible_to_roles TEXT DEFAULT NULL,
                max_rows INTEGER DEFAULT 1000,
                bundled_module TEXT DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            );
        ');

        $this->insertRow('users', ['name' => 'Mario']);
        $this->insertRow('report_style_presets', ['name' => 'Base']);

        $this->repo = new TemplateRepository($this->pdo);
    }

    public function testListVisibleIncludesOwnAndGlobalTemplates(): void
    {
        $this->insertRow('report_templates', [
            'name' => 'Privato',
            'module' => 'Sales',
            'source_key' => 'orders',
            'output_format' => 'pdf',
            'source_type' => 'list',
            'visibility' => 'private',
            'created_by' => 1,
        ]);
        $this->insertRow('report_templates', [
            'name' => 'Globale',
            'module' => 'Sales',
            'source_key' => 'customers',
            'output_format' => 'csv',
            'source_type' => 'list',
            'visibility' => 'global',
            'created_by' => 99,
        ]);

        $result = $this->repo->listVisible(1, ['manager'], ['sort' => 'created_at', 'dir' => 'DESC'], 1, 20);

        $this->assertCount(2, $result['items']);
    }

    public function testListVisibleIncludesRoleTemplateWhenRoleMatches(): void
    {
        $this->insertRow('report_templates', [
            'name' => 'Ruolo',
            'module' => 'HR',
            'source_key' => 'employees',
            'output_format' => 'pdf',
            'source_type' => 'list',
            'visibility' => 'role',
            'visible_to_roles' => json_encode(['manager']),
            'created_by' => 5,
        ]);

        $result = $this->repo->listVisible(1, ['manager'], ['sort' => 'created_at', 'dir' => 'DESC'], 1, 20);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Ruolo', $result['items'][0]['name']);
    }
}
