<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Providers;

use App\Contracts\SearchableModule;
use App\Modules\Tasks\Services\TasksService;

class TasksSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        if (!has_permission('tasks.view')) {
            return [];
        }

        $service = app(TasksService::class);
        $results = $service->search($userId, $query, $limit);

        $statuses = TasksService::getStatuses();

        return array_map(function ($task) use ($statuses) {
            $statusMeta = $statuses[$task['status']] ?? ['label' => $task['status']];
            return [
                'title'    => $task['title'],
                'subtitle' => $statusMeta['label'] . ($task['due_date'] ? ' — Scadenza: ' . date('d/m/Y', strtotime($task['due_date'])) : ''),
                'url'      => route('tasks.show', ['id' => $task['id']]),
                'icon'     => 'fa-clipboard-check',
                'badge'    => $task['status'] === 'done' ? 'completata' : null,
            ];
        }, $results);
    }

    public function getSearchLabel(): string
    {
        return 'Attività';
    }

    public function getSearchIcon(): string
    {
        return 'fa-clipboard-check';
    }
}
