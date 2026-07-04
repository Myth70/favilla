<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\DocumentBindingRepository;

class ReportsDocumentBindingService
{
    private DocumentBindingRepository $bindingRepo;
    private ExportProviderService $exportProviderService;

    public function __construct()
    {
        $this->bindingRepo = app(DocumentBindingRepository::class);
        $this->exportProviderService = app(ExportProviderService::class);
    }

    public function getIndexData(): array
    {
        return [
            'bindings' => $this->bindingRepo->listAll(),
        ];
    }

    public function getBindFormData(array $user): array
    {
        return [
            'sources' => $this->exportProviderService->getSourcesForUser($user),
            'templates' => $this->bindingRepo->listDocumentTemplates(),
        ];
    }

    public function createBinding(array $data): int
    {
        return (int) $this->bindingRepo->create($data);
    }

    public function updateBinding(int $id, array $data): bool
    {
        return $this->bindingRepo->update($id, $data);
    }

    public function findBinding(int $id): ?array
    {
        return $this->bindingRepo->find($id);
    }

    public function deleteBinding(int $id): bool
    {
        return $this->bindingRepo->delete($id);
    }

    public function listBindingsForTemplate(int $templateId): array
    {
        return $this->bindingRepo->listForTemplate($templateId);
    }
}
