<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\HistoryRepository;

class ReportsHistoryQueryService
{
    private HistoryRepository $historyRepo;

    public function __construct()
    {
        $this->historyRepo = app(HistoryRepository::class);
    }

    public function getPaginatedHistory(array $filters, int $page, int $userId, bool $adminView): array
    {
        $result = $this->historyRepo->listPaginated($filters, $page, 20, $userId, $adminView);

        return array_merge($result, [
            'modules' => $this->historyRepo->getDistinctModules(),
            'adminView' => $adminView,
            'filters' => $filters,
        ]);
    }

    public function latestForUser(int $userId, bool $adminView, int $limit = 10): array
    {
        $filters = [
            'q' => '', 'module' => '', 'format' => '',
            'date_from' => '', 'date_to' => '',
            'sort' => 'generated_at', 'dir' => 'DESC',
        ];
        $result = $this->historyRepo->listPaginated($filters, 1, max(1, $limit), $userId, $adminView);
        return $result['items'] ?? [];
    }

    public function findEntryForUser(int $id, int $userId, bool $adminView): ?array
    {
        return $this->historyRepo->findForUser($id, $userId, $adminView);
    }

    public function buildStoredFilePath(array $entry): ?string
    {
        if (empty($entry['stored_filename'])) {
            return null;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        return $basePath . '/storage/reports/' . basename($entry['stored_filename']);
    }

    public function buildDownloadMetadata(array $entry): array
    {
        $mimeTypes = [
            'csv' => 'text/csv; charset=UTF-8',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
        ];

        $ext = match ($entry['output_format']) {
            'csv' => '.csv',
            'excel' => '.xlsx',
            'pdf' => '.pdf',
            default => '',
        };

        return [
            'mime' => $mimeTypes[$entry['output_format']] ?? 'application/octet-stream',
            'downloadName' => preg_replace('/[^a-zA-Z0-9_\-]/', '_', $entry['template_name'])
                . '_' . date('Ymd', strtotime($entry['generated_at']))
                . $ext,
        ];
    }

    public function deleteEntry(int $id): bool
    {
        return $this->historyRepo->delete($id);
    }
}
