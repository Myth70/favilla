<?php

declare(strict_types=1);

namespace App\Modules\Home\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class ChangelogRepository extends BaseRepository
{
    protected string $table = 'changelogs';

    private ?bool $translationsReady = null;

    /**
     * Restituisce lo storico release pubblicate (piu recenti in cima).
     *
     * title/notes sono localizzati sulla locale attiva (o $locale) tramite la
     * sidecar changelog_translations, con fallback alla riga base italiana.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublished(int $limit = 30, ?string $locale = null): array
    {
        $limit  = max(1, min(200, $limit));
        $locale = $locale ?? locale();

        if ($this->translationsTableReady()) {
            $stmt = $this->pdo->prepare(
                'SELECT c.id, c.version,
                        COALESCE(tr.title, c.title) AS title,
                        COALESCE(tr.notes, c.notes) AS notes,
                        c.release_date, c.created_at
                 FROM changelogs c
                 LEFT JOIN changelog_translations tr
                        ON tr.changelog_id = c.id AND tr.locale = ?
                 WHERE c.is_published = 1
                 ORDER BY c.release_date DESC, c.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $locale, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, version, title, notes, release_date, created_at
             FROM changelogs
             WHERE is_published = 1
             ORDER BY release_date DESC, id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Verifica (una volta per istanza) se la sidecar changelog_translations è
     * disponibile. Su installazioni non ancora migrate degrada alla riga base.
     */
    private function translationsTableReady(): bool
    {
        if ($this->translationsReady !== null) {
            return $this->translationsReady;
        }
        try {
            $this->pdo->query('SELECT 1 FROM changelog_translations LIMIT 1');
            return $this->translationsReady = true;
        } catch (\Throwable) {
            return $this->translationsReady = false;
        }
    }

    /**
     * Totale release pubblicate.
     */
    public function countPublished(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM changelogs WHERE is_published = 1');
        return (int) $stmt->fetchColumn();
    }
}
