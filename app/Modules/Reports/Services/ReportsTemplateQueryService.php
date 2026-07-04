<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Modules\Reports\Repositories\TemplateRepository;

class ReportsTemplateQueryService
{
    private TemplateRepository $templateRepo;
    private StylePresetRepository $styleRepo;
    private ExportProviderService $exportProviderService;

    public function __construct()
    {
        $this->templateRepo = app(TemplateRepository::class);
        $this->styleRepo = app(StylePresetRepository::class);
        $this->exportProviderService = app(ExportProviderService::class);
    }

    public function getIndexData(int $userId, array $userRoles, array $filters, int $page): array
    {
        $result = $this->templateRepo->listVisible($userId, $userRoles, $filters, $page, 20);

        return [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['lastPage'],
            'filters' => $filters,
            'modules' => $this->templateRepo->getDistinctModules(),
        ];
    }

    public function getDesignerData(array $user, ?int $templateId = null): ?array
    {
        $template = null;
        if ($templateId !== null) {
            $template = $this->templateRepo->findWithStyle($templateId);
            if ($template === null) {
                return null;
            }
        }

        return [
            'template' => $template,
            'sources' => $this->exportProviderService->getSourcesForUser($user),
            'styles' => $this->styleRepo->listAll(),
        ];
    }

    public function createTemplate(array $data): int
    {
        return $this->templateRepo->create($data);
    }

    public function findTemplate(int $id): ?array
    {
        return $this->templateRepo->find($id);
    }

    public function updateTemplate(int $id, array $data): bool
    {
        return $this->templateRepo->update($id, $data);
    }

    public function deleteTemplate(int $id): bool
    {
        return $this->templateRepo->delete($id);
    }

    public function duplicateTemplate(int $id, int $userId): ?int
    {
        $template = $this->templateRepo->find($id);
        if ($template === null) {
            return null;
        }

        $cloneData = [
            'name' => $template['name'] . ' (copia)',
            'description' => $template['description'],
            'module' => $template['module'],
            'source_key' => $template['source_key'],
            'output_format' => $template['output_format'],
            'source_type' => $template['source_type'],
            'filters_config' => $template['filters_config'],
            'sorting_config' => $template['sorting_config'],
            'template_html' => $template['template_html'],
            'style_preset_id' => $template['style_preset_id'],
            'visibility' => 'private',
            'visible_to_roles' => null,
            'max_rows' => $template['max_rows'],
            'created_by' => $userId,
        ];

        return $this->templateRepo->create($cloneData);
    }

    public function getTemplatePreviewData(int $id): ?array
    {
        return $this->templateRepo->find($id);
    }

    public function getBundledData(BundledTemplateService $bundledService): array
    {
        return [
            'available' => $bundledService->discoverAvailable(),
            'bundledCounts' => $this->templateRepo->countBundledByModule(),
        ];
    }
}
