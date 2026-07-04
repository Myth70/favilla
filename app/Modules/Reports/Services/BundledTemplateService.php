<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Core\ModuleLoader;
use App\Modules\Reports\Repositories\TemplateRepository;
use App\Services\AuditService;

/**
 * Gestisce l'import/sync dei template report forniti dai singoli moduli.
 *
 * Convenzione: ogni modulo può includere template preconfigurati in
 *   app/Modules/{NomeModulo}/report_templates/*.json
 *
 * Formato JSON:
 * {
 *     "name": "Nome Template",
 *     "description": "Descrizione opzionale",
 *     "source_key": "chiave_sorgente",
 *     "source_type": "list|document",
 *     "output_format": "csv|excel|pdf",
 *     "visibility": "global",
 *     "max_rows": 10000,
 *     "style_preset_id": null,
 *     "template_html": "<...full HTML template with {{ placeholders }} and Smart Components...>",
 *     "filters_config": { ... },
 *     "sorting_config": { ... }
 * }
 *
 * Il campo "module" viene impostato automaticamente dal nome della cartella del modulo.
 * Il campo "bundled_module" viene impostato automaticamente.
 */
class BundledTemplateService
{
    private TemplateRepository $templateRepo;
    private ExportProviderService $providerService;

    public function __construct()
    {
        $this->templateRepo = app(TemplateRepository::class);
        $this->providerService = app(ExportProviderService::class);
    }

    /**
     * Scansiona tutti i moduli attivi e importa i template bundled.
     *
     * @param bool $overwrite Se true, aggiorna template esistenti con lo stesso nome
     * @return array ['imported' => int, 'updated' => int, 'skipped' => int, 'errors' => string[]]
     */
    public function importAll(bool $overwrite = false): array
    {
        $moduleLoader = app(ModuleLoader::class);
        $modules = $moduleLoader->getModules();

        $totals = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($modules as $module) {
            if (!($module['enabled'] ?? true)) {
                continue;
            }

            $result = $this->importFromModule($module['name'], $overwrite);

            $totals['imported'] += $result['imported'];
            $totals['updated']  += $result['updated'];
            $totals['skipped']  += $result['skipped'];
            $totals['errors']    = array_merge($totals['errors'], $result['errors']);
        }

        return $totals;
    }

    /**
     * Importa template da un singolo modulo.
     *
     * @param string $moduleName Nome del modulo (es. 'Admin', 'Clienti')
     * @param bool   $overwrite  Se true, sovrascrive template esistenti
     * @return array ['imported' => int, 'updated' => int, 'skipped' => int, 'errors' => string[]]
     */
    public function importFromModule(string $moduleName, bool $overwrite = false): array
    {
        $result = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $templatesDir = $basePath . '/app/Modules/' . $moduleName . '/report_templates';

        if (!is_dir($templatesDir)) {
            return $result;
        }

        $files = glob($templatesDir . '/*.json');
        if (!$files) {
            return $result;
        }

        foreach ($files as $file) {
            $filename = basename($file);

            $raw = file_get_contents($file);
            if ($raw === false) {
                $result['errors'][] = "{$moduleName}/{$filename}: impossibile leggere il file.";
                continue;
            }

            $def = json_decode($raw, true);
            if (!is_array($def) || empty($def['name'])) {
                $result['errors'][] = "{$moduleName}/{$filename}: JSON non valido o manca il campo 'name'.";
                continue;
            }

            try {
                $outcome = $this->importTemplate($moduleName, $def, $overwrite);
                $result[$outcome]++;
            } catch (\Throwable $e) {
                $result['errors'][] = "{$moduleName}/{$filename}: {$e->getMessage()}";
            }
        }

        if ($result['imported'] > 0 || $result['updated'] > 0) {
            AuditService::log(
                'bundled_templates_imported',
                'report_template',
                null,
                null,
                [
                    'module'   => $moduleName,
                    'imported' => $result['imported'],
                    'updated'  => $result['updated'],
                ]
            );
        }

        return $result;
    }

    /**
     * Importa un singolo template.
     *
     * @return string 'imported' | 'updated' | 'skipped'
     */
    private function importTemplate(string $moduleName, array $def, bool $overwrite): string
    {
        $name = trim($def['name']);

        // Check se esiste già
        $existing = $this->templateRepo->findBundledByName($moduleName, $name);

        if ($existing && !$overwrite) {
            return 'skipped';
        }

        // Valida source_key
        if (empty($def['source_key'])) {
            throw new \InvalidArgumentException("Campo 'source_key' mancante.");
        }

        if (!$this->providerService->sourceExists($moduleName, (string) $def['source_key'])) {
            throw new \InvalidArgumentException("source_key '{$def['source_key']}' non disponibile per il modulo '{$moduleName}'.");
        }

        // Build data
        $data = [
            'name'            => $name,
            'description'     => $def['description'] ?? '',
            'module'          => $moduleName,
            'source_key'      => $def['source_key'],
            'source_type'     => in_array($def['source_type'] ?? '', ['list', 'document'], true)
                                    ? $def['source_type'] : 'list',
            'output_format'   => in_array($def['output_format'] ?? '', ['csv', 'excel', 'pdf'], true)
                                    ? $def['output_format'] : 'pdf',
            'template_html'   => is_string($def['template_html'] ?? null) && trim($def['template_html']) !== ''
                                    ? $def['template_html']
                                    : null,
            'filters_config'  => is_array($def['filters_config'] ?? null)
                                    ? json_encode($def['filters_config'], JSON_UNESCAPED_UNICODE)
                                    : null,
            'sorting_config'  => is_array($def['sorting_config'] ?? null)
                                    ? json_encode($def['sorting_config'], JSON_UNESCAPED_UNICODE)
                                    : null,
            'style_preset_id' => !empty($def['style_preset_id']) ? (int) $def['style_preset_id'] : null,
            'visibility'      => in_array($def['visibility'] ?? '', ['private', 'role', 'global'], true)
                                    ? $def['visibility'] : 'global',
            'visible_to_roles' => is_array($def['visible_to_roles'] ?? null)
                                    ? json_encode($def['visible_to_roles'])
                                    : null,
            'max_rows'        => max(1, (int) ($def['max_rows'] ?? 10000)),
            'bundled_module'  => $moduleName,
            'created_by'      => null,
        ];

        if ($existing) {
            $this->templateRepo->update($existing['id'], $data);
            return 'updated';
        }

        $this->templateRepo->create($data);
        return 'imported';
    }

    /**
     * Rimuove tutti i template bundled di un modulo.
     */
    public function removeBundledByModule(string $moduleName): int
    {
        $count = $this->templateRepo->deleteBundledByModule($moduleName);

        if ($count > 0) {
            AuditService::log(
                'bundled_templates_removed',
                'report_template',
                null,
                null,
                ['module' => $moduleName, 'count' => $count]
            );
        }

        return $count;
    }

    /**
     * Elenca i moduli con template bundled disponibili su disco.
     *
     * @return array [moduleName => ['files' => int, 'imported' => int]]
     */
    public function discoverAvailable(): array
    {
        $moduleLoader = app(ModuleLoader::class);
        $modules = $moduleLoader->getModules();

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $importedCounts = $this->templateRepo->countBundledByModule();

        $available = [];

        foreach ($modules as $module) {
            if (!($module['enabled'] ?? true)) {
                continue;
            }

            $dir = $basePath . '/app/Modules/' . $module['name'] . '/report_templates';
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*.json');
            $fileCount = $files ? count($files) : 0;

            if ($fileCount > 0) {
                $available[$module['name']] = [
                    'files'    => $fileCount,
                    'imported' => $importedCounts[$module['name']] ?? 0,
                ];
            }
        }

        ksort($available);

        return $available;
    }
}
