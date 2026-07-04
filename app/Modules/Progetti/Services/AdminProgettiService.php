<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Services;

use App\Modules\Progetti\Repositories\ProgettiRepository;

class AdminProgettiService
{
    private ProgettiRepository $repo;
    private ProgettiService $progettiService;

    public function __construct()
    {
        $this->repo = app(ProgettiRepository::class);
        $this->progettiService = app(ProgettiService::class);
    }

    public function getStats(): array
    {
        return $this->repo->adminStats();
    }

    public function getOwnerOptions(): array
    {
        return $this->repo->adminOwnerOptions();
    }

    public function getTableData(array $filters, int $page, int $perPage): array
    {
        return [
            'items' => $this->repo->adminList($filters, $page, $perPage),
            'total' => $this->repo->adminCount($filters),
        ];
    }

    public function moveToTrash(int $projectId, int $actorUserId): bool
    {
        $project = $this->repo->find($projectId);
        if (!$project) {
            return false;
        }

        return $this->progettiService->deleteProject($projectId, $actorUserId);
    }

    public function restoreFromTrash(int $projectId): bool
    {
        $project = $this->repo->findWithTrashed($projectId);
        if (!$project || empty($project['deleted_at'])) {
            return false;
        }

        return $this->repo->restore($projectId);
    }

    public function purgeFromTrash(int $projectId): bool
    {
        $project = $this->repo->findWithTrashed($projectId);
        if (!$project || empty($project['deleted_at'])) {
            return false;
        }

        return $this->repo->forceDelete($projectId);
    }
}
