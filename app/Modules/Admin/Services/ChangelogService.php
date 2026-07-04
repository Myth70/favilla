<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\ChangelogRepository;

class ChangelogService
{
    private ChangelogRepository $repo;

    public function __construct()
    {
        $this->repo = app(ChangelogRepository::class);
    }

    public function listPaginated(array $filters, int $page): array
    {
        return $this->repo->listPaginated($filters, $page);
    }

    public function find(int|string $id): ?array
    {
        return $this->repo->find($id);
    }

    public function findByVersion(string $version): ?array
    {
        return $this->repo->findByVersion($version);
    }

    public function create(array $data, array $translations = []): int|string
    {
        $id = $this->repo->create($data);
        $this->repo->saveTranslations((int) $id, $translations);
        return $id;
    }

    public function update(int|string $id, array $data, array $translations = []): void
    {
        $this->repo->update($id, $data);
        $this->repo->saveTranslations((int) $id, $translations);
    }

    /**
     * Traduzioni per-locale di una release (per il form di modifica).
     *
     * @return array<string, array{title: string, notes: string}>
     */
    public function getTranslations(int|string $id): array
    {
        return $this->repo->getTranslations((int) $id);
    }

    public function delete(int|string $id): void
    {
        $this->repo->delete($id);
    }

    public function togglePublished(int|string $id): array
    {
        $release = $this->repo->find($id);
        if (!$release) {
            throw new \RuntimeException("Release #{$id} non trovata.");
        }

        $newState = $release['is_published'] ? 0 : 1;
        $this->repo->update($id, ['is_published' => $newState]);

        return $this->repo->find($id);
    }

    public function getLatestPublished(): ?array
    {
        return $this->repo->getLatestPublished();
    }
}
