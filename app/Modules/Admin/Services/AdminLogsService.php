<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\AdminLogsRepository;

class AdminLogsService
{
    private AdminLogsRepository $repo;

    public function __construct()
    {
        $this->repo = app(AdminLogsRepository::class);
    }

    public function getAuditStats(): array
    {
        return $this->repo->getAuditStats();
    }

    public function getAttemptsStats(): array
    {
        return $this->repo->getAttemptsStats();
    }

    public function getSessionsStats(): array
    {
        return $this->repo->getSessionsStats();
    }

    public function getUsersForFilter(): array
    {
        return $this->repo->getUsersForFilter();
    }

    public function getDistinctAuditActions(): array
    {
        return $this->repo->getDistinctAuditActions();
    }

    public function getDistinctAuditEntities(): array
    {
        return $this->repo->getDistinctAuditEntities();
    }

    public function listAudit(array $filters, int $page): array
    {
        return $this->repo->listAudit($filters, $page);
    }

    public function listAttempts(array $filters, int $page): array
    {
        return $this->repo->listAttempts($filters, $page);
    }

    public function listSessions(array $filters, int $page): array
    {
        return $this->repo->listSessions($filters, $page);
    }

    public function exportAudit(array $filters): array
    {
        return $this->repo->exportAudit($filters);
    }

    public function exportAttempts(array $filters): array
    {
        return $this->repo->exportAttempts($filters);
    }

    public function exportSessions(array $filters): array
    {
        return $this->repo->exportSessions($filters);
    }

    public function purgeAudit(int $days): int
    {
        return $this->repo->purgeAudit($days);
    }

    public function purgeAttempts(int $days): int
    {
        return $this->repo->purgeAttempts($days);
    }

    public function purgeExpiredSessions(): int
    {
        return $this->repo->purgeExpiredSessions();
    }

    public function purgePasswordResets(int $days): int
    {
        return $this->repo->purgePasswordResets($days);
    }

    public function getExportLimit(): int
    {
        return AdminLogsRepository::EXPORT_LIMIT;
    }
}
