<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Providers;

use App\Contracts\SearchableModule;
use App\Modules\HelpOnline\Services\HelpOnlineService;

class HelpOnlineSearchProvider implements SearchableModule
{
    private HelpOnlineService $service;

    public function __construct()
    {
        $this->service = app(HelpOnlineService::class);
    }

    public function search(string $query, int $userId, int $limit = 5): array
    {
        $results = $this->service->searchKnowledgeBase($query, '', $limit);

        return array_map(static function (array $result): array {
            $subtitleParts = array_values(array_filter([
                trim((string) ($result['module_title'] ?? '')),
                trim((string) ($result['excerpt'] ?? '')),
            ], static fn (string $p): bool => $p !== ''));

            $badge = trim((string) ($result['module_title'] ?? $result['module_name'] ?? ''));
            if (($result['module_name'] ?? null) === 'HelpOnline') {
                $badge = '';
            }

            return [
                'title' => $result['title'],
                'subtitle' => implode(' — ', $subtitleParts),
                'url' => $result['help_url'],
                'icon' => 'fa-circle-question',
                'badge' => $badge !== '' ? $badge : null,
            ];
        }, $results);
    }

    public function getSearchLabel(): string
    {
        return 'Guida';
    }

    public function getSearchIcon(): string
    {
        return 'fa-circle-question';
    }
}
