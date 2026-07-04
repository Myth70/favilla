<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class StylePresetRepository extends BaseRepository
{
    protected string $table = 'report_style_presets';
    protected bool $timestamps = true;
    protected bool $auditable = true;
    protected string $auditEntity = 'report_style';

    protected array $fillable = [
        'name', 'description', 'is_default', 'logo_path', 'logo_secondary_path',
        'primary_color', 'secondary_color', 'accent_color', 'header_bg_color',
        'header_text_color', 'zebra_color', 'font_family', 'font_size_base',
        'created_by',
    ];

    /**
     * List all style presets with creator name.
     * Default preset first, then alphabetical.
     */
    public function listAll(): array
    {
        $sql = 'SELECT sp.*, u.name AS creator_name
                FROM report_style_presets sp
                LEFT JOIN users u ON u.id = sp.created_by
                ORDER BY sp.is_default DESC, sp.name ASC';

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get the default style preset.
     */
    public function getDefault(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM report_style_presets WHERE is_default = 1 LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Set a preset as the default (unsets all others in a transaction).
     */
    public function setDefault(int $id): void
    {
        $this->transaction(function () use ($id) {
            $this->pdo->exec('UPDATE report_style_presets SET is_default = 0');

            $stmt = $this->pdo->prepare(
                'UPDATE report_style_presets SET is_default = 1 WHERE id = ?'
            );
            $stmt->execute([$id]);
        });
    }

    /**
     * Count how many templates reference a given preset.
     */
    public function countTemplatesUsing(int $presetId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM report_templates WHERE style_preset_id = ?'
        );
        $stmt->execute([$presetId]);

        return (int) $stmt->fetchColumn();
    }
}
