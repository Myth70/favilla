<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Providers;

use App\Contracts\SearchableModule;
use App\Modules\Progetti\Services\ProgettiService;
use PDO;

class ProgettiSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        if (!has_permission('progetti.view')) {
            return [];
        }

        $pdo        = app(PDO::class);
        $like       = '%' . $query . '%';
        $isAdmin    = has_permission('progetti.view_all') || has_permission('progetti.manage_all');

        // Search both projects and tasks
        $results = [];

        // --- Projects ---
        if ($isAdmin) {
            $stmt = $pdo->prepare(
                'SELECT p.id, p.name, p.status, p.description
                 FROM projects p
                 WHERE p.deleted_at IS NULL
                   AND (p.name LIKE ? OR p.description LIKE ?)
                 ORDER BY p.name ASC
                 LIMIT ?'
            );
            $stmt->execute([$like, $like, (int) ceil($limit / 2)]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT p.id, p.name, p.status, p.description
                 FROM projects p
                 JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
                 WHERE p.deleted_at IS NULL
                   AND (p.name LIKE ? OR p.description LIKE ?)
                 ORDER BY p.name ASC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $like, $like, (int) ceil($limit / 2)]);
        }

        $projectStatuses = ProgettiService::getProjectStatuses();
        foreach ($stmt->fetchAll() as $row) {
            $statusLabel = $projectStatuses[$row['status']]['label'] ?? (string) $row['status'];
            $results[] = [
                'title'    => $row['name'],
                'subtitle' => t('progetti.search.project_subtitle', ['status' => $statusLabel]),
                'url'      => route('projects.show', ['id' => $row['id']]),
                'icon'     => 'fa-diagram-project',
                'badge'    => $row['status'] === 'completed' ? t('progetti.search.badge_completed') : null,
            ];
        }

        // --- Tasks ---
        $remaining = $limit - count($results);
        if ($remaining > 0) {
            if ($isAdmin) {
                $stmt = $pdo->prepare(
                    'SELECT t.id, t.title, t.status, p.id AS project_id, p.name AS project_name
                     FROM project_tasks t
                     JOIN projects p ON p.id = t.project_id
                     WHERE p.deleted_at IS NULL
                       AND t.deleted_at IS NULL
                       AND (t.title LIKE ? OR t.description LIKE ?)
                     ORDER BY t.created_at DESC
                     LIMIT ?'
                );
                $stmt->execute([$like, $like, $remaining]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT t.id, t.title, t.status, p.id AS project_id, p.name AS project_name
                     FROM project_tasks t
                     JOIN projects p ON p.id = t.project_id
                     JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
                     WHERE p.deleted_at IS NULL
                       AND t.deleted_at IS NULL
                       AND (t.title LIKE ? OR t.description LIKE ?)
                     ORDER BY t.created_at DESC
                     LIMIT ?'
                );
                $stmt->execute([$userId, $like, $like, $remaining]);
            }

            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'title'    => $row['title'],
                    'subtitle' => t('progetti.search.task_subtitle', ['project' => (string) ($row['project_name'] ?? '')]),
                    'url'      => route('projects.show', ['id' => $row['project_id']]),
                    'icon'     => 'fa-list-check',
                    'badge'    => $row['status'] === 'done' ? t('progetti.search.badge_completed') : null,
                ];
            }
        }

        return $results;
    }

    public function getSearchLabel(): string
    {
        return t('progetti.title');
    }

    public function getSearchIcon(): string
    {
        return 'fa-diagram-project';
    }
}
