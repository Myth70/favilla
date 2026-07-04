<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\HistoryRepository;

/**
 * Business logic for report generation history.
 */
class HistoryService
{
    private HistoryRepository $repo;

    public function __construct()
    {
        $this->repo = app(HistoryRepository::class);
    }

    /**
     * Record a new history entry.
     *
     * @return int New history ID
     */
    public function record(
        ?int    $templateId,
        string  $templateName,
        string  $module,
        string  $sourceKey,
        string  $format,
        string  $filename,
        int     $fileSize,
        int     $rowCount,
        ?array  $filters = null,
        int     $expiresInDays = 30
    ): int {
        $user = auth();

        return $this->repo->create([
            'template_id'     => $templateId,
            'template_name'   => $templateName,
            'module'          => $module,
            'source_key'      => $sourceKey,
            'output_format'   => $format,
            'stored_filename' => $filename,
            'file_size'       => $fileSize,
            'row_count'       => $rowCount,
            'filters_used'    => $filters !== null ? json_encode($filters, JSON_UNESCAPED_UNICODE) : null,
            'generated_by'    => $user['id'] ?? null,
            'generated_at'    => date('Y-m-d H:i:s'),
            'expires_at'      => date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days")),
        ]);
    }

    /**
     * Cleanup expired history entries (delete files + DB records).
     *
     * @return array {deleted_count, freed_bytes}
     */
    public function cleanupExpired(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $uploadsDir = $basePath . '/storage/reports';

        $expired = $this->repo->deleteExpired();

        $freedBytes = 0;
        foreach ($expired as $entry) {
            $filename = $entry['stored_filename'] ?? '';
            if ($filename === '') {
                continue;
            }

            $filePath = $uploadsDir . '/' . basename($filename);
            if (file_exists($filePath)) {
                $freedBytes += (int) ($entry['file_size'] ?? 0);
                @unlink($filePath);
            }
        }

        return [
            'deleted_count' => count($expired),
            'freed_bytes'   => $freedBytes,
        ];
    }

    /**
     * Get aggregate statistics.
     */
    public function getStats(): array
    {
        return $this->repo->getStats();
    }
}
