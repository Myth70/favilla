<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\TemplateRepository;

class ReportsDashboardService
{
    private ExportProviderService $exportProviderService;
    private HistoryService $historyService;
    private TemplateRepository $templateRepo;

    public function __construct()
    {
        $this->exportProviderService = app(ExportProviderService::class);
        $this->historyService = app(HistoryService::class);
        $this->templateRepo = app(TemplateRepository::class);
    }

    public function getDashboardData(array $user): array
    {
        return [
            'stats' => $this->historyService->getStats(),
            'templateCount' => $this->templateRepo->count(),
            'sources' => $this->exportProviderService->getSourcesForUser($user),
        ];
    }

    public function getSourcesForUser(array $user): array
    {
        return $this->exportProviderService->getSourcesForUser($user);
    }

    public function getSourceFields(string $module, string $sourceKey): ?array
    {
        return $this->exportProviderService->getSourceFields($module, $sourceKey);
    }
}
