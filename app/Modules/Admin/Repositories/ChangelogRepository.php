<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class ChangelogRepository extends BaseRepository
{
    protected string $table = 'changelogs';

    private const SORT_WHITELIST = ['release_date', 'version', 'title', 'created_at'];

    private ?bool $translationsReady = null;

    /**
     * Lista paginata con filtri opzionali.
     *
     * @return array{items: array, total: int, page: int, per_page: int, lastPage: int}
     */
    public function listPaginated(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where  = ['1=1'];
        $params = [];

        if (isset($filters['published']) && $filters['published'] !== '') {
            $where[]  = 'c.is_published = ?';
            $params[] = (int) $filters['published'];
        }

        if (!empty($filters['search'])) {
            $q        = '%' . $filters['search'] . '%';
            $where[]  = '(c.version LIKE ? OR c.title LIKE ? OR c.notes LIKE ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sort   = in_array($filters['sort'] ?? '', self::SORT_WHITELIST, true)
                  ? $filters['sort'] : 'release_date';
        $dir    = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $where  = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT c.*, u.name AS author_name
            FROM changelogs c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE {$where}
            ORDER BY c.{$sort} {$dir}
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $items = $stmt->fetchAll();

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM changelogs c WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * Ritorna l'ultima release pubblicata (per badge footer), con titolo
     * localizzato sulla locale attiva (o $locale) e fallback all'italiano.
     */
    public function getLatestPublished(?string $locale = null): ?array
    {
        $locale = $locale ?? locale();

        if ($this->translationsTableReady()) {
            $stmt = $this->pdo->prepare(
                'SELECT c.version,
                        COALESCE(tr.title, c.title) AS title,
                        c.release_date
                 FROM changelogs c
                 LEFT JOIN changelog_translations tr
                        ON tr.changelog_id = c.id AND tr.locale = ?
                 WHERE c.is_published = 1
                 ORDER BY c.release_date DESC, c.id DESC
                 LIMIT 1'
            );
            $stmt->execute([$locale]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT version, title, release_date FROM changelogs
             WHERE is_published = 1
             ORDER BY release_date DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cerca una release per stringa di versione.
     */
    public function findByVersion(string $version): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM changelogs WHERE version = ?');
        $stmt->execute([$version]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Traduzioni per-locale di una release.
     *
     * @return array<string, array{title: string, notes: string}> locale => campi
     */
    public function getTranslations(int $changelogId): array
    {
        if (!$this->translationsTableReady()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT locale, title, notes FROM changelog_translations WHERE changelog_id = ?'
        );
        $stmt->execute([$changelogId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['locale']] = [
                'title' => (string) $row['title'],
                'notes' => (string) $row['notes'],
            ];
        }

        return $out;
    }

    /**
     * Upsert/elimina le traduzioni di una release. L'italiano (fallback) vive
     * nella riga base e viene ignorato. Una locale con title E notes vuoti viene
     * rimossa; altrimenti viene inserita/aggiornata (delete-then-insert portabile
     * SQLite/MariaDB).
     *
     * @param array<string, array{title?: string, notes?: string}> $translations
     */
    public function saveTranslations(int $changelogId, array $translations): void
    {
        if (!$this->translationsTableReady()) {
            return;
        }

        $supported = (array) config('localization.supported', []);
        $fallback  = (string) config('localization.fallback', 'it');

        $delete = $this->pdo->prepare(
            'DELETE FROM changelog_translations WHERE changelog_id = ? AND locale = ?'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO changelog_translations (changelog_id, locale, title, notes)
             VALUES (?, ?, ?, ?)'
        );

        foreach ($translations as $locale => $fields) {
            $locale = (string) $locale;
            // La locale canonica (IT) vive nella riga base; ignora le non supportate.
            if ($locale === $fallback || !in_array($locale, $supported, true)) {
                continue;
            }

            $title = trim((string) ($fields['title'] ?? ''));
            $notes = trim((string) ($fields['notes'] ?? ''));

            $delete->execute([$changelogId, $locale]);
            if ($title === '' && $notes === '') {
                continue;
            }
            $insert->execute([$changelogId, $locale, $title, $notes]);
        }
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
}
