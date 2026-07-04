<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\DocumentBindingRepository;
use Tests\ModuleTestCase;

class DocumentBindingRepositoryTest extends ModuleTestCase
{
    private DocumentBindingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE report_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                module TEXT NOT NULL,
                source_key TEXT NOT NULL,
                source_type TEXT NOT NULL
            );
            CREATE TABLE document_bindings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module TEXT NOT NULL,
                operation TEXT NOT NULL,
                label TEXT NOT NULL,
                template_id INTEGER NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            );
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                action TEXT NOT NULL,
                entity TEXT NOT NULL,
                entity_id INTEGER DEFAULT NULL,
                old_value TEXT DEFAULT NULL,
                new_value TEXT DEFAULT NULL,
                ip TEXT DEFAULT NULL
            );
        ');

        $this->repo = new DocumentBindingRepository();
    }

    public function testListDocumentTemplatesReturnsOnlyDocumentTemplates(): void
    {
        $this->insertRow('report_templates', [
            'name' => 'Lista Contatti',
            'module' => 'Contatti',
            'source_key' => 'contatti',
            'source_type' => 'list',
        ]);
        $documentId = $this->insertRow('report_templates', [
            'name' => 'Scheda Cliente',
            'module' => 'Contatti',
            'source_key' => 'cliente_singolo',
            'source_type' => 'document',
        ]);

        $templates = $this->repo->listDocumentTemplates();

        $this->assertCount(1, $templates);
        $this->assertSame($documentId, (int) $templates[0]['id']);
        $this->assertSame('Scheda Cliente', $templates[0]['name']);
    }

    public function testUpdatePersistsBindingChangesAndListAllIncludesTemplateName(): void
    {
        $templateId = $this->insertRow('report_templates', [
            'name' => 'Scheda Cliente',
            'module' => 'Contatti',
            'source_key' => 'cliente_singolo',
            'source_type' => 'document',
        ]);
        $newTemplateId = $this->insertRow('report_templates', [
            'name' => 'Scheda Cliente Pro',
            'module' => 'Contatti',
            'source_key' => 'cliente_singolo_pro',
            'source_type' => 'document',
        ]);

        $bindingId = $this->repo->create([
            'module' => 'Contatti',
            'operation' => 'scheda_cliente',
            'label' => 'Genera PDF',
            'template_id' => $templateId,
            'created_by' => 1,
        ]);

        $this->assertTrue($this->repo->update($bindingId, [
            'label' => 'Genera PDF avanzato',
            'template_id' => $newTemplateId,
        ]));

        $binding = $this->repo->find($bindingId);
        $this->assertSame('Genera PDF avanzato', $binding['label']);
        $this->assertSame($newTemplateId, (int) $binding['template_id']);

        $rows = $this->repo->listAll();
        $this->assertCount(1, $rows);
        $this->assertSame('Scheda Cliente Pro', $rows[0]['template_name']);
    }
}
