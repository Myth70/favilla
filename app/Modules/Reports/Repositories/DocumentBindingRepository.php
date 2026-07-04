<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class DocumentBindingRepository extends BaseRepository
{
    protected string $table = 'document_bindings';
    protected bool $timestamps = true;
    protected bool $auditable = true;
    protected string $auditEntity = 'document_binding';

    protected array $fillable = [
        'module', 'operation', 'label', 'template_id', 'created_by',
    ];

    /**
     * Find a binding by module + operation, with template and style info joined.
     */
    public function findByOperation(string $module, string $operation): ?array
    {
        $sql = 'SELECT db.*,
                       t.name AS template_name,
                       t.template_html AS template_html,
                       t.output_format AS template_output_format,
                       t.source_key AS template_source_key,
                       t.source_type AS template_source_type,
                       t.style_preset_id AS template_style_preset_id,
                       sp.name AS style_name,
                       sp.logo_path AS style_logo_path,
                       sp.logo_secondary_path AS style_logo_secondary_path,
                       sp.primary_color AS style_primary_color,
                       sp.secondary_color AS style_secondary_color,
                       sp.accent_color AS style_accent_color,
                       sp.header_bg_color AS style_header_bg_color,
                       sp.header_text_color AS style_header_text_color,
                       sp.zebra_color AS style_zebra_color,
                       sp.font_family AS style_font_family,
                       sp.font_size_base AS style_font_size_base
                FROM document_bindings db
                INNER JOIN report_templates t ON t.id = db.template_id
                LEFT JOIN report_style_presets sp ON sp.id = t.style_preset_id
                WHERE db.module = ? AND db.operation = ?
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$module, $operation]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * List all bindings with template name joined.
     */
    public function listAll(): array
    {
        $sql = 'SELECT db.*, t.name AS template_name
                FROM document_bindings db
                INNER JOIN report_templates t ON t.id = db.template_id
                ORDER BY db.module ASC, db.operation ASC';

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List all bindings for a specific template.
     */
    public function listForTemplate(int $templateId): array
    {
        $sql = 'SELECT db.*
                FROM document_bindings db
                WHERE db.template_id = ?
                ORDER BY db.module ASC, db.operation ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List all templates with source_type = 'document' (for binding dropdowns).
     */
    public function listDocumentTemplates(): array
    {
        $sql = "SELECT id, name, module, source_key
                FROM report_templates
                WHERE source_type = 'document'
                ORDER BY module ASC, name ASC";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
