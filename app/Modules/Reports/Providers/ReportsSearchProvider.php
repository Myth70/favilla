<?php

declare(strict_types=1);

namespace App\Modules\Reports\Providers;

use App\Contracts\SearchableModule;
use PDO;

class ReportsSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        $pdo = app(PDO::class);
        $like = '%' . $query . '%';

        $results = [];

        // Search templates
        $stmt = $pdo->prepare(
            'SELECT id, name, description, module, output_format
             FROM report_templates
             WHERE name LIKE ? OR description LIKE ?
             ORDER BY name ASC
             LIMIT ?'
        );
        $stmt->execute([$like, $like, $limit]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($templates as $t) {
            $results[] = [
                'title'    => $t['name'],
                'subtitle' => $t['description'] ?: ($t['module'] . ' — ' . strtoupper($t['output_format'])),
                'url'      => route('reports.templates.edit', ['id' => $t['id']]),
                'icon'     => 'fa-wand-magic-sparkles',
            ];
        }

        return $results;
    }

    public function getSearchLabel(): string
    {
        return 'Report';
    }

    public function getSearchIcon(): string
    {
        return 'fa-file-export';
    }
}
