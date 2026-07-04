<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\ChangelogRepository;

class ChangelogService
{
    private ChangelogRepository $repo;

    public function __construct()
    {
        $this->repo = app(ChangelogRepository::class);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, latest: ?array<string, mixed>}
     */
    public function getPublicTimeline(int $limit = 30): array
    {
        $items = $this->repo->listPublished($limit);

        return [
            'items'  => $items,
            'total'  => $this->repo->countPublished(),
            'latest' => $items[0] ?? null,
        ];
    }
}
